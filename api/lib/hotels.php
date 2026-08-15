<?php

declare(strict_types=1);

/** Default kitchen prep when fewer than 5 measurable past orders. */
const FM_DEFAULT_PREP_MINS = 19;

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

/**
 * Automatic kitchen prep minutes for customer ETA (not admin-settable).
 *
 * Preparing duration = minutes from kitchen start → ready:
 *   - Preferred: orders.preparing_at → orders.ready_at
 *     (stamped when hotel accepts → preparing, then marks ready)
 *   - Fallback for older rows: COALESCE(paid_at, created_at) → delivery_offered_at
 *     (delivery_offered_at is set when hotel accept triggers partner dispatch)
 *
 * Uses the last 5 ready+ orders with a usable duration (1–180 min).
 * Returns 19 if fewer than 5 samples.
 */
function fm_hotel_prep_mins(PDO $pdo, int $hotelDbId): int
{
    static $cache = [];
    if ($hotelDbId <= 0) {
        return FM_DEFAULT_PREP_MINS;
    }
    if (array_key_exists($hotelDbId, $cache)) {
        return $cache[$hotelDbId];
    }

    $publicId = '';
    try {
        $h = $pdo->prepare('SELECT public_id FROM hotels WHERE id = :id LIMIT 1');
        $h->execute([':id' => $hotelDbId]);
        $publicId = (string) ($h->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $cache[$hotelDbId] = FM_DEFAULT_PREP_MINS;
        return FM_DEFAULT_PREP_MINS;
    }

    $hasPrepAt = false;
    try {
        $hasPrepAt = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'preparing_at'")->fetch();
    } catch (Throwable $e) {
        $hasPrepAt = false;
    }

    try {
        if ($hasPrepAt) {
            $sql = 'SELECT prep_mins FROM (
                        SELECT
                          CASE
                            WHEN preparing_at IS NOT NULL AND ready_at IS NOT NULL
                              THEN TIMESTAMPDIFF(MINUTE, preparing_at, ready_at)
                            WHEN delivery_offered_at IS NOT NULL
                              THEN TIMESTAMPDIFF(
                                MINUTE,
                                COALESCE(paid_at, created_at),
                                delivery_offered_at
                              )
                            ELSE NULL
                          END AS prep_mins,
                          id AS sort_key
                        FROM orders
                        WHERE (hotel_db_id = :hid OR restaurant_id = :pid)
                          AND status IN (\'ready\', \'out_for_delivery\', \'delivered\')
                      ) t
                      WHERE prep_mins BETWEEN 1 AND 180
                      ORDER BY sort_key DESC
                      LIMIT 5';
        } else {
            // No stamp columns yet: paid/created → partner offer time (approx ready).
            $sql = 'SELECT TIMESTAMPDIFF(
                        MINUTE,
                        COALESCE(paid_at, created_at),
                        delivery_offered_at
                      ) AS prep_mins
                      FROM orders
                      WHERE (hotel_db_id = :hid OR restaurant_id = :pid)
                        AND status IN (\'ready\', \'out_for_delivery\', \'delivered\')
                        AND delivery_offered_at IS NOT NULL
                        AND TIMESTAMPDIFF(
                          MINUTE,
                          COALESCE(paid_at, created_at),
                          delivery_offered_at
                        ) BETWEEN 1 AND 180
                      ORDER BY id DESC
                      LIMIT 5';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':hid' => $hotelDbId, ':pid' => $publicId !== '' ? $publicId : '__none__']);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $cache[$hotelDbId] = FM_DEFAULT_PREP_MINS;
        return FM_DEFAULT_PREP_MINS;
    }

    if (count($rows) < 5) {
        $cache[$hotelDbId] = FM_DEFAULT_PREP_MINS;
        return FM_DEFAULT_PREP_MINS;
    }

    $sum = 0;
    foreach ($rows as $m) {
        $sum += (int) $m;
    }
    $avg = (int) round($sum / count($rows));
    if ($avg < 1) {
        $avg = FM_DEFAULT_PREP_MINS;
    }
    $cache[$hotelDbId] = $avg;
    return $avg;
}

function present_hotel(?array $hotel): ?array
{
    if (!$hotel) {
        return null;
    }
    $hotelDbId = isset($hotel['id']) ? (int) $hotel['id'] : 0;
    $prep = $hotelDbId > 0 ? fm_hotel_prep_mins(db(), $hotelDbId) : FM_DEFAULT_PREP_MINS;
    return [
        'id' => $hotel['public_id'],
        'name' => $hotel['name'],
        'image' => $hotel['image'],
        'cover_image_url' => $hotel['cover_image_url'] ?? null,
        'rating' => (float) $hotel['rating'],
        'rating_count' => (int) $hotel['rating_count'],
        'area' => $hotel['area'],
        // Without customer lat/lng, floor ETA as prep + typical travel (15).
        'mins' => $prep + 15,
        'prep_mins' => $prep,
        'km' => (float) $hotel['distance_km'],
        'fee' => (float) $hotel['delivery_fee'],
        'avg_price' => (float) $hotel['avg_price'],
        'tags' => $hotel['tags'],
        'pure_veg' => (bool) $hotel['pure_veg'],
        'offer_active' => (bool) $hotel['offer_active'],
        'is_open' => !isset($hotel['is_open']) || (bool) $hotel['is_open'],
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

    // Prefer open hotels when column exists (migration 009)
    try {
        $hasOpen = (bool) db()->query("SHOW COLUMNS FROM hotels LIKE 'is_open'")->fetch();
        if ($hasOpen) {
            $where[] = 'is_open = 1';
        }
    } catch (Throwable $e) {
        // ignore
    }

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
