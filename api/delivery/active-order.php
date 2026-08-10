<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/hotels.php';
require_once __DIR__ . '/../lib/Osrm.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$stmt = db()->prepare(
    "SELECT * FROM orders
     WHERE assigned_partner_id = :id
       AND status IN ('preparing','ready','out_for_delivery')
     ORDER BY id DESC LIMIT 1"
);
$stmt->execute([':id' => $partner['id']]);
$order = $stmt->fetch();
if (!$order) {
    respond(['ok' => true, 'order' => null]);
}

$hotelLat = null;
$hotelLng = null;
if (!empty($order['hotel_db_id'])) {
    $h = find_hotel_by_id((int) $order['hotel_db_id']);
    if ($h) {
        $hotelLat = $h['latitude'] !== null ? (float) $h['latitude'] : null;
        $hotelLng = $h['longitude'] !== null ? (float) $h['longitude'] : null;
    }
}

$phase = empty($order['hotel_otp_verified']) ? 'pickup' : 'dropoff';
$fromLat = (float) ($partner['current_latitude'] ?? 0);
$fromLng = (float) ($partner['current_longitude'] ?? 0);
$toLat = $phase === 'pickup'
    ? (float) ($hotelLat ?? $order['delivery_lat'])
    : (float) $order['delivery_lat'];
$toLng = $phase === 'pickup'
    ? (float) ($hotelLng ?? $order['delivery_lng'])
    : (float) $order['delivery_lng'];

$route = (new Osrm())->route($fromLat, $fromLng, $toLat, $toLng);
$deadline = $order['pickup_deadline_at'] ?? null;
$overdue = $deadline && strtotime((string) $deadline) < time() && $phase === 'pickup';

respond([
    'ok' => true,
    'order' => present_order($order),
    'phase' => $phase,
    'hotel_otp' => $order['hotel_otp'],
    'items' => json_decode((string) $order['items_json'], true) ?: [],
    'hotel' => [
        'name' => $order['restaurant_name'],
        'latitude' => $hotelLat,
        'longitude' => $hotelLng,
    ],
    'customer' => [
        'name' => $order['customer_name'],
        'phone' => $order['customer_phone'],
        'line' => $order['delivery_line'],
        'details' => $order['delivery_details'],
        'latitude' => $order['delivery_lat'] !== null ? (float) $order['delivery_lat'] : null,
        'longitude' => $order['delivery_lng'] !== null ? (float) $order['delivery_lng'] : null,
    ],
    'route' => $route,
    'pickup_deadline_at' => $deadline,
    'overdue' => (bool) $overdue,
    'timer_message' => $overdue
        ? 'If any issue contact admin'
        : null,
]);
