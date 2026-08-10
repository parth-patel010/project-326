<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/hotels.php';
require_once __DIR__ . '/../lib/Osrm.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    fail('id required');
}

$order = find_order_by_public_id($id);
if (!$order) {
    fail('Order not found', 404);
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

$custLat = $order['delivery_lat'] !== null ? (float) $order['delivery_lat'] : null;
$custLng = $order['delivery_lng'] !== null ? (float) $order['delivery_lng'] : null;
$partnerLat = $order['partner_lat'] !== null ? (float) $order['partner_lat'] : null;
$partnerLng = $order['partner_lng'] !== null ? (float) $order['partner_lng'] : null;

$route = null;
if ($order['status'] === 'out_for_delivery' && $partnerLat && $custLat) {
    $route = (new Osrm())->route($partnerLat, $partnerLng, $custLat, $custLng);
} elseif ($hotelLat && $custLat) {
    $route = (new Osrm())->route($hotelLat, $hotelLng, $custLat, $custLng);
}

$payload = [
    'ok' => true,
    'order' => present_order($order),
    'hotel' => [
        'name' => $order['restaurant_name'],
        'latitude' => $hotelLat,
        'longitude' => $hotelLng,
    ],
    'customer' => [
        'latitude' => $custLat,
        'longitude' => $custLng,
    ],
    'partner' => [
        'latitude' => $partnerLat,
        'longitude' => $partnerLng,
    ],
    'route' => $route,
    // Customer only sees delivery OTP when out for delivery
    'delivery_otp' => ($order['status'] === 'out_for_delivery')
        ? ($order['delivery_otp'] ?? null)
        : null,
];

respond($payload);
