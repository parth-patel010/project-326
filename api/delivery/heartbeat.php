<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';
require_once __DIR__ . '/../lib/H3.php';
require_once __DIR__ . '/../lib/Realtime.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$body = json_body();
$lat = (float) ($body['latitude'] ?? $body['lat'] ?? 0);
$lng = (float) ($body['longitude'] ?? $body['lng'] ?? 0);
$online = array_key_exists('is_online', $body) ? (!empty($body['is_online']) ? 1 : 0) : (int) $partner['is_online'];

if ($lat === 0.0 && $lng === 0.0) {
    fail('latitude and longitude required');
}

$cell = H3::latLngToCell($lat, $lng);
db()->prepare(
    'UPDATE delivery_partners SET
       current_latitude = :lat,
       current_longitude = :lng,
       h3_cell = :cell,
       is_online = :online,
       last_location_update = NOW()
     WHERE id = :id'
)->execute([
    ':lat' => $lat,
    ':lng' => $lng,
    ':cell' => $cell,
    ':online' => $online,
    ':id' => $partner['id'],
]);

Realtime::emit('location.update', [
    'partner_id' => (int) $partner['id'],
    'latitude' => $lat,
    'longitude' => $lng,
], 'partner:' . $partner['id']);

// Also push to active order room
$active = db()->prepare(
    "SELECT public_id FROM orders WHERE assigned_partner_id = :id AND status IN ('out_for_delivery','ready','preparing') LIMIT 1"
);
$active->execute([':id' => $partner['id']]);
$order = $active->fetch();
if ($order) {
    db()->prepare('UPDATE orders SET partner_lat=:lat, partner_lng=:lng WHERE public_id=:pid')
        ->execute([':lat' => $lat, ':lng' => $lng, ':pid' => $order['public_id']]);
    Realtime::emit('location.update', [
        'order_id' => $order['public_id'],
        'partner_id' => (int) $partner['id'],
        'latitude' => $lat,
        'longitude' => $lng,
    ], 'order:' . $order['public_id']);
}

$fresh = db()->prepare('SELECT * FROM delivery_partners WHERE id = :id');
$fresh->execute([':id' => $partner['id']]);

respond([
    'ok' => true,
    'partner' => present_partner($fresh->fetch()),
]);
