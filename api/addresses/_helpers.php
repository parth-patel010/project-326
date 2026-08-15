<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/users.php';

function addresses_require_user_from_request(): array
{
    $phone = normalize_phone((string) ($_GET['phone'] ?? ''));
    if ($phone === '') {
        $body = json_body();
        $phone = normalize_phone((string) ($body['phone'] ?? ''));
    }
    if (strlen($phone) !== 10) {
        fail('Valid 10-digit phone required');
    }
    $user = find_user_by_phone($phone);
    if (!$user) {
        fail('User not found', 404);
    }
    return $user;
}

function present_address(array $row): array
{
    return [
        'id' => $row['public_id'],
        'label' => $row['label'],
        'line' => $row['line'],
        'details' => $row['details'] ?? '',
        'receiverName' => $row['receiver_name'] ?? '',
        'receiverPhone' => $row['receiver_phone'] ?? '',
        'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : 0,
        'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : 0,
        'is_default' => !empty($row['is_default']),
    ];
}

function ensure_addresses_table(): void
{
    db()->exec(
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
          KEY idx_user_addr_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}
