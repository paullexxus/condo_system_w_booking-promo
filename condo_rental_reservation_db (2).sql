-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 10, 2026 at 02:49 AM
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
-- Table structure for table `add_ons`
--

DROP TABLE IF EXISTS `add_ons`;
CREATE TABLE IF NOT EXISTS `add_ons` (
  `addon_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`addon_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `amenity_bookings`
--

INSERT INTO `amenity_bookings` (`booking_id`, `user_id`, `amenity_id`, `branch_id`, `booking_date`, `start_time`, `end_time`, `total_amount`, `status`, `created_at`) VALUES
(1, 10, 4, 3, '2025-11-15', '06:30:00', '18:30:00', 2160.00, 'pending', '2025-11-15 09:11:25');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action_type`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(1, 1, 'Admin Approved Application', 'host_application', 3, 'Notes: approve', '2026-04-09 19:14:20'),
(2, 1, 'Admin Approved Application', 'host_application', 4, 'Notes: approve', '2026-04-09 19:39:43');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `user_id` int NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `addon_total` decimal(10,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_addons`
--

DROP TABLE IF EXISTS `booking_addons`;
CREATE TABLE IF NOT EXISTS `booking_addons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `addon_id` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `host_id` int DEFAULT NULL,
  PRIMARY KEY (`branch_id`),
  KEY `manager_id` (`manager_id`),
  KEY `branches_host_id_index` (`host_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_name`, `address`, `city`, `contact_number`, `email`, `manager_id`, `is_active`, `created_at`, `latitude`, `longitude`, `host_id`) VALUES
(1, 'BookIT Makati', '123 Ayala Avenue', 'Makati', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, NULL),
(2, 'BookIT BGC', '456 BGC High Street', 'Taguig', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, 11),
(3, 'BookIT Ortigas', '789 Ortigas Center', 'Pasig', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, NULL),
(4, 'BookIT Caloocan', '199 Gen Malvar Ext, Bagong Barrio West, Caloocan, Metro Manila', 'caloocan', '123456789', 'jackiepascual@bookit.com', NULL, 1, '2026-03-22 03:07:02', NULL, NULL, NULL),
(5, 'BookIT manila', 'n/A', 'manila', '', '', 13, 1, '2026-03-22 06:05:25', NULL, NULL, NULL),
(6, 'BookIT malabon', '153 sanciangco street', 'malabon', '', '', NULL, 1, '2026-03-23 06:48:49', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `calendar_blackouts`
--

DROP TABLE IF EXISTS `calendar_blackouts`;
CREATE TABLE IF NOT EXISTS `calendar_blackouts` (
  `blackout_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`blackout_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `calendar_blackouts`
--

INSERT INTO `calendar_blackouts` (`blackout_id`, `unit_id`, `start_date`, `end_date`, `reason`, `created_at`) VALUES
(1, 1, '2026-04-01', '2026-04-07', NULL, '2026-04-06 14:41:45');

-- --------------------------------------------------------

--
-- Table structure for table `host_applications`
--

DROP TABLE IF EXISTS `host_applications`;
CREATE TABLE IF NOT EXISTS `host_applications` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `condo_name` varchar(150) DEFAULT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `complete_address` text,
  `social_media_link` varchar(255) DEFAULT NULL,
  `primary_id_type` varchar(100) DEFAULT NULL,
  `primary_id_number` varchar(100) DEFAULT NULL,
  `primary_id_path` varchar(255) DEFAULT NULL,
  `secondary_id_path` varchar(255) DEFAULT NULL,
  `selfie_with_id_path` varchar(255) DEFAULT NULL,
  `proof_of_ownership_path` varchar(255) DEFAULT NULL,
  `utility_bill_path` varchar(255) DEFAULT NULL,
  `payout_method` varchar(50) DEFAULT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `account_number` varchar(150) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`application_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `host_applications`
--

INSERT INTO `host_applications` (`application_id`, `user_id`, `condo_name`, `branch_name`, `complete_address`, `social_media_link`, `primary_id_type`, `primary_id_number`, `primary_id_path`, `secondary_id_path`, `selfie_with_id_path`, `proof_of_ownership_path`, `utility_bill_path`, `payout_method`, `account_name`, `account_number`, `bank_name`, `status`, `admin_notes`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 15, 'Vanessa Skies', 'Valenzuela City', '#1 Makipot St., Brgy. Bahoma, Valenzuela City', 'https://www.facebook.com/king.caliuag', 'Passport', '2022-08-00322', '../uploads/host_applications/1775757776_7351_valid_id1.jpg', '../uploads/host_applications/1775757776_7759_valid_id2.jpg', '../uploads/host_applications/1775757776_3015_selfie_with_id.jpg', '../uploads/host_applications/1775757776_4975_proof_ownership.jpg', '../uploads/host_applications/1775757776_9341_utility_bill.jpg', 'Bank Transfer', 'Vanessa Hudgens', '0987654321', 'Bangkoan', 'approved', 'kapangalan mo ex ko eh. YOU ARE NOT ACCEPTED. good day.', 1, '2026-04-10 02:03:49', '2026-04-09 18:02:56'),
(2, 16, 'Spidertel', 'Queens', 'Queens, New York, USA', 'https://www.facebook.com/king.caliuag', 'Passport', '2022-08-00322', '../uploads/host_applications/1775758932_6447_valid_id1.jpg', '../uploads/host_applications/1775758932_4938_valid_id2.jpg', '../uploads/host_applications/1775758932_6644_selfie_with_id.jpg', '../uploads/host_applications/1775758932_6310_proof_ownership.jpg', '../uploads/host_applications/1775758932_8788_utility_bill.jpg', 'Bank Transfer', 'Peter Parker', '0123456789', 'Bangkoan', 'rejected', 'bye', 1, '2026-04-10 02:23:00', '2026-04-09 18:22:12'),
(3, 17, 'sun', 'makati', '77 anonas bagong barrio baranagy 142\\r\\nal10', '', 'National ID', '123456789', '../uploads/host_applications/1775762032_2413_valid_id1.jpg', '../uploads/host_applications/1775762032_8499_valid_id2.jpg', '../uploads/host_applications/1775762032_6879_selfie_with_id.jpg', '../uploads/host_applications/1775762032_7249_proof_ownership.jpg', '../uploads/host_applications/1775762032_7349_utility_bill.jpg', 'Bank Transfer', 'paul lexxus antonio', '456789123', 'gcash', 'approved', 'approve', 1, '2026-04-10 03:14:20', '2026-04-09 19:13:52'),
(4, 18, 'heights', 'caloocan heights', 'ddaadaddada', 'https://www.facebook.com/paul.antonio.12345567897777777', 'National ID', '45667899', '../uploads/host_applications/1775763492_8813_valid_id1.jpg', '../uploads/host_applications/1775763492_9253_valid_id2.jpg', '../uploads/host_applications/1775763492_2761_selfie_with_id.jpg', '../uploads/host_applications/1775763492_2392_proof_ownership.jpg', '../uploads/host_applications/1775763492_3020_utility_bill.jpg', 'Bank Transfer', 'paul lexxus antonio', '74715899966', 'gcash', 'approved', 'approve', 1, '2026-04-10 03:39:43', '2026-04-09 19:38:12');

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_verifications`
--

INSERT INTO `otp_verifications` (`id`, `user_id`, `email`, `otp_code`, `purpose`, `expires_at`, `attempts`, `is_used`, `created_at`) VALUES
(16, 3, 'jersonedanao2002@gmail.com', '$2y$10$lYgXxHVleOBSHDpALkOefuCZ89fFFOQT0A5l9OCs5UQkwlTdJSVC6', 'reset_password', '2026-01-24 15:40:19', 0, 1, '2026-01-24 07:30:19'),
(17, 9, 'p2235361@gmail.com', '$2y$10$gKAk5YgVGm0n/fxN7rHwXehQ4nOuameqJuDyyv8nHsCiZWOYMtxMC', 'reset_password', '2026-03-07 14:56:04', 0, 1, '2026-03-07 06:46:04'),
(18, 7, 'paullexxusantonio@gmail.com', '$2y$10$0fPsqEjTG2teDs.vkGDamutnN2wcz/kBz2XSeuLPykdBfT46ShZ9u', 'reset_password', '2026-03-23 15:10:16', 0, 1, '2026-03-23 07:00:16');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pricing_rules`
--

DROP TABLE IF EXISTS `pricing_rules`;
CREATE TABLE IF NOT EXISTS `pricing_rules` (
  `rule_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `rule_type` enum('weekend','seasonal') NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `adjustment_type` enum('percentage','fixed') NOT NULL,
  `adjustment_value` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pricing_rules`
--

INSERT INTO `pricing_rules` (`rule_id`, `unit_id`, `rule_type`, `start_date`, `end_date`, `adjustment_type`, `adjustment_value`, `created_at`) VALUES
(1, 1, 'seasonal', '2026-04-09', '2026-04-11', 'percentage', 100.00, '2026-04-06 14:41:45'),
(2, 1, 'weekend', NULL, NULL, 'fixed', 500.00, '2026-04-06 14:41:45');

-- --------------------------------------------------------

--
-- Table structure for table `promos`
--

DROP TABLE IF EXISTS `promos`;
CREATE TABLE IF NOT EXISTS `promos` (
  `promo_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('percentage','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `usage_limit` int UNSIGNED DEFAULT NULL,
  `used_count` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`promo_id`),
  UNIQUE KEY `promos_code_unique` (`code`),
  KEY `promos_is_active_index` (`is_active`),
  KEY `promos_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promos`
--

INSERT INTO `promos` (`promo_id`, `code`, `type`, `value`, `expires_at`, `usage_limit`, `used_count`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SAVE50', 'fixed', 50.00, '2026-04-10 23:59:59', 1, 0, 1, 1, '2026-04-08 13:44:03', '2026-04-08 13:44:03'),
(3, 'SUMMER100', 'fixed', 100.00, '2026-04-30 23:59:59', 1, 0, 1, 1, '2026-04-08 13:56:09', '2026-04-08 13:56:09'),
(4, 'FIRSTBOOK20PERCENT', 'percentage', 20.00, '2026-04-30 23:59:59', 1, 0, 1, 1, '2026-04-08 14:19:20', '2026-04-08 14:19:20');

-- --------------------------------------------------------

--
-- Table structure for table `promo_codes`
--

DROP TABLE IF EXISTS `promo_codes`;
CREATE TABLE IF NOT EXISTS `promo_codes` (
  `promo_id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `usage_limit` int DEFAULT NULL,
  `used_count` int DEFAULT '0',
  `expiration_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`promo_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `promo_codes`
--

INSERT INTO `promo_codes` (`promo_id`, `code`, `discount_type`, `discount_value`, `usage_limit`, `used_count`, `expiration_date`, `created_at`) VALUES
(1, 'SAVE10', 'percentage', 10.00, NULL, 0, NULL, '2026-04-06 14:41:45'),
(2, 'SAVE100', 'fixed', 100.00, 1, 0, '2026-04-15', '2026-04-06 14:41:45');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `renter_rating` tinyint UNSIGNED DEFAULT NULL,
  `renter_feedback` text,
  `government_id_path` varchar(500) DEFAULT NULL,
  `promo_code` varchar(50) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`reservation_id`),
  KEY `user_id` (`user_id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `user_id`, `unit_id`, `branch_id`, `check_in_date`, `check_out_date`, `total_amount`, `security_deposit`, `status`, `payment_status`, `special_requests`, `created_at`, `updated_at`, `host_notes`, `admin_notes`, `cancellation_reason`, `approved_by`, `approved_at`, `renter_rating`, `renter_feedback`, `government_id_path`, `promo_code`, `discount_amount`) VALUES
(1, 9, 29, 6, '2026-03-25', '2026-03-26', 5000.00, 0.00, 'cancelled', 'pending', '', '2026-03-23 06:56:45', '2026-03-31 09:57:47', NULL, NULL, 'Host cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(2, 9, 29, 6, '2026-04-01', '2026-04-13', 60000.00, 0.00, 'confirmed', 'pending', '', '2026-03-31 10:04:15', '2026-04-02 05:34:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(3, 9, 30, 6, '2026-04-04', '2026-04-06', 8000.00, 0.00, 'pending', 'pending', '', '2026-04-02 10:27:35', '2026-04-02 10:27:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(4, 9, 30, 6, '2026-04-09', '2026-04-11', 8000.00, 0.00, 'pending', 'pending', '', '2026-04-08 16:58:56', '2026-04-08 16:58:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `building_name` varchar(255) DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address_hash` varchar(64) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `max_occupancy` int DEFAULT '2',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `bedrooms` int DEFAULT NULL,
  `host_id` int DEFAULT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `instant_booking` tinyint(1) DEFAULT '0',
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text,
  `sqm` decimal(10,2) DEFAULT NULL,
  `bed_type` varchar(50) DEFAULT NULL,
  `num_beds` int DEFAULT '1',
  `num_bathrooms` int DEFAULT '1',
  `pricing_type` enum('daily','monthly') DEFAULT 'monthly',
  PRIMARY KEY (`unit_id`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_id`, `branch_id`, `unit_number`, `unit_type`, `floor_number`, `monthly_rate`, `security_deposit`, `is_available`, `description`, `building_name`, `street_address`, `city`, `address_hash`, `latitude`, `longitude`, `max_occupancy`, `created_at`, `bedrooms`, `host_id`, `unit_name`, `instant_booking`, `approval_status`, `rejection_reason`, `sqm`, `bed_type`, `num_beds`, `num_bathrooms`, `pricing_type`) VALUES
(1, 1, 'A101', 'Studio', NULL, 15000.00, 0.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2026-01-21 01:45:52', NULL, NULL, NULL, 1, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(2, 2, 'B101', 'Studio', NULL, 18000.00, 0.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2026-01-21 01:45:52', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(3, 1, '142', '3 Bedrooms', 6, 2500.00, 15.00, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 4, '2026-03-10 11:32:02', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(4, 1, '142', '3 Bedrooms', 6, 2500.00, 15.00, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 4, '2026-03-10 11:35:19', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(5, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 11:50:57', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(6, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 11:51:10', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(7, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 11:51:56', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(8, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:09:18', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(9, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:13:09', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(10, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:13:29', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(11, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:13:55', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(12, 2, 'w202', '3 Bedrooms', 1, 422.00, 600.01, 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:29:43', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly'),
(29, 6, 'f20', '', NULL, 5000.00, 0.00, 1, '', NULL, '153 sanciangco street', 'malabon', NULL, 14.67087800, 120.96074500, 4, '2026-03-23 06:55:09', NULL, 10, 'penthouse', 0, 'approved', NULL, 80.00, 'double deck', 4, 10, 'monthly'),
(30, 6, 'suite10', '', NULL, 4000.00, 0.00, 1, '', NULL, '555 sanciangco street, catmon', 'malabon city', NULL, 14.66819300, 120.95915300, 11, '2026-04-02 10:25:32', NULL, 10, 'Unit 101', 0, 'approved', NULL, 40.00, 'double deck', 4, 2, 'monthly'),
(31, 6, 'm30', '', NULL, 2000.00, 0.00, 1, '', NULL, 'Balut', 'Malabón', NULL, 14.65740300, 120.95914500, 5, '2026-04-09 13:17:24', NULL, 10, 'unit302', 0, 'approved', NULL, 60.00, 'queen', 2, 2, 'daily');

-- --------------------------------------------------------

--
-- Table structure for table `unit_addons`
--

DROP TABLE IF EXISTS `unit_addons`;
CREATE TABLE IF NOT EXISTS `unit_addons` (
  `addon_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`addon_id`),
  KEY `unit_addons_unit_id_index` (`unit_id`),
  KEY `unit_addons_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unit_blackouts`
--

DROP TABLE IF EXISTS `unit_blackouts`;
CREATE TABLE IF NOT EXISTS `unit_blackouts` (
  `blackout_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` int UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`blackout_id`),
  KEY `unit_blackouts_unit_id_index` (`unit_id`),
  KEY `unit_blackouts_dates_index` (`unit_id`,`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `unit_blackouts`
--

INSERT INTO `unit_blackouts` (`blackout_id`, `unit_id`, `start_date`, `end_date`, `reason`, `created_at`) VALUES
(1, 31, '2026-04-09', '2026-04-25', 'maintenance', '2026-04-09 13:20:01');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `unit_images`
--

INSERT INTO `unit_images` (`image_id`, `unit_id`, `image_path`, `image_hash`, `room_type`, `upload_date`, `is_flagged`, `flag_reason`, `created_at`) VALUES
(37, 14, '../uploads/unit_images/unit_14_1774029159_4124.jpg', NULL, NULL, '2026-03-20 17:52:39', 0, NULL, '2026-03-20 17:52:39'),
(38, 14, '../uploads/unit_images/unit_14_1774029159_4847.jpg', NULL, NULL, '2026-03-20 17:52:39', 0, NULL, '2026-03-20 17:52:39'),
(39, 14, '../uploads/unit_images/unit_14_1774029159_4390.jpg', NULL, NULL, '2026-03-20 17:52:39', 0, NULL, '2026-03-20 17:52:39'),
(40, 14, '../uploads/unit_images/unit_14_1774029159_9036.jpg', NULL, NULL, '2026-03-20 17:52:39', 0, NULL, '2026-03-20 17:52:39'),
(41, 15, '../uploads/unit_images/unit_15_1774029256_4445.jpg', NULL, NULL, '2026-03-20 17:54:16', 0, NULL, '2026-03-20 17:54:16'),
(42, 15, '../uploads/unit_images/unit_15_1774029257_2754.jpg', NULL, NULL, '2026-03-20 17:54:17', 0, NULL, '2026-03-20 17:54:17'),
(43, 15, '../uploads/unit_images/unit_15_1774029257_6479.jpg', NULL, NULL, '2026-03-20 17:54:17', 0, NULL, '2026-03-20 17:54:17'),
(44, 15, '../uploads/unit_images/unit_15_1774029257_5769.jpg', NULL, NULL, '2026-03-20 17:54:17', 0, NULL, '2026-03-20 17:54:17'),
(45, 16, '../uploads/unit_images/unit_16_1774029900_5143.jpg', NULL, NULL, '2026-03-20 18:05:00', 0, NULL, '2026-03-20 18:05:00'),
(46, 16, '../uploads/unit_images/unit_16_1774029900_2943.jpg', NULL, NULL, '2026-03-20 18:05:00', 0, NULL, '2026-03-20 18:05:00'),
(47, 16, '../uploads/unit_images/unit_16_1774029900_8134.jpg', NULL, NULL, '2026-03-20 18:05:00', 0, NULL, '2026-03-20 18:05:00'),
(48, 16, '../uploads/unit_images/unit_16_1774029900_7652.jpg', NULL, NULL, '2026-03-20 18:05:00', 0, NULL, '2026-03-20 18:05:00'),
(49, 17, '../uploads/unit_images/unit_17_1774030067_2283.jpg', NULL, NULL, '2026-03-20 18:07:47', 0, NULL, '2026-03-20 18:07:47'),
(50, 17, '../uploads/unit_images/unit_17_1774030067_6551.jpg', NULL, NULL, '2026-03-20 18:07:47', 0, NULL, '2026-03-20 18:07:47'),
(51, 17, '../uploads/unit_images/unit_17_1774030067_4295.jpg', NULL, NULL, '2026-03-20 18:07:47', 0, NULL, '2026-03-20 18:07:47'),
(52, 17, '../uploads/unit_images/unit_17_1774030067_8855.jpg', NULL, NULL, '2026-03-20 18:07:47', 0, NULL, '2026-03-20 18:07:47'),
(53, 18, '../uploads/unit_images/unit_18_1774030238_2638.jpg', NULL, NULL, '2026-03-20 18:10:38', 0, NULL, '2026-03-20 18:10:38'),
(54, 18, '../uploads/unit_images/unit_18_1774030238_9375.jpg', NULL, NULL, '2026-03-20 18:10:38', 0, NULL, '2026-03-20 18:10:38'),
(55, 18, '../uploads/unit_images/unit_18_1774030238_3368.jpg', NULL, NULL, '2026-03-20 18:10:38', 0, NULL, '2026-03-20 18:10:38'),
(56, 18, '../uploads/unit_images/unit_18_1774030238_8103.jpg', NULL, NULL, '2026-03-20 18:10:38', 0, NULL, '2026-03-20 18:10:38'),
(57, 19, '../uploads/unit_images/unit_19_1774031226_4707.jpg', NULL, NULL, '2026-03-20 18:27:06', 0, NULL, '2026-03-20 18:27:06'),
(58, 19, '../uploads/unit_images/unit_19_1774031226_4872.jpg', NULL, NULL, '2026-03-20 18:27:06', 0, NULL, '2026-03-20 18:27:06'),
(59, 19, '../uploads/unit_images/unit_19_1774031226_4024.jpg', NULL, NULL, '2026-03-20 18:27:06', 0, NULL, '2026-03-20 18:27:06'),
(60, 19, '../uploads/unit_images/unit_19_1774031226_2322.jpg', NULL, NULL, '2026-03-20 18:27:06', 0, NULL, '2026-03-20 18:27:06'),
(61, 20, '../uploads/unit_images/unit_20_1774031466_8633.jpg', NULL, NULL, '2026-03-20 18:31:06', 0, NULL, '2026-03-20 18:31:06'),
(62, 20, '../uploads/unit_images/unit_20_1774031466_9996.jpg', NULL, NULL, '2026-03-20 18:31:06', 0, NULL, '2026-03-20 18:31:06'),
(63, 20, '../uploads/unit_images/unit_20_1774031466_1746.jpg', NULL, NULL, '2026-03-20 18:31:06', 0, NULL, '2026-03-20 18:31:06'),
(64, 20, '../uploads/unit_images/unit_20_1774031466_1102.jpg', NULL, NULL, '2026-03-20 18:31:06', 0, NULL, '2026-03-20 18:31:06'),
(81, 21, '../uploads/unit_images/unit_21_1774102614_3081.jpg', NULL, NULL, '2026-03-21 14:16:54', 0, NULL, '2026-03-21 14:16:54'),
(82, 21, '../uploads/unit_images/unit_21_1774102614_2399.jpg', NULL, NULL, '2026-03-21 14:16:54', 0, NULL, '2026-03-21 14:16:54'),
(83, 21, '../uploads/unit_images/unit_21_1774102614_7854.jpg', NULL, NULL, '2026-03-21 14:16:54', 0, NULL, '2026-03-21 14:16:54'),
(84, 21, '../uploads/unit_images/unit_21_1774102614_7362.jpg', NULL, NULL, '2026-03-21 14:16:54', 0, NULL, '2026-03-21 14:16:54'),
(85, 26, '../uploads/unit_images/unit_26_1774103580_9509.jpg', NULL, NULL, '2026-03-21 14:33:00', 0, NULL, '2026-03-21 14:33:00'),
(86, 26, '../uploads/unit_images/unit_26_1774103580_3062.jpg', NULL, NULL, '2026-03-21 14:33:00', 0, NULL, '2026-03-21 14:33:00'),
(87, 26, '../uploads/unit_images/unit_26_1774103580_3807.jpg', NULL, NULL, '2026-03-21 14:33:00', 0, NULL, '2026-03-21 14:33:00'),
(88, 26, '../uploads/unit_images/unit_26_1774103580_9734.jpg', NULL, NULL, '2026-03-21 14:33:00', 0, NULL, '2026-03-21 14:33:00'),
(89, 27, '../uploads/unit_images/unit_27_1774150967_2225.jpg', NULL, NULL, '2026-03-22 03:42:47', 0, NULL, '2026-03-22 03:42:47'),
(90, 27, '../uploads/unit_images/unit_27_1774150967_8862.jpg', NULL, NULL, '2026-03-22 03:42:47', 0, NULL, '2026-03-22 03:42:47'),
(91, 27, '../uploads/unit_images/unit_27_1774150967_4557.jpg', NULL, NULL, '2026-03-22 03:42:47', 0, NULL, '2026-03-22 03:42:47'),
(92, 27, '../uploads/unit_images/unit_27_1774150967_3404.jpg', NULL, NULL, '2026-03-22 03:42:47', 0, NULL, '2026-03-22 03:42:47'),
(93, 28, '../uploads/unit_images/unit_28_1774153200_9507.jpg', NULL, NULL, '2026-03-22 04:20:00', 0, NULL, '2026-03-22 04:20:00'),
(94, 28, '../uploads/unit_images/unit_28_1774153200_3146.jpg', NULL, NULL, '2026-03-22 04:20:00', 0, NULL, '2026-03-22 04:20:00'),
(95, 28, '../uploads/unit_images/unit_28_1774153200_3255.jpg', NULL, NULL, '2026-03-22 04:20:00', 0, NULL, '2026-03-22 04:20:00'),
(96, 28, '../uploads/unit_images/unit_28_1774153200_8914.jpg', NULL, NULL, '2026-03-22 04:20:00', 0, NULL, '2026-03-22 04:20:00'),
(97, 29, '../uploads/unit_images/unit_29_1774248937_6964.jpg', NULL, NULL, '2026-03-23 06:55:37', 0, NULL, '2026-03-23 06:55:37'),
(98, 29, '../uploads/unit_images/unit_29_1774248937_5217.jpg', NULL, NULL, '2026-03-23 06:55:37', 0, NULL, '2026-03-23 06:55:37'),
(99, 29, '../uploads/unit_images/unit_29_1774248937_4577.jpg', NULL, NULL, '2026-03-23 06:55:37', 0, NULL, '2026-03-23 06:55:37'),
(100, 29, '../uploads/unit_images/unit_29_1774248937_8853.jpg', NULL, NULL, '2026-03-23 06:55:37', 0, NULL, '2026-03-23 06:55:37'),
(101, 30, '../uploads/unit_images/unit_30_1775125532_7076.jpg', NULL, NULL, '2026-04-02 10:25:32', 0, NULL, '2026-04-02 10:25:32'),
(102, 30, '../uploads/unit_images/unit_30_1775125532_7335.jpg', NULL, NULL, '2026-04-02 10:25:32', 0, NULL, '2026-04-02 10:25:32'),
(103, 30, '../uploads/unit_images/unit_30_1775125532_7838.jpg', NULL, NULL, '2026-04-02 10:25:32', 0, NULL, '2026-04-02 10:25:32'),
(104, 30, '../uploads/unit_images/unit_30_1775125532_7100.jpg', NULL, NULL, '2026-04-02 10:25:32', 0, NULL, '2026-04-02 10:25:32'),
(105, 31, '../uploads/unit_images/unit_31_1775740683_3666.jpg', NULL, NULL, '2026-04-09 13:18:03', 0, NULL, '2026-04-09 13:18:03'),
(106, 31, '../uploads/unit_images/unit_31_1775740683_4268.jpg', NULL, NULL, '2026-04-09 13:18:03', 0, NULL, '2026-04-09 13:18:03'),
(107, 31, '../uploads/unit_images/unit_31_1775740683_7416.jpg', NULL, NULL, '2026-04-09 13:18:03', 0, NULL, '2026-04-09 13:18:03'),
(108, 31, '../uploads/unit_images/unit_31_1775740683_5148.jpg', NULL, NULL, '2026-04-09 13:18:03', 0, NULL, '2026-04-09 13:18:03');

-- --------------------------------------------------------

--
-- Table structure for table `unit_pricing_rules`
--

DROP TABLE IF EXISTS `unit_pricing_rules`;
CREATE TABLE IF NOT EXISTS `unit_pricing_rules` (
  `rule_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` int UNSIGNED NOT NULL,
  `rule_type` enum('weekend','date_range') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_type` enum('fixed','percentage') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rule_id`),
  KEY `unit_pricing_rules_unit_id_index` (`unit_id`),
  KEY `unit_pricing_rules_type_index` (`rule_type`),
  KEY `unit_pricing_rules_active_index` (`is_active`),
  KEY `unit_pricing_rules_dates_index` (`unit_id`,`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `unit_pricing_rules`
--

INSERT INTO `unit_pricing_rules` (`rule_id`, `unit_id`, `rule_type`, `adjustment_type`, `adjustment_value`, `start_date`, `end_date`, `is_active`, `created_at`) VALUES
(1, 30, 'date_range', 'fixed', 600.00, '2026-04-09', '2026-04-11', 1, '2026-04-08 17:14:40'),
(2, 31, 'date_range', 'fixed', 500.00, '2026-04-26', '2026-04-30', 1, '2026-04-09 13:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `unit_pricing_settings`
--

DROP TABLE IF EXISTS `unit_pricing_settings`;
CREATE TABLE IF NOT EXISTS `unit_pricing_settings` (
  `unit_id` int UNSIGNED NOT NULL,
  `base_nightly_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `role` enum('admin','host','renter','manager') DEFAULT 'renter',
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
  `verification_status` enum('none','pending','verified','rejected') DEFAULT 'none',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `role`, `branch_id`, `is_active`, `created_at`, `last_login`, `updated_at`, `address`, `profile_picture`, `login_method`, `reset_token`, `token_expiry`, `verification_status`) VALUES
(1, 'System Administrator', 'admin@bookit.com', '$2y$12$AYpgLYJaWCzNyNyL.2lbDeJcOi/TkaTfmOywIDemsndYSb2p8SYaC', NULL, 'admin', NULL, 1, '2026-01-21 01:45:52', NULL, '2026-01-21 01:45:52', NULL, NULL, 'email', NULL, NULL, 'none'),
(3, 'Test User 1', 'jersonedanao2002@gmail.com', '$2y$10$tAebel8lIeI8nR9nyyslCOF6LlZpqMUflF28lG758Wsm.dElN5a6S', '09123456789', 'renter', NULL, 1, '2026-01-21 03:43:59', NULL, '2026-01-24 07:31:35', NULL, NULL, 'email', NULL, NULL, 'none'),
(4, 'Test 1', 'onedummy53@gmail.com', '$2y$10$bWTCGqvDp0mZAY6bf8uinuLBFX7ydNAI6okAHSKQEKJdjIUx99JRi', '09123456789', '', 1, 0, '2026-01-21 04:29:27', NULL, '2026-01-31 14:54:00', NULL, NULL, 'email', NULL, NULL, 'none'),
(5, 'Host 1', 'host1@bookit.com', '$2y$10$tmltabYVkk02FcKKuiMAXutUN3innt69rU9nom9x8Jtjy0gOkvZZO', '09123456789', 'host', 1, 1, '2026-01-27 14:52:17', NULL, '2026-03-22 10:10:42', NULL, NULL, 'email', NULL, NULL, 'none'),
(6, 'jackie lou pascual', 'jackieloupascual@gmail.com', '$2y$10$Vd.k0JLQJ4yW/QpdoBAY7.6rUsqljPdESD.7KfUUuUjC2npgMPHh.', '09054289264', '', NULL, 1, '2026-02-01 01:31:27', NULL, '2026-02-01 10:43:01', NULL, NULL, 'email', NULL, NULL, 'none'),
(7, 'jackie lou pascual', 'paullexxusantonio@gmail.com', '$2y$10$fbAw5Z0N1L60gTrG52DZI.dwcQS.OkXrnKDpmj78tAZCYNEXX2Q5W', '09054289264', '', 2, 1, '2026-02-01 10:45:19', NULL, '2026-03-23 07:03:40', NULL, NULL, 'email', NULL, NULL, 'none'),
(8, 'jackie lou pascual', 'paullexxusantonio20@gmail.com', '$2y$10$64n0KiFSYsJ6sTGQw1aycOoVihENSLYqvrdie9L4QSf4FxKFUZwny', '09054289264', 'admin', NULL, 1, '2026-02-01 10:45:50', NULL, '2026-02-22 02:23:36', NULL, NULL, 'email', NULL, NULL, 'none'),
(9, 'Antonio, Paul Lexxus B.', 'p2235361@gmail.com', '$2y$10$fAZ.R874fvFM/re280G85uf9FRapmj9ZFxhgYo7zFxVLNnKx0B4wm', '1230456789', 'renter', NULL, 1, '2026-02-22 02:25:58', NULL, '2026-03-22 03:45:18', NULL, NULL, 'email', NULL, NULL, 'none'),
(10, 'jackie', 'jackiepascual@bookit.com', '$2y$10$ymI/EQN2g1UnX.naHJYJhuTKMDjymtdUAac5EdZsfqI4UN6U8DiO2', '123456789', 'host', 6, 1, '2026-02-22 04:14:49', NULL, '2026-03-23 06:53:41', NULL, NULL, 'email', NULL, NULL, 'none'),
(11, 'jackie', 'jackie@bookit.com', '$2y$10$gFE7a8xZ5NiGCsgd1CWOfu/4Ez1cjpIq.mB1LnB1v5eHfttg..vim', '789456123', 'host', 2, 1, '2026-02-22 04:15:32', NULL, '2026-03-22 03:56:39', NULL, NULL, 'email', NULL, NULL, 'none'),
(12, 'paul', 'paul1234@bookit.com', '$2y$10$T9Oezy91p2mRVjT73miy7u6L80/koJ5H97IdNylqYaUWgUNMx/k4G', '88944556456', 'host', 3, 1, '2026-03-07 06:54:39', NULL, '2026-03-22 04:03:20', NULL, NULL, 'email', NULL, NULL, 'none'),
(13, 'kunwari lang', 'adminhost@bookit.com', '$2y$10$TMg3x.zSqVLr8axCTIdUMuhv5lEmej6J4WUcxTicisG6M5COLx6T.', '0908543307762', 'host', 5, 1, '2026-03-22 06:08:56', NULL, '2026-03-22 10:10:55', NULL, NULL, 'email', NULL, NULL, 'none'),
(14, 'Caps Of all trades', 'capsofalltrades@gmail.com', '$2y$10$L60Z3IlGB3nqfiMDbW0oaOyWNvmCIKVW1HWNvjvlfU7PYKsUpOM3W', '09123456789', 'renter', NULL, 1, '2026-04-08 17:16:01', NULL, '2026-04-08 17:16:01', NULL, NULL, 'email', NULL, NULL, 'none'),
(15, 'Vanessa Hudges', 'vanessahudges@gmail.com', '$2y$10$ypu0GnWV5Yb1fgFDQBN87OwFq9UW60T48qs7pqqcWMQQB0FzKftY.', '09054289261', 'host', NULL, 1, '2026-04-09 18:02:56', NULL, '2026-04-09 18:03:49', NULL, NULL, 'email', NULL, NULL, 'none'),
(16, 'Peter Benjamin Parker', 'peterparker@gmail.com', '$2y$10$n.k9Nn.XAyBw99HOgHHA1.20fb1fIDhjhCA2mUaVoVelg1GLGsHl.', '09876543210', 'renter', NULL, 1, '2026-04-09 18:22:12', NULL, '2026-04-09 18:22:12', NULL, NULL, 'email', NULL, NULL, 'none'),
(17, 'Antonio, Paul Lexxus B.', 'pao@gmail.com', '$2y$10$7ld2f6.Fog.yZH0vMsmcUevqX.DPNmduTNPNpe8G0HBmaAscC108u', '89456', 'host', NULL, 1, '2026-04-09 19:13:52', NULL, '2026-04-09 19:14:20', NULL, NULL, 'email', NULL, NULL, 'none'),
(18, 'Antonio, Paul Lexxus B.', 'yg@gmail.com', '$2y$10$FtKrd13hZtEFurMdqi4EGOowxesvioYFKTC9cZ2iXrDN3Z2z4n7eC', '09054289264', 'host', NULL, 1, '2026-04-09 19:38:12', NULL, '2026-04-09 19:39:43', NULL, NULL, 'email', NULL, NULL, 'none');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
