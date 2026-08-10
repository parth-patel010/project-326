-- Users, hotels (restaurants), menu catalog
USE foodmitra;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  name VARCHAR(255) NOT NULL DEFAULT '',
  email VARCHAR(255) NULL,
  avatar_url TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_public_id (public_id),
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotels (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  name VARCHAR(255) NOT NULL,
  image TEXT NOT NULL,
  rating DECIMAL(3,1) NOT NULL DEFAULT 0.0,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  area VARCHAR(128) NOT NULL DEFAULT '',
  delivery_mins INT UNSIGNED NOT NULL DEFAULT 30,
  distance_km DECIMAL(6,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  avg_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  tags VARCHAR(255) NOT NULL DEFAULT '',
  pure_veg TINYINT(1) NOT NULL DEFAULT 1,
  offer_active TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hotels_public_id (public_id),
  KEY idx_hotels_active_sort (is_active, sort_order),
  KEY idx_hotels_pure_veg (pure_veg)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotel_offers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hotel_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_hotel_offers_hotel (hotel_id),
  CONSTRAINT fk_hotel_offers_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hotel_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  icon VARCHAR(32) NOT NULL DEFAULT 'meal',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_menu_cat_hotel_slug (hotel_id, slug),
  KEY idx_menu_cat_hotel (hotel_id, sort_order),
  CONSTRAINT fk_menu_cat_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(64) NOT NULL,
  hotel_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  image TEXT NOT NULL,
  is_veg TINYINT(1) NOT NULL DEFAULT 1,
  is_recommended TINYINT(1) NOT NULL DEFAULT 0,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_menu_items_public_id (public_id),
  KEY idx_menu_items_hotel (hotel_id, is_available, sort_order),
  KEY idx_menu_items_category (category_id),
  CONSTRAINT fk_menu_items_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE,
  CONSTRAINT fk_menu_items_category FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
