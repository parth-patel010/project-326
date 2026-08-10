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
    $travelMins = H3::approxMinutesFromKm($km);
    if (count($nearby) < 15) {
        $route = $osrm->route($lat, $lng, (float) $hotel['latitude'], (float) $hotel['longitude']);
        if (!empty($route['ok'])) {
            $km = (float) ($route['distance_km'] ?? $km);
            $travelMins = (int) ($route['duration_min'] ?? $travelMins);
        }
    }
    // prep_mins from present_hotel = avg of last 5 order prep durations (default 19)
    $prepMins = (int) ($hotel['prep_mins'] ?? FM_DEFAULT_PREP_MINS);
    if ($prepMins <= 0) {
        $prepMins = FM_DEFAULT_PREP_MINS;
    }
    $hotel['km'] = round($km, 2);
    $hotel['travel_mins'] = $travelMins;
    $hotel['prep_mins'] = $prepMins;
    // Customer-facing ETA = kitchen prep + road travel
    $hotel['mins'] = $prepMins + $travelMins;
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
