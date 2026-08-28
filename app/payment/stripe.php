<?php
declare(strict_types=1);

// ============================================================
// Stripe Payment Integration
// Uses Stripe Checkout Sessions (hosted payment page)
// ============================================================

use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripePayment
{
    public function __construct()
    {
        $this->loadStripe();
    }

    private function loadStripe(): void
    {
        $autoload = BASE_PATH . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new RuntimeException('Stripe SDK not found. Run: composer install');
        }
        require_once $autoload;
        Stripe::setApiKey(STRIPE_SECRET_KEY);
        Stripe::setMaxNetworkRetries(2);
    }

    // --- Create a Checkout Session and return its URL ---
    public function createCheckoutSession(
        array  $participant,
        array  $payment,
        string $categoryName
    ): string {
        $amountCents = (int) round($payment['total_amount'] * 100); // Stripe uses cents

        $lineItems = [
            [
                'price_data' => [
                    'currency'     => 'myr',
                    'unit_amount'  => $amountCents,
                    'product_data' => [
                        'name'        => getSetting('event_name') . ' – ' . $categoryName,
                        'description' => 'Registration Ref: ' . $participant['reference_number'],
                    ],
                ],
                'quantity' => 1,
            ],
        ];

        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'customer_email'       => $participant['email'],
            'success_url'          => APP_URL . '/success.php?gateway=stripe&session_id={CHECKOUT_SESSION_ID}&ref=' . urlencode($payment['payment_reference']),
            'cancel_url'           => APP_URL . '/failed.php?gateway=stripe&ref=' . urlencode($payment['payment_reference']),
            'metadata'             => [
                'participant_ref' => $participant['reference_number'],
                'payment_ref'     => $payment['payment_reference'],
                'participant_id'  => (string) $participant['id'],
            ],
            'expires_at'           => time() + (30 * 60), // 30-minute window
        ]);

        // Store the Stripe session ID in payments table
        Database::execute(
            'UPDATE payments SET gateway_transaction_id = ? WHERE payment_reference = ?',
            [$session->id, $payment['payment_reference']]
        );

        return $session->url;
    }

    // --- Verify payment after redirect from Stripe ---
    public function verifyCheckoutSession(string $sessionId): ?array
    {
        try {
            $session = StripeSession::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                return [
                    'success'        => true,
                    'transaction_id' => $session->payment_intent,
                    'amount'         => $session->amount_total / 100,
                    'metadata'       => (array) $session->metadata,
                ];
            }

            return ['success' => false, 'status' => $session->payment_status];
        } catch (Throwable $e) {
            error_log('Stripe verify error: ' . $e->getMessage());
            return null;
        }
    }

    // --- Handle Stripe webhook (for reliable payment confirmation) ---
    public function handleWebhook(string $payload, string $sigHeader): array
    {
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
        } catch (SignatureVerificationException $e) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }

        if ($event->type === 'checkout.session.completed') {
            $session  = $event->data->object;
            $payRef   = $session->metadata->payment_ref ?? null;

            if (!$payRef) {
                return ['success' => false, 'error' => 'No payment reference in metadata'];
            }

            $this->markPaymentSuccess(
                $payRef,
                $session->id,
                (array) $session
            );

            return ['success' => true];
        }

        if ($event->type === 'checkout.session.expired') {
            $session = $event->data->object;
            $payRef  = $session->metadata->payment_ref ?? null;
            if ($payRef) {
                $this->markPaymentFailed($payRef, 'expired');
            }
            return ['success' => true];
        }

        return ['success' => true, 'ignored' => $event->type];
    }

    // --- Update DB on successful payment ---
    public function markPaymentSuccess(string $paymentRef, string $transactionId, array $gatewayData): void
    {
        $payment = Database::fetchOne(
            'SELECT * FROM payments WHERE payment_reference = ?',
            [$paymentRef]
        );

        if (!$payment || $payment['status'] === 'Success') {
            return; // Already processed or not found
        }

        Database::execute(
            'UPDATE payments
             SET status = ?, gateway_transaction_id = ?, payment_date = NOW(), gateway_response = ?
             WHERE payment_reference = ?',
            ['Success', $transactionId, json_encode($gatewayData), $paymentRef]
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

        // Send confirmation email
        $participant = Database::fetchOne('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']]);
        $category    = Database::fetchOne('SELECT * FROM event_categories WHERE id = ?', [$participant['category_id']]);
        if ($participant && $category) {
            EmailService::sendConfirmation($participant, $payment, $category);
        }
    }

    public function markPaymentFailed(string $paymentRef, string $reason = 'cancelled'): void
    {
        Database::execute(
            "UPDATE payments SET status = 'Failed', gateway_response = ? WHERE payment_reference = ?",
            [json_encode(['reason' => $reason]), $paymentRef]
        );

        $payment = Database::fetchOne('SELECT * FROM payments WHERE payment_reference = ?', [$paymentRef]);
        if ($payment) {
            Database::execute(
                "UPDATE participants SET registration_status = 'Failed' WHERE id = ?",
                [$payment['participant_id']]
            );
        }
    }
}
