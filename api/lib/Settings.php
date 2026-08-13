<?php

declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $row = db()->query('SELECT * FROM admin_settings WHERE id = 1 LIMIT 1')->fetch();
            if (!$row) {
                self::$cache = self::defaults();
                return self::$cache;
            }
            if (isset($row['delivery_charges_config']) && is_string($row['delivery_charges_config'])) {
                $row['delivery_charges_config'] = json_decode($row['delivery_charges_config'], true) ?: [];
            }
            self::$cache = $row;
            return self::$cache;
        } catch (Throwable $e) {
            self::$cache = self::defaults();
            return self::$cache;
        }
    }

    public static function refresh(): void
    {
        self::$cache = null;
    }

    public static function defaults(): array
    {
        return [
            'delivery_commission_percent' => 3.0,
            'max_delivery_radius_km' => 10.0,
            'default_partner_radius_km' => 5.0,
            'delivery_charges_config' => [
                ['from_km' => 0, 'to_km' => 3, 'charge' => 20],
                ['from_km' => 3, 'to_km' => 6, 'charge' => 35],
                ['from_km' => 6, 'to_km' => 10, 'charge' => 49],
            ],
            'min_cart_for_free_delivery' => 0,
            'delivery_charge_below_min' => 25.0,
            'partner_earn_fixed' => 30.0,
            'partner_earn_percent' => 0,
            'cod_hold_enabled' => 1,
            'offer_ttl_seconds' => 60,
            'delivery_support_phone' => '',
            'payment_qr_url' => '',
            'maintenance_mode_delivery' => 0,
            'admin_contact_number' => '',
            'delivery_app_min_version_android' => '1.0.0',
            'delivery_app_min_version_ios' => '1.0.0',
            'delivery_app_download_url_android' => '',
            'delivery_app_download_url_ios' => '',
        ];
    }

    public static function deliveryChargeForKm(float $km): float
    {
        $s = self::get();
        $config = $s['delivery_charges_config'] ?? [];
        if (!is_array($config) || !$config) {
            return (float) ($s['delivery_charge_below_min'] ?? 25);
        }
        foreach ($config as $row) {
            $from = (float) ($row['from_km'] ?? 0);
            $to = (float) ($row['to_km'] ?? 999);
            if ($km >= $from && $km <= $to) {
                return (float) ($row['charge'] ?? 0);
            }
        }
        $last = $config[count($config) - 1];
        return (float) ($last['charge'] ?? 25);
    }
}
