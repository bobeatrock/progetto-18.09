-- ========================================
-- FESTALAUREA DATABASE SCHEMA
-- ========================================

CREATE DATABASE IF NOT EXISTS `festalaurea_db` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `festalaurea_db`;

-- ========================================
-- USERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `user_type` ENUM('student', 'venue', 'admin') DEFAULT 'student',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `email_verified` TINYINT(1) DEFAULT 0,
    `email_verification_token` VARCHAR(255) DEFAULT NULL,
    `password_reset_token` VARCHAR(255) DEFAULT NULL,
    `password_reset_expires` DATETIME DEFAULT NULL,
    `google_id` VARCHAR(255) DEFAULT NULL,
    `facebook_id` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `user_type` (`user_type`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- VENUES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `venues` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `venue_type` ENUM('restaurant', 'pub', 'club', 'outdoor', 'villa', 'hotel') NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) DEFAULT 'Roma',
    `postal_code` VARCHAR(10) DEFAULT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `capacity_min` INT(11) DEFAULT 10,
    `capacity_max` INT(11) DEFAULT 500,
    `price_per_person_min` DECIMAL(10,2) DEFAULT NULL,
    `price_per_person_max` DECIMAL(10,2) DEFAULT NULL,
    `images` JSON DEFAULT NULL,
    `amenities` JSON DEFAULT NULL,
    `business_hours` JSON DEFAULT NULL,
    `payment_methods` JSON DEFAULT NULL,
    `rating` DECIMAL(3,2) DEFAULT 0.00,
    `total_reviews` INT(11) DEFAULT 0,
    `vat_number` VARCHAR(50) DEFAULT NULL,
    `commission_rate` DECIMAL(5,2) DEFAULT 10.00,
    `featured` TINYINT(1) DEFAULT 0,
    `verified` TINYINT(1) DEFAULT 0,
    `status` ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `user_id` (`user_id`),
    KEY `venue_type` (`venue_type`),
    KEY `city` (`city`),
    KEY `status` (`status`),
    KEY `featured` (`featured`),
    FULLTEXT KEY `search` (`name`, `description`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- BOOKINGS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_code` VARCHAR(20) NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `venue_id` INT(11) UNSIGNED NOT NULL,
    `event_date` DATE NOT NULL,
    `event_time` TIME NOT NULL,
    `guests_count` INT(11) NOT NULL,
    `package_type` VARCHAR(100) DEFAULT NULL,
    `special_requests` TEXT,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `deposit_amount` DECIMAL(10,2) DEFAULT NULL,
    `commission_amount` DECIMAL(10,2) DEFAULT NULL,
    `payment_status` ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    `booking_status` ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    `cancelled_at` DATETIME DEFAULT NULL,
    `cancellation_reason` TEXT,
    `confirmed_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `booking_code` (`booking_code`),
    KEY `user_id` (`user_id`),
    KEY `venue_id` (`venue_id`),
    KEY `event_date` (`event_date`),
    KEY `payment_status` (`payment_status`),
    KEY `booking_status` (`booking_status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- PAYMENTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `payment_method` ENUM('stripe', 'paypal', 'bank_transfer', 'cash') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'EUR',
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    `gateway_response` JSON DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `refunded_at` DATETIME DEFAULT NULL,
    `refund_amount` DECIMAL(10,2) DEFAULT NULL,
    `refund_reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `booking_id` (`booking_id`),
    KEY `user_id` (`user_id`),
    KEY `transaction_id` (`transaction_id`),
    KEY `status` (`status`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- REVIEWS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) UNSIGNED NOT NULL,
    `venue_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `rating` INT(1) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
    `title` VARCHAR(255) DEFAULT NULL,
    `comment` TEXT,
    `photos` JSON DEFAULT NULL,
    `venue_response` TEXT,
    `venue_response_at` DATETIME DEFAULT NULL,
    `helpful_count` INT(11) DEFAULT 0,
    `verified_booking` TINYINT(1) DEFAULT 1,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_review` (`booking_id`),
    KEY `venue_id` (`venue_id`),
    KEY `user_id` (`user_id`),
    KEY `rating` (`rating`),
    KEY `status` (`status`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- GUESTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `guests` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `attendance_status` ENUM('invited', 'confirmed', 'declined', 'maybe') DEFAULT 'invited',
    `payment_status` ENUM('pending', 'paid') DEFAULT 'pending',
    `payment_amount` DECIMAL(10,2) DEFAULT NULL,
    `dietary_restrictions` TEXT,
    `plus_ones` INT(11) DEFAULT 0,
    `token` VARCHAR(255) DEFAULT NULL,
    `responded_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `booking_id` (`booking_id`),
    KEY `token` (`token`),
    KEY `attendance_status` (`attendance_status`),
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- MESSAGES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id` INT(11) UNSIGNED NOT NULL,
    `receiver_id` INT(11) UNSIGNED NOT NULL,
    `booking_id` INT(11) UNSIGNED DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `sender_id` (`sender_id`),
    KEY `receiver_id` (`receiver_id`),
    KEY `booking_id` (`booking_id`),
    KEY `is_read` (`is_read`),
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- NOTIFICATIONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT,
    `data` JSON DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_read` (`is_read`),
    KEY `type` (`type`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- SETTINGS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT,
    `type` VARCHAR(50) DEFAULT 'string',
    `group` VARCHAR(50) DEFAULT 'general',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`),
    KEY `group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- INSERT DEFAULT DATA
-- ========================================

-- Default Admin User (password: admin123)
INSERT INTO `users` (`email`, `password`, `first_name`, `last_name`, `user_type`, `email_verified`, `status`) VALUES
('admin@festalaurea.eu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'FestaLaurea', 'admin', 1, 'active');

-- Default Settings
INSERT INTO `settings` (`key`, `value`, `type`, `group`) VALUES
('site_name', 'FestaLaurea', 'string', 'general'),
('site_email', 'info@festalaurea.eu', 'string', 'general'),
('commission_rate', '10', 'number', 'payment'),
('min_booking_days', '7', 'number', 'booking'),
('max_guests', '500', 'number', 'booking'),
('currency', 'EUR', 'string', 'payment'),
('timezone', 'Europe/Rome', 'string', 'general');

-- Sample Venues
INSERT INTO `venues` (`user_id`, `name`, `slug`, `description`, `venue_type`, `address`, `city`, `capacity_min`, `capacity_max`, `price_per_person_min`, `price_per_person_max`, `rating`, `status`, `verified`, `featured`) VALUES
(1, 'Ristorante La Torre', 'ristorante-la-torre', 'Elegante ristorante nel cuore di Roma con vista panoramica', 'restaurant', 'Via del Corso 123', 'Roma', 20, 80, 35.00, 65.00, 4.8, 'active', 1, 1),
(1, 'The Crown Pub', 'the-crown-pub', 'Autentico pub inglese con ampia selezione di birre', 'pub', 'Via Trastevere 45', 'Roma', 30, 100, 20.00, 35.00, 4.6, 'active', 1, 0),
(1, 'Villa Borghese Eventi', 'villa-borghese-eventi', 'Location esclusiva immersa nel verde', 'villa', 'Villa Borghese', 'Roma', 50, 200, 50.00, 100.00, 4.9, 'active', 1, 1);