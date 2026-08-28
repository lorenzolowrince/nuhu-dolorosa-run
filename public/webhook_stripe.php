<?php
declare(strict_types=1);

// ============================================================
// Stripe Webhook Endpoint
// URL: https://yoursite.com/webhook_stripe.php
// Configure in Stripe Dashboard → Webhooks
// Events: checkout.session.completed, checkout.session.expired
// ============================================================

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/payment/stripe.php';
require_once APP_PATH . '/email.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sigHeader)) {
    http_response_code(400);
    exit('Bad Request');
}

try {
    $stripe = new StripePayment();
    $result = $stripe->handleWebhook($payload, $sigHeader);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } else {
        error_log('Stripe webhook failed: ' . ($result['error'] ?? 'unknown'));
        http_response_code(400);
        echo json_encode(['error' => $result['error'] ?? 'Processing failed']);
    }
} catch (Throwable $e) {
    error_log('Stripe webhook exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
