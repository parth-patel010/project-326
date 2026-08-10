-- If hotels was created earlier with lat/lng, rename to latitude/longitude
USE foodmitra;

-- Add columns if missing (safe for fresh or partial installs)
SET @db := DATABASE();

SET @has_lat := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'hotels' AND COLUMN_NAME = 'lat'
);
SET @has_latitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'hotels' AND COLUMN_NAME = 'latitude'
);

-- Rename lat -> latitude when old column exists and new does not
SET @sql := IF(
  @has_lat > 0 AND @has_latitude = 0,
  'ALTER TABLE hotels CHANGE COLUMN lat latitude DECIMAL(10,7) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_lng := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'hotels' AND COLUMN_NAME = 'lng'
);
SET @has_longitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'hotels' AND COLUMN_NAME = 'longitude'
);

SET @sql := IF(
  @has_lng > 0 AND @has_longitude = 0,
  'ALTER TABLE hotels CHANGE COLUMN lng longitude DECIMAL(10,7) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure columns exist even if neither old nor new was present
SET @has_latitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'hotels' AND COLUMN_NAME = 'latitude'
);
SET @sql := IF(
  @has_latitude = 0,
  'ALTER TABLE hotels ADD COLUMN latitude DECIMAL(10,7) NULL AFTER is_active',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_longitude := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'hotels' AND COLUMN_NAME = 'longitude'
);
SET @sql := IF(
  @has_longitude = 0,
  'ALTER TABLE hotels ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed Vadodara area coordinates
UPDATE hotels SET latitude = 22.3225200, longitude = 73.1812000 WHERE public_id = '1';
UPDATE hotels SET latitude = 22.3145000, longitude = 73.1685000 WHERE public_id = '2';
UPDATE hotels SET latitude = 22.3102000, longitude = 73.1810000 WHERE public_id = '3';
UPDATE hotels SET latitude = 22.2730000, longitude = 73.1890000 WHERE public_id = '4';
UPDATE hotels SET latitude = 22.3260000, longitude = 73.1850000 WHERE public_id = '5';
