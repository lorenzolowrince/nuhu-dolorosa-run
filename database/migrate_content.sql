-- ============================================================
-- Content Update Migration
-- Run in phpMyAdmin on u200803951_nuhudolorosa
-- ============================================================

-- Update race categories: distances, names, and fees
UPDATE `event_categories` SET `name` = '7KM Fun Run',      `fee` = 85.00  WHERE `distance` = '7KM';
UPDATE `event_categories` SET `name` = '15KM Trail Run',   `distance` = '15KM', `fee` = 140.00 WHERE `distance` = '12KM';
UPDATE `event_categories` SET `name` = '43KM Ultra Trail', `distance` = '43KM', `fee` = 220.00 WHERE `distance` = '30KM';

-- Update system settings
UPDATE `system_settings` SET `setting_value` = 'Nuhu Dolorosa Ultra Trail 3.0 Charity Run' WHERE `setting_key` = 'event_name';
UPDATE `system_settings` SET `setting_value` = 'nuhudolorosa@gmail.com'                     WHERE `setting_key` = 'contact_email';
UPDATE `system_settings` SET `setting_value` = 'Jessel Kamijan'                             WHERE `setting_key` = 'contact_name';
UPDATE `system_settings` SET `setting_value` = '019-8834954'                                WHERE `setting_key` = 'contact_phone';

-- Add second contact (safe to re-run)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('contact_name2',  'Prisca Kinsik', 'Secondary contact name'),
  ('contact_phone2', '017-2574954',   'Secondary contact phone')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
