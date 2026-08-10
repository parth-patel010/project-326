<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/hotels.php';
require_once __DIR__ . '/../lib/menu.php';

/**
 * Full hotel menu: categories + items + offers
 * GET /menu/list.php?hotel_id=1
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$hotelId = trim((string) ($_GET['hotel_id'] ?? $_GET['id'] ?? ''));
if ($hotelId === '') {
    fail('hotel_id required');
}

$hotel = find_hotel_by_public_id($hotelId);
if (!$hotel) {
    fail('Hotel not found', 404);
}

respond([
    'ok' => true,
    'menu' => get_hotel_menu($hotel),
]);
