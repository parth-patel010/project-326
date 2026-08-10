-- FoodMitra core schema (MySQL / MariaDB)
CREATE DATABASE IF NOT EXISTS foodmitra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foodmitra;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  restaurant_id VARCHAR(64) NOT NULL,
  restaurant_name VARCHAR(255) NOT NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(32) NOT NULL DEFAULT '',
  delivery_label VARCHAR(32) NOT NULL DEFAULT 'Home',
  delivery_line TEXT NOT NULL,
  delivery_details TEXT NULL,
  delivery_lat DECIMAL(10,7) NULL,
  delivery_lng DECIMAL(10,7) NULL,
  note TEXT NULL,
  no_cutlery TINYINT(1) NOT NULL DEFAULT 1,
  payment_mode ENUM('cod','prepaid') NOT NULL,
  status ENUM(
    'awaiting_payment',
    'paid',
    'placed',
    'preparing',
    'out_for_delivery',
    'delivered',
    'cancelled',
    'payment_failed'
  ) NOT NULL,
  subtotal_paise INT UNSIGNED NOT NULL,
  delivery_fee_paise INT UNSIGNED NOT NULL DEFAULT 0,
  platform_fee_paise INT UNSIGNED NOT NULL DEFAULT 0,
  discount_paise INT UNSIGNED NOT NULL DEFAULT 0,
  total_paise INT UNSIGNED NOT NULL,
  razorpay_order_id VARCHAR(64) NULL,
  razorpay_payment_id VARCHAR(64) NULL,
  razorpay_signature VARCHAR(255) NULL,
  items_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  paid_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_public_id (public_id),
  UNIQUE KEY uq_orders_razorpay_order (razorpay_order_id),
  KEY idx_orders_status (status),
  KEY idx_orders_payment (razorpay_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id VARCHAR(64) NULL,
  event_type VARCHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_webhook_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
