<?php

declare(strict_types=1);

/**
 * Save item discount / BOGO offer for hotel menu items.
 * POST JSON: item_id, offer_type (none|discount|bogo), discount_price, buy_qty, get_qty
 */
require_once __DIR__ . '/includes/auth.php';
ha_require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$hotelId = (int) $_SESSION['ha_hotel_id'];
$pdo = admin_db();
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) {
    $body = $_POST;
}

$itemId = (int) ($body['item_id'] ?? 0);
$offerType = strtolower(trim((string) ($body['offer_type'] ?? 'none')));
if (!in_array($offerType, ['none', 'discount', 'bogo'], true)) {
    $offerType = 'none';
}
$discountRaw = $body['discount_price'] ?? null;
$discountPrice = ($discountRaw === '' || $discountRaw === null) ? null : (float) $discountRaw;
$buyQty = max(1, (int) ($body['buy_qty'] ?? 1));
$getQty = max(0, (int) ($body['get_qty'] ?? 0));

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'item_id required']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, price FROM menu_items WHERE id = :id AND hotel_id = :h LIMIT 1');
$stmt->execute([':id' => $itemId, ':h' => $hotelId]);
$item = $stmt->fetch();
if (!$item) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Item not found']);
    exit;
}

if ($offerType === 'none') {
    $discountPrice = null;
    $buyQty = 1;
    $getQty = 0;
} elseif ($offerType === 'discount') {
    if ($discountPrice === null || $discountPrice < 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Valid discount_price required']);
        exit;
    }
    if ($discountPrice >= (float) $item['price']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Discount must be less than base price']);
        exit;
    }
    $buyQty = 1;
    $getQty = 0;
} else { // bogo
    if ($getQty < 1) {
        $getQty = 1;
    }
    // optional cut price still allowed on BOGO
    if ($discountPrice !== null && $discountPrice >= (float) $item['price']) {
        $discountPrice = null;
    }
}

try {
    $pdo->prepare(
        'UPDATE menu_items SET
           discount_price = :dp,
           offer_type = :ot,
           buy_qty = :bq,
           get_qty = :gq
         WHERE id = :id AND hotel_id = :h'
    )->execute([
        ':dp' => $discountPrice,
        ':ot' => $offerType,
        ':bq' => $buyQty,
        ':gq' => $getQty,
        ':id' => $itemId,
        ':h' => $hotelId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'ok' => true,
    'item_id' => $itemId,
    'offer_type' => $offerType,
    'discount_price' => $discountPrice,
    'buy_qty' => $buyQty,
    'get_qty' => $getQty,
]);
