<?php

declare(strict_types=1);

/**
 * Safely apply 010 hotel POS / settings / menu columns.
 * Usage: php bin/migrate_hotel_pos_ops.php
 */
require_once __DIR__ . '/../lib/admin_db.php';

$pdo = admin_db();
$dbName = Env::get('DB_NAME', 'foodmitra');

function col_exists(PDO $pdo, string $db, string $table, string $col): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND COLUMN_NAME=:c'
    );
    $stmt->execute([':db' => $db, ':t' => $table, ':c' => $col]);
    return (int) $stmt->fetchColumn() > 0;
}

$hotelCols = [
    'address' => 'TEXT NULL',
    'city' => 'VARCHAR(128) NULL',
    'description' => 'TEXT NULL',
    'phone' => 'VARCHAR(20) NULL',
    'gst_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'gst_percent' => 'DECIMAL(5,2) NOT NULL DEFAULT 5.00',
    'gst_number' => 'VARCHAR(32) NULL',
    'service_charge_percent' => 'DECIMAL(5,2) NOT NULL DEFAULT 0',
    'dining_total_tables' => 'INT UNSIGNED NOT NULL DEFAULT 12',
    'dining_has_tents' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'dining_total_tents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'dining_has_garden_tables' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'dining_total_garden_tables' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'dining_has_bar_tables' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'dining_total_bar_tables' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'dining_has_rooms' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'dining_total_rooms' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'dining_room_labels' => 'JSON NULL',
    'dining_has_ac_tables' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'dining_total_ac_tables' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'dining_has_counter' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'operating_hours' => 'JSON NULL',
];

foreach ($hotelCols as $name => $def) {
    if (!col_exists($pdo, $dbName, 'hotels', $name)) {
        $pdo->exec("ALTER TABLE hotels ADD COLUMN {$name} {$def}");
        echo "Added hotels.{$name}\n";
    } else {
        echo "Skip hotels.{$name}\n";
    }
}

$menuCols = [
    'variants_json' => 'JSON NULL',
    'extras_json' => 'JSON NULL',
    'is_jain' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'is_spicy' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'is_sugar_free' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'gst_inclusive' => 'TINYINT(1) NOT NULL DEFAULT 1',
];
foreach ($menuCols as $name => $def) {
    if (!col_exists($pdo, $dbName, 'menu_items', $name)) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN {$name} {$def}");
        echo "Added menu_items.{$name}\n";
    } else {
        echo "Skip menu_items.{$name}\n";
    }
}

$posCols = [
    'table_no' => 'VARCHAR(32) NULL',
    'order_type' => "ENUM('dine_in','pickup','delivery') NOT NULL DEFAULT 'dine_in'",
    'payment_mode' => 'VARCHAR(32) NULL',
    'discount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
    'tax_amount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
    'service_charge' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
    'kot_printed' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'bill_printed' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'customer_address' => 'TEXT NULL',
];
foreach ($posCols as $name => $def) {
    if (!col_exists($pdo, $dbName, 'pos_orders', $name)) {
        $pdo->exec("ALTER TABLE pos_orders ADD COLUMN {$name} {$def}");
        echo "Added pos_orders.{$name}\n";
    } else {
        echo "Skip pos_orders.{$name}\n";
    }
}

try {
    $pdo->exec(
        "ALTER TABLE pos_orders MODIFY COLUMN status ENUM(
            'open','preparing','ready','printed','paid','completed','cancelled'
         ) NOT NULL DEFAULT 'open'"
    );
    echo "Updated pos_orders.status enum\n";
} catch (Throwable $e) {
    echo 'Status enum: ' . $e->getMessage() . "\n";
}

echo "Done.\n";
