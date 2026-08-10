<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';
require_once __DIR__ . '/../lib/Dispatch.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/H3.php';
require_once __DIR__ . '/../lib/Realtime.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$body = json_body();
$orderId = trim((string) ($body['order_id'] ?? ''));
if ($orderId === '') {
    fail('order_id required');
}

$order = find_order_by_public_id($orderId);
if (!$order) {
    fail('Order not found', 404);
}

try {
    $result = Dispatch::accept($order, (int) $partner['id']);
} catch (Throwable $e) {
    fail($e->getMessage(), 409);
}

$fresh = find_order_by_public_id($orderId);
respond([
    'ok' => true,
    'order' => present_order($fresh),
    'hotel_otp' => $result['hotel_otp'],
    'delivery_otp' => $result['delivery_otp'],
    'pickup_deadline_minutes' => $result['pickup_deadline_minutes'],
]);
