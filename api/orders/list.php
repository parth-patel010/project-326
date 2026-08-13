<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$phone = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? '')) ?? '';
$phone = substr($phone, -10);

if (strlen($phone) !== 10) {
    fail('phone required (10 digits)');
}

$stmt = db()->prepare(
    'SELECT * FROM orders
     WHERE RIGHT(REPLACE(REPLACE(REPLACE(customer_phone, " ", ""), "-", ""), "+", ""), 10) = :phone
     ORDER BY created_at DESC
     LIMIT 50'
);
$stmt->execute([':phone' => $phone]);
$rows = $stmt->fetchAll() ?: [];

$orders = [];
foreach ($rows as $row) {
    $presented = present_order($row);
    if ($presented) {
        $orders[] = $presented;
    }
}

respond([
    'ok' => true,
    'orders' => $orders,
]);
