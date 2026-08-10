<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/H3.php';
require_once __DIR__ . '/../lib/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();
$lat = (float) ($body['latitude'] ?? $body['lat'] ?? 0);
$lng = (float) ($body['longitude'] ?? $body['lng'] ?? 0);
$phone = normalize_phone((string) ($body['phone'] ?? ''));
$userId = null;

if (!empty($body['user_id'])) {
    $u = find_user_by_public_id(trim((string) $body['user_id']));
    if ($u) {
        $userId = (int) $u['id'];
        $phone = (string) $u['phone'];
    }
} elseif ($phone !== '') {
    $u = find_user_by_phone($phone);
    if ($u) {
        $userId = (int) $u['id'];
    }
}

if ($lat === 0.0 && $lng === 0.0) {
    fail('latitude and longitude required');
}

$cell = H3::latLngToCell($lat, $lng);
$pdo = db();

if ($userId) {
    $pdo->prepare(
        'INSERT INTO user_locations (user_id, phone, latitude, longitude, h3_cell)
         VALUES (:uid, :phone, :lat, :lng, :cell)
         ON DUPLICATE KEY UPDATE phone=VALUES(phone), latitude=VALUES(latitude),
           longitude=VALUES(longitude), h3_cell=VALUES(h3_cell), updated_at=NOW()'
    )->execute([
        ':uid' => $userId,
        ':phone' => $phone !== '' ? $phone : null,
        ':lat' => $lat,
        ':lng' => $lng,
        ':cell' => $cell,
    ]);
} else {
    // store by phone if possible
    if ($phone === '') {
        fail('user_id or phone required with location');
    }
    $existing = $pdo->prepare('SELECT id FROM user_locations WHERE phone = :p LIMIT 1');
    $existing->execute([':p' => $phone]);
    $row = $existing->fetch();
    if ($row) {
        $pdo->prepare(
            'UPDATE user_locations SET latitude=:lat, longitude=:lng, h3_cell=:cell, updated_at=NOW() WHERE id=:id'
        )->execute([':lat' => $lat, ':lng' => $lng, ':cell' => $cell, ':id' => $row['id']]);
    } else {
        $pdo->prepare(
            'INSERT INTO user_locations (phone, latitude, longitude, h3_cell) VALUES (:p,:lat,:lng,:cell)'
        )->execute([':p' => $phone, ':lat' => $lat, ':lng' => $lng, ':cell' => $cell]);
    }
}

respond([
    'ok' => true,
    'latitude' => $lat,
    'longitude' => $lng,
    'h3_cell' => $cell,
]);
