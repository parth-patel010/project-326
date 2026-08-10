<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/RazorpayClient.php';
require_once __DIR__ . '/../lib/orders.php';

/**
 * Client-side verify after Checkout success.
 * Webhook is the source of truth if this call never reaches the server.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

global $CONFIG;
$body = json_body();

$publicId = trim((string) ($body['order_id'] ?? ''));
$rzOrderId = trim((string) ($body['razorpay_order_id'] ?? ''));
$rzPaymentId = trim((string) ($body['razorpay_payment_id'] ?? ''));
$rzSignature = trim((string) ($body['razorpay_signature'] ?? ''));

if ($publicId === '' || $rzOrderId === '' || $rzPaymentId === '' || $rzSignature === '') {
    fail('order_id, razorpay_order_id, razorpay_payment_id, razorpay_signature required');
}

$order = find_order_by_public_id($publicId);
if (!$order) {
    fail('Order not found', 404);
}

if ($order['payment_mode'] !== 'prepaid') {
    fail('Order is not prepaid');
}

if (($order['razorpay_order_id'] ?? '') !== $rzOrderId) {
    fail('Razorpay order mismatch', 409);
}

$ok = RazorpayClient::verifyPaymentSignature(
    $rzOrderId,
    $rzPaymentId,
    $rzSignature,
    $CONFIG['razorpay']['key_secret']
);

if (!$ok) {
    fail('Invalid payment signature', 401);
}

$updated = mark_order_paid($order, $rzPaymentId, $rzSignature);

respond([
    'ok' => true,
    'order' => present_order($updated),
]);
