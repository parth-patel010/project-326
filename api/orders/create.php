<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/RazorpayClient.php';
require_once __DIR__ . '/../lib/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$body = json_body();

$paymentMode = $body['payment_mode'] ?? '';
if (!in_array($paymentMode, ['cod', 'prepaid'], true)) {
    fail('payment_mode must be cod or prepaid');
}

$items = $body['items'] ?? null;
if (!is_array($items) || count($items) === 0) {
    fail('items required');
}

$normalizedItems = [];
$subtotalPaise = 0;
foreach ($items as $item) {
    $qty = (int) ($item['qty'] ?? 0);
    $price = (float) ($item['price'] ?? 0);
    $name = trim((string) ($item['name'] ?? ''));
    $id = trim((string) ($item['id'] ?? ''));
    if ($qty < 1 || $price < 0 || $name === '' || $id === '') {
        fail('Invalid item payload');
    }
    $linePaise = rupees_to_paise($price) * $qty;
    $subtotalPaise += $linePaise;
    $normalizedItems[] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'qty' => $qty,
        'image' => $item['image'] ?? null,
        'veg' => !empty($item['veg']),
    ];
}

$deliveryFeePaise = rupees_to_paise($body['delivery_fee'] ?? 29);
$platformFeePaise = rupees_to_paise($body['platform_fee'] ?? 5);
$discountPaise = rupees_to_paise($body['discount'] ?? 0);
$totalPaise = max(0, $subtotalPaise + $deliveryFeePaise + $platformFeePaise - $discountPaise);

if ($totalPaise < 100 && $paymentMode === 'prepaid') {
    fail('Minimum prepaid amount is ₹1.00');
}

$restaurantId = trim((string) ($body['restaurant_id'] ?? ''));
$restaurantName = trim((string) ($body['restaurant_name'] ?? ''));
$customerName = trim((string) ($body['customer_name'] ?? ''));
$deliveryLine = trim((string) ($body['delivery_line'] ?? ''));

if ($restaurantId === '' || $restaurantName === '' || $customerName === '' || $deliveryLine === '') {
    fail('restaurant_id, restaurant_name, customer_name, delivery_line required');
}

$publicId = public_order_id();
$status = $paymentMode === 'cod' ? 'placed' : 'awaiting_payment';

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO orders (
            public_id, restaurant_id, restaurant_name, customer_name, customer_phone,
            delivery_label, delivery_line, delivery_details, delivery_lat, delivery_lng,
            note, no_cutlery, payment_mode, status,
            subtotal_paise, delivery_fee_paise, platform_fee_paise, discount_paise, total_paise,
            items_json
        ) VALUES (
            :public_id, :restaurant_id, :restaurant_name, :customer_name, :customer_phone,
            :delivery_label, :delivery_line, :delivery_details, :delivery_lat, :delivery_lng,
            :note, :no_cutlery, :payment_mode, :status,
            :subtotal_paise, :delivery_fee_paise, :platform_fee_paise, :discount_paise, :total_paise,
            :items_json
        )'
    );

    $stmt->execute([
        ':public_id' => $publicId,
        ':restaurant_id' => $restaurantId,
        ':restaurant_name' => $restaurantName,
        ':customer_name' => $customerName,
        ':customer_phone' => (string) ($body['customer_phone'] ?? ''),
        ':delivery_label' => (string) ($body['delivery_label'] ?? 'Home'),
        ':delivery_line' => $deliveryLine,
        ':delivery_details' => $body['delivery_details'] ?? null,
        ':delivery_lat' => $body['delivery_lat'] ?? null,
        ':delivery_lng' => $body['delivery_lng'] ?? null,
        ':note' => $body['note'] ?? null,
        ':no_cutlery' => !empty($body['no_cutlery']) ? 1 : 0,
        ':payment_mode' => $paymentMode,
        ':status' => $status,
        ':subtotal_paise' => $subtotalPaise,
        ':delivery_fee_paise' => $deliveryFeePaise,
        ':platform_fee_paise' => $platformFeePaise,
        ':discount_paise' => $discountPaise,
        ':total_paise' => $totalPaise,
        ':items_json' => json_encode($normalizedItems, JSON_UNESCAPED_UNICODE),
    ]);

    $orderDbId = (int) $pdo->lastInsertId();

    // Link hotel + commission snapshot when columns exist
    try {
        require_once __DIR__ . '/../lib/Settings.php';
        $settings = Settings::get();
        $commissionPercent = (float) ($settings['delivery_commission_percent'] ?? 3);
        $commissionPaise = (int) round(($subtotalPaise * $commissionPercent) / 100);
        $hotelRow = $pdo->prepare('SELECT id FROM hotels WHERE public_id = :pid LIMIT 1');
        $hotelRow->execute([':pid' => $restaurantId]);
        $hid = $hotelRow->fetchColumn();
        $pdo->prepare(
            'UPDATE orders SET hotel_db_id = :hid, commission_percent = :cp, commission_amount_paise = :ca WHERE id = :id'
        )->execute([
            ':hid' => $hid ?: null,
            ':cp' => $commissionPercent,
            ':ca' => $commissionPaise,
            ':id' => $orderDbId,
        ]);
    } catch (Throwable $e) {
        // columns may not be migrated yet
    }

    $razorpayOrderId = null;
    $keyId = null;

    if ($paymentMode === 'prepaid') {
        global $CONFIG;
        $rz = new RazorpayClient(
            $CONFIG['razorpay']['key_id'],
            $CONFIG['razorpay']['key_secret']
        );
        $rzOrder = $rz->createOrder(
            $totalPaise,
            $CONFIG['currency'] ?? 'INR',
            $publicId,
            [
                'public_id' => $publicId,
                'restaurant_id' => $restaurantId,
            ]
        );
        $razorpayOrderId = $rzOrder['id'] ?? null;
        if (!$razorpayOrderId) {
            throw new RuntimeException('Razorpay order id missing');
        }
        $upd = $pdo->prepare(
            'UPDATE orders SET razorpay_order_id = :rid WHERE id = :id'
        );
        $upd->execute([':rid' => $razorpayOrderId, ':id' => $orderDbId]);
        $keyId = $CONFIG['razorpay']['key_id'];
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail('Failed to create order: ' . $e->getMessage(), 500);
}

$order = find_order_by_public_id($publicId);
respond([
    'ok' => true,
    'order' => present_order($order),
    'razorpay' => $paymentMode === 'prepaid' ? [
        'key_id' => $keyId,
        'order_id' => $razorpayOrderId,
        'amount' => $totalPaise,
        'currency' => $CONFIG['currency'] ?? 'INR',
        'name' => 'FoodMitra',
        'description' => 'Order ' . $publicId,
        'prefill' => [
            'name' => $customerName,
            'contact' => (string) ($body['customer_phone'] ?? ''),
        ],
    ] : null,
]);
