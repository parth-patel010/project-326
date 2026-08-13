<?php

declare(strict_types=1);

/**
 * Adds delivery-app admin settings columns + partner_push_tokens table.
 *
 *   php bin/migrate_delivery_app_admin.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$pdo = db();

function fm_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$cols = [
    'delivery_support_phone' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'payment_qr_url' => 'TEXT NULL',
    'maintenance_mode_delivery' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'admin_contact_number' => "VARCHAR(20) NOT NULL DEFAULT ''",
    'delivery_app_min_version_android' => "VARCHAR(32) NOT NULL DEFAULT '1.0.0'",
    'delivery_app_min_version_ios' => "VARCHAR(32) NOT NULL DEFAULT '1.0.0'",
    'delivery_app_download_url_android' => 'TEXT NULL',
    'delivery_app_download_url_ios' => 'TEXT NULL',
];

foreach ($cols as $name => $def) {
    if (fm_has_column($pdo, 'admin_settings', $name)) {
        echo "skip column admin_settings.$name\n";
        continue;
    }
    $pdo->exec("ALTER TABLE admin_settings ADD COLUMN `$name` $def");
    echo "added column admin_settings.$name\n";
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS partner_push_tokens (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      partner_id BIGINT UNSIGNED NOT NULL,
      push_token VARCHAR(512) NOT NULL,
      platform VARCHAR(32) NOT NULL DEFAULT 'android',
      client VARCHAR(32) NOT NULL DEFAULT 'eas',
      device_id VARCHAR(128) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_partner_token (push_token),
      KEY idx_ppt_partner (partner_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "ok partner_push_tokens\n";
echo "Done.\n";
