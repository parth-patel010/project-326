-- Delivery partner app support (super-admin config + FCM tokens)
-- Prefer: php api/bin/migrate_delivery_app_admin.php
-- Or run statements carefully on foodmitra DB.

USE foodmitra;

CREATE TABLE IF NOT EXISTS partner_push_tokens (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
