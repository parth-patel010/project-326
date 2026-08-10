<?php

declare(strict_types=1);

/**
 * Add preparing_at / ready_at on orders for automatic prep ETA.
 * Usage: php bin/migrate_order_prep_timestamps.php
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
    'preparing_at' => 'TIMESTAMP NULL',
    'ready_at' => 'TIMESTAMP NULL',
];

foreach ($cols as $name => $def) {
    if (!column_exists($pdo, $dbName, 'orders', $name)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN {$name} {$def}");
        echo "Added orders.{$name}\n";
    } else {
        echo "Skip orders.{$name}\n";
    }
}

try {
    $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_hotel_ready (hotel_db_id, ready_at)');
    echo "Added idx_orders_hotel_ready\n";
} catch (Throwable $e) {
    echo "Index: " . $e->getMessage() . "\n";
}

echo "Done.\n";
