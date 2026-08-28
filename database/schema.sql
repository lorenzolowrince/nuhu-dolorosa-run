-- ============================================================
-- Nuhu Dolorosa - Charity Run Registration Portal
-- Database Schema
-- MySQL 8.0+ / MariaDB 10.4+
-- ============================================================

-- ============================================================
-- Admin Users
-- ============================================================
CREATE TABLE `admin_users` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `username`     VARCHAR(50)      NOT NULL,
    `email`        VARCHAR(100)     NOT NULL,
    `password`     VARCHAR(255)     NOT NULL,
    `full_name`    VARCHAR(150)     NOT NULL,
    `is_active`    TINYINT(1)       NOT NULL DEFAULT 1,
    `last_login`   DATETIME             NULL,
    `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`),
    UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Event Categories (7KM, 12KM, 30KM)
-- ============================================================
CREATE TABLE `event_categories` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(80)   NOT NULL,
    `distance`             VARCHAR(20)   NOT NULL,
    `description`          TEXT              NULL,
    `fee`                  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_active`            TINYINT(1)    NOT NULL DEFAULT 1,
    `max_participants`     INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
    `current_participants` INT UNSIGNED  NOT NULL DEFAULT 0,
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- System Settings (key-value store)
-- ============================================================
CREATE TABLE `system_settings` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)  NOT NULL,
    `setting_value` TEXT          NOT NULL,
    `description`   VARCHAR(255)      NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Participants (registrations)
-- ============================================================
CREATE TABLE `participants` (
    `id`                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_number`          VARCHAR(20)  NOT NULL,
    `full_name`                 VARCHAR(150) NOT NULL,
    `ic_number`                 VARCHAR(20)  NOT NULL,
    `contact_number`            VARCHAR(20)  NOT NULL,
    `email`                     VARCHAR(150) NOT NULL,
    `gender`                    ENUM('Male','Female') NOT NULL,
    `age`                       TINYINT UNSIGNED NOT NULL,
    `date_of_birth`             DATE         NOT NULL,
    `address`                   TEXT         NOT NULL,
    `emergency_contact_name`    VARCHAR(150) NOT NULL,
    `emergency_contact_number`  VARCHAR(20)  NOT NULL,
    `category_id`               INT UNSIGNED NOT NULL,
    `health_declaration`        TINYINT(1)   NOT NULL DEFAULT 0,
    `terms_accepted`            TINYINT(1)   NOT NULL DEFAULT 0,
    `registration_status`       ENUM('Pending','Paid','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
    `created_at`                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reference_number` (`reference_number`),
    KEY `idx_email`       (`email`),
    KEY `idx_ic_number`   (`ic_number`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_status`      (`registration_status`),
    CONSTRAINT `fk_participants_category`
        FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Shirt Orders
-- ============================================================
CREATE TABLE `shirt_orders` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `participant_id` INT UNSIGNED  NOT NULL,
    `size`           ENUM('XS','S','M','L','XL','XXL') NOT NULL,
    `quantity`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price`     DECIMAL(10,2) NOT NULL,
    `total_price`    DECIMAL(10,2) NOT NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_participant_id` (`participant_id`),
    CONSTRAINT `fk_shirt_participant`
        FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Payments
-- ============================================================
CREATE TABLE `payments` (
    `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `participant_id`         INT UNSIGNED  NOT NULL,
    `payment_reference`      VARCHAR(60)   NOT NULL COMMENT 'Internal payment ref',
    `gateway`                ENUM('FPX','Stripe') NOT NULL,
    `gateway_transaction_id` VARCHAR(255)      NULL COMMENT 'Gateway-assigned ID',
    `registration_fee`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `shirt_fee`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount`           DECIMAL(10,2) NOT NULL,
    `currency`               CHAR(3)       NOT NULL DEFAULT 'MYR',
    `status`                 ENUM('Pending','Success','Failed','Cancelled','Refunded') NOT NULL DEFAULT 'Pending',
    `payment_date`           DATETIME          NULL,
    `gateway_response`       JSON              NULL,
    `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_reference` (`payment_reference`),
    KEY `idx_participant_id`         (`participant_id`),
    KEY `idx_gateway_transaction_id` (`gateway_transaction_id`),
    KEY `idx_status`                 (`status`),
    KEY `idx_gateway`                (`gateway`),
    CONSTRAINT `fk_payments_participant`
        FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Audit Logs
-- ============================================================
CREATE TABLE `audit_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED     NULL,
    `action`      VARCHAR(100) NOT NULL,
    `table_name`  VARCHAR(60)      NULL,
    `record_id`   INT UNSIGNED     NULL,
    `old_values`  JSON             NULL,
    `new_values`  JSON             NULL,
    `ip_address`  VARCHAR(45)      NULL,
    `user_agent`  VARCHAR(300)     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id`  (`admin_id`),
    KEY `idx_action`    (`action`),
    KEY `idx_record`    (`table_name`, `record_id`),
    CONSTRAINT `fk_audit_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Login Attempts (rate limiting)
-- ============================================================
CREATE TABLE `login_attempts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`   VARCHAR(45)  NOT NULL,
    `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed: Event Categories
-- ============================================================
INSERT INTO `event_categories` (`name`, `distance`, `description`, `fee`, `max_participants`) VALUES
('7KM Fun Run',    '7KM',  'A fun run suitable for all fitness levels and beginners. Enjoy the scenic route!',                             50.00, 500),
('12KM Trail Run', '12KM', 'An intermediate trail run through beautiful nature trails. For regular runners.',                              80.00, 300),
('30KM Trail Run', '30KM', 'The ultimate challenge for experienced ultra-runners. Test your limits for hope!',                            120.00, 100);

-- ============================================================
-- Seed: System Settings
-- ============================================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('event_name',           'Charity Run Nuhu Dolorosa',               'Full event name'),
('event_theme',          'Run For Hope',                             'Event theme / tagline'),
('event_date',           '2026-12-19',                                               'Event date (YYYY-MM-DD)'),
('event_date_display',   '19 Disember 2026',                                         'Human-readable event date'),
('event_start_time',     '5:30 AM',                                                  'Event start time'),
('event_venue',          'St Anne Church, Penampang Baru, Kimanis, Papar, Sabah',    'Event venue'),
('contact_name',         'Mr Jessel Kamijan',                        'Primary contact name'),
('contact_email',        'nuhudolorosadekamijan8@gmail.com',         'Contact email address'),
('contact_phone',        '013-8804954',                              'Contact phone number'),
('shirt_price',          '35.00',                                    'Price per shirt (RM)'),
('shirt_extra_size_fee_1', '12.00',                                  'Extra charge (RM) for 3XL-5XL t-shirt sizes'),
('shirt_extra_size_fee_2', '14.00',                                  'Extra charge (RM) for 6XL-7XL t-shirt sizes'),
('registration_open',    '1',                                        '1 = open, 0 = closed'),
('bank_account_name',    'Nuhu Dolorosa Charity Run',                'Bank account name for reference'),
('fpx_enabled',          '1',                                        'Enable FPX payment gateway'),
('stripe_enabled',       '1',                                        'Enable Stripe payment gateway');

-- ============================================================
-- Seed: Default Admin User
-- Password: Admin@123456  (change immediately after install!)
-- Hash generated with: password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12])
-- ============================================================
INSERT INTO `admin_users` (`username`, `email`, `password`, `full_name`) VALUES
('admin', 'admin@nuhurun.my',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
 'System Administrator');
