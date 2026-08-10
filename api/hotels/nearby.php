<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/H3.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Osrm.php';
require_once __DIR__ . '/../lib/hotels.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$lat = (float) ($_GET['lat'] ?? $_GET['latitude'] ?? 0);
$lng = (float) ($_GET['lng'] ?? $_GET['longitude'] ?? 0);
if ($lat === 0.0 && $lng === 0.0) {
    fail('lat and lng required');
}

$settings = Settings::get();
$maxKm = (float) ($settings['max_delivery_radius_km'] ?? 10);
$all = list_hotels([
    'q' => trim((string) ($_GET['q'] ?? '')),
    'pure_veg' => isset($_GET['pure_veg']),
]);

$osrm = new Osrm($CONFIG['osrm_base_url'] ?? null);
$nearby = [];
foreach ($all as $hotel) {
    if ($hotel['latitude'] === null || $hotel['longitude'] === null) {
        continue;
    }
    $km = H3::haversineKm($lat, $lng, (float) $hotel['latitude'], (float) $hotel['longitude']);
    if ($km > $maxKm) {
        continue;
    }
    $mins = H3::approxMinutesFromKm($km);
    // Optional OSRM refine for first 15
    if (count($nearby) < 15) {
        $route = $osrm->route($lat, $lng, (float) $hotel['latitude'], (float) $hotel['longitude']);
        if (!empty($route['ok'])) {
            $km = (float) ($route['distance_km'] ?? $km);
            $mins = (int) ($route['duration_min'] ?? $mins);
        }
    }
    $hotel['km'] = round($km, 2);
    $hotel['mins'] = $mins;
    $hotel['fee'] = Settings::deliveryChargeForKm($km);
    $nearby[] = $hotel;
}

usort($nearby, static fn ($a, $b) => $a['km'] <=> $b['km']);

respond([
    'ok' => true,
    'radius_km' => $maxKm,
    'count' => count($nearby),
    'hotels' => $nearby,
]);
