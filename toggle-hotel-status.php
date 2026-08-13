<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (empty($_SESSION['ha_user_id']) || empty($_SESSION['ha_hotel_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();

if (!ha_col_exists('hotels', 'is_open', $pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'is_open column missing — run migrate_hotel_menu_ops.php']);
    exit;
}

try {
    $st = $pdo->prepare('SELECT is_open FROM hotels WHERE id = :id LIMIT 1');
    $st->execute([':id' => $hotelId]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException('Hotel not found');
    }

    $currentlyOpen = (int) ($row['is_open'] ?? 1) === 1;
    $newOpen = $currentlyOpen ? 0 : 1;

    $pdo->prepare('UPDATE hotels SET is_open = :o WHERE id = :id')
        ->execute([':o' => $newOpen, ':id' => $hotelId]);

    $isOnline = $newOpen === 1;
    echo json_encode([
        'success' => true,
        'is_online' => $isOnline,
        'is_open' => $isOnline,
        'message' => $isOnline ? 'Hotel is now Online' : 'Hotel is now Offline',
    ]);
} catch (Throwable $e) {
    error_log('toggle-hotel-status: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
