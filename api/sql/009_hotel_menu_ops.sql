-- Hotel ops: kitchen prep time + open/closed for ETA and listing
USE foodmitra;

-- Kitchen prep minutes (added to travel time for customer ETA)
ALTER TABLE hotels
  ADD COLUMN prep_mins INT UNSIGNED NOT NULL DEFAULT 20 AFTER delivery_mins;

ALTER TABLE hotels
  ADD COLUMN is_open TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;

-- Backfill delivery_mins as a display hint (prep + typical travel); prep stays authoritative
UPDATE hotels SET prep_mins = 20 WHERE prep_mins IS NULL OR prep_mins = 0;
