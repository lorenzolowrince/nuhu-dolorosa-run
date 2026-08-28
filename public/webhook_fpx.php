<?php
declare(strict_types=1);

// ============================================================
// Billplz FPX Webhook / Callback Endpoint
// URL: https://yoursite.com/webhook_fpx.php
// Configure as callback_url in Billplz bill creation
// ============================================================

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/payment/fpx.php';
require_once APP_PATH . '/email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$params = $_POST;

if (empty($params)) {
    http_response_code(400);
    exit('Bad Request');
}

try {
    $fpx = new FPXPayment();

    // Verify X-Signature to prevent spoofing
    if (!$fpx->verifySignature($params)) {
        error_log('FPX webhook: invalid signature. Params: ' . json_encode($params));
        http_response_code(400);
        exit('Invalid signature');
    }

    $billId    = $params['id']        ?? '';
    $paid      = ($params['paid']     ?? 'false') === 'true';
    $reference = $params['reference_2'] ?? ''; // We stored payment_reference here

    if (!$reference) {
        http_response_code(400);
        exit('Missing reference');
    }

    if ($paid) {
        $fpx->markPaymentSuccess($reference, $billId, $params);
    } else {
        $fpx->markPaymentFailed($reference, $params);
    }

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    error_log('FPX webhook exception: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal Error');
}
