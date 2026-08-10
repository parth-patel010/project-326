<?php

declare(strict_types=1);

function find_hotel_by_public_id(string $publicId): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM hotels WHERE public_id = :id AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([':id' => $publicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_hotel_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM hotels WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function present_hotel(?array $hotel): ?array
{
    if (!$hotel) {
        return null;
    }
    return [
        'id' => $hotel['public_id'],
        'name' => $hotel['name'],
        'image' => $hotel['image'],
        'rating' => (float) $hotel['rating'],
        'rating_count' => (int) $hotel['rating_count'],
        'area' => $hotel['area'],
        'mins' => (int) $hotel['delivery_mins'],
        'km' => (float) $hotel['distance_km'],
        'fee' => (float) $hotel['delivery_fee'],
        'avg_price' => (float) $hotel['avg_price'],
        'tags' => $hotel['tags'],
        'pure_veg' => (bool) $hotel['pure_veg'],
        'offer_active' => (bool) $hotel['offer_active'],
        'latitude' => isset($hotel['latitude']) && $hotel['latitude'] !== null
            ? (float) $hotel['latitude']
            : (isset($hotel['lat']) && $hotel['lat'] !== null ? (float) $hotel['lat'] : null),
        'longitude' => isset($hotel['longitude']) && $hotel['longitude'] !== null
            ? (float) $hotel['longitude']
            : (isset($hotel['lng']) && $hotel['lng'] !== null ? (float) $hotel['lng'] : null),
    ];
}

function list_hotels(array $filters = []): array
{
    $where = ['is_active = 1'];
    $params = [];

    if (!empty($filters['pure_veg'])) {
        $where[] = 'pure_veg = 1';
    }
    if (!empty($filters['offer_active'])) {
        $where[] = 'offer_active = 1';
    }
    if (!empty($filters['q'])) {
        $where[] = '(name LIKE :q OR tags LIKE :q OR area LIKE :q)';
        $params[':q'] = '%' . $filters['q'] . '%';
    }

    $sql = 'SELECT * FROM hotels WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return array_map('present_hotel', $rows);
}

function hotel_offers(int $hotelId): array
{
    $stmt = db()->prepare(
        'SELECT title, subtitle FROM hotel_offers
         WHERE hotel_id = :id AND is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([':id' => $hotelId]);
    return $stmt->fetchAll();
}
