-- ============================================================
-- Migration: Add roles to admin_users + Cash gateway support
-- Run this once against the live database via phpMyAdmin
-- ============================================================

-- 1. Add role column to admin_users
ALTER TABLE `admin_users`
    ADD COLUMN `role` ENUM('super_admin','admin','viewer') NOT NULL DEFAULT 'admin'
    AFTER `full_name`;

-- 2. Make the existing admin user a super_admin
UPDATE `admin_users` SET `role` = 'super_admin' WHERE `username` = 'admin';

-- 3. Allow Cash as a payment gateway for offline/manual registrations
ALTER TABLE `payments`
    MODIFY COLUMN `gateway` ENUM('FPX','Stripe','Cash') NOT NULL;
