<?php

declare(strict_types=1);

/**
 * Update order kitchen/dispatch status and stamp prep duration markers.
 * preparing_at: first transition into preparing (hotel accept).
 * ready_at: first transition into ready (food ready for pickup).
 *
 * Safe to include from hotel/super-admin HTML pages (no API bootstrap).
 */
function fm_order_set_status(PDO $pdo, int $orderId, string $newStatus, ?int $hotelDbId = null): void
{
    static $hasPrepAt = null;
    if ($hasPrepAt === null) {
        try {
            $hasPrepAt = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'preparing_at'")->fetch();
        } catch (Throwable $e) {
            $hasPrepAt = false;
        }
    }

    $sets = ['status = :s'];
    $params = [':s' => $newStatus, ':id' => $orderId];

    if ($hotelDbId !== null && $hotelDbId > 0) {
        $sets[] = 'hotel_db_id = COALESCE(hotel_db_id, :hid)';
        $params[':hid'] = $hotelDbId;
    }

    if ($hasPrepAt) {
        if ($newStatus === 'preparing') {
            $sets[] = 'preparing_at = COALESCE(preparing_at, NOW())';
        }
        if ($newStatus === 'ready') {
            $sets[] = 'ready_at = COALESCE(ready_at, NOW())';
        }
    }

    $pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
}
