-- FoodMitra hotel POS + settings + menu extras
USE foodmitra;

-- Hotel settings extras
ALTER TABLE hotels ADD COLUMN address TEXT NULL;
ALTER TABLE hotels ADD COLUMN city VARCHAR(128) NULL;
ALTER TABLE hotels ADD COLUMN description TEXT NULL;
ALTER TABLE hotels ADD COLUMN phone VARCHAR(20) NULL;
ALTER TABLE hotels ADD COLUMN gst_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE hotels ADD COLUMN gst_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00;
ALTER TABLE hotels ADD COLUMN gst_number VARCHAR(32) NULL;
ALTER TABLE hotels ADD COLUMN service_charge_percent DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE hotels ADD COLUMN dining_total_tables INT UNSIGNED NOT NULL DEFAULT 12;
ALTER TABLE hotels ADD COLUMN operating_hours JSON NULL;

-- Menu item extras (EatnSay-like, FoodMitra stays pure veg)
ALTER TABLE menu_items ADD COLUMN variants_json JSON NULL;
ALTER TABLE menu_items ADD COLUMN extras_json JSON NULL;
ALTER TABLE menu_items ADD COLUMN is_jain TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE menu_items ADD COLUMN is_spicy TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE menu_items ADD COLUMN is_sugar_free TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE menu_items ADD COLUMN gst_inclusive TINYINT(1) NOT NULL DEFAULT 1;

-- POS order extras
ALTER TABLE pos_orders ADD COLUMN table_no VARCHAR(32) NULL;
ALTER TABLE pos_orders ADD COLUMN order_type ENUM('dine_in','pickup','delivery') NOT NULL DEFAULT 'dine_in';
ALTER TABLE pos_orders ADD COLUMN payment_mode VARCHAR(32) NULL;
ALTER TABLE pos_orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE pos_orders ADD COLUMN tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE pos_orders ADD COLUMN service_charge DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE pos_orders ADD COLUMN kot_printed TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pos_orders ADD COLUMN bill_printed TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pos_orders ADD COLUMN customer_address TEXT NULL;

-- Widen POS status for floor map
ALTER TABLE pos_orders MODIFY COLUMN status ENUM(
  'open','preparing','ready','printed','paid','completed','cancelled'
) NOT NULL DEFAULT 'open';
