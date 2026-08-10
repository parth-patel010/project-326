-- Extend orders for dispatch, OTPs, hotel/user links, payouts
USE foodmitra;

-- Widen status enum
ALTER TABLE orders
  MODIFY COLUMN status ENUM(
    'awaiting_payment',
    'paid',
    'placed',
    'preparing',
    'ready',
    'out_for_delivery',
    'delivered',
    'cancelled',
    'payment_failed'
  ) NOT NULL;

-- Add columns if missing (safe re-run via procedure-style checks in 008b if needed)
ALTER TABLE orders
  ADD COLUMN hotel_db_id BIGINT UNSIGNED NULL AFTER restaurant_id,
  ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER hotel_db_id,
  ADD COLUMN hotel_otp VARCHAR(8) NULL,
  ADD COLUMN delivery_otp VARCHAR(8) NULL,
  ADD COLUMN hotel_otp_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN delivery_otp_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN assigned_partner_id BIGINT UNSIGNED NULL,
  ADD COLUMN delivery_offered_to BIGINT UNSIGNED NULL,
  ADD COLUMN delivery_offered_at TIMESTAMP NULL,
  ADD COLUMN delivery_skip_drivers JSON NULL,
  ADD COLUMN partner_lat DECIMAL(10,7) NULL,
  ADD COLUMN partner_lng DECIMAL(10,7) NULL,
  ADD COLUMN eta_minutes INT UNSIGNED NULL,
  ADD COLUMN pickup_deadline_at TIMESTAMP NULL,
  ADD COLUMN commission_percent DECIMAL(5,2) NULL,
  ADD COLUMN commission_amount_paise INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN partner_earn_paise INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE orders
  ADD KEY idx_orders_hotel_db (hotel_db_id),
  ADD KEY idx_orders_partner (assigned_partner_id),
  ADD KEY idx_orders_offered (delivery_offered_to, delivery_offered_at);
