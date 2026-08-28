<?php
declare(strict_types=1);

// ============================================================
// FPX Payment via Billplz API
// Docs: https://www.billplz.com/api
// ============================================================

class FPXPayment
{
    private string $apiKey;
    private string $collectionId;
    private string $xSignatureKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey        = BILLPLZ_API_KEY;
        $this->collectionId  = BILLPLZ_COLLECTION_ID;
        $this->xSignatureKey = BILLPLZ_X_SIGNATURE_KEY;
        $this->baseUrl       = BILLPLZ_BASE_URL;
    }

    // --- Create a Billplz bill and return the payment URL ---
    public function createBill(array $participant, array $payment, string $categoryName): string
    {
        $amountCents = (int) round($payment['total_amount'] * 100); // Billplz uses cents

        $callbackUrl = APP_URL . '/webhook_fpx.php';
        $redirectUrl = APP_URL . '/success.php?gateway=fpx&ref=' . urlencode($payment['payment_reference']);

        $postData = [
            'collection_id'          => $this->collectionId,
            'paid_callback_url'      => $callbackUrl,
            'callback_url'           => $callbackUrl,
            'redirect_url'           => $redirectUrl,
            'email'                  => $participant['email'],
            'mobile'                 => preg_replace('/[^0-9]/', '', $participant['contact_number']),
            'name'                   => $participant['full_name'],
            'amount'                 => $amountCents,
            'description'            => getSetting('event_name') . ' – ' . $categoryName . ' (Ref: ' . $payment['payment_reference'] . ')',
            'reference_1_label'      => 'Participant Ref',
            'reference_1'            => $participant['reference_number'],
            'reference_2_label'      => 'Payment Ref',
            'reference_2'            => $payment['payment_reference'],
        ];

        $response = $this->apiRequest('POST', '/bills', $postData);

        if (isset($response['error'])) {
            error_log('Billplz create bill error: ' . json_encode($response));
            throw new RuntimeException('Failed to create FPX payment. Please try again.');
        }

        // Store Billplz bill ID
        Database::execute(
            'UPDATE payments SET gateway_transaction_id = ? WHERE payment_reference = ?',
            [$response['id'], $payment['payment_reference']]
        );

        return $response['url'];
    }

    // --- Get bill status from Billplz (polling / verify on redirect) ---
    public function getBillStatus(string $billId): ?array
    {
        $response = $this->apiRequest('GET', '/bills/' . $billId);

        if (isset($response['error'])) {
            return null;
        }

        return $response;
    }

    // --- Verify Billplz X-Signature for callbacks and redirects ---
    public function verifySignature(array $params): bool
    {
        $signatureReceived = $params['x_signature'] ?? '';
        unset($params['x_signature']);

        // Billplz signature: alphabetically sorted key|value pairs, HMAC-SHA256
        ksort($params);
        $source = implode('|', array_map(
            fn($k, $v) => "{$k}|{$v}",
            array_keys($params),
            array_values($params)
        ));

        $expected = hash_hmac('sha256', $source, $this->xSignatureKey);
        return hash_equals($expected, $signatureReceived);
    }

    // --- Process successful FPX payment ---
    public function markPaymentSuccess(string $paymentRef, string $billId, array $gatewayData): void
    {
        $payment = Database::fetchOne(
            'SELECT * FROM payments WHERE payment_reference = ?',
            [$paymentRef]
        );

        if (!$payment || $payment['status'] === 'Success') {
            return;
        }

        Database::execute(
            'UPDATE payments
             SET status = ?, gateway_transaction_id = ?, payment_date = NOW(), gateway_response = ?
             WHERE payment_reference = ?',
            ['Success', $billId, json_encode($gatewayData), $paymentRef]
        );

        Database::execute(
            'UPDATE participants SET registration_status = ? WHERE id = ?',
            ['Paid', $payment['participant_id']]
        );

        Database::execute(
            'UPDATE event_categories ec
             JOIN participants p ON p.category_id = ec.id
             SET ec.current_participants = ec.current_participants + 1
             WHERE p.id = ?',
            [$payment['participant_id']]
        );

        $participant = Database::fetchOne('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']]);
        $category    = Database::fetchOne('SELECT * FROM event_categories WHERE id = ?', [$participant['category_id']]);
        if ($participant && $category) {
            EmailService::sendConfirmation($participant, $payment, $category);
        }
    }

    public function markPaymentFailed(string $paymentRef, array $gatewayData = []): void
    {
        Database::execute(
            "UPDATE payments SET status = 'Failed', gateway_response = ? WHERE payment_reference = ?",
            [json_encode($gatewayData), $paymentRef]
        );

        $payment = Database::fetchOne('SELECT * FROM payments WHERE payment_reference = ?', [$paymentRef]);
        if ($payment) {
            Database::execute(
                "UPDATE participants SET registration_status = 'Failed' WHERE id = ?",
                [$payment['participant_id']]
            );
        }
    }

    // --- Internal cURL API helper ---
    private function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERPWD        => $this->apiKey . ':',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_URL,        $url);
            curl_setopt($ch, CURLOPT_POST,       true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_URL,           $url . (!empty($data) ? '?' . http_build_query($data) : ''));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'cURL error: ' . $error];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['error' => 'Invalid JSON response', 'raw' => $response];
        }

        return $decoded;
    }
}
