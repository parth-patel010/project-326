<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/delivery_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$partner = delivery_partner_from_request();
$body = json_body();
$token = trim((string) ($body['push_token'] ?? ''));
$platform = strtolower(trim((string) ($body['platform'] ?? 'android')));
$client = trim((string) ($body['client'] ?? 'eas'));
$deviceId = trim((string) ($body['device_id'] ?? ''));

if ($token === '' || strlen($token) < 20) {
    fail('push_token required');
}
if (!in_array($platform, ['android', 'ios', 'web'], true)) {
    $platform = 'android';
}

db()->exec(
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

// One active token per device if device_id provided; always upsert by push_token
db()->prepare(
    'INSERT INTO partner_push_tokens (partner_id, push_token, platform, client, device_id, is_active)
     VALUES (:p, :t, :plat, :c, :d, 1)
     ON DUPLICATE KEY UPDATE
       partner_id = VALUES(partner_id),
       platform = VALUES(platform),
       client = VALUES(client),
       device_id = VALUES(device_id),
       is_active = 1,
       updated_at = CURRENT_TIMESTAMP'
)->execute([
    ':p' => (int) $partner['id'],
    ':t' => $token,
    ':plat' => $platform,
    ':c' => $client !== '' ? $client : 'eas',
    ':d' => $deviceId !== '' ? $deviceId : null,
]);

respond(['ok' => true, 'success' => true]);
