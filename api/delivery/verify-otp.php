<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Realtime.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$body = json_body();
$orderId = trim((string) ($body['order_id'] ?? ''));
$type = trim((string) ($body['type'] ?? '')); // pickup | delivery
$otp = trim((string) ($body['otp'] ?? ''));

$order = find_order_by_public_id($orderId);
if (!$order) {
    fail('Order not found', 404);
}
if ((int) ($order['assigned_partner_id'] ?? 0) !== (int) $partner['id']) {
    fail('Not your order', 403);
}

if ($type === 'pickup') {
    if ($otp !== (string) ($order['hotel_otp'] ?? '')) {
        fail('Invalid hotel OTP', 401);
    }
    db()->prepare(
        'UPDATE orders SET hotel_otp_verified = 1, status = \'out_for_delivery\' WHERE id = :id'
    )->execute([':id' => $order['id']]);
    Realtime::emit('order.status', [
        'order_id' => $orderId,
        'status' => 'out_for_delivery',
    ], 'order:' . $orderId);
} elseif ($type === 'delivery') {
    if (empty($order['hotel_otp_verified'])) {
        fail('Pickup OTP not verified yet', 409);
    }
    if ($otp !== (string) ($order['delivery_otp'] ?? '')) {
        fail('Invalid delivery OTP', 401);
    }
    db()->prepare(
        'UPDATE orders SET delivery_otp_verified = 1, status = \'delivered\' WHERE id = :id'
    )->execute([':id' => $order['id']]);

    $earn = ((int) ($order['partner_earn_paise'] ?? 0)) / 100;
    db()->prepare(
        'UPDATE delivery_partners SET
           orders_completed = orders_completed + 1,
           earnings_total = earnings_total + :earn,
           is_available = 1
         WHERE id = :id'
    )->execute([':earn' => $earn, ':id' => $partner['id']]);

    if (($order['payment_mode'] ?? '') === 'cod' && !empty(Settings::get()['cod_hold_enabled'])) {
        $amount = ((int) $order['total_paise']) / 100;
        db()->prepare(
            'INSERT INTO cod_holds (partner_id, order_id, amount, status) VALUES (:p, :o, :a, \'held\')'
        )->execute([':p' => $partner['id'], ':o' => $order['id'], ':a' => $amount]);
        db()->prepare(
            'UPDATE delivery_partners SET cod_wallet = cod_wallet + :a WHERE id = :id'
        )->execute([':a' => $amount, ':id' => $partner['id']]);
    }

    Realtime::emit('order.status', [
        'order_id' => $orderId,
        'status' => 'delivered',
    ], 'order:' . $orderId);
} else {
    fail('type must be pickup or delivery');
}

respond([
    'ok' => true,
    'order' => present_order(find_order_by_public_id($orderId)),
]);
