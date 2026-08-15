<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['ha_user_id']) || empty($_SESSION['ha_hotel_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$hotelId = (int) $_SESSION['ha_hotel_id'];
$hotel = ha_hotel() ?? [];
$publicId = (string) ($hotel['public_id'] ?? '');
$hotelName = (string) ($hotel['name'] ?? '');
$pdo = admin_db();

$deliveryOrders = [];
try {
    $sql = "SELECT id, public_id, customer_name, customer_phone, delivery_label, delivery_line, delivery_details,
                   note, payment_mode, status, items_json, total_paise, subtotal_paise, delivery_fee_paise,
                   discount_paise, created_at, restaurant_name
            FROM orders
            WHERE (hotel_db_id = :hid OR restaurant_id = :pid)
              AND status IN ('placed','paid')
              AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at ASC
            LIMIT 30";
    $st = $pdo->prepare($sql);
    $st->execute([':hid' => $hotelId, ':pid' => $publicId]);
    foreach ($st->fetchAll() as $row) {
        $items = json_decode((string) ($row['items_json'] ?? '[]'), true);
        if (!is_array($items)) {
            $items = [];
        }
        // orders.note → cooking_request for popup "Cooking instructions"
        $cookingRequest = trim((string) ($row['note'] ?? ''));
        $deliveryOrders[] = [
            'order_id' => (int) $row['id'],
            'order_number' => (string) $row['public_id'],
            'customer_name' => (string) $row['customer_name'],
            'customer_phone' => (string) ($row['customer_phone'] ?? ''),
            'delivery_address' => trim(
                ((string) ($row['delivery_label'] ?? '')) . ': ' . ((string) ($row['delivery_line'] ?? ''))
            ),
            'delivery_details' => (string) ($row['delivery_details'] ?? ''),
            'note' => $cookingRequest,
            'cooking_request' => $cookingRequest,
            'payment_method' => (string) ($row['payment_mode'] ?? ''),
            'status' => (string) $row['status'],
            'items' => $items,
            'total_amount' => ((int) $row['total_paise']) / 100,
            'subtotal' => ((int) ($row['subtotal_paise'] ?? 0)) / 100,
            'delivery_fee' => ((int) ($row['delivery_fee_paise'] ?? 0)) / 100,
            'discount' => ((int) ($row['discount_paise'] ?? 0)) / 100,
            'created_at' => (string) $row['created_at'],
            'order_fulfillment_type' => 'delivery',
            'managed_by_hotel' => 0,
            'restaurant_name' => (string) ($row['restaurant_name'] ?? $hotelName),
        ];
    }
} catch (Throwable $e) {
    error_log('ha-api check-new-orders: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'hotel_name' => $hotelName,
    'orders' => [],
    'staff_requests' => [],
    'delivery_orders' => $deliveryOrders,
]);
