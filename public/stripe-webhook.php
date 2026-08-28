<?php
declare(strict_types=1);

/**
 * Stripe Webhook Endpoint
 *
 * Stripe calls this URL server-to-server to reliably confirm payment.
 * It verifies the signature, then delegates to StripePayment::handleWebhook().
 *
 * Register this URL in: Stripe Dashboard → Developers → Webhooks
 * Events to listen for: checkout.session.completed, checkout.session.expired
 */

require_once __DIR__ . '/app/config.php';
require_once APP_PATH . '/database.php';
require_once APP_PATH . '/functions.php';
require_once APP_PATH . '/email.php';
require_once APP_PATH . '/payment/stripe.php';

// Read raw body BEFORE any output
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sigHeader)) {
    http_response_code(400);
    exit('Missing payload or signature');
}

if (empty(STRIPE_WEBHOOK_SECRET)) {
    http_response_code(500);
    error_log('Stripe webhook secret not configured');
    exit('Webhook secret not set');
}

try {
    $stripe = new StripePayment();
    $result = $stripe->handleWebhook($payload, $sigHeader);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Unknown error']);
    }
} catch (Throwable $e) {
    error_log('Stripe webhook exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal error']);
}
