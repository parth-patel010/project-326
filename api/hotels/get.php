<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/hotels.php';
require_once __DIR__ . '/../lib/H3.php';
require_once __DIR__ . '/../lib/Osrm.php';
require_once __DIR__ . '/../lib/Settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    fail('id required');
}

$hotel = find_hotel_by_public_id($id);
if (!$hotel) {
    fail('Hotel not found', 404);
}

$presented = present_hotel($hotel);
$prep = (int) ($presented['prep_mins'] ?? FM_DEFAULT_PREP_MINS);
if ($prep <= 0) {
    $prep = FM_DEFAULT_PREP_MINS;
}

$lat = (float) ($_GET['lat'] ?? $_GET['latitude'] ?? 0);
$lng = (float) ($_GET['lng'] ?? $_GET['longitude'] ?? 0);
if ($lat !== 0.0 || $lng !== 0.0) {
    $hLat = $presented['latitude'] ?? null;
    $hLng = $presented['longitude'] ?? null;
    if ($hLat !== null && $hLng !== null) {
        $km = H3::haversineKm($lat, $lng, (float) $hLat, (float) $hLng);
        $travel = H3::approxMinutesFromKm($km);
        $osrm = new Osrm($CONFIG['osrm_base_url'] ?? null);
        $route = $osrm->route($lat, $lng, (float) $hLat, (float) $hLng);
        if (!empty($route['ok'])) {
            $km = (float) ($route['distance_km'] ?? $km);
            $travel = (int) ($route['duration_min'] ?? $travel);
        }
        $presented['km'] = round($km, 2);
        $presented['travel_mins'] = $travel;
        $presented['prep_mins'] = $prep;
        $presented['mins'] = $prep + $travel;
        $presented['fee'] = Settings::deliveryChargeForKm($km);
    }
} else {
    // No customer location: show prep + typical travel floor so UI isn't prep-only
    $presented['prep_mins'] = $prep;
    $presented['travel_mins'] = 15;
    $presented['mins'] = $prep + 15;
}

$includeMenu = isset($_GET['menu']) && ($_GET['menu'] === '1' || $_GET['menu'] === 'true');
$payload = [
    'ok' => true,
    'hotel' => $presented,
];

if ($includeMenu) {
    require_once __DIR__ . '/../lib/menu.php';
    $payload['menu'] = get_hotel_menu($hotel);
}

respond($payload);
