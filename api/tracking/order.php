<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/hotels.php';
require_once __DIR__ . '/../lib/H3.php';
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
$prepMins = FM_DEFAULT_PREP_MINS;
if (!empty($order['hotel_db_id'])) {
    $h = find_hotel_by_id((int) $order['hotel_db_id']);
    if ($h) {
        $hotelLat = $h['latitude'] !== null ? (float) $h['latitude'] : null;
        $hotelLng = $h['longitude'] !== null ? (float) $h['longitude'] : null;
        $prepMins = fm_hotel_prep_mins(db(), (int) $order['hotel_db_id']);
    }
}
if ($prepMins <= 0) {
    $prepMins = FM_DEFAULT_PREP_MINS;
}

$custLat = $order['delivery_lat'] !== null ? (float) $order['delivery_lat'] : null;
$custLng = $order['delivery_lng'] !== null ? (float) $order['delivery_lng'] : null;
$partnerLat = isset($order['partner_lat']) && $order['partner_lat'] !== null
    ? (float) $order['partner_lat']
    : null;
$partnerLng = isset($order['partner_lng']) && $order['partner_lng'] !== null
    ? (float) $order['partner_lng']
    : null;

$status = (string) ($order['status'] ?? '');
$outForDelivery = $status === 'out_for_delivery';
$hasPartner =
    !empty($order['assigned_partner_id'])
    || ($partnerLat !== null && $partnerLng !== null);
$partnerAssigned = $hasPartner
    && !in_array($status, ['delivered', 'cancelled', 'payment_failed', 'awaiting_payment'], true);

// Phase A (accepted…ready): show hotel + customer + partner pin, NO route polyline.
// Phase B (out_for_delivery): hide hotel, live partner→customer route.
$route = null;
$distanceKm = null;
$mapHotelLat = $outForDelivery ? null : $hotelLat;
$mapHotelLng = $outForDelivery ? null : $hotelLng;

if ($outForDelivery && $partnerLat && $partnerLng && $custLat && $custLng) {
    $route = (new Osrm())->route($partnerLat, $partnerLng, $custLat, $custLng);
} elseif (!$partnerAssigned && $hotelLat && $hotelLng && $custLat && $custLng) {
    // Pre-accept: hotel→customer route for ETA only (still returned; clients may ignore pre-partner)
    $route = (new Osrm())->route($hotelLat, $hotelLng, $custLat, $custLng);
}

if ($partnerAssigned && !$outForDelivery) {
    $route = null;
}

if ($route && !empty($route['ok'])) {
    $distanceKm = (float) ($route['distance_km'] ?? 0);
} elseif ($outForDelivery && $partnerLat && $partnerLng && $custLat && $custLng) {
    $distanceKm = H3::haversineKm($partnerLat, $partnerLng, $custLat, $custLng);
} elseif ($hotelLat && $hotelLng && $custLat && $custLng) {
    $distanceKm = H3::haversineKm($hotelLat, $hotelLng, $custLat, $custLng);
}

// Delivery time always at 20 km/h from road/haversine distance
$travelMins = $distanceKm !== null && $distanceKm > 0
    ? H3::approxMinutesFromKm($distanceKm, 20.0)
    : 15;

if (is_array($route) && !empty($route['ok'])) {
    $route['duration_min'] = $travelMins;
    $route['speed_kmh'] = 20;
}

$totalMins = $prepMins + $travelMins;

// Countdown starts when kitchen accepts (preparing), else paid/created
$startRaw = $order['preparing_at']
    ?? $order['paid_at']
    ?? $order['created_at']
    ?? null;
$startTs = $startRaw ? strtotime((string) $startRaw) : false;
if ($startTs === false) {
    $startTs = time();
}
$expectedByTs = $startTs + ($totalMins * 60);
$remainingSec = max(0, $expectedByTs - time());
$isLate = !in_array((string) $order['status'], ['delivered', 'cancelled', 'payment_failed'], true)
    && time() >= $expectedByTs;

$presented = present_order($order);
if (is_array($presented)) {
    $presented['eta_minutes'] = $totalMins;
    $presented['prep_mins'] = $prepMins;
    $presented['travel_mins'] = $travelMins;
    $presented['expected_by'] = date('c', $expectedByTs);
    $presented['eta_start_at'] = date('c', $startTs);
}

respond([
    'ok' => true,
    'order' => $presented,
    'phase' => $outForDelivery ? 'out_for_delivery' : ($partnerAssigned ? 'partner_accepted' : 'pre_partner'),
    'hotel' => [
        'name' => $order['restaurant_name'],
        'latitude' => $mapHotelLat,
        'longitude' => $mapHotelLng,
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
    'eta' => [
        'prep_mins' => $prepMins,
        'travel_mins' => $travelMins,
        'total_mins' => $totalMins,
        'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
        'speed_kmh' => 20,
        'expected_by' => date('c', $expectedByTs),
        'eta_start_at' => date('c', $startTs),
        'remaining_seconds' => $remainingSec,
        'is_late' => $isLate,
    ],
    // Customer only sees delivery OTP when out for delivery
    'delivery_otp' => $outForDelivery
        ? ($order['delivery_otp'] ?? null)
        : null,
]);
