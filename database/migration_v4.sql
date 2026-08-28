-- ============================================================
-- Migration v4: Add extra t-shirt sizes (3XL-7XL) with surcharge
-- Run this AFTER migration_v2.sql (and migration_v3.sql) have been executed.
-- ============================================================

-- Extend shirt size options on participants (included shirts)
ALTER TABLE `participants`
  MODIFY COLUMN `event_shirt_size`    ENUM('2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL') NOT NULL DEFAULT 'M',
  MODIFY COLUMN `finisher_shirt_size` ENUM('2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL') NULL DEFAULT NULL;

-- Extend shirt size options on optional shirt purchases
ALTER TABLE `shirt_orders`
  MODIFY COLUMN `size` ENUM('2X','X','S','M','L','XL','2XL','3XL','4XL','5XL','6XL','7XL') NOT NULL;

-- Surcharge settings for extra (oversized) shirt sizes
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('shirt_extra_size_fee_1', '12.00', 'Extra charge (RM) for 3XL-5XL t-shirt sizes'),
('shirt_extra_size_fee_2', '14.00', 'Extra charge (RM) for 6XL-7XL t-shirt sizes')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
