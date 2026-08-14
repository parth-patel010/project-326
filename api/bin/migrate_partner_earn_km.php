<?php

declare(strict_types=1);

/**
 * Partner earn = km × ₹/km + earn_wallet.
 * Usage: php /var/www/foodmitra/api/bin/migrate_partner_earn_km.php
 */
require_once __DIR__ . '/../lib/admin_db.php';

$pdo = admin_db();
$dbName = Env::get('DB_NAME', 'foodmitra');

function fm_col_exists(PDO $pdo, string $db, string $table, string $col): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':db' => $db, ':t' => $table, ':c' => $col]);
    return (int) $stmt->fetchColumn() > 0;
}

$orderCols = [
    'delivery_distance_km' => 'DECIMAL(8,3) NULL DEFAULT NULL',
    'delivery_partner_revenue' => 'DECIMAL(10,2) NULL DEFAULT NULL',
];
foreach ($orderCols as $name => $def) {
    if (!fm_col_exists($pdo, $dbName, 'orders', $name)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN {$name} {$def}");
        echo "orders.{$name} added\n";
    } else {
        echo "orders.{$name} skip\n";
    }
}

if (!fm_col_exists($pdo, $dbName, 'delivery_partners', 'earn_wallet')) {
    $pdo->exec(
        'ALTER TABLE delivery_partners
         ADD COLUMN earn_wallet DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER cod_wallet'
    );
    echo "delivery_partners.earn_wallet added\n";
} else {
    echo "delivery_partners.earn_wallet skip\n";
}

if (!fm_col_exists($pdo, $dbName, 'admin_settings', 'platform_partner_per_order_revenue')) {
    $pdo->exec(
        'ALTER TABLE admin_settings
         ADD COLUMN platform_partner_per_order_revenue DECIMAL(10,2) NOT NULL DEFAULT 0.00'
    );
    echo "admin_settings.platform_partner_per_order_revenue added\n";
} else {
    echo "admin_settings.platform_partner_per_order_revenue skip\n";
}

foreach (['kind' => "VARCHAR(32) NOT NULL DEFAULT 'delivery_fee'", 'status' => null] as $name => $def) {
    if ($def === null) {
        continue;
    }
    if (!fm_col_exists($pdo, $dbName, 'payouts', $name)) {
        $pdo->exec("ALTER TABLE payouts ADD COLUMN {$name} {$def}");
        echo "payouts.{$name} added\n";
    } else {
        echo "payouts.{$name} skip\n";
    }
}

echo "ok\n";
