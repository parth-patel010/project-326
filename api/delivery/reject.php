<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';
require_once __DIR__ . '/../lib/Dispatch.php';
require_once __DIR__ . '/../lib/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$body = json_body();
$orderId = trim((string) ($body['order_id'] ?? ''));
$order = find_order_by_public_id($orderId);
if (!$order) {
    fail('Order not found', 404);
}

Dispatch::rejectOffer($order, (int) $partner['id']);
respond(['ok' => true, 'message' => 'Offer rejected']);
