<?php

declare(strict_types=1);

/**
 * Safely add prep_mins / is_open on hotels.
 * Usage: php bin/migrate_hotel_menu_ops.php
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
    'prep_mins' => 'INT UNSIGNED NOT NULL DEFAULT 20',
    'is_open' => 'TINYINT(1) NOT NULL DEFAULT 1',
];

foreach ($cols as $name => $def) {
    if (!column_exists($pdo, $dbName, 'hotels', $name)) {
        $pdo->exec("ALTER TABLE hotels ADD COLUMN {$name} {$def}");
        echo "Added hotels.{$name}\n";
    } else {
        echo "Skip hotels.{$name}\n";
    }
}

echo "Done.\n";
