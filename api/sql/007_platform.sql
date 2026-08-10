-- FoodMitra platform tables (super-admin, hotel users, partners, settings, payouts)
USE foodmitra;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(128) NOT NULL DEFAULT 'Admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotel_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hotel_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(128) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hotel_users_email (email),
  KEY idx_hotel_users_hotel (hotel_id),
  CONSTRAINT fk_hotel_users_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  delivery_commission_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00,
  max_delivery_radius_km DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  default_partner_radius_km DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  delivery_charges_config JSON NULL,
  min_cart_for_free_delivery DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_charge_below_min DECIMAL(10,2) NOT NULL DEFAULT 25.00,
  partner_earn_fixed DECIMAL(10,2) NOT NULL DEFAULT 30.00,
  partner_earn_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  cod_hold_enabled TINYINT(1) NOT NULL DEFAULT 1,
  offer_ttl_seconds INT UNSIGNED NOT NULL DEFAULT 60,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin_settings (id, delivery_charges_config) VALUES
(1, JSON_ARRAY(
  JSON_OBJECT('from_km', 0, 'to_km', 3, 'charge', 20),
  JSON_OBJECT('from_km', 3, 'to_km', 6, 'charge', 35),
  JSON_OBJECT('from_km', 6, 'to_km', 10, 'charge', 49)
))
ON DUPLICATE KEY UPDATE id = id;

-- Default super admin: admin@foodmitra.com / admin123
INSERT INTO admin_users (email, password_hash, name)
VALUES (
  'admin@foodmitra.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Super Admin'
)
ON DUPLICATE KEY UPDATE email = email;

CREATE TABLE IF NOT EXISTS delivery_partners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  full_name VARCHAR(128) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(191) NULL,
  vehicle_type VARCHAR(64) NOT NULL DEFAULT 'bike',
  vehicle_number VARCHAR(64) NULL,
  profile_image_url TEXT NULL,
  service_radius_km DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  is_online TINYINT(1) NOT NULL DEFAULT 0,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  has_insurance TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
  current_latitude DECIMAL(10,7) NULL,
  current_longitude DECIMAL(10,7) NULL,
  h3_cell VARCHAR(32) NULL,
  last_location_update TIMESTAMP NULL,
  orders_completed INT UNSIGNED NOT NULL DEFAULT 0,
  earnings_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  cod_wallet DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_partners_public (public_id),
  UNIQUE KEY uq_partners_phone (phone),
  KEY idx_partners_online (is_online, is_available, status),
  KEY idx_partners_h3 (h3_cell)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  hotel_id BIGINT UNSIGNED NOT NULL,
  customer_name VARCHAR(255) NOT NULL DEFAULT 'Walk-in',
  customer_phone VARCHAR(32) NOT NULL DEFAULT '',
  status ENUM('open','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'open',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  items_json JSON NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_public (public_id),
  KEY idx_pos_hotel (hotel_id, status),
  CONSTRAINT fk_pos_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotel_discount_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hotel_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(128) NOT NULL,
  discount_type ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_discount_hotel (hotel_id),
  CONSTRAINT fk_discount_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payout_type ENUM('hotel','partner') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  period_start DATE NULL,
  period_end DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_payouts_type_target (payout_type, target_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cod_holds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('held','released','settled') NOT NULL DEFAULT 'held',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_cod_partner (partner_id, status),
  KEY idx_cod_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  phone VARCHAR(15) NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  h3_cell VARCHAR(32) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_loc_user (user_id),
  KEY idx_user_loc_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cms_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cms_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cms_pages (slug, title, body_html) VALUES
('terms', 'Terms & Conditions', '<h2>Terms & Conditions</h2><p>Welcome to FoodMitra. By using our app you agree to these terms.</p>'),
('privacy', 'Privacy Policy', '<h2>Privacy Policy</h2><p>We collect location and order data to fulfill deliveries.</p>')
ON DUPLICATE KEY UPDATE title = VALUES(title);
