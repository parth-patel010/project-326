-- Kitchen prep duration stamps for automatic ETA (avg of last 5 orders).
USE foodmitra;

ALTER TABLE orders
  ADD COLUMN preparing_at TIMESTAMP NULL AFTER paid_at,
  ADD COLUMN ready_at TIMESTAMP NULL AFTER preparing_at;

ALTER TABLE orders
  ADD KEY idx_orders_hotel_ready (hotel_db_id, ready_at);
