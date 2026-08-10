<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$publicId = trim((string) ($_GET['id'] ?? ''));
if ($publicId === '') {
    fail('id required');
}

$order = find_order_by_public_id($publicId);
if (!$order) {
    fail('Order not found', 404);
}

respond([
    'ok' => true,
    'order' => present_order($order),
]);
