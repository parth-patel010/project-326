<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/hotels.php';
require_once __DIR__ . '/../lib/menu.php';

/**
 * Menu items for a hotel (filterable)
 * GET /menu/items.php?hotel_id=1&category=tea&veg_only=1&q=chai&recommended=1
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$hotelId = trim((string) ($_GET['hotel_id'] ?? ''));
if ($hotelId === '') {
    fail('hotel_id required');
}

$hotel = find_hotel_by_public_id($hotelId);
if (!$hotel) {
    fail('Hotel not found', 404);
}

$items = list_menu_items((int) $hotel['id'], [
    'category' => trim((string) ($_GET['category'] ?? '')),
    'veg_only' => isset($_GET['veg_only']) && ($_GET['veg_only'] === '1' || $_GET['veg_only'] === 'true'),
    'recommended' => isset($_GET['recommended']) && ($_GET['recommended'] === '1' || $_GET['recommended'] === 'true'),
    'q' => trim((string) ($_GET['q'] ?? '')),
]);

respond([
    'ok' => true,
    'hotel_id' => $hotel['public_id'],
    'count' => count($items),
    'items' => $items,
]);
