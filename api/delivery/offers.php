<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';
require_once __DIR__ . '/../lib/Dispatch.php';
require_once __DIR__ . '/../lib/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
Dispatch::reofferExpired();

$stmt = db()->prepare(
    'SELECT * FROM orders
     WHERE assigned_partner_id IS NULL
       AND delivery_offered_to = :pid
       AND status IN (\'ready\',\'preparing\',\'placed\')
     ORDER BY delivery_offered_at DESC LIMIT 1'
);
$stmt->execute([':pid' => $partner['id']]);
$order = $stmt->fetch();

if (!$order) {
    respond(['ok' => true, 'offer' => null]);
}

$ttl = Dispatch::offerTtlSeconds();
$offeredAt = strtotime((string) $order['delivery_offered_at']);
$expiresIn = max(0, $ttl - (time() - $offeredAt));

respond([
    'ok' => true,
    'offer' => [
        'order' => present_order($order),
        'expires_in' => $expiresIn,
        'ttl' => $ttl,
        'hotel_name' => $order['restaurant_name'],
        'pickup' => [
            'line' => $order['delivery_line'],
            'lat' => $order['delivery_lat'],
            'lng' => $order['delivery_lng'],
        ],
    ],
]);
