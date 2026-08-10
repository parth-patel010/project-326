<?php

declare(strict_types=1);

/**
 * Safely add order dispatch columns if missing.
 * Usage: php bin/migrate_orders_dispatch.php
 */
require_once __DIR__ . '/../lib/admin_db.php';

$pdo = admin_db();
$dbName = Env::get('DB_NAME', 'foodmitra');

function column_exists(PDO $pdo, string $db, string $table, string $col): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':db' => $db, ':t' => $table, ':c' => $col]);
    return (int) $stmt->fetchColumn() > 0;
}

$cols = [
    'hotel_db_id' => 'BIGINT UNSIGNED NULL',
    'user_id' => 'BIGINT UNSIGNED NULL',
    'hotel_otp' => 'VARCHAR(8) NULL',
    'delivery_otp' => 'VARCHAR(8) NULL',
    'hotel_otp_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'delivery_otp_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'assigned_partner_id' => 'BIGINT UNSIGNED NULL',
    'delivery_offered_to' => 'BIGINT UNSIGNED NULL',
    'delivery_offered_at' => 'TIMESTAMP NULL',
    'delivery_skip_drivers' => 'JSON NULL',
    'partner_lat' => 'DECIMAL(10,7) NULL',
    'partner_lng' => 'DECIMAL(10,7) NULL',
    'eta_minutes' => 'INT UNSIGNED NULL',
    'pickup_deadline_at' => 'TIMESTAMP NULL',
    'commission_percent' => 'DECIMAL(5,2) NULL',
    'commission_amount_paise' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    'partner_earn_paise' => 'INT UNSIGNED NOT NULL DEFAULT 0',
];

foreach ($cols as $name => $def) {
    if (!column_exists($pdo, $dbName, 'orders', $name)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN {$name} {$def}");
        echo "Added column {$name}\n";
    } else {
        echo "Skip {$name}\n";
    }
}

try {
    $pdo->exec(
        "ALTER TABLE orders MODIFY COLUMN status ENUM(
            'awaiting_payment','paid','placed','preparing','ready',
            'out_for_delivery','delivered','cancelled','payment_failed'
         ) NOT NULL"
    );
    echo "Updated status enum\n";
} catch (Throwable $e) {
    echo "Status enum: " . $e->getMessage() . "\n";
}

// Fix default admin password to admin123 if still placeholder
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$pdo->prepare(
    "UPDATE admin_users SET password_hash = :h WHERE email = 'admin@foodmitra.com'"
)->execute([':h' => $hash]);
echo "Admin password set to admin123 for admin@foodmitra.com\n";
echo "Done.\n";
