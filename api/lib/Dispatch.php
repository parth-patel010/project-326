<?php

declare(strict_types=1);

require_once __DIR__ . '/H3.php';
require_once __DIR__ . '/Settings.php';

/**
 * Exclusive delivery partner offer dispatch (60s lock).
 */
final class Dispatch
{
    public static function offerTtlSeconds(): int
    {
        $s = Settings::get();
        return max(30, (int) ($s['offer_ttl_seconds'] ?? 60));
    }

    /**
     * Rank online partners near hotel within partner service radius + global max.
     * @return list<array{id:int,distance:float}>
     */
    public static function nearbyPartners(float $hotelLat, float $hotelLng, array $skipIds = []): array
    {
        $settings = Settings::get();
        $maxKm = (float) ($settings['max_delivery_radius_km'] ?? 10);

        $pdo = db();
        $stmt = $pdo->query(
            "SELECT id, current_latitude, current_longitude, service_radius_km
             FROM delivery_partners
             WHERE status = 'active' AND is_online = 1 AND is_available = 1
               AND current_latitude IS NOT NULL AND current_longitude IS NOT NULL"
        );
        $rows = $stmt->fetchAll();
        $ranked = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (in_array($id, $skipIds, true)) {
                continue;
            }
            $plat = (float) $row['current_latitude'];
            $plng = (float) $row['current_longitude'];
            $partnerRadius = (float) ($row['service_radius_km'] ?? 5);
            $dist = H3::haversineKm($hotelLat, $hotelLng, $plat, $plng);
            if ($dist > $partnerRadius || $dist > $maxKm) {
                continue;
            }
            $ranked[] = ['id' => $id, 'distance' => $dist];
        }
        usort($ranked, static fn ($a, $b) => $a['distance'] <=> $b['distance']);
        return $ranked;
    }

    public static function skipIdsFromOrder(array $order): array
    {
        $raw = $order['delivery_skip_drivers'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }
        return [];
    }

    /** Offer order to next nearest partner. Returns partner id or null. */
    public static function offerToNext(array $order): ?int
    {
        $hotelLat = (float) ($order['delivery_lat'] ?? 0);
        $hotelLng = (float) ($order['delivery_lng'] ?? 0);

        // Prefer hotel coordinates if linked
        if (!empty($order['hotel_db_id'])) {
            $h = db()->prepare('SELECT latitude, longitude FROM hotels WHERE id = :id LIMIT 1');
            $h->execute([':id' => $order['hotel_db_id']]);
            $hotel = $h->fetch();
            if ($hotel && $hotel['latitude'] !== null) {
                $hotelLat = (float) $hotel['latitude'];
                $hotelLng = (float) $hotel['longitude'];
            }
        }

        $skip = self::skipIdsFromOrder($order);
        $ranked = self::nearbyPartners($hotelLat, $hotelLng, $skip);
        if (!$ranked) {
            db()->prepare(
                'UPDATE orders SET delivery_offered_to = NULL, delivery_offered_at = NULL WHERE id = :id'
            )->execute([':id' => $order['id']]);
            return null;
        }

        $partnerId = (int) $ranked[0]['id'];
        db()->prepare(
            'UPDATE orders
             SET delivery_offered_to = :pid, delivery_offered_at = NOW()
             WHERE id = :id AND assigned_partner_id IS NULL'
        )->execute([':pid' => $partnerId, ':id' => $order['id']]);

        Realtime::emit('delivery.offer', [
            'order_id' => $order['public_id'],
            'partner_id' => $partnerId,
            'ttl' => self::offerTtlSeconds(),
        ], 'partner:' . $partnerId);

        return $partnerId;
    }

    public static function rejectOffer(array $order, int $partnerId): void
    {
        $skip = self::skipIdsFromOrder($order);
        if (!in_array($partnerId, $skip, true)) {
            $skip[] = $partnerId;
        }
        db()->prepare(
            'UPDATE orders
             SET delivery_skip_drivers = :skip,
                 delivery_offered_to = NULL,
                 delivery_offered_at = NULL
             WHERE id = :id AND delivery_offered_to = :pid AND assigned_partner_id IS NULL'
        )->execute([
            ':skip' => json_encode($skip),
            ':id' => $order['id'],
            ':pid' => $partnerId,
        ]);

        $fresh = db()->prepare('SELECT * FROM orders WHERE id = :id');
        $fresh->execute([':id' => $order['id']]);
        $row = $fresh->fetch();
        if ($row) {
            self::offerToNext($row);
        }
    }

    public static function accept(array $order, int $partnerId): array
    {
        if ((int) ($order['delivery_offered_to'] ?? 0) !== $partnerId) {
            throw new RuntimeException('Offer not assigned to this partner');
        }
        if (!empty($order['assigned_partner_id'])) {
            throw new RuntimeException('Order already assigned');
        }

        $hotelOtp = (string) random_int(1000, 9999);
        $deliveryOtp = (string) random_int(1000, 9999);
        $settings = Settings::get();
        $earnFixed = (float) ($settings['partner_earn_fixed'] ?? 30);
        $earnPaise = (int) round($earnFixed * 100);

        // Pickup deadline: approx from partner → hotel
        $deadlineMin = 20;
        $p = db()->prepare('SELECT current_latitude, current_longitude FROM delivery_partners WHERE id = :id');
        $p->execute([':id' => $partnerId]);
        $partner = $p->fetch();
        if ($partner && $partner['current_latitude'] !== null) {
            $hLat = (float) ($order['delivery_lat'] ?? 0);
            $hLng = (float) ($order['delivery_lng'] ?? 0);
            if (!empty($order['hotel_db_id'])) {
                $h = db()->prepare('SELECT latitude, longitude FROM hotels WHERE id = :id');
                $h->execute([':id' => $order['hotel_db_id']]);
                $hotel = $h->fetch();
                if ($hotel && $hotel['latitude'] !== null) {
                    $hLat = (float) $hotel['latitude'];
                    $hLng = (float) $hotel['longitude'];
                }
            }
            $km = H3::haversineKm(
                (float) $partner['current_latitude'],
                (float) $partner['current_longitude'],
                $hLat,
                $hLng
            );
            $deadlineMin = H3::approxMinutesFromKm($km);
        }

        db()->prepare(
            'UPDATE orders SET
                assigned_partner_id = :pid,
                hotel_otp = :hotp,
                delivery_otp = :dotp,
                partner_earn_paise = :earn,
                pickup_deadline_at = DATE_ADD(NOW(), INTERVAL :mins MINUTE),
                delivery_offered_to = NULL,
                delivery_offered_at = NULL,
                status = CASE WHEN status = \'ready\' THEN \'out_for_delivery\' ELSE status END
             WHERE id = :id AND assigned_partner_id IS NULL'
        )->execute([
            ':pid' => $partnerId,
            ':hotp' => $hotelOtp,
            ':dotp' => $deliveryOtp,
            ':earn' => $earnPaise,
            ':mins' => $deadlineMin,
            ':id' => $order['id'],
        ]);

        Realtime::emit('order.status', [
            'order_id' => $order['public_id'],
            'status' => 'assigned',
            'partner_id' => $partnerId,
        ], 'order:' . $order['public_id']);

        return [
            'hotel_otp' => $hotelOtp,
            'delivery_otp' => $deliveryOtp,
            'pickup_deadline_minutes' => $deadlineMin,
        ];
    }

    /** Re-offer expired locks. */
    public static function reofferExpired(): int
    {
        $ttl = self::offerTtlSeconds();
        $stmt = db()->query(
            "SELECT * FROM orders
             WHERE assigned_partner_id IS NULL
               AND delivery_offered_to IS NOT NULL
               AND delivery_offered_at IS NOT NULL
               AND status IN ('ready','preparing','placed')
               AND delivery_offered_at < DATE_SUB(NOW(), INTERVAL {$ttl} SECOND)"
        );
        $count = 0;
        foreach ($stmt->fetchAll() as $order) {
            $pid = (int) $order['delivery_offered_to'];
            self::rejectOffer($order, $pid);
            $count++;
        }
        return $count;
    }
}
