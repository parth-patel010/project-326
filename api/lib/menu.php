<?php

declare(strict_types=1);

function find_menu_item_by_public_id(string $publicId): ?array
{
    $stmt = db()->prepare(
        'SELECT mi.*, mc.slug AS category_slug, mc.name AS category_name, h.public_id AS hotel_public_id
         FROM menu_items mi
         INNER JOIN menu_categories mc ON mc.id = mi.category_id
         INNER JOIN hotels h ON h.id = mi.hotel_id
         WHERE mi.public_id = :id AND mi.is_available = 1 AND h.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([':id' => $publicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function present_menu_item(?array $item): ?array
{
    if (!$item) {
        return null;
    }
    $discount = isset($item['discount_price']) && $item['discount_price'] !== null
        ? (float) $item['discount_price']
        : null;
    $offerType = (string) ($item['offer_type'] ?? 'none');
    if ($offerType === '') {
        $offerType = 'none';
    }
    return [
        'id' => $item['public_id'],
        'hotel_id' => $item['hotel_public_id'] ?? null,
        'name' => $item['name'],
        'price' => (float) $item['price'],
        'discount_price' => $discount,
        'offer_type' => $offerType,
        'buy_qty' => (int) ($item['buy_qty'] ?? 1),
        'get_qty' => (int) ($item['get_qty'] ?? 0),
        'image' => $item['image'],
        'veg' => (bool) $item['is_veg'],
        'categoryId' => $item['category_slug'] ?? null,
        'category_name' => $item['category_name'] ?? null,
        'recommended' => (bool) $item['is_recommended'],
        'description' => $item['description'],
        'available' => (bool) ($item['is_available'] ?? 1),
    ];
}

function present_category(array $cat): array
{
    return [
        'id' => $cat['slug'],
        'name' => $cat['name'],
        'icon' => $cat['icon'],
    ];
}

/**
 * Full menu payload matching app RestaurantMenu shape.
 */
function get_hotel_menu(array $hotel): array
{
    $hotelId = (int) $hotel['id'];
    $pdo = db();

    $cats = $pdo->prepare(
        'SELECT * FROM menu_categories
         WHERE hotel_id = :id AND is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $cats->execute([':id' => $hotelId]);
    $categories = $cats->fetchAll();

    $itemsStmt = $pdo->prepare(
        'SELECT mi.*, mc.slug AS category_slug, mc.name AS category_name, h.public_id AS hotel_public_id
         FROM menu_items mi
         INNER JOIN menu_categories mc ON mc.id = mi.category_id
         INNER JOIN hotels h ON h.id = mi.hotel_id
         WHERE mi.hotel_id = :id AND mi.is_available = 1
         ORDER BY mi.sort_order ASC, mi.id ASC'
    );
    $itemsStmt->execute([':id' => $hotelId]);
    $items = $itemsStmt->fetchAll();

    $offers = hotel_offers($hotelId);

    return [
        'hotel' => present_hotel($hotel),
        'area' => $hotel['area'],
        'rating_count' => (int) $hotel['rating_count'],
        'offers' => array_map(static function ($o) {
            return [
                'title' => $o['title'],
                'subtitle' => $o['subtitle'],
            ];
        }, $offers),
        'categories' => array_map('present_category', $categories),
        'items' => array_map('present_menu_item', $items),
    ];
}

function list_menu_items(int $hotelId, array $filters = []): array
{
    $where = ['mi.hotel_id = :hid', 'mi.is_available = 1'];
    $params = [':hid' => $hotelId];

    if (!empty($filters['category'])) {
        $where[] = 'mc.slug = :cat';
        $params[':cat'] = $filters['category'];
    }
    if (!empty($filters['veg_only'])) {
        $where[] = 'mi.is_veg = 1';
    }
    if (!empty($filters['recommended'])) {
        $where[] = 'mi.is_recommended = 1';
    }
    if (!empty($filters['q'])) {
        $where[] = '(mi.name LIKE :q OR mi.description LIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }

    $sql = 'SELECT mi.*, mc.slug AS category_slug, mc.name AS category_name, h.public_id AS hotel_public_id
            FROM menu_items mi
            INNER JOIN menu_categories mc ON mc.id = mi.category_id
            INNER JOIN hotels h ON h.id = mi.hotel_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY mi.sort_order ASC, mi.id ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return array_map('present_menu_item', $stmt->fetchAll());
}
