<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/menu.php';

/**
 * Single menu item
 * GET /menu/item.php?id=1-masala-chai
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    fail('id required');
}

$item = find_menu_item_by_public_id($id);
if (!$item) {
    fail('Menu item not found', 404);
}

respond([
    'ok' => true,
    'item' => present_menu_item($item),
]);
