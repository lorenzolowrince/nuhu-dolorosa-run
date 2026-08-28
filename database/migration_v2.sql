-- ============================================================
-- Migration v2: Remove Stripe/payment flow, add runner fields
-- Run this AFTER schema.sql has already been imported.
-- ============================================================

-- Add race_category, included shirt sizes to participants
ALTER TABLE `participants`
  ADD COLUMN `race_category`       ENUM('Kanak-Kanak','Open','Veteran','Super Veteran') NOT NULL DEFAULT 'Open'  AFTER `category_id`,
  ADD COLUMN `event_shirt_size`    ENUM('2X','X','S','M','L','XL','2XL')               NOT NULL DEFAULT 'M'     AFTER `race_category`,
  ADD COLUMN `finisher_shirt_size` ENUM('2X','X','S','M','L','XL','2XL')               NULL     DEFAULT NULL    AFTER `event_shirt_size`,
  MODIFY COLUMN `registration_status` ENUM('Pending','Confirmed','Cancelled') NOT NULL DEFAULT 'Pending';

-- Add shirt_type to shirt_orders (optional purchases only)
ALTER TABLE `shirt_orders`
  ADD COLUMN `shirt_type` ENUM('Event','Finisher') NOT NULL DEFAULT 'Event' AFTER `participant_id`,
  MODIFY COLUMN `size` ENUM('2X','X','S','M','L','XL','2XL') NOT NULL;

-- Add payment receipt upload column
ALTER TABLE `participants`
  ADD COLUMN `payment_receipt` VARCHAR(255) NULL DEFAULT NULL AFTER `registration_status`;

-- Disable Stripe; update shirt purchase price to RM40 per PDF
UPDATE `system_settings` SET `setting_value` = '0'     WHERE `setting_key` = 'stripe_enabled';
UPDATE `system_settings` SET `setting_value` = '40.00' WHERE `setting_key` = 'shirt_price';
