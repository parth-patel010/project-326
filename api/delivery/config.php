<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/Settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$s = Settings::get();
$partnerEarn = (float) ($s['platform_partner_per_order_revenue'] ?? 0);
if ($partnerEarn <= 0) {
    $partnerEarn = (float) ($s['partner_earn_fixed'] ?? 10);
}

respond([
    'ok' => true,
    'success' => true,
    'delivery_user_charge' => (float) ($s['delivery_charge_below_min'] ?? 25),
    'delivery_hotel_charge' => 0,
    'platform_partner_per_order_revenue' => $partnerEarn,
    'partner_earn_fixed' => $partnerEarn,
    'delivery_support_phone' => (string) ($s['delivery_support_phone'] ?? ''),
    'max_delivery_radius_km' => (float) ($s['max_delivery_radius_km'] ?? 10),
    'nearby_radius_km' => (float) ($s['max_delivery_radius_km'] ?? 10),
    'default_partner_radius_km' => (float) ($s['default_partner_radius_km'] ?? 5),
    'payment_qr_url' => (string) ($s['payment_qr_url'] ?? ''),
    'offer_ttl_seconds' => (int) ($s['offer_ttl_seconds'] ?? 60),
    'cod_hold_enabled' => !empty($s['cod_hold_enabled']),
]);
