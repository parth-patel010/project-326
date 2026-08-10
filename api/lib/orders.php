<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

function find_order_by_public_id(string $publicId): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE public_id = :id LIMIT 1');
    $stmt->execute([':id' => $publicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_order_by_razorpay_order_id(string $rzOrderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE razorpay_order_id = :id LIMIT 1');
    $stmt->execute([':id' => $rzOrderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mark_order_paid(
    array $order,
    string $paymentId,
    ?string $signature = null
): array {
    if (in_array($order['status'], ['paid', 'placed', 'preparing', 'out_for_delivery', 'delivered'], true)) {
        // Already past awaiting_payment — idempotent success
        return find_order_by_public_id($order['public_id']) ?? $order;
    }

    $stmt = db()->prepare(
        'UPDATE orders
         SET status = :status,
             razorpay_payment_id = :payment_id,
             razorpay_signature = COALESCE(:signature, razorpay_signature),
             paid_at = COALESCE(paid_at, NOW())
         WHERE public_id = :public_id
           AND status = :awaiting'
    );
    $stmt->execute([
        // After online payment, treat as placed for kitchen
        ':status' => 'placed',
        ':payment_id' => $paymentId,
        ':signature' => $signature,
        ':public_id' => $order['public_id'],
        ':awaiting' => 'awaiting_payment',
    ]);

    return find_order_by_public_id($order['public_id']) ?? $order;
}

function mark_order_payment_failed(array $order): array
{
    if ($order['status'] !== 'awaiting_payment') {
        return $order;
    }
    $stmt = db()->prepare(
        'UPDATE orders SET status = :status WHERE public_id = :id AND status = :awaiting'
    );
    $stmt->execute([
        ':status' => 'payment_failed',
        ':id' => $order['public_id'],
        ':awaiting' => 'awaiting_payment',
    ]);
    return find_order_by_public_id($order['public_id']) ?? $order;
}

function present_order(?array $order): ?array
{
    if (!$order) {
        return null;
    }
    $items = json_decode((string) $order['items_json'], true);
    return [
        'id' => $order['public_id'],
        'restaurant_id' => $order['restaurant_id'],
        'restaurant_name' => $order['restaurant_name'],
        'customer_name' => $order['customer_name'],
        'customer_phone' => $order['customer_phone'],
        'delivery' => [
            'label' => $order['delivery_label'],
            'line' => $order['delivery_line'],
            'details' => $order['delivery_details'],
            'lat' => $order['delivery_lat'],
            'lng' => $order['delivery_lng'],
        ],
        'note' => $order['note'],
        'no_cutlery' => (bool) $order['no_cutlery'],
        'payment_mode' => $order['payment_mode'],
        'status' => $order['status'],
        'amounts' => [
            'subtotal' => ((int) $order['subtotal_paise']) / 100,
            'delivery_fee' => ((int) $order['delivery_fee_paise']) / 100,
            'platform_fee' => ((int) $order['platform_fee_paise']) / 100,
            'discount' => ((int) $order['discount_paise']) / 100,
            'total' => ((int) $order['total_paise']) / 100,
            'total_paise' => (int) $order['total_paise'],
        ],
        'razorpay_order_id' => $order['razorpay_order_id'],
        'razorpay_payment_id' => $order['razorpay_payment_id'],
        'assigned_partner_id' => $order['assigned_partner_id'] ?? null,
        'hotel_otp_verified' => (bool) ($order['hotel_otp_verified'] ?? 0),
        'delivery_otp_verified' => (bool) ($order['delivery_otp_verified'] ?? 0),
        'eta_minutes' => $order['eta_minutes'] ?? null,
        'pickup_deadline_at' => $order['pickup_deadline_at'] ?? null,
        'items' => is_array($items) ? $items : [],
        'created_at' => $order['created_at'],
        'paid_at' => $order['paid_at'],
    ];
}
