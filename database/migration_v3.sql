-- ============================================================
-- Migration v3: Add payment receipt upload column
-- Run this only if migration_v2.sql was already executed.
-- ============================================================

ALTER TABLE `participants`
  ADD COLUMN `payment_receipt` VARCHAR(255) NULL DEFAULT NULL AFTER `registration_status`;
