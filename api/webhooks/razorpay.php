<?php

declare(strict_types=1);

/**
 * Razorpay webhook — set URL to:
 *   https://YOUR_HOST/api/webhooks/razorpay.php
 * Enable events: payment.captured, payment.failed, order.paid
 *
 * This is the safety net when the app dies after payment success
 * but before verify-payment.php is called. Order was already created
 * as awaiting_payment, so we only flip status here.
 */

define('FM_SKIP_SECURITY', true);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/RazorpayClient.php';
require_once __DIR__ . '/../lib/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

global $CONFIG;
$raw = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

$secret = $CONFIG['razorpay']['webhook_secret'] ?? '';
if ($secret === '' || $secret === 'YOUR_WEBHOOK_SECRET') {
    fail('Webhook secret not configured', 500);
}

if (!RazorpayClient::verifyWebhookSignature($raw, $signature, $secret)) {
    fail('Invalid webhook signature', 401);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    fail('Invalid JSON');
}

$eventType = (string) ($payload['event'] ?? '');
$eventId = $payload['id'] ?? null;

$pdo = db();

// Idempotent event log
try {
    $ins = $pdo->prepare(
        'INSERT INTO webhook_events (event_id, event_type, payload_json, processed)
         VALUES (:event_id, :event_type, :payload, 0)'
    );
    $ins->execute([
        ':event_id' => $eventId,
        ':event_type' => $eventType,
        ':payload' => $raw,
    ]);
} catch (PDOException $e) {
    // Duplicate event_id → already handled
    if ((int) $e->getCode() === 23000) {
        respond(['ok' => true, 'duplicate' => true]);
    }
    throw $e;
}

$paymentEntity = $payload['payload']['payment']['entity'] ?? null;
$orderEntity = $payload['payload']['order']['entity'] ?? null;

$updated = null;

if (in_array($eventType, ['payment.captured', 'order.paid'], true)) {
    $rzOrderId = null;
    $paymentId = null;

    if (is_array($paymentEntity)) {
        $rzOrderId = $paymentEntity['order_id'] ?? null;
        $paymentId = $paymentEntity['id'] ?? null;
    }
    if (!$rzOrderId && is_array($orderEntity)) {
        $rzOrderId = $orderEntity['id'] ?? null;
    }

    if ($rzOrderId && $paymentId) {
        $order = find_order_by_razorpay_order_id((string) $rzOrderId);
        if ($order) {
            $updated = mark_order_paid($order, (string) $paymentId, null);
        }
    } elseif ($rzOrderId && !$paymentId && is_array($orderEntity)) {
        // order.paid without payment id in payload — mark using placeholder fetch later
        $order = find_order_by_razorpay_order_id((string) $rzOrderId);
        if ($order && $order['status'] === 'awaiting_payment') {
            $updated = mark_order_paid($order, 'webhook_order_paid', null);
        }
    }
}

if ($eventType === 'payment.failed' && is_array($paymentEntity)) {
    $rzOrderId = $paymentEntity['order_id'] ?? null;
    if ($rzOrderId) {
        $order = find_order_by_razorpay_order_id((string) $rzOrderId);
        if ($order) {
            $updated = mark_order_payment_failed($order);
        }
    }
}

$pdo->prepare('UPDATE webhook_events SET processed = 1 WHERE event_id = :id')
    ->execute([':id' => $eventId]);

respond([
    'ok' => true,
    'event' => $eventType,
    'order' => $updated ? present_order($updated) : null,
]);
