<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/hotels.php';

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

$includeMenu = isset($_GET['menu']) && ($_GET['menu'] === '1' || $_GET['menu'] === 'true');
$payload = [
    'ok' => true,
    'hotel' => present_hotel($hotel),
];

if ($includeMenu) {
    require_once __DIR__ . '/../lib/menu.php';
    $payload['menu'] = get_hotel_menu($hotel);
}

respond($payload);
