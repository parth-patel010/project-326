<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/hotels.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'pure_veg' => isset($_GET['pure_veg']) && ($_GET['pure_veg'] === '1' || $_GET['pure_veg'] === 'true'),
    'offer_active' => isset($_GET['offer_active']) && ($_GET['offer_active'] === '1' || $_GET['offer_active'] === 'true'),
];

$hotels = list_hotels($filters);

respond([
    'ok' => true,
    'count' => count($hotels),
    'hotels' => $hotels,
]);
