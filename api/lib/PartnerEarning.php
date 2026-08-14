<?php

declare(strict_types=1);

require_once __DIR__ . '/H3.php';
require_once __DIR__ . '/Settings.php';

/**
 * Platform partner earn = hotel→customer km × ₹/km (EatnSay rules).
 * Never use customer delivery_fee as partner payout.
 */
final class PartnerEarning
{
    public static function ratePerKm(?array $order = null): float
    {
        if ($order && isset($order['delivery_partner_revenue']) && is_numeric($order['delivery_partner_revenue'])) {
            $snap = (float) $order['delivery_partner_revenue'];
            if ($snap > 0) {
                return $snap;
            }
        }
        $s = Settings::get();
        $rate = (float) ($s['platform_partner_per_order_revenue'] ?? 0);
        if ($rate <= 0) {
            $rate = (float) ($s['partner_earn_fixed'] ?? 0);
        }
        return max(0.0, $rate);
    }

    public static function distanceKm(array $order): float
    {
        if (isset($order['delivery_distance_km']) && is_numeric($order['delivery_distance_km'])) {
            $snap = (float) $order['delivery_distance_km'];
            if ($snap > 0) {
                return $snap;
            }
        }

        $custLat = isset($order['delivery_lat']) && $order['delivery_lat'] !== null
            ? (float) $order['delivery_lat']
            : (isset($order['delivery']['lat']) ? (float) $order['delivery']['lat'] : 0.0);
        $custLng = isset($order['delivery_lng']) && $order['delivery_lng'] !== null
            ? (float) $order['delivery_lng']
            : (isset($order['delivery']['lng']) ? (float) $order['delivery']['lng'] : 0.0);

        $hLat = 0.0;
        $hLng = 0.0;
        if (!empty($order['hotel_db_id'])) {
            try {
                $h = db()->prepare('SELECT latitude, longitude FROM hotels WHERE id = :id LIMIT 1');
                $h->execute([':id' => $order['hotel_db_id']]);
                $hotel = $h->fetch();
                if ($hotel && $hotel['latitude'] !== null) {
                    $hLat = (float) $hotel['latitude'];
                    $hLng = (float) $hotel['longitude'];
                }
            } catch (Throwable $e) {
                // fall through
            }
        }
        if ($hLat == 0.0 && $hLng == 0.0) {
            $hLat = (float) ($order['hotel_lat'] ?? $order['pickup_lat'] ?? 0);
            $hLng = (float) ($order['hotel_lng'] ?? $order['pickup_lng'] ?? 0);
        }

        if ($hLat == 0.0 && $hLng == 0.0) {
            return 0.0;
        }
        if ($custLat == 0.0 && $custLng == 0.0) {
            return 0.0;
        }
        return max(0.0, H3::haversineKm($hLat, $hLng, $custLat, $custLng));
    }

    /** Partner payout ₹ = km × ₹/km. Prefers stored partner_earn_paise after accept. */
    public static function amountRupees(array $order): float
    {
        if (isset($order['partner_earn_paise']) && (int) $order['partner_earn_paise'] > 0) {
            return round(((int) $order['partner_earn_paise']) / 100, 2);
        }
        $km = self::distanceKm($order);
        $rate = self::ratePerKm($order);
        if ($km <= 0 || $rate <= 0) {
            return 0.0;
        }
        return max(0.0, round($km * $rate, 2));
    }

    public static function amountPaise(array $order): int
    {
        return (int) round(self::amountRupees($order) * 100);
    }

    public static function ensureEarnWalletColumn(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $has = $pdo->query("SHOW COLUMNS FROM delivery_partners LIKE 'earn_wallet'")->fetch();
            if (!$has) {
                $pdo->exec(
                    'ALTER TABLE delivery_partners
                     ADD COLUMN earn_wallet DECIMAL(12,2) NOT NULL DEFAULT 0.00
                     AFTER cod_wallet'
                );
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    public static function getEarnWallet(PDO $pdo, int $partnerId): float
    {
        self::ensureEarnWalletColumn($pdo);
        $stmt = $pdo->prepare('SELECT COALESCE(earn_wallet, 0) FROM delivery_partners WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $partnerId]);
        return (float) $stmt->fetchColumn();
    }

    public static function creditEarnWallet(PDO $pdo, int $partnerId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }
        self::ensureEarnWalletColumn($pdo);
        $stmt = $pdo->prepare(
            'UPDATE delivery_partners
             SET earn_wallet = COALESCE(earn_wallet, 0) + :amt,
                 earnings_total = COALESCE(earnings_total, 0) + :amt2
             WHERE id = :id'
        );
        $stmt->execute([':amt' => $amount, ':amt2' => $amount, ':id' => $partnerId]);
    }

    public static function debitEarnWallet(PDO $pdo, int $partnerId, float $amount, bool $requireSufficient = true): void
    {
        if ($amount <= 0) {
            return;
        }
        self::ensureEarnWalletColumn($pdo);
        $balance = self::getEarnWallet($pdo, $partnerId);
        if ($requireSufficient && $amount > $balance + 0.0001) {
            throw new RuntimeException(
                'Earn wallet balance (₹' . number_format($balance, 2) . ') is less than payout amount'
            );
        }
        $stmt = $pdo->prepare(
            'UPDATE delivery_partners
             SET earn_wallet = GREATEST(0, COALESCE(earn_wallet, 0) - :amt)
             WHERE id = :id'
        );
        $stmt->execute([':amt' => $amount, ':id' => $partnerId]);
    }
}
