<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/api/lib/Env.php';
Env::load(dirname(__DIR__) . '/api/.env');
require_once dirname(__DIR__) . '/api/lib/Realtime.php';
require_once dirname(__DIR__) . '/api/lib/order_status.php';

header('Content-Type: application/json');

if (empty($_SESSION['ha_user_id']) || empty($_SESSION['ha_hotel_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) {
    $body = $_POST;
}
$orderId = (int) ($body['order_id'] ?? 0);
if ($orderId < 1) {
    echo json_encode(['success' => false, 'error' => 'order_id required']);
    exit;
}

$hotelId = (int) $_SESSION['ha_hotel_id'];
$hotel = ha_hotel() ?? [];
$publicId = (string) ($hotel['public_id'] ?? '');
$pdo = admin_db();

$st = $pdo->prepare(
    'SELECT * FROM orders WHERE id = :id AND (hotel_db_id = :hid OR restaurant_id = :pid) LIMIT 1'
);
$st->execute([':id' => $orderId, ':hid' => $hotelId, ':pid' => $publicId]);
$order = $st->fetch();
if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

if (!in_array((string) $order['status'], ['placed', 'paid'], true)) {
    echo json_encode(['success' => false, 'error' => 'Order is not pending accept']);
    exit;
}

fm_order_set_status($pdo, $orderId, 'preparing', $hotelId);
try {
    Realtime::emit('order.status', [
        'order_id' => $order['public_id'],
        'status' => 'preparing',
    ], 'order:' . $order['public_id']);
} catch (Throwable $e) {
    // realtime optional
}

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'public_id' => $order['public_id'],
    'status' => 'preparing',
    'print_url' => 'print-online-kot.php?id=' . $orderId,
]);
