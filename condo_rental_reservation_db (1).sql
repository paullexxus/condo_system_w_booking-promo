-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 20, 2026 at 05:01 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `condo_rental_reservation_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

DROP TABLE IF EXISTS `amenities`;
CREATE TABLE IF NOT EXISTS `amenities` (
  `amenity_id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `amenity_name` varchar(100) NOT NULL,
  `description` text,
  `hourly_rate` decimal(8,2) DEFAULT '0.00',
  `is_available` tinyint(1) DEFAULT '1',
  `max_capacity` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`amenity_id`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `amenity_bookings`
--

DROP TABLE IF EXISTS `amenity_bookings`;
CREATE TABLE IF NOT EXISTS `amenity_bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `amenity_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `total_amount` decimal(8,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `user_id` (`user_id`),
  KEY `amenity_id` (`amenity_id`),
  KEY `fk_amenity_bookings_branch` (`branch_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `amenity_bookings`
--

INSERT INTO `amenity_bookings` (`booking_id`, `user_id`, `amenity_id`, `branch_id`, `booking_date`, `start_time`, `end_time`, `total_amount`, `status`, `created_at`) VALUES
(1, 10, 4, 3, '2025-11-15', '06:30:00', '18:30:00', 2160.00, 'pending', '2025-11-15 09:11:25');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
CREATE TABLE IF NOT EXISTS `branches` (
  `branch_id` int NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `host_id` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`branch_id`),
  KEY `manager_id` (`manager_id`),
  KEY `branches_host_id_index` (`host_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_name`, `address`, `city`, `contact_number`, `email`, `manager_id`, `is_active`, `created_at`, `latitude`, `longitude`, `host_id`) VALUES
(1, 'BookIT Makati', '123 Ayala Avenue', 'Makati', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, NULL),
(2, 'BookIT BGC', '456 BGC High Street', 'Taguig', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, NULL),
(3, 'BookIT Ortigas', '789 Ortigas Center', 'Pasig', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `host_payment_methods`
--

DROP TABLE IF EXISTS `host_payment_methods`;
CREATE TABLE IF NOT EXISTS `host_payment_methods` (
  `payment_method_id` int NOT NULL AUTO_INCREMENT,
  `host_id` int NOT NULL,
  `method_type` enum('paymongo','paypal','gcash','bank_transfer') NOT NULL,
  `method_name` varchar(100) NOT NULL,
  `account_id` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_method_id`),
  KEY `host_id` (`host_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('booking','payment','reminder','system') NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

DROP TABLE IF EXISTS `otp_verifications`;
CREATE TABLE IF NOT EXISTS `otp_verifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` enum('signup','reset_password') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` int DEFAULT '0',
  `is_used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_verifications`
--

INSERT INTO `otp_verifications` (`id`, `user_id`, `email`, `otp_code`, `purpose`, `expires_at`, `attempts`, `is_used`, `created_at`) VALUES
(16, 3, 'jersonedanao2002@gmail.com', '$2y$10$lYgXxHVleOBSHDpALkOefuCZ89fFFOQT0A5l9OCs5UQkwlTdJSVC6', 'reset_password', '2026-01-24 15:40:19', 0, 1, '2026-01-24 07:30:19'),
(17, 9, 'p2235361@gmail.com', '$2y$10$gKAk5YgVGm0n/fxN7rHwXehQ4nOuameqJuDyyv8nHsCiZWOYMtxMC', 'reset_password', '2026-03-07 14:56:04', 0, 1, '2026-03-07 06:46:04');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `reservation_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','gcash','paymaya','credit_card') NOT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payout_released` tinyint(1) DEFAULT '0',
  `payout_released_at` datetime DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `reservation_id` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payouts`
--

DROP TABLE IF EXISTS `payouts`;
CREATE TABLE IF NOT EXISTS `payouts` (
  `payout_id` int NOT NULL AUTO_INCREMENT,
  `host_id` int NOT NULL,
  `reservation_id` int DEFAULT NULL,
  `payment_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','released','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `released_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payout_id`),
  KEY `host_id` (`host_id`),
  KEY `reservation_id` (`reservation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
CREATE TABLE IF NOT EXISTS `refunds` (
  `refund_id` int NOT NULL AUTO_INCREMENT,
  `reservation_id` int NOT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `refund_id_paymongo` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text,
  `status` varchar(50) DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`refund_id`),
  KEY `reservation_id` (`reservation_id`),
  KEY `processed_by` (`processed_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
CREATE TABLE IF NOT EXISTS `reservations` (
  `reservation_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','partial','paid','refunded') DEFAULT 'pending',
  `special_requests` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `host_notes` text,
  `admin_notes` text,
  `cancellation_reason` text,
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `renter_rating` tinyint UNSIGNED DEFAULT NULL,
  `renter_feedback` text,
  `government_id_path` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`reservation_id`),
  KEY `user_id` (`user_id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_notes`
--

DROP TABLE IF EXISTS `reservation_notes`;
CREATE TABLE IF NOT EXISTS `reservation_notes` (
  `note_id` int NOT NULL AUTO_INCREMENT,
  `reservation_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `note_type` enum('host','admin','internal') DEFAULT NULL,
  `note_text` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`note_id`),
  KEY `reservation_id` (`reservation_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `rating` int DEFAULT NULL,
  `comment` text,
  `is_approved` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `user_id` (`user_id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext,
  `setting_type` varchar(20) DEFAULT NULL,
  `description` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'system_name', 'BookIT', 'text', 'System name displayed in browser', '2025-11-13 07:59:41'),
(2, 'company_name', 'BookIT Management', 'text', 'Company name', '2025-11-13 07:59:41'),
(3, 'address', 'Manila, Philippines', 'text', 'Company address', '2025-11-13 07:59:41'),
(4, 'email', 'admin@bookit.com', 'email', 'Contact email', '2025-11-13 07:59:41'),
(5, 'contact_number', '+63 9XX XXXX XXXX', 'text', 'Contact phone number', '2025-11-13 07:59:41'),
(6, 'timezone', 'Asia/Manila', 'text', 'System timezone', '2025-11-13 07:59:41'),
(7, 'date_format', 'MM/DD/YYYY', 'select', 'Date format', '2025-11-13 07:59:41'),
(8, 'sender_email', 'noreply@bookit.com', 'email', 'SMTP sender email', '2025-11-13 07:59:41'),
(9, 'smtp_host', '', 'text', 'SMTP host', '2025-11-13 07:59:41'),
(10, 'smtp_port', '587', 'number', 'SMTP port', '2025-11-13 07:59:41'),
(11, 'smtp_username', '', 'text', 'SMTP username', '2025-11-13 07:59:41'),
(12, 'smtp_password', '', 'password', 'SMTP password', '2025-11-13 07:59:41'),
(13, 'smtp_encryption', 'tls', 'select', 'SMTP encryption (tls/ssl/none)', '2025-11-13 07:59:41'),
(14, 'default_currency', 'PHP', 'text', 'Default currency', '2025-11-13 07:59:41'),
(15, 'transaction_fee', '2.5', 'number', 'Transaction fee percentage', '2025-11-13 07:59:41'),
(16, 'payment_instructions', '', 'textarea', 'Manual payment instructions', '2025-11-13 07:59:41'),
(17, 'login_attempts', '5', 'number', 'Max login attempts before lockout', '2025-11-13 07:59:41'),
(18, 'lockout_duration', '30', 'number', 'Lockout duration in minutes', '2025-11-13 07:59:41'),
(19, 'password_min_length', '8', 'number', 'Minimum password length', '2025-11-13 07:59:41'),
(20, 'session_timeout', '60', 'number', 'Session timeout in minutes', '2025-11-13 07:59:41'),
(21, 'force_https', '1', 'boolean', 'Force HTTPS connection', '2025-11-13 07:59:41'),
(22, 'two_factor_auth', '0', 'boolean', 'Enable 2FA for admin', '2025-11-13 07:59:41'),
(23, 'primary_color', '#3498db', 'color', 'Primary theme color', '2025-11-13 07:59:41'),
(24, 'secondary_color', '#2c3e50', 'color', 'Secondary theme color', '2025-11-13 07:59:41'),
(25, 'success_color', '#27ae60', 'color', 'Success status color', '2025-11-13 07:59:41'),
(26, 'danger_color', '#e74c3c', 'color', 'Danger status color', '2025-11-13 07:59:41'),
(27, 'logo_path', '/assets/images/logo.png', 'text', 'Logo file path', '2025-11-13 07:59:41'),
(28, 'favicon_path', '/assets/images/favicon.ico', 'text', 'Favicon file path', '2025-11-13 07:59:41'),
(29, 'banner_path', '/assets/images/banner.jpg', 'text', 'Homepage banner path', '2025-11-13 07:59:41'),
(30, 'homepage_title', 'Welcome to BookIT Rentals', 'text', 'Homepage title', '2025-11-13 07:59:41'),
(31, 'homepage_description', 'Find your perfect rental property', 'textarea', 'Homepage description', '2025-11-13 07:59:41'),
(32, 'footer_copyright', '© 2025 BookIT. All rights reserved.', 'text', 'Footer copyright text', '2025-11-13 07:59:41'),
(33, 'payment_methods', '[\"gcash\", \"bank_transfer\"]', 'json', 'Enabled payment methods', '2025-11-13 07:59:41'),
(34, 'notification_settings', '{\"reservation\": true, \"payment\": true, \"review\": true, \"system\": true}', 'json', 'Notification preferences', '2025-11-13 07:59:41'),
(35, 'custom_message', '', NULL, NULL, '2026-02-01 00:50:21');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `unit_id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `unit_number` varchar(20) NOT NULL,
  `unit_type` varchar(50) NOT NULL,
  `floor_number` int DEFAULT NULL,
  `monthly_rate` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) DEFAULT '0.00',
  `is_available` tinyint(1) DEFAULT '1',
  `description` text,
  `max_occupancy` int DEFAULT '2',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `bedrooms` int DEFAULT NULL,
  `host_id` int DEFAULT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `instant_booking` tinyint(1) DEFAULT '0',
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text,
  PRIMARY KEY (`unit_id`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_id`, `branch_id`, `unit_number`, `unit_type`, `floor_number`, `monthly_rate`, `security_deposit`, `is_available`, `description`, `max_occupancy`, `created_at`, `bedrooms`, `host_id`, `unit_name`, `instant_booking`, `approval_status`, `rejection_reason`) VALUES
(1, 1, 'A101', 'Studio', NULL, 15000.00, 0.00, 1, NULL, 2, '2026-01-21 01:45:52', NULL, NULL, NULL, 1, 'approved', NULL),
(2, 2, 'B101', 'Studio', NULL, 18000.00, 0.00, 1, NULL, 2, '2026-01-21 01:45:52', NULL, NULL, NULL, 0, 'approved', NULL),
(3, 1, '142', '3 Bedrooms', 6, 2500.00, 15.00, 0, '', 4, '2026-03-10 11:32:02', NULL, NULL, NULL, 0, 'pending', NULL),
(4, 1, '142', '3 Bedrooms', 6, 2500.00, 15.00, 0, '', 4, '2026-03-10 11:35:19', NULL, NULL, NULL, 0, 'pending', NULL),
(5, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 11:50:57', NULL, NULL, NULL, 0, 'pending', NULL),
(6, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 11:51:10', NULL, NULL, NULL, 0, 'pending', NULL),
(7, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 11:51:56', NULL, NULL, NULL, 0, 'pending', NULL),
(8, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 12:09:18', NULL, NULL, NULL, 0, 'pending', NULL),
(9, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 12:13:09', NULL, NULL, NULL, 0, 'pending', NULL),
(10, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 12:13:29', NULL, NULL, NULL, 0, 'pending', NULL),
(11, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 12:13:55', NULL, NULL, NULL, 0, 'pending', NULL),
(12, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 1, '', 5, '2026-03-10 12:29:43', NULL, NULL, NULL, 0, 'pending', NULL),
(13, 1, '', '', NULL, 500.00, 0.00, 1, '', 10, '2026-03-20 04:53:34', NULL, 10, 'A202', 0, 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `unit_geolocation`
--

DROP TABLE IF EXISTS `unit_geolocation`;
CREATE TABLE IF NOT EXISTS `unit_geolocation` (
  `geolocation_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `address_latitude` decimal(10,8) DEFAULT NULL,
  `address_longitude` decimal(11,8) DEFAULT NULL,
  `coordinate_hash` varchar(128) DEFAULT NULL,
  `proximity_matches` json DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`geolocation_id`),
  UNIQUE KEY `unique_unit_geolocation` (`unit_id`),
  KEY `unit_id` (`unit_id`),
  KEY `coordinate_hash` (`coordinate_hash`),
  KEY `latitude` (`latitude`),
  KEY `longitude` (`longitude`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unit_images`
--

DROP TABLE IF EXISTS `unit_images`;
CREATE TABLE IF NOT EXISTS `unit_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_hash` varchar(128) DEFAULT NULL,
  `room_type` varchar(100) DEFAULT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_flagged` tinyint(1) DEFAULT '0',
  `flag_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `unit_id` (`unit_id`),
  KEY `image_hash` (`image_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','renter') NOT NULL,
  `branch_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `address` text,
  `profile_picture` varchar(255) DEFAULT NULL,
  `login_method` varchar(20) DEFAULT 'email',
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `role`, `branch_id`, `is_active`, `created_at`, `last_login`, `updated_at`, `address`, `profile_picture`, `login_method`, `reset_token`, `token_expiry`) VALUES
(1, 'System Administrator', 'admin@bookit.com', '$2y$12$AYpgLYJaWCzNyNyL.2lbDeJcOi/TkaTfmOywIDemsndYSb2p8SYaC', NULL, 'admin', NULL, 1, '2026-01-21 01:45:52', NULL, '2026-01-21 01:45:52', NULL, NULL, 'email', NULL, NULL),
(3, 'Test User 1', 'jersonedanao2002@gmail.com', '$2y$10$tAebel8lIeI8nR9nyyslCOF6LlZpqMUflF28lG758Wsm.dElN5a6S', '09123456789', 'renter', NULL, 1, '2026-01-21 03:43:59', NULL, '2026-01-24 07:31:35', NULL, NULL, 'email', NULL, NULL),
(4, 'Test 1', 'onedummy53@gmail.com', '$2y$10$bWTCGqvDp0mZAY6bf8uinuLBFX7ydNAI6okAHSKQEKJdjIUx99JRi', '09123456789', '', 1, 0, '2026-01-21 04:29:27', NULL, '2026-01-31 14:54:00', NULL, NULL, 'email', NULL, NULL),
(5, 'Host 1', 'host1@bookit.com', '$2y$10$tmltabYVkk02FcKKuiMAXutUN3innt69rU9nom9x8Jtjy0gOkvZZO', '09123456789', 'manager', 1, 1, '2026-01-27 14:52:17', NULL, '2026-01-27 14:52:40', NULL, NULL, 'email', NULL, NULL),
(6, 'jackie lou pascual', 'jackieloupascual@gmail.com', '$2y$10$Vd.k0JLQJ4yW/QpdoBAY7.6rUsqljPdESD.7KfUUuUjC2npgMPHh.', '09054289264', '', NULL, 1, '2026-02-01 01:31:27', NULL, '2026-02-01 10:43:01', NULL, NULL, 'email', NULL, NULL),
(7, 'jackie lou pascual', 'paullexxusantonio@gmail.com', '$2y$10$gDwdt3RzyjmtPcdiO3VeRO5o2Wlk/sfLqVEBJWkqiJ1Y1V9v8YhVa', '09054289264', '', 2, 1, '2026-02-01 10:45:19', NULL, '2026-02-01 10:45:19', NULL, NULL, 'email', NULL, NULL),
(8, 'jackie lou pascual', 'paullexxusantonio20@gmail.com', '$2y$10$64n0KiFSYsJ6sTGQw1aycOoVihENSLYqvrdie9L4QSf4FxKFUZwny', '09054289264', 'admin', NULL, 1, '2026-02-01 10:45:50', NULL, '2026-02-22 02:23:36', NULL, NULL, 'email', NULL, NULL),
(9, 'Antonio, Paul Lexxus B.', 'p2235361@gmail.com', '$2y$10$fAZ.R874fvFM/re280G85uf9FRapmj9ZFxhgYo7zFxVLNnKx0B4wm', '1230456789', '', NULL, 1, '2026-02-22 02:25:58', NULL, '2026-03-07 07:17:03', NULL, NULL, 'email', NULL, NULL),
(10, 'jackie', 'jackiepascual@bookit.com', '$2y$10$ymI/EQN2g1UnX.naHJYJhuTKMDjymtdUAac5EdZsfqI4UN6U8DiO2', '123456789', 'manager', 1, 1, '2026-02-22 04:14:49', NULL, '2026-03-20 04:31:38', NULL, NULL, 'email', NULL, NULL),
(11, 'jackie', 'jackie@bookit.com', '$2y$10$gFE7a8xZ5NiGCsgd1CWOfu/4Ez1cjpIq.mB1LnB1v5eHfttg..vim', '789456123', '', 2, 1, '2026-02-22 04:15:32', NULL, '2026-02-22 04:15:32', NULL, NULL, 'email', NULL, NULL),
(12, 'paul', 'paul1234@bookit.com', '$2y$10$T9Oezy91p2mRVjT73miy7u6L80/koJ5H97IdNylqYaUWgUNMx/k4G', '88944556456', '', 3, 1, '2026-03-07 06:54:39', NULL, '2026-03-07 07:12:21', NULL, NULL, 'email', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_bank_accounts`
--

DROP TABLE IF EXISTS `user_bank_accounts`;
CREATE TABLE IF NOT EXISTS `user_bank_accounts` (
  `bank_account_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`bank_account_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_payment_methods`
--

DROP TABLE IF EXISTS `user_payment_methods`;
CREATE TABLE IF NOT EXISTS `user_payment_methods` (
  `method_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `method` varchar(50) NOT NULL,
  `account_details` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`method_id`),
  UNIQUE KEY `user_id` (`user_id`,`method`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `amenities`
--
ALTER TABLE `amenities`
  ADD CONSTRAINT `fk_amenity_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE;

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `fk_branch_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_res_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_res_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_review_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `fk_unit_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
