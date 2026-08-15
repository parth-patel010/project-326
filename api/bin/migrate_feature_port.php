<?php

declare(strict_types=1);

/**
 * Feature-port schema: passwords, addresses, offers, cover, push, combos.
 *
 *   php bin/migrate_feature_port.php
 */
require_once __DIR__ . '/../lib/admin_db.php';

$pdo = admin_db();
$dbName = Env::get('DB_NAME', 'foodmitra');

function fp_has_column(PDO $pdo, string $db, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':db' => $db, ':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function fp_add_column(PDO $pdo, string $db, string $table, string $column, string $def): void
{
    if (fp_has_column($pdo, $db, $table, $column)) {
        echo "skip {$table}.{$column}\n";
        return;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$def}");
    echo "added {$table}.{$column}\n";
}

fp_add_column($pdo, $dbName, 'users', 'password_hash', 'VARCHAR(255) NULL');

fp_add_column($pdo, $dbName, 'menu_items', 'discount_price', 'DECIMAL(10,2) NULL');
fp_add_column($pdo, $dbName, 'menu_items', 'offer_type', "VARCHAR(16) NOT NULL DEFAULT 'none'");
fp_add_column($pdo, $dbName, 'menu_items', 'buy_qty', 'INT UNSIGNED NOT NULL DEFAULT 1');
fp_add_column($pdo, $dbName, 'menu_items', 'get_qty', 'INT UNSIGNED NOT NULL DEFAULT 0');

fp_add_column($pdo, $dbName, 'hotels', 'cover_image_url', 'TEXT NULL');

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS user_addresses (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      public_id VARCHAR(32) NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      label VARCHAR(32) NOT NULL DEFAULT 'Home',
      line VARCHAR(512) NOT NULL DEFAULT '',
      details VARCHAR(512) NOT NULL DEFAULT '',
      receiver_name VARCHAR(128) NOT NULL DEFAULT '',
      receiver_phone VARCHAR(15) NOT NULL DEFAULT '',
      latitude DECIMAL(10,7) NULL,
      longitude DECIMAL(10,7) NULL,
      is_default TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_user_addr_public (public_id),
      KEY idx_user_addr_user (user_id),
      CONSTRAINT fk_user_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "ok user_addresses\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS combo_offers (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      hotel_id BIGINT UNSIGNED NOT NULL,
      title VARCHAR(255) NOT NULL DEFAULT '',
      buy_requirements JSON NULL,
      get_items JSON NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_combo_hotel (hotel_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "ok combo_offers\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS user_push_tokens (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      push_token VARCHAR(512) NOT NULL,
      platform VARCHAR(32) NOT NULL DEFAULT 'android',
      client VARCHAR(32) NOT NULL DEFAULT 'eas',
      device_id VARCHAR(128) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_user_push_token (push_token),
      KEY idx_upt_user (user_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "ok user_push_tokens\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS user_notification_campaigns (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(255) NOT NULL,
      body TEXT NOT NULL,
      audience ENUM('all_users','specific_user') NOT NULL DEFAULT 'all_users',
      target_phone VARCHAR(15) NULL,
      status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
      scheduled_at DATETIME NULL,
      sent_at DATETIME NULL,
      sent_count INT UNSIGNED NOT NULL DEFAULT 0,
      fail_count INT UNSIGNED NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_unc_status (status, scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "ok user_notification_campaigns\n";

echo "Done.\n";
