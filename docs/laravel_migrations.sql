-- Laravel-ready SQL schema for BookIT
-- Charset/engine set for MySQL InnoDB

SET FOREIGN_KEY_CHECKS=0;

CREATE DATABASE IF NOT EXISTS `condo_rental_reservation_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `condo_rental_reservation_db`;

-- Users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20),
  `role` ENUM('admin','host','renter') NOT NULL DEFAULT 'renter',
  `branch_id` INT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `email_verified` TINYINT(1) DEFAULT 0,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_branch_id_index` (`branch_id`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branches
CREATE TABLE IF NOT EXISTS `branches` (
  `branch_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_name` VARCHAR(100) NOT NULL,
  `address` TEXT NOT NULL,
  `city` VARCHAR(50) NOT NULL,
  `contact_number` VARCHAR(20),
  `email` VARCHAR(191),
  `host_id` INT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_id`),
  KEY `branches_host_id_index` (`host_id`),
  KEY `branches_city_index` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Units
CREATE TABLE IF NOT EXISTS `units` (
  `unit_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED NOT NULL,
  `unit_number` VARCHAR(50) NOT NULL,
  `unit_type` VARCHAR(50) NOT NULL,
  `floor_number` INT DEFAULT NULL,
  `host_id` INT UNSIGNED DEFAULT NULL,
  `monthly_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `security_deposit` DECIMAL(10,2) DEFAULT 0.00,
  `is_available` TINYINT(1) DEFAULT 1,
  `description` TEXT,
  `max_occupancy` INT DEFAULT 2,
  `building_name` VARCHAR(255),
  `street_address` VARCHAR(255),
  `city` VARCHAR(100),
  `address_hash` VARCHAR(64),
  `latitude` DECIMAL(10,8),
  `longitude` DECIMAL(11,8),
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`),
  KEY `units_branch_id_index` (`branch_id`),
  KEY `units_host_id_index` (`host_id`),
  KEY `units_unit_number_index` (`unit_number`),
  KEY `units_address_hash_index` (`address_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Amenities
CREATE TABLE IF NOT EXISTS `amenities` (
  `amenity_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED NOT NULL,
  `amenity_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `hourly_rate` DECIMAL(8,2) DEFAULT 0.00,
  `is_available` TINYINT(1) DEFAULT 1,
  `max_capacity` INT DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`amenity_id`),
  KEY `amenities_branch_id_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reservations
CREATE TABLE IF NOT EXISTS `reservations` (
  `reservation_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `unit_id` INT UNSIGNED DEFAULT NULL,
  `branch_id` INT UNSIGNED DEFAULT NULL,
  `check_in_date` DATE NOT NULL,
  `check_out_date` DATE NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `security_deposit` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending',
  `payment_status` ENUM('pending','partial','paid','refunded') DEFAULT 'pending',
  `special_requests` TEXT,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`),
  KEY `reservations_user_id_index` (`user_id`),
  KEY `reservations_unit_id_index` (`unit_id`),
  KEY `reservations_branch_id_index` (`branch_id`),
  KEY `reservations_status_index` (`status`),
  KEY `reservations_payment_status_index` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Amenity bookings
CREATE TABLE IF NOT EXISTS `amenity_bookings` (
  `booking_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amenity_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED DEFAULT NULL,
  `booking_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `total_amount` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `amenity_bookings_user_id_index` (`user_id`),
  KEY `amenity_bookings_amenity_id_index` (`amenity_id`),
  KEY `amenity_bookings_branch_id_index` (`branch_id`),
  KEY `amenity_bookings_booking_date_index` (`booking_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` INT UNSIGNED DEFAULT NULL,
  `amenity_booking_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','bank_transfer','gcash','paymaya','credit_card') NOT NULL,
  `payment_status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_reference` VARCHAR(191),
  `payment_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `payments_reservation_id_index` (`reservation_id`),
  KEY `payments_amenity_booking_id_index` (`amenity_booking_id`),
  KEY `payments_user_id_index` (`user_id`),
  KEY `payments_transaction_reference_index` (`transaction_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('booking','payment','reminder','system') NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `sent_via` ENUM('email','sms','system') NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `notifications_user_id_index` (`user_id`),
  KEY `notifications_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews
CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `unit_id` INT UNSIGNED DEFAULT NULL,
  `branch_id` INT UNSIGNED DEFAULT NULL,
  `rating` TINYINT NOT NULL,
  `comment` TEXT,
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `reviews_user_id_index` (`user_id`),
  KEY `reviews_unit_id_index` (`unit_id`),
  KEY `reviews_branch_id_index` (`branch_id`),
  KEY `reviews_rating_index` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment sources / payment intents
CREATE TABLE IF NOT EXISTS `payment_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `reservation_id` INT UNSIGNED DEFAULT NULL,
  `amenity_booking_id` INT UNSIGNED DEFAULT NULL,
  `source_id_paymongo` VARCHAR(191) NOT NULL UNIQUE,
  `payment_method` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending','completed','failed') DEFAULT 'pending',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_sources_user_id_index` (`user_id`),
  KEY `payment_sources_reservation_id_index` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User bank accounts
CREATE TABLE IF NOT EXISTS `user_bank_accounts` (
  `account_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `bank_name` VARCHAR(100) NOT NULL,
  `account_number` VARCHAR(50) NOT NULL,
  `account_name` VARCHAR(100) NOT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `user_bank_accounts_user_id_unique` (`user_id`),
  KEY `user_bank_accounts_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User payment methods (e-wallets, cards metadata)
CREATE TABLE IF NOT EXISTS `user_payment_methods` (
  `method_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `method` VARCHAR(50) NOT NULL,
  `account_details` VARCHAR(255) NOT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`method_id`),
  UNIQUE KEY `user_payment_methods_user_method_unique` (`user_id`,`method`),
  KEY `user_payment_methods_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP verifications
CREATE TABLE IF NOT EXISTS `otp_verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `otp_code` VARCHAR(255) NOT NULL,
  `purpose` VARCHAR(50) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` INT DEFAULT 0,
  `is_used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `otp_user_id_index` (`user_id`),
  KEY `otp_email_index` (`email`),
  KEY `otp_purpose_index` (`purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign keys (choose reasonable ON DELETE rules)
ALTER TABLE `users` ADD CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `branches` ADD CONSTRAINT `fk_branches_host` FOREIGN KEY (`host_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `units` ADD CONSTRAINT `fk_units_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`branch_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `units` ADD CONSTRAINT `fk_units_host` FOREIGN KEY (`host_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `amenities` ADD CONSTRAINT `fk_amenities_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`branch_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `reservations` ADD CONSTRAINT `fk_reservations_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `reservations` ADD CONSTRAINT `fk_reservations_unit` FOREIGN KEY (`unit_id`) REFERENCES `units`(`unit_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `reservations` ADD CONSTRAINT `fk_reservations_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `amenity_bookings` ADD CONSTRAINT `fk_amenity_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `amenity_bookings` ADD CONSTRAINT `fk_amenity_bookings_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities`(`amenity_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `amenity_bookings` ADD CONSTRAINT `fk_amenity_bookings_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`reservation_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_amenity_booking` FOREIGN KEY (`amenity_booking_id`) REFERENCES `amenity_bookings`(`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_unit` FOREIGN KEY (`unit_id`) REFERENCES `units`(`unit_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `reviews` ADD CONSTRAINT `fk_reviews_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `payment_sources` ADD CONSTRAINT `fk_payment_sources_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `payment_sources` ADD CONSTRAINT `fk_payment_sources_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`reservation_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `payment_sources` ADD CONSTRAINT `fk_payment_sources_amenity_booking` FOREIGN KEY (`amenity_booking_id`) REFERENCES `amenity_bookings`(`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `user_bank_accounts` ADD CONSTRAINT `fk_user_bank_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `user_payment_methods` ADD CONSTRAINT `fk_user_payment_methods_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `otp_verifications` ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;

-- End of schema
