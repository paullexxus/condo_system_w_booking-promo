-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 20, 2026 at 01:16 AM
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
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_paid` tinyint(1) DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `is_paid`, `price`) VALUES
(1, 'WiFi', 0, 0.00),
(2, 'Air Conditioning', 0, 0.00),
(3, 'Swimming Pool', 0, 0.00),
(4, 'Parking', 0, 0.00),
(5, 'TV', 0, 0.00);

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
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `from_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action_type`, `entity_type`, `entity_id`, `from_status`, `to_status`, `details`, `created_at`) VALUES
(1, 1, 'Admin Approved Application', 'host_application', 3, NULL, NULL, 'Notes: approve', '2026-04-09 19:14:20'),
(2, 1, 'Admin Approved Application', 'host_application', 4, NULL, NULL, 'Notes: approve', '2026-04-09 19:39:43'),
(3, 1, 'Admin Re-opened Application', 'host_application', 2, NULL, NULL, 'Notes: bye', '2026-04-10 02:55:11'),
(4, 1, 'Admin Rejected Application', 'host_application', 2, NULL, NULL, 'Reason: bye', '2026-04-10 02:55:18'),
(5, 1, 'Admin Rejected Application', 'host_application', 2, NULL, NULL, 'Reason: bye', '2026-04-10 02:55:26'),
(6, 1, 'Admin Rejected Application', 'host_application', 2, NULL, NULL, 'Reason: bye', '2026-04-10 02:55:30'),
(7, 1, 'Admin Rejected Application', 'host_application', 2, NULL, NULL, 'Reason: bye', '2026-04-10 02:55:38'),
(8, 1, 'Admin Approved Rejected Application', 'host_application', 2, NULL, NULL, 'Notes: bye', '2026-04-10 02:56:58'),
(9, 1, 'Admin Approved Application', 'host_application', 2, NULL, NULL, 'Notes: bye', '2026-04-10 02:57:09'),
(10, 1, 'Admin Approved Application', 'host_application', 2, NULL, NULL, 'Notes: bye', '2026-04-10 02:57:20'),
(11, 1, 'Admin Approved Application', 'host_application', 2, NULL, NULL, 'Notes: bye', '2026-04-10 02:57:48'),
(12, 1, 'Admin Approved Application', 'host_application', 2, NULL, NULL, 'Notes: bye', '2026-04-10 02:58:01'),
(13, 1, 'Admin Rejected Application', 'host_application', 5, NULL, NULL, 'Reason: pasensya ka na masyado ka nang mayaman at mayabang, iron man.', '2026-04-10 03:03:43'),
(14, 1, 'Admin Approved Application', 'host_application', 6, NULL, NULL, 'Notes: approve, welcome to our system', '2026-04-11 16:03:11'),
(15, 1, 'Admin Rejected Application', 'host_application', 7, NULL, NULL, 'Reason: im sorry but im busted', '2026-04-13 06:12:22'),
(16, 1, 'Admin Rejected Application', 'host_application', 7, NULL, NULL, 'Reason: im sorry but im busted', '2026-04-13 06:12:28'),
(17, 1, 'Admin Re-opened Application', 'host_application', 7, NULL, NULL, 'Notes: im sorry but im busted', '2026-04-13 06:21:15'),
(18, 1, 'Admin Approved Application', 'host_application', 7, NULL, NULL, 'Notes: approve', '2026-04-13 06:21:39'),
(19, 1, 'approve_unit', 'unit', 33, NULL, NULL, '[UNIT-33] APPROVE | PENDING -> APPROVED | N/A', '2026-04-16 11:57:31'),
(20, 1, 'reject_host', 'host_application', 8, NULL, NULL, '[HOSTAPP-8] REJECT | PENDING -> REJECTED | Reason: panget mo', '2026-04-16 13:30:21'),
(21, 1, 'reject_host', 'host_application', 8, NULL, NULL, '[HOSTAPP-8] REJECT | REJECTED -> REJECTED | Reason: panget mo', '2026-04-16 13:30:25'),
(22, 1, 'approve_host', 'host_application', 9, NULL, NULL, '[HOSTAPP-9] APPROVE | PENDING -> APPROVED | Notes: you are welcome to our system, king of the pirates!', '2026-04-17 07:06:50'),
(23, 1, 'approve_unit', 'unit', 34, NULL, NULL, '[UNIT-34] APPROVE | PENDING -> APPROVED | N/A', '2026-04-17 07:13:04'),
(24, NULL, 'abuse_lockout', 'search', 0, NULL, NULL, 'User/IP ip_ locked out from search for 10 mins.', '2026-04-19 02:18:55'),
(25, 10, 'Add Unit', 'unit', 35, NULL, NULL, 'Host added new unit: unit506. Status set to pending.', '2026-04-19 11:52:37'),
(26, 1, 'approve_unit', 'unit', 35, NULL, NULL, '[UNIT-35] APPROVE | PENDING -> APPROVED | N/A', '2026-04-19 11:53:05'),
(27, 25, 'User Registration', 'user', 25, NULL, NULL, 'User registered and accepted Terms & Conditions. IP: ::1', '2026-04-19 12:22:07'),
(28, 10, 'Edit Unit', 'unit', 35, NULL, NULL, 'Host updated unit: unit506. Status reset to pending.', '2026-04-19 14:01:17'),
(29, 10, 'Edit Unit', 'unit', 35, NULL, NULL, 'Host updated unit: unit506. Status reset to pending.', '2026-04-19 16:10:19');

--
-- Triggers `audit_logs`
--
DROP TRIGGER IF EXISTS `audit_logs_protect_delete`;
DELIMITER $$
CREATE TRIGGER `audit_logs_protect_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DATA INTEGRITY VIOLATION: Audit logs are immutable and cannot be deleted.';
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `audit_logs_protect_update`;
DELIMITER $$
CREATE TRIGGER `audit_logs_protect_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DATA INTEGRITY VIOLATION: Audit logs are immutable and cannot be updated.';
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `before_audit_delete`;
DELIMITER $$
CREATE TRIGGER `before_audit_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW BEGIN 
               SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable and cannot be deleted.'; 
             END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `before_audit_update`;
DELIMITER $$
CREATE TRIGGER `before_audit_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW BEGIN 
               SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable and cannot be updated.'; 
             END
$$
DELIMITER ;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_price` decimal(10,2) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `admin_note` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_res_addon` (`booking_id`,`addon_id`)
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_name`, `address`, `city`, `contact_number`, `email`, `manager_id`, `is_active`, `created_at`, `latitude`, `longitude`, `host_id`) VALUES
(1, 'BookIT Makati', '123 Ayala Avenue', 'Makati', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, NULL),
(2, 'BookIT BGC', '456 BGC High Street', 'Taguig', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, 11),
(3, 'BookIT Ortigas', '789 Ortigas Center', 'Pasig', NULL, NULL, NULL, 1, '2026-01-21 01:45:52', NULL, NULL, NULL),
(4, 'BookIT Caloocan', '199 Gen Malvar Ext, Bagong Barrio West, Caloocan, Metro Manila', 'caloocan', '123456789', 'jackiepascual@bookit.com', NULL, 1, '2026-03-22 03:07:02', NULL, NULL, NULL),
(5, 'BookIT manila', 'n/A', 'manila', '', '', 13, 1, '2026-03-22 06:05:25', NULL, NULL, NULL),
(6, 'BookIT malabon', '153 sanciangco street', 'malabon', '', '', NULL, 1, '2026-03-23 06:48:49', NULL, NULL, NULL),
(7, 'bulacan', 'Yakal Street\r\n303', 'Quezon City', '', 'monkeydluffy@gmail.com', NULL, 1, '2026-04-19 16:53:40', NULL, NULL, 24);

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
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
CREATE TABLE IF NOT EXISTS `conversations` (
  `conversation_id` int NOT NULL AUTO_INCREMENT,
  `user_one` int NOT NULL,
  `user_two` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `geocoding_cache`
--

DROP TABLE IF EXISTS `geocoding_cache`;
CREATE TABLE IF NOT EXISTS `geocoding_cache` (
  `id` int NOT NULL AUTO_INCREMENT,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'nominatim',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_address_hash` (`address_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `host_applications`
--

INSERT INTO `host_applications` (`application_id`, `user_id`, `condo_name`, `branch_name`, `complete_address`, `social_media_link`, `primary_id_type`, `primary_id_number`, `primary_id_path`, `secondary_id_path`, `selfie_with_id_path`, `proof_of_ownership_path`, `utility_bill_path`, `payout_method`, `account_name`, `account_number`, `bank_name`, `status`, `admin_notes`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 15, 'Vanessa Skies', 'Valenzuela City', '#1 Makipot St., Brgy. Bahoma, Valenzuela City', 'https://www.facebook.com/king.caliuag', 'Passport', '2022-08-00322', '../uploads/host_applications/1775757776_7351_valid_id1.jpg', '../uploads/host_applications/1775757776_7759_valid_id2.jpg', '../uploads/host_applications/1775757776_3015_selfie_with_id.jpg', '../uploads/host_applications/1775757776_4975_proof_ownership.jpg', '../uploads/host_applications/1775757776_9341_utility_bill.jpg', 'Bank Transfer', 'Vanessa Hudgens', '0987654321', 'Bangkoan', 'approved', 'kapangalan mo ex ko eh. YOU ARE NOT ACCEPTED. good day.', 1, '2026-04-10 02:03:49', '2026-04-09 18:02:56'),
(2, 16, 'Spidertel', 'Queens', 'Queens, New York, USA', 'https://www.facebook.com/king.caliuag', 'Passport', '2022-08-00322', '../uploads/host_applications/1775758932_6447_valid_id1.jpg', '../uploads/host_applications/1775758932_4938_valid_id2.jpg', '../uploads/host_applications/1775758932_6644_selfie_with_id.jpg', '../uploads/host_applications/1775758932_6310_proof_ownership.jpg', '../uploads/host_applications/1775758932_8788_utility_bill.jpg', 'Bank Transfer', 'Peter Parker', '0123456789', 'Bangkoan', 'approved', 'bye', 1, '2026-04-10 10:58:01', '2026-04-09 18:22:12'),
(3, 17, 'sun', 'makati', '77 anonas bagong barrio baranagy 142\\r\\nal10', '', 'National ID', '123456789', '../uploads/host_applications/1775762032_2413_valid_id1.jpg', '../uploads/host_applications/1775762032_8499_valid_id2.jpg', '../uploads/host_applications/1775762032_6879_selfie_with_id.jpg', '../uploads/host_applications/1775762032_7249_proof_ownership.jpg', '../uploads/host_applications/1775762032_7349_utility_bill.jpg', 'Bank Transfer', 'paul lexxus antonio', '456789123', 'gcash', 'approved', 'approve', 1, '2026-04-10 03:14:20', '2026-04-09 19:13:52'),
(4, 18, 'heights', 'caloocan heights', 'ddaadaddada', 'https://www.facebook.com/paul.antonio.12345567897777777', 'National ID', '45667899', '../uploads/host_applications/1775763492_8813_valid_id1.jpg', '../uploads/host_applications/1775763492_9253_valid_id2.jpg', '../uploads/host_applications/1775763492_2761_selfie_with_id.jpg', '../uploads/host_applications/1775763492_2392_proof_ownership.jpg', '../uploads/host_applications/1775763492_3020_utility_bill.jpg', 'Bank Transfer', 'paul lexxus antonio', '74715899966', 'gcash', 'approved', 'approve', 1, '2026-04-10 03:39:43', '2026-04-09 19:38:12'),
(5, 19, 'Irontel', 'Malibu', 'Malibu road, New York', 'https://www.facebook.com/paul.antonio.12345567897777777', 'Driver License', '2022-07-00322', '../uploads/host_applications/1775790095_1915_valid_id1.jpg', '../uploads/host_applications/1775790095_5615_valid_id2.jpg', '../uploads/host_applications/1775790095_6049_selfie_with_id.jpg', '../uploads/host_applications/1775790095_1316_proof_ownership.jpg', '../uploads/host_applications/1775790095_8318_utility_bill.jpg', 'Bank Transfer', 'Anthony Stark', '2022-07-00322', 'Bruce Wayne Banks', 'rejected', 'pasensya ka na masyado ka nang mayaman at mayabang, iron man.', 1, '2026-04-10 11:03:43', '2026-04-10 03:01:35'),
(6, 20, 'condo', 'boracay', '77 anonas bagong barrio baranagy 142\\r\\nal10', 'https://www.facebook.com/paul.antonio.12345567897777777', 'National ID', '1111111111', '../uploads/host_applications/1775923215_5189_valid_id1.jpg', '../uploads/host_applications/1775923215_6406_valid_id2.jpg', '../uploads/host_applications/1775923215_8550_selfie_with_id.jpg', '../uploads/host_applications/1775923215_7858_proof_ownership.jpg', '../uploads/host_applications/1775923215_8499_utility_bill.jpg', 'Maya', 'paul lexxus antonio', '74715899966', 'gcash', 'approved', 'approve, welcome to our system', 1, '2026-04-12 00:03:11', '2026-04-11 16:00:15'),
(7, 21, 'Cabajar Hotels', 'Pampanga', '77 anonas bagong barrio baranagy 142\\r\\nal10', 'https://www.facebook.com/paul.antonio.12345567897777777', 'Passport', '45667899', '../uploads/host_applications/1776060573_6099_valid_id1.jpg', '../uploads/host_applications/1776060573_7342_valid_id2.jpg', '', '../uploads/host_applications/1776060573_7472_proof_ownership.jpg', '../uploads/host_applications/1776060573_5597_utility_bill.jpg', 'Bank Transfer', 'Leonardo Cabajar', '74715899966', 'Bangkoan', 'approved', 'approve', 1, '2026-04-13 14:21:39', '2026-04-13 06:09:33'),
(8, 22, 'MarkHerras Condo City', 'Makati', '159 Makati Sanciangco street catmon', '', 'Passport', '122323232', '../uploads/host_applications/1776346158_6709_valid_id1.jpg', '../uploads/host_applications/1776346158_5858_valid_id2.jpg', '', '../uploads/host_applications/1776346158_2890_proof_ownership.jpg', '../uploads/host_applications/1776346158_8211_utility_bill.jpg', 'GCash', 'mark herras', '09090909', '', 'rejected', 'panget mo', 1, '2026-04-16 21:30:25', '2026-04-16 13:29:18'),
(9, 24, 'Mugiwara Homes', 'Antonio', '32 Cabajar St., Brgy. Caliuag, Antonio City', '', 'Passport', '2022-07-00321', '../uploads/host_applications/1776409519_1845_valid_id1.jpg', '../uploads/host_applications/1776409519_8893_valid_id2.jpg', '../uploads/host_applications/1776409519_9545_selfie_with_id.jpg', '../uploads/host_applications/1776409519_4676_proof_ownership.jpg', '../uploads/host_applications/1776409519_9579_utility_bill.jpg', 'GCash', 'Monkey D. Luffy', '56', 'GWash', 'approved', 'you are welcome to our system, king of the pirates!', 1, '2026-04-17 15:06:50', '2026-04-17 07:05:19');

--
-- Triggers `host_applications`
--
DROP TRIGGER IF EXISTS `trg_host_applications_audit`;
DELIMITER $$
CREATE TRIGGER `trg_host_applications_audit` AFTER UPDATE ON `host_applications` FOR EACH ROW BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NEW.reviewed_by, 'application_status_change', 'host_application', NEW.application_id, OLD.status, NEW.status, CONCAT('Host application status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `host_earnings`
--

DROP TABLE IF EXISTS `host_earnings`;
CREATE TABLE IF NOT EXISTS `host_earnings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `host_id` int NOT NULL,
  `reservation_id` int NOT NULL,
  `total_booking_amount` decimal(10,2) NOT NULL,
  `platform_fee` decimal(10,2) NOT NULL,
  `host_amount` decimal(10,2) NOT NULL,
  `status` enum('pending_review','pending_release','available','paid_out','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_review',
  `available_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `host_id` (`host_id`),
  KEY `reservation_id` (`reservation_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `host_earnings`
--
DROP TRIGGER IF EXISTS `prevent_earnings_delete`;
DELIMITER $$
CREATE TRIGGER `prevent_earnings_delete` BEFORE DELETE ON `host_earnings` FOR EACH ROW BEGIN
            IF OLD.status = 'paid_out' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCIAL INTEGRITY ERROR: Cannot delete paid out logs.';
            END IF;
        END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `protect_host_earnings_paid_out`;
DELIMITER $$
CREATE TRIGGER `protect_host_earnings_paid_out` BEFORE UPDATE ON `host_earnings` FOR EACH ROW BEGIN
            IF OLD.status = 'paid_out' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCIAL INTEGRITY ERROR: Paid out earnings are immutable.';
            END IF;
        END
$$
DELIMITER ;

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
-- Table structure for table `idempotency_keys`
--

DROP TABLE IF EXISTS `idempotency_keys`;
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key_action` (`key_id`,`action`),
  KEY `idx_user_key` (`user_id`,`key_id`),
  KEY `idx_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `message` text NOT NULL,
  `is_encrypted` tinyint(1) DEFAULT '1',
  `moderation_status` enum('clean','flagged','blocked') DEFAULT 'clean',
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  `deleted_by_sender` tinyint(1) DEFAULT '0',
  `deleted_by_receiver` tinyint(1) DEFAULT '0',
  `is_deleted_everyone` tinyint(1) DEFAULT '0',
  `deleted_by_admin` tinyint(1) DEFAULT '0',
  `message_type` enum('text','image','file') DEFAULT 'text',
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `conversation_id` int DEFAULT NULL,
  `status` enum('sent','delivered','seen') DEFAULT 'sent',
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `booking_id`, `message`, `is_encrypted`, `moderation_status`, `sent_at`, `read_at`, `deleted_by_sender`, `deleted_by_receiver`, `is_deleted_everyone`, `deleted_by_admin`, `message_type`, `file_path`, `file_name`, `file_size`, `conversation_id`, `status`) VALUES
(1, 10, 9, NULL, 'tEWwHX3t/HeK89y+E1Q4UWNmOXlFMVZ1QXZvYWc4d21lRG10Umc9PQ==', 1, 'clean', '2026-04-12 14:52:33', '2026-04-14 15:26:29', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(2, 10, 9, NULL, 'MqwVBeD57gg3/OAeyKd4P25KVkliWFVKdlBqeUZ4M3dXb1Irc2c9PQ==', 1, 'blocked', '2026-04-12 14:52:52', '2026-04-14 15:26:29', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(4, 10, 9, NULL, 'l4d5BsjxWX7amLLMSCtd1WFMYlNWWi9ZNTFiZTBWdmJEaW96dFU3MkgzUVhMQWk5V3lGWnVoeXZoQ1k9', 1, 'clean', '2026-04-12 15:06:04', '2026-04-14 15:26:29', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(5, 10, 9, NULL, 'aXtfET+Zq85Aku85WjlwOlhndlR2bHlkNTkyMjhXVGN4d1lNYXc9PQ==', 1, 'clean', '2026-04-12 15:06:07', '2026-04-14 15:26:29', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(6, 10, 9, NULL, 'chIoVDSAcKYtG1c+Dyu2ZTl4ZkprcnNKN1J0K3o4VVNHWm9SN09vYlJkU2pZUEI4YTdHUEwyRFc2U3pTZG5HeHlTeTMzeVcyL3htNHhwci84VWFIT2N5YTlsaGJGNzJlQ2w0U3kzMmVQWHVKQXNJRUpzdmhtdVBVM0FEZ1FDZ25PbUl2ZnVSemoydzA2YitFck9aZkx1WmxLVXc1Qkxsc1BYOGhQazV5MHAyd3hsbXRCK0VWY3dadEdYT2daZGhBZGM1UW9tQ2NPNFBycmJCY2RCT0lDVUkrY0JiMjJvUzhFV083NTNMUU5GL296MEloV0U4V0J1ZXFBYVVtYUpyRHpBbWR4ZjIveHdYSU0zNnJWajRlYkptV0RlUFRDenduOXJrSCtodXNmczk3Q2NQUm9pMUJnWGxVMDZGY0thRWhRTEVyaFE4bkhFRks3a1UzU2VvZjNSMm92TEl1ek55UUt4c3Jvd2VHcTRHZ3RqaUlGNGdmM09kSkxpeDloTXM2SGpmVHdzb1lUS3MyZFhlL01NQmpIbFR0eVYydnNyeHpjU0x6ZU84eXBvNlJBcHlhUHNyQ1Fyd1dXNnIwNjg5VHFQZE1JLytSVlhJRnNnaGtUMy84S1E2ZHpyc3hsRTNrV1JyRFY2Y0d3UFR0RFY3OGQvN2NtVWtKWlNLeVJoempiSWxuRG9qYWE4NytYRXd1a2dudlpSTjR3LzFuaFJtelJxaHJQTWZocWZxTlVmUFJqOUNyeFhFMnkxeE1LdHp0SHh6OTg3YUVqMU1jbVRSS25jNS83cE53QzN4K2pqaUw5UmtBSThDWUR2eWVOeW9jd3IvS3JIc3hCbDFMUUFVaDhMb1JoTFk4NUk5NkFKODNDT25tSXNnMVU5UGtVY0wyeXVoUDBMdzExWnorM2IrL3FtQXR6Z0ZGTEI2T2JFektRNlU2Vnc1UXNIcWtTbUF6a2xCbFowYXZTbFVmaWdqREVXQ3Ntb3pVQXpIdkJhbkNyYTFORFRESG41dFRyT3d1aXRJSlI3d0FaOHBzcTlLeHJNS0R5TTZ1S2hxLzZmM0MxVVd5UmM0Nm9tV0hTVFNvYWNXK2Ruak81QVk3WVB6UXdJQ0tiM2dtUXNEZlJGT2lnYWllZjVzMVZZeU0yR0VUeGFvOFNlUUx3TDdGZGNJbDRQVFVBV3QzSlZaaUtabGgyQy9ZcGJpUUhwayt1cmFZdTh4K2FOUjgzQUM1anlSRDUvWncyMDhXcncybUw4RE9ibGFsckk0N3lpeHluZ1dSdEhHSFRROUIvVHV5TURoamx2cUgwUjVuWWl5bjcyZS92ekpKTTlVczBwaHdaRmZhNS9zdUlUTHhKVFdPVUd3RnpVK1Zwbzk4VmlrRVRubm1pM1lBRlZNdlpWVEdJbElDeTNBZkR5elp1ZlZQbTIya3V5QVA1RHpBMGpaMks2c1NjODNOU1ZCNkF5Tk1UOTVnOGw1cEYyZ3FxWStaRXJMSUczajFzMHRZN25ORGxCNkpLbnYwa3FUTnJTSGM1eFFjVmRHYmVBeDlSV0xEWml6QnNqNWNBbU5OVUpuRmgxd1Rmb0RMQVFqRmNTd2EzUU9qdjhqRUl3MEx2cEUwMUR3WG5paHhFVWt6aGJXZHZadEZ6bFpjVzIzUStINW5QM1JWUy94WUlvWXk3M1dFc0lZemZ4V21rYkFlNWpEY25VVnZ2WHZiRGNTNE9FSjVMNGxpSk5ubEZ6Z09UWFp1NW5tMFFnck1DRm9KRjBacDdzTkdRRFpoZ1pkSTNYdTgzbVUreXN4ak5vNGlxUVB4cWpEdlVFdmsvZDduQ1kxK2R4MUZnY0lTMHd5RVpvamNWeEhQU1E0eWtlRmtSbWJ1azNEN0JqMFArbkJJOGlCZ0FLTVB1T3NkdElxcTRCNHFCMU9JSTFkN3oweEltRzlHRURncmtHOFZTNXMyWGdyZExkcno4bXlaRlVCSDhad2VKT3V0SFl5ZUFJNFB4WXBIVEpkbkJJclJNU0Y2eWlwS2h0MGJwVmgySzRvYkRQZGE0Yk1adW1PSjRHNS9ZSnB1UTR1YzlpdTBXd24rVHpaU0syM0dmL2FEcDBLVjNsRTJKcHhFenozUTBXYTM1cmRKSTY3WFRkNW1pNDZuNUVwbVk5QkJQd3JtaHJKWGY4dkZabmVmblBJRWpOb044V2FBUzBIMWJuQzFXYjhOTEcxNVljajNkMzNYK2xLbFozZHE5QXlNWGh1d1ppdkV1YS9YNnZIZEU4b3F2SG1OdUV0T3BTNGE2aFZlaCtQVWVNNkRETmtZYnhXQU1LaWJNK0N3NzJxTm5UT3QxMHdxMzIrR1NndDgycElTeUloMjZwck11SlZNSGhkK0l2YVRHbUlVeHY3R3Z3OFpsaGpOR1lpL3ZiTENJaU9UbmF0dDNsSzBOR2tkV2ZwN1RCdTRWbk1XMEs0QkEweit5Q1Y5cW5PWXpEU2VFcWFoamc2SW1iNHFIcmlORHlycmFzUmJSLzVGNHJ1VmdzbzB2M2RFVk1tTmR4eVhUNG84czh0QXBvTERzSkZJcllyM2NEZE5vUUh3UWVGVi9QTTFHWENKQWhXTDVzdTZLNThMZnY5VEpTRUxscGN6TlVDd0p4VzgxUWJsRTZCR2VrZTZwR0FieUJRL2cvWGhXZlFuVEQ3eUtENFJQMFB2QVJ3TllnYzdicnQxZW1sKy96V3VMQzV4S3MwM3BybG9ybXA0UXNjZzZMcHRqNWdsaXR2QUdhSExaSDc2ZjRDVmZzQWFWZU9OUXhYZEc3dGQ5NVFLYWF3eXZsYVdFOTZGR1lJeE1ENWJqS3BOaEIvdnhIMkJXb2Foa292T0w5MWFXdE96VURvTDIzYXVzcloxVzNWMytZNUphSDVwQlI4eHJqYytIOUtscmJsakh3eFZqd0VnTGhNYUpXL2ZTeDhoc2dHN2R5SEVaSFNTd0NKZ0VFQzJuU1VQS0U0blJhRjBZQ0YzcXcrak1aZStuS3ZnSDUyZVY5cGlTSjVNUUZWbmxHR2E0V0xlb01nb3FNUEk3d0xMZ2JqSUE1bVRyZWQwTkIyR0dCOWRFVTIzM3JxTw==', 1, 'blocked', '2026-04-12 15:10:12', '2026-04-14 15:26:29', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(7, 9, 10, NULL, 'NgW04iunk77sH1LTxC4ppzJIa2ZUS0E2QmpueEUwZ2E5S29aenc9PQ==', 1, 'clean', '2026-04-14 23:49:54', '2026-04-15 00:13:52', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(8, 9, 10, NULL, 'MsM9QwaIIKgPuRv5qGQrmzRYajVrcnpVQmY5aTZrcnczaVR6cVE9PQ==', 1, 'clean', '2026-04-15 16:24:18', '2026-04-15 16:24:19', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(9, 9, 10, NULL, 'NfRNF8MNjxRJuYfVGuvbny8zc05zS3A1ZDI4czB1M296VysvaUE9PQ==', 1, 'clean', '2026-04-15 16:33:06', '2026-04-16 11:07:39', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(10, 9, 10, NULL, 's8u478pWkzfxt5TTYLjC0klFNUc2alQ4YldmZ3ljcTFkSmdLMXc9PQ==', 1, 'clean', '2026-04-16 13:38:39', '2026-04-16 13:39:38', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(11, 9, 10, NULL, '', 1, 'clean', '2026-04-16 13:39:08', '2026-04-16 13:39:38', 0, 0, 0, 0, 'image', 'uploads/chat_attachments/msg_69e0e67c3916a.jpg', 'Scan_20250818.jpg', NULL, NULL, 'sent'),
(12, 10, 9, NULL, 'aKOkagv723vShGFTjclvtHRiQUt5QmQyb1JwNVhXV08yYklmUlE9PQ==', 1, 'clean', '2026-04-16 13:39:48', '2026-04-16 13:39:50', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(13, 9, 10, NULL, 'mviLG6B13mvv5/gL9xjn9HN5elFhVjY1cSs2Y29seE91ODlJWVE9PQ==', 1, 'clean', '2026-04-16 13:51:52', '2026-04-16 14:39:09', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(14, 9, 10, NULL, 'lfCVG2j0SGkgSN1AJr3stEN3QS9kY05BZFRBRFhieGl3TFpKMVE9PQ==', 1, 'clean', '2026-04-16 13:53:03', '2026-04-16 14:39:09', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(15, 9, 10, NULL, '4kbre41eltZHBlIbrsSyYXhlTEhyeFVHeUJMcnhXNmlNd2U2MUE9PQ==', 1, 'clean', '2026-04-16 13:53:09', '2026-04-16 14:39:09', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(16, 9, 10, NULL, 'NiBF9eaeIXg6ND1xxRyJNFRzT09wRHZ4Y1g1dElRM2paOTlDTVE9PQ==', 1, 'clean', '2026-04-16 13:53:18', '2026-04-16 14:39:09', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(17, 9, 10, NULL, 'KADlrLpU6WpVLxQZ6+U/MVBkNjFkQnVRT3QzL1ZQTlNjbWFsdGc9PQ==', 1, 'clean', '2026-04-16 13:53:21', '2026-04-16 14:39:09', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(18, 9, 10, NULL, 'gBKBsqcGOJBoDiIdZOy5W21rTTNHMCs2Q3QzZ3pldG9wTGU5M3c9PQ==', 1, 'clean', '2026-04-16 13:53:24', '2026-04-16 14:39:09', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(19, 9, 10, NULL, 'd6z1yIQg7fohQ++CPzMJOWN6OGtjUFZNSG1UbEVvclpndVpxd0E9PQ==', 1, 'clean', '2026-04-16 13:53:28', '2026-04-16 14:39:09', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(20, 24, 23, NULL, 'OrqMOYVMrE4LQECDIhL1HmVYbFJIbzlab3dHQkVaa0NueVJ4Umc9PQ==', 1, 'clean', '2026-04-17 07:15:39', '2026-04-17 07:15:56', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(21, 23, 24, NULL, '8hlcIgH81VKJQ5bSlOVVHlBxdG4yRjdhRU1BVVFvVDZzZFdkR0E9PQ==', 1, 'clean', '2026-04-17 07:16:06', '2026-04-17 07:16:07', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(22, 24, 23, NULL, 'OyHotOPvq9BBeSqlOCW6HTg4VU5RMkU4enpzOUJBNXc3QVpXZXc9PQ==', 1, 'clean', '2026-04-17 07:16:18', '2026-04-17 07:16:21', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(23, 24, 9, NULL, 'jlBbGOfBIzQBwcwdFrjvRk04SkNKZnNsREF6aHgwZy9oL1JlRnhQMUh6OG04ODFSZ0hSTDg5V3Ztc1k9', 1, 'clean', '2026-04-17 07:18:41', '2026-04-17 07:18:54', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent'),
(24, 9, 24, NULL, 'irRGVfDC7ze3l0JZms6PuWdaNUcwbXRrVVltamxMSUQvM0JEVHc9PQ==', 1, 'clean', '2026-04-17 07:19:00', '2026-04-17 07:19:02', 0, 0, 0, 0, 'text', NULL, NULL, NULL, NULL, 'sent');

-- --------------------------------------------------------

--
-- Table structure for table `message_logs`
--

DROP TABLE IF EXISTS `message_logs`;
CREATE TABLE IF NOT EXISTS `message_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `message_id` int DEFAULT NULL,
  `action` enum('sent','deleted','flagged','blocked') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `performed_by` int DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `message_logs`
--

INSERT INTO `message_logs` (`log_id`, `message_id`, `action`, `created_at`, `performed_by`) VALUES
(1, 1, 'sent', '2026-04-12 14:52:33', NULL),
(2, 2, 'sent', '2026-04-12 14:52:52', NULL),
(3, 2, 'blocked', '2026-04-12 14:57:50', NULL),
(10, 4, 'sent', '2026-04-12 15:06:04', NULL),
(11, 5, 'sent', '2026-04-12 15:06:07', NULL),
(12, 6, 'sent', '2026-04-12 15:10:12', NULL),
(13, 6, 'blocked', '2026-04-12 19:47:35', NULL),
(16, 7, 'sent', '2026-04-14 23:49:54', NULL),
(17, 8, 'sent', '2026-04-15 16:24:18', NULL),
(18, 9, 'sent', '2026-04-15 16:33:06', NULL),
(19, 10, 'sent', '2026-04-16 13:38:39', NULL),
(20, 11, 'sent', '2026-04-16 13:39:08', NULL),
(21, 12, 'sent', '2026-04-16 13:39:48', NULL),
(22, 13, 'sent', '2026-04-16 13:51:52', NULL),
(23, 14, 'sent', '2026-04-16 13:53:03', NULL),
(24, 15, 'sent', '2026-04-16 13:53:09', NULL),
(25, 16, 'sent', '2026-04-16 13:53:18', NULL),
(26, 17, 'sent', '2026-04-16 13:53:21', NULL),
(27, 18, 'sent', '2026-04-16 13:53:24', NULL),
(28, 19, 'sent', '2026-04-16 13:53:28', NULL),
(29, 1, 'flagged', '2026-04-17 02:32:03', NULL),
(30, 1, 'deleted', '2026-04-17 02:32:03', NULL),
(31, 20, 'sent', '2026-04-17 07:15:39', NULL),
(32, 21, 'sent', '2026-04-17 07:16:06', NULL),
(33, 22, 'sent', '2026-04-17 07:16:18', NULL),
(34, 23, 'sent', '2026-04-17 07:18:41', NULL),
(35, 24, 'sent', '2026-04-17 07:19:00', NULL);

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
  `admin_message` text,
  `status` varchar(50) DEFAULT 'info',
  `type` enum('booking','payment','reminder','system','message') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `related_id` int DEFAULT NULL,
  `redirect_url` varchar(255) DEFAULT NULL,
  `priority` enum('normal','urgent','overdue') DEFAULT 'normal',
  `is_popup_shown` tinyint(1) DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `email_sent` tinyint(1) DEFAULT '0',
  `is_archived` tinyint(1) DEFAULT '0',
  `email_retries` tinyint DEFAULT '0',
  `next_retry_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_priority` (`priority`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `admin_message`, `status`, `type`, `is_read`, `created_at`, `related_id`, `redirect_url`, `priority`, `is_popup_shown`, `expires_at`, `email_sent`, `is_archived`, `email_retries`, `next_retry_at`) VALUES
(1, 20, '🎉 Host Application Approved', 'Your application has been approved. You can now start listing your property.', 'approve, welcome to our system', 'approved', 'system', 0, '2026-04-11 16:03:15', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(2, 10, 'Unit updated by admin', 'An administrator updated your listing \"equiste unit\" (unit #unit 102). Open Host → Unit Management to review.', 'Price per month changed from ₱12366 to ₱1000, Unit name changed from \'weweewe\' to \'equiste unit\', Unit number changed from \'ddad\' to \'unit 102\', Unit type changed from \'\' to \'Studio\'.\n\nMessage from admin:\nI edited the unit you uploaded', 'info', 'system', 1, '2026-04-12 19:44:10', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(3, 10, 'Unit updated by admin', 'An administrator updated your listing \"equiste exclusive unit\" (unit #unit 103). Open Host → Unit Management to review.', 'Unit name changed from \'equiste unit\' to \'equiste exclusive unit\', Unit number changed from \'unit 102\' to \'unit 103\'.\n\nMessage from admin:\nI edited your unit, you must check it', 'info', 'system', 1, '2026-04-12 19:46:41', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(4, 10, 'Message Blocked', 'Admin has blocked a message in your conversation for violating terms.', NULL, 'info', 'system', 1, '2026-04-12 19:47:35', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(5, 10, 'New Booking Request - Awaiting Approval', 'New reservation #5 needs your approval. Please review in your dashboard.', NULL, 'info', 'booking', 1, '2026-04-13 02:30:47', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(6, 19, 'Booking Submitted for Approval', 'Your reservation #5 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-13 02:30:47', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(7, 19, 'Reservation Created', 'Your reservation for Unit unit 103 has been created successfully. Reservation ID: 5', NULL, 'info', 'booking', 0, '2026-04-13 02:30:47', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(8, 19, 'Reservation Approved', 'Your reservation #5 has been approved by the host! You can now proceed to payment.', NULL, 'info', 'booking', 0, '2026-04-13 02:31:02', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(9, 10, 'Guest Checked-In', 'Guest has checked in for reservation #5', NULL, 'info', 'booking', 1, '2026-04-13 02:33:04', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(10, 10, 'Message Blocked', 'Admin has blocked a message in your conversation for violating terms.', NULL, 'info', 'system', 1, '2026-04-13 02:42:23', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(11, 10, 'New Booking Request - Awaiting Approval', 'New reservation #6 needs your approval. Please review in your dashboard.', NULL, 'info', 'booking', 1, '2026-04-13 05:01:24', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(12, 9, 'Booking Submitted for Approval', 'Your reservation #6 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 1, '2026-04-13 05:01:24', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(13, 9, 'Reservation Created', 'Your reservation for Unit suite10 has been created successfully. Reservation ID: 6', NULL, 'info', 'booking', 1, '2026-04-13 05:01:24', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(14, 9, 'Booking Submitted for Approval', 'Your reservation #7 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 1, '2026-04-13 05:15:09', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(15, 9, 'Reservation Created', 'Your reservation for Unit m307 has been created successfully. Reservation ID: 7', NULL, 'info', 'booking', 1, '2026-04-13 05:15:09', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(16, 21, 'Application Submitted', 'Thank you for applying! Your application is under review.', NULL, 'info', 'system', 0, '2026-04-13 06:09:33', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(17, 21, '❌ Host Application Rejected', 'Your application was not approved. Please check the reason below.', 'im sorry but im busted', 'rejected', 'system', 0, '2026-04-13 06:12:28', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(18, 21, '❌ Host Application Rejected', 'Your application was not approved. Please check the reason below.', 'im sorry but im busted', 'rejected', 'system', 0, '2026-04-13 06:12:32', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(19, 21, '🎉 Host Application Approved', 'Your application has been approved. You can now start listing your property.', 'approve', 'approved', 'system', 0, '2026-04-13 06:21:43', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(20, 10, 'New Message from Antonio, Paul Lexxus B.', 'You have received a secure message. Check your inbox.', NULL, 'info', 'system', 1, '2026-04-15 16:24:18', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(21, 10, 'New Message from Antonio, Paul Lexxus B.', 'You have received a secure message. Check your inbox.', NULL, 'info', 'system', 1, '2026-04-15 16:33:06', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(22, 1, 'New Unit Pending Approval', 'Host (ID: 10) added unit \'faf\' and it is waiting for your review.', NULL, 'info', '', 1, '2026-04-16 11:56:07', 33, 'pending_units.php?unit_id=33', 'normal', 0, NULL, 0, 0, 0, NULL),
(23, 8, 'New Unit Pending Approval', 'Host (ID: 10) added unit \'faf\' and it is waiting for your review.', NULL, 'info', '', 0, '2026-04-16 11:56:07', 33, 'pending_units.php?unit_id=33', 'normal', 0, NULL, 0, 0, 0, NULL),
(24, 10, 'Unit Approved', 'Your unit \'faf\' has been approved and is now live.', NULL, 'info', 'system', 1, '2026-04-16 11:57:31', 33, 'unit_management.php', 'normal', 0, NULL, 0, 0, 0, NULL),
(25, 22, 'Application Submitted', 'Thank you for applying! Your application is under review.', NULL, 'info', 'system', 0, '2026-04-16 13:29:18', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(26, 22, '❌ Host Application Rejected', 'Your application was not approved. Please check the reason below.', 'panget mo', 'rejected', 'system', 0, '2026-04-16 13:30:25', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(27, 22, '❌ Host Application Rejected', 'Your application was not approved. Please check the reason below.', 'panget mo', 'rejected', 'system', 0, '2026-04-16 13:30:29', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(28, 10, 'New File from Antonio, Paul Lexxus B.', 'You have received a secure message. Check your inbox.', NULL, 'info', 'system', 1, '2026-04-16 13:39:08', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(29, 9, 'New Message from jackie', 'You have received a secure message. Check your inbox.', NULL, 'info', 'system', 0, '2026-04-16 13:39:48', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(30, 10, 'New Booking Request - Awaiting Approval', 'New reservation #8 needs your approval. Please review in your dashboard.', NULL, 'info', 'booking', 1, '2026-04-16 13:41:15', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(31, 9, 'Booking Submitted for Approval', 'Your reservation #8 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-16 13:41:15', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(32, 9, 'Reservation Created', 'Your reservation for Unit f20 has been created successfully. Reservation ID: 8', NULL, 'info', 'booking', 0, '2026-04-16 13:41:15', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(33, 9, 'Reservation Approved', 'Your reservation #8 has been approved by the host! You can now proceed to payment.', NULL, 'info', 'booking', 0, '2026-04-16 13:41:34', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(34, 9, 'Checked In', 'You have been checked in to reservation #8.', NULL, 'info', 'booking', 0, '2026-04-16 13:41:46', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(35, 10, 'New Booking Request - Awaiting Approval', 'New reservation #9 needs your approval. Please review in your dashboard.', NULL, 'info', 'booking', 1, '2026-04-16 16:12:46', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(36, 9, 'Booking Submitted for Approval', 'Your reservation #9 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-16 16:12:46', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(37, 9, 'Reservation Created', 'Your reservation for Unit gsgsgs has been created successfully. Reservation ID: 9', NULL, 'info', 'booking', 0, '2026-04-16 16:12:46', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(38, 9, 'Booking Submitted for Approval', 'Your reservation #10 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-16 16:30:32', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(39, 9, 'Reservation Created', 'Your reservation for Unit m307 has been created successfully. Reservation ID: 10', NULL, 'info', 'booking', 0, '2026-04-16 16:30:32', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(40, 9, 'Booking Submitted for Approval', 'Your reservation #11 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-17 02:21:48', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(41, 9, 'Reservation Created', 'Your reservation for Unit m307 has been created successfully. Reservation ID: 11', NULL, 'info', 'booking', 0, '2026-04-17 02:21:48', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(42, 9, 'Reservation Approved', 'Your reservation #9 has been approved by the host! You can now proceed to payment.', NULL, 'info', 'booking', 0, '2026-04-17 02:23:19', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(43, 1, 'System Initialized', 'The system is ready and running.', NULL, 'info', 'system', 0, '2026-04-17 02:32:03', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(44, 1, 'Test Booking Alert', 'This is a test booking notification.', NULL, 'info', 'booking', 0, '2026-04-17 02:32:03', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(45, 23, 'Booking Submitted for Approval', 'Your reservation #12 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-17 03:11:13', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(46, 23, 'Reservation Created', 'Your reservation for Unit m307 has been created successfully. Reservation ID: 12', NULL, 'info', 'booking', 0, '2026-04-17 03:11:13', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(47, 23, 'Booking Submitted for Approval', 'Your reservation #13 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-17 03:16:25', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(48, 23, 'Reservation Created', 'Your reservation for Unit m307 has been created successfully. Reservation ID: 13', NULL, 'info', 'booking', 0, '2026-04-17 03:16:25', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(49, 10, 'New Booking Request - Awaiting Approval', 'New reservation #14 needs your approval. Please review in your dashboard.', NULL, 'info', 'booking', 1, '2026-04-17 03:18:08', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(50, 23, 'Booking Submitted for Approval', 'Your reservation #14 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-17 03:18:08', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(51, 23, 'Reservation Created', 'Your reservation for Unit gsgsgs has been created successfully. Reservation ID: 14', NULL, 'info', 'booking', 0, '2026-04-17 03:18:08', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(52, 10, 'New Booking Request - Awaiting Payment', 'New reservation #14 has been created and is awaiting payment confirmation.', NULL, 'info', 'booking', 1, '2026-04-17 03:18:08', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(53, 24, 'Application Submitted', 'Thank you for applying! Your application is under review.', NULL, 'info', 'system', 1, '2026-04-17 07:05:19', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(54, 24, '🎉 Host Application Approved', 'Your application has been approved. You can now start listing your property.', 'you are welcome to our system, king of the pirates!', 'approved', 'system', 1, '2026-04-17 07:06:55', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(55, 1, 'New Unit Pending Approval', 'Host (ID: 24) added unit \'Unit\' and it is waiting for your review.', NULL, 'info', '', 0, '2026-04-17 07:10:12', 34, 'pending_units.php?unit_id=34', 'normal', 0, NULL, 0, 0, 0, NULL),
(56, 8, 'New Unit Pending Approval', 'Host (ID: 24) added unit \'Unit\' and it is waiting for your review.', NULL, 'info', '', 0, '2026-04-17 07:10:12', 34, 'pending_units.php?unit_id=34', 'normal', 0, NULL, 0, 0, 0, NULL),
(57, 24, 'Unit Approved', 'Your unit \'Unit\' has been approved and is now live.', NULL, 'info', 'system', 1, '2026-04-17 07:13:04', 34, 'unit_management.php', 'normal', 0, NULL, 0, 0, 0, NULL),
(58, 24, 'New Booking Request - Awaiting Approval', 'New reservation #15 needs your approval. Please review in your dashboard.', NULL, 'info', 'booking', 1, '2026-04-17 07:14:00', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(59, 23, 'Booking Submitted for Approval', 'Your reservation #15 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-17 07:14:00', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(60, 23, 'Reservation Created', 'Your reservation for Unit 303 has been created successfully. Reservation ID: 15', NULL, 'info', 'booking', 0, '2026-04-17 07:14:00', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(61, 24, 'New Booking Request - Awaiting Payment', 'New reservation #15 has been created and is awaiting payment confirmation.', NULL, 'info', 'booking', 1, '2026-04-17 07:14:00', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(62, 23, 'New Message from Monkey D. Luffy', 'You have received a secure message. Check your inbox.', NULL, 'info', 'system', 1, '2026-04-17 07:15:39', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(63, 24, 'New Message from Antonio, Paul Lexxus B.', 'You have received a secure message. Check your inbox.', NULL, 'info', 'system', 1, '2026-04-17 07:16:06', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(64, 23, 'Reservation Approved', 'Your reservation #15 has been approved by the host! You can now proceed to payment.', NULL, 'info', 'booking', 0, '2026-04-17 07:16:33', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(65, 24, 'New Booking Request - Awaiting Approval', 'New reservation #16 needs your approval. Please review in your dashboard.', NULL, 'info', 'booking', 1, '2026-04-17 07:18:12', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(66, 9, 'Booking Submitted for Approval', 'Your reservation #16 has been submitted and is awaiting host approval.', NULL, 'info', 'booking', 0, '2026-04-17 07:18:12', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(67, 9, 'Reservation Created', 'Your reservation for Unit 303 has been created successfully. Reservation ID: 16', NULL, 'info', 'booking', 0, '2026-04-17 07:18:12', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(68, 24, 'New Booking Request - Awaiting Payment', 'New reservation #16 has been created and is awaiting payment confirmation.', NULL, 'info', 'booking', 1, '2026-04-17 07:18:12', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(69, 9, 'New Message from Monkey D. Luffy', 'You have received a secure message. Check your inbox.', NULL, 'info', 'system', 0, '2026-04-17 07:18:41', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(70, 9, 'Reservation Cancelled', 'Your reservation #16 has been cancelled. Reason: di ka pasok sa standards ko.', NULL, 'info', 'booking', 0, '2026-04-17 07:19:21', NULL, NULL, 'normal', 0, NULL, 0, 0, 0, NULL),
(71, 1, 'New Unit Pending Approval', 'Host (ID: 10) added unit \'unit506\' and it is waiting for your review.', NULL, 'info', '', 0, '2026-04-19 11:52:37', 35, 'pending_units.php?unit_id=35', 'normal', 0, NULL, 0, 0, 0, NULL),
(72, 8, 'New Unit Pending Approval', 'Host (ID: 10) added unit \'unit506\' and it is waiting for your review.', NULL, 'info', '', 0, '2026-04-19 11:52:37', 35, 'pending_units.php?unit_id=35', 'normal', 0, NULL, 0, 0, 0, NULL),
(73, 10, 'Unit Approved', 'Your unit \'unit506\' has been approved and is now live.', NULL, 'info', 'system', 1, '2026-04-19 11:53:05', 35, 'unit_management.php', 'normal', 0, NULL, 0, 0, 0, NULL),
(74, 1, 'Unit Edited - Pending Approval', 'Host (ID: 10) updated unit \'unit506\' and it requires re-approval.', NULL, 'info', '', 0, '2026-04-19 14:01:17', 35, 'pending_units.php?unit_id=35', 'normal', 0, NULL, 0, 0, 0, NULL),
(75, 8, 'Unit Edited - Pending Approval', 'Host (ID: 10) updated unit \'unit506\' and it requires re-approval.', NULL, 'info', '', 0, '2026-04-19 14:01:17', 35, 'pending_units.php?unit_id=35', 'normal', 0, NULL, 0, 0, 0, NULL),
(76, 1, 'Unit Edited - Pending Approval', 'Host (ID: 10) updated unit \'unit506\' and it requires re-approval.', NULL, 'info', '', 0, '2026-04-19 16:10:19', 35, 'pending_units.php?unit_id=35', 'normal', 0, NULL, 0, 0, 0, NULL),
(77, 8, 'Unit Edited - Pending Approval', 'Host (ID: 10) updated unit \'unit506\' and it requires re-approval.', NULL, 'info', '', 0, '2026-04-19 16:10:19', 35, 'pending_units.php?unit_id=35', 'normal', 0, NULL, 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

DROP TABLE IF EXISTS `notification_templates`;
CREATE TABLE IF NOT EXISTS `notification_templates` (
  `template_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(150) DEFAULT NULL,
  `message` text,
  `category` enum('checkin_reminder','welcome_message','house_rules') DEFAULT NULL,
  `trigger_event` enum('24_hours_before_checkin','on_booking_confirmed','manual_send') DEFAULT 'manual_send',
  `host_id` int DEFAULT NULL,
  PRIMARY KEY (`template_id`),
  KEY `host_id` (`host_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notification_templates`
--

INSERT INTO `notification_templates` (`template_id`, `title`, `message`, `category`, `trigger_event`, `host_id`) VALUES
(1, 'Hello Good Day', 'This conversation has altered my life trajectory.', 'welcome_message', 'on_booking_confirmed', 10),
(2, 'Pending Unit Reminder', 'You have pending unit approvals that require your attention.', 'checkin_reminder', 'manual_send', NULL),
(3, 'Host Verification Reminder', 'There are pending host verification requests awaiting review.', 'checkin_reminder', 'manual_send', NULL),
(4, 'Promo Applied', 'Your promo has been successfully applied to your booking.', '', 'manual_send', NULL),
(5, 'Promo Expired', 'The promo you tried to use is no longer valid.', '', 'manual_send', NULL),
(6, 'Payment Submitted', 'A payment has been submitted and is awaiting verification.', '', 'manual_send', NULL),
(7, 'Payment Verified', 'Your payment has been verified successfully.', '', 'manual_send', NULL),
(8, 'Payment Failed', 'Your payment attempt failed. Please try again.', '', 'manual_send', NULL),
(9, 'Booking Created', 'Your reservation has been created successfully.', '', 'manual_send', NULL),
(10, 'Booking Expired', 'Your reservation expired due to non-payment.', '', 'manual_send', NULL),
(11, 'Booking Cancelled', 'Your reservation has been cancelled.', '', 'manual_send', NULL),
(12, 'Check-in Reminder', 'Reminder: Your check-in is within 24 hours.', 'checkin_reminder', '24_hours_before_checkin', NULL),
(13, 'Admin Reminder', 'You have pending approvals that require attention.', '', 'manual_send', NULL),
(14, 'Overdue Review', 'Some pending requests are overdue. Please review immediately.', '', 'manual_send', NULL),
(15, 'Host Application Submitted', 'Your host application is under review.', '', 'manual_send', NULL),
(16, 'Host Application Approved', 'Congratulations! Your host application has been approved.', '', 'manual_send', NULL),
(17, 'Host Application Rejected', 'Your host application was rejected. Please review feedback and reapply.', '', 'manual_send', NULL),
(18, 'New Message', 'You have received a new message.', '', 'manual_send', NULL),
(19, 'Message Blocked', 'A message was blocked due to policy violation.', '', 'manual_send', NULL),
(20, 'Admin Escalation Warning', 'You have pending reviews older than 3 days. Please review immediately.', '', 'manual_send', NULL),
(21, 'Unit Pending Review', 'A new unit has been uploaded and requires approval.', '', 'manual_send', NULL),
(22, 'Unit Approved', 'Your unit has been approved and is now visible to renters.', '', 'manual_send', NULL),
(23, 'Unit Rejected', 'Your unit was rejected. Please review feedback and resubmit.', '', 'manual_send', NULL),
(24, 'Host Application Submitted', 'A new host verification request requires review.', '', 'manual_send', NULL),
(25, 'Host Approved', 'Your host application has been approved.', '', 'manual_send', NULL),
(26, 'Host Rejected', 'Your host application has been rejected. Please check your email for details.', '', 'manual_send', NULL),
(27, 'Booking Reminder', 'Reminder: Your check-in is scheduled within 24 hours.', 'checkin_reminder', '24_hours_before_checkin', NULL),
(28, 'Payment Reminder', 'Your booking is pending payment. Please complete payment to secure your reservation.', '', 'manual_send', NULL),
(29, 'Message Notification', 'You have received a new message.', '', 'manual_send', NULL),
(30, 'File Received', 'A file/image has been sent to you.', '', 'manual_send', NULL);

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
  `payment_method` enum('gcash','paymaya','paypal') NOT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `verified_by_admin` tinyint(1) DEFAULT '0',
  `verified_at` datetime DEFAULT NULL,
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
  `user_id` int NOT NULL,
  `reservation_id` int DEFAULT NULL,
  `payment_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `account_details` text COLLATE utf8mb4_unicode_ci,
  `method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','released','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `released_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payout_id`),
  KEY `host_id` (`user_id`),
  KEY `reservation_id` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `payouts`
--
DROP TRIGGER IF EXISTS `trg_payouts_audit`;
DELIMITER $$
CREATE TRIGGER `trg_payouts_audit` AFTER UPDATE ON `payouts` FOR EACH ROW BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NEW.host_id, 'payout_status_change', 'payout', NEW.payout_id, OLD.status, NEW.status, CONCAT('Payout status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payout_accounts`
--

DROP TABLE IF EXISTS `payout_accounts`;
CREATE TABLE IF NOT EXISTS `payout_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `method` enum('GCash','PayMaya','PayPal','Bank') NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_details` varchar(255) NOT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payout_requests`
--

DROP TABLE IF EXISTS `payout_requests`;
CREATE TABLE IF NOT EXISTS `payout_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `host_id` int NOT NULL,
  `payout_account_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected','paid') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `host_id` (`host_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

DROP TABLE IF EXISTS `platform_settings`;
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('platform_fee_percent', '10', 'Percentage taken by the platform per booking', '2026-04-19 12:06:06');

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
-- Table structure for table `prohibited_words`
--

DROP TABLE IF EXISTS `prohibited_words`;
CREATE TABLE IF NOT EXISTS `prohibited_words` (
  `word_id` int NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL,
  `severity` enum('mild','severe') DEFAULT 'severe',
  PRIMARY KEY (`word_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `prohibited_words`
--

INSERT INTO `prohibited_words` (`word_id`, `word`, `severity`) VALUES
(1, 'nigga', 'severe'),
(2, 'tangina mo', 'severe'),
(3, 'siraulo', 'mild'),
(5, 'kinangina mo', 'severe'),
(6, 'papatayin kita', 'severe');

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promos`
--

INSERT INTO `promos` (`promo_id`, `code`, `type`, `value`, `expires_at`, `usage_limit`, `used_count`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SAVE50', 'fixed', 50.00, '2026-04-10 23:59:59', 1, 0, 1, 1, '2026-04-08 13:44:03', '2026-04-08 13:44:03'),
(3, 'SUMMER100', 'fixed', 100.00, '2026-04-30 23:59:59', 1, 0, 1, 1, '2026-04-08 13:56:09', '2026-04-08 13:56:09'),
(4, 'FIRSTBOOK20PERCENT', 'percentage', 20.00, '2026-04-30 23:59:59', 1, 0, 1, 1, '2026-04-08 14:19:20', '2026-04-08 14:19:20'),
(5, 'SAVECALIUAG', 'percentage', 10.00, '2026-04-13 23:59:59', NULL, 0, 1, 1, '2026-04-13 05:06:10', '2026-04-13 05:06:10');

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
  `status` enum('active','inactive') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT '1',
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `scope` enum('global','host','branch') DEFAULT 'global',
  `host_id` int DEFAULT NULL,
  `branch_id` int DEFAULT NULL,
  `min_booking_amount` decimal(10,2) DEFAULT '0.00',
  `max_discount` decimal(10,2) DEFAULT NULL,
  `per_user_limit` int DEFAULT '1',
  PRIMARY KEY (`promo_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `promo_codes`
--

INSERT INTO `promo_codes` (`promo_id`, `code`, `discount_type`, `discount_value`, `usage_limit`, `used_count`, `expiration_date`, `created_at`, `status`, `is_active`, `valid_from`, `valid_until`, `scope`, `host_id`, `branch_id`, `min_booking_amount`, `max_discount`, `per_user_limit`) VALUES
(1, 'SAVE10', 'percentage', 10.00, NULL, 0, NULL, '2026-04-06 14:41:45', 'active', 1, NULL, NULL, 'global', NULL, NULL, 0.00, NULL, 1),
(2, 'SAVE100', 'fixed', 100.00, 1, 0, '2026-04-15', '2026-04-06 14:41:45', 'active', 1, NULL, NULL, 'global', NULL, NULL, 0.00, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `promo_usage`
--

DROP TABLE IF EXISTS `promo_usage`;
CREATE TABLE IF NOT EXISTS `promo_usage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `promo_id` int NOT NULL,
  `user_id` int NOT NULL,
  `reservation_id` int NOT NULL,
  `used_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `promo_id` (`promo_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
-- Table structure for table `request_throttles`
--

DROP TABLE IF EXISTS `request_throttles`;
CREATE TABLE IF NOT EXISTS `request_throttles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int DEFAULT '1',
  `first_hit` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_hit` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_endpoint` (`ip_address`,`endpoint`),
  KEY `idx_user_endpoint` (`user_id`,`endpoint`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_throttles`
--

INSERT INTO `request_throttles` (`id`, `ip_address`, `user_id`, `endpoint`, `hits`, `first_hit`, `last_hit`) VALUES
(27, '::1', NULL, 'login', 2, '2026-04-19 17:25:34', '2026-04-19 17:31:07'),
(28, '::1', 24, 'messaging_api', 8, '2026-04-19 17:41:41', '2026-04-19 17:42:00'),
(29, '::1', 10, 'messaging_api', 2, '2026-04-19 17:42:14', '2026-04-19 17:42:14'),
(30, '::1', 10, 'messaging_api', 2, '2026-04-19 17:47:05', '2026-04-19 17:47:05'),
(31, '::1', 24, 'messaging_api', 2, '2026-04-19 17:47:25', '2026-04-19 17:47:25'),
(32, '::1', 10, 'messaging_api', 13, '2026-04-19 17:50:11', '2026-04-19 17:51:33'),
(33, '::1', 24, 'messaging_api', 2, '2026-04-19 17:52:41', '2026-04-19 17:52:41'),
(34, '::1', NULL, 'login', 1, '2026-04-19 18:08:30', '2026-04-19 18:08:30'),
(35, '::1', 9, 'messaging_api', 38, '2026-04-19 18:08:33', '2026-04-19 18:11:55'),
(36, '::1', 9, 'messaging_api', 1, '2026-04-19 18:12:55', '2026-04-19 18:12:55'),
(37, '::1', 9, 'messaging_api', 1, '2026-04-19 18:13:55', '2026-04-19 18:13:55'),
(38, '::1', 9, 'messaging_api', 1, '2026-04-19 18:14:55', '2026-04-19 18:14:55'),
(39, '::1', 9, 'messaging_api', 1, '2026-04-19 18:15:55', '2026-04-19 18:15:55'),
(40, '::1', 9, 'messaging_api', 1, '2026-04-19 18:16:55', '2026-04-19 18:16:55'),
(41, '::1', 9, 'messaging_api', 1, '2026-04-19 18:17:55', '2026-04-19 18:17:55'),
(42, '::1', 9, 'messaging_api', 1, '2026-04-19 18:18:55', '2026-04-19 18:18:55'),
(43, '::1', 9, 'messaging_api', 1, '2026-04-19 18:19:55', '2026-04-19 18:19:55'),
(44, '::1', NULL, 'login', 1, '2026-04-19 18:20:06', '2026-04-19 18:20:06'),
(45, '::1', 9, 'messaging_api', 1, '2026-04-19 18:20:55', '2026-04-19 18:20:55'),
(46, '::1', 9, 'messaging_api', 2, '2026-04-19 18:22:07', '2026-04-19 18:22:55'),
(47, '::1', 9, 'messaging_api', 1, '2026-04-19 18:23:55', '2026-04-19 18:23:55'),
(48, '::1', 9, 'messaging_api', 1, '2026-04-19 18:24:55', '2026-04-19 18:24:55'),
(49, '::1', 9, 'messaging_api', 1, '2026-04-19 18:25:55', '2026-04-19 18:25:55'),
(50, '::1', 9, 'messaging_api', 1, '2026-04-19 18:26:55', '2026-04-19 18:26:55'),
(51, '::1', 9, 'messaging_api', 1, '2026-04-19 18:27:55', '2026-04-19 18:27:55'),
(52, '::1', 10, 'messaging_api', 4, '2026-04-19 18:28:06', '2026-04-19 18:28:11'),
(53, '::1', 9, 'messaging_api', 1, '2026-04-19 18:28:55', '2026-04-19 18:28:55'),
(54, '::1', 9, 'messaging_api', 4, '2026-04-19 18:29:55', '2026-04-19 18:30:47'),
(55, '::1', NULL, 'login', 2, '2026-04-20 00:32:46', '2026-04-20 00:33:12'),
(56, '::1', NULL, 'login', 1, '2026-04-20 00:43:41', '2026-04-20 00:43:41');

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
  `partial_payment_amount` decimal(10,2) DEFAULT '0.00',
  `security_deposit` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','confirmed','checked_in','checked_out','cancelled','expired','completed') DEFAULT 'pending',
  `hold_expiry` timestamp NULL DEFAULT NULL,
  `payment_status` enum('pending','partial_paid','paid','failed','refunded') DEFAULT 'pending',
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
  `base_amount_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amenities_amount_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `extra_guest_amount_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `platform_fee_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  `host_amount_snapshot` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`reservation_id`),
  KEY `user_id` (`user_id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `user_id`, `unit_id`, `branch_id`, `check_in_date`, `check_out_date`, `total_amount`, `partial_payment_amount`, `security_deposit`, `status`, `hold_expiry`, `payment_status`, `special_requests`, `created_at`, `updated_at`, `host_notes`, `admin_notes`, `cancellation_reason`, `approved_by`, `approved_at`, `renter_rating`, `renter_feedback`, `government_id_path`, `promo_code`, `discount_amount`, `base_amount_snapshot`, `amenities_amount_snapshot`, `extra_guest_amount_snapshot`, `platform_fee_snapshot`, `host_amount_snapshot`) VALUES
(1, 9, 29, 6, '2026-03-25', '2026-03-26', 5000.00, 0.00, 0.00, 'cancelled', NULL, 'pending', '', '2026-03-23 06:56:45', '2026-03-31 09:57:47', NULL, NULL, 'Host cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(2, 9, 29, 6, '2026-04-01', '2026-04-13', 60000.00, 0.00, 0.00, 'confirmed', NULL, 'pending', '', '2026-03-31 10:04:15', '2026-04-02 05:34:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(3, 9, 30, 6, '2026-04-04', '2026-04-06', 8000.00, 0.00, 0.00, 'pending', NULL, 'pending', '', '2026-04-02 10:27:35', '2026-04-02 10:27:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(4, 9, 30, 6, '2026-04-09', '2026-04-11', 8000.00, 0.00, 0.00, 'confirmed', NULL, 'pending', '', '2026-04-08 16:58:56', '2026-04-12 15:18:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(5, 19, 32, 6, '2026-04-13', '2026-04-21', 266.67, 0.00, 0.00, 'checked_in', NULL, 'pending', '', '2026-04-13 02:30:47', '2026-04-13 02:33:04', NULL, NULL, NULL, 10, '2026-04-13 10:31:02', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(6, 9, 30, 6, '2026-04-13', '2026-04-22', 1200.00, 0.00, 0.00, 'pending', NULL, 'pending', '', '2026-04-13 05:01:24', '2026-04-13 05:01:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(8, 9, 29, 6, '2026-04-17', '2026-04-28', 1833.33, 0.00, 0.00, 'confirmed', NULL, 'pending', '', '2026-04-16 13:41:15', '2026-04-16 13:41:34', NULL, NULL, NULL, 10, '2026-04-16 21:41:34', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(9, 9, 33, 6, '2026-04-22', '2026-04-23', 123.00, 0.00, 0.00, 'confirmed', NULL, 'pending', '', '2026-04-16 16:12:46', '2026-04-17 02:23:19', NULL, NULL, NULL, 10, '2026-04-17 10:23:19', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(10, 9, 31, 6, '2026-04-22', '2026-04-29', 14000.00, 0.00, 100.00, 'cancelled', '2026-04-16 16:40:32', 'pending', '', '2026-04-16 16:30:32', '2026-04-17 02:20:58', NULL, NULL, 'Automated: Payment hold expired (10-minute limit exceeded)', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(11, 9, 31, 6, '2026-04-22', '2026-04-23', 2000.00, 0.00, 100.00, 'cancelled', '2026-04-17 02:31:48', 'pending', '', '2026-04-17 02:21:48', '2026-04-17 03:11:02', NULL, NULL, 'Automated: Payment hold expired (10-minute limit exceeded)', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(12, 23, 31, 6, '2026-04-22', '2026-04-23', 2000.00, 0.00, 100.00, 'cancelled', '2026-04-17 03:21:13', 'pending', '', '2026-04-17 03:11:13', '2026-04-17 07:13:42', NULL, NULL, 'Automated: Payment hold expired (10-minute limit exceeded)', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(13, 23, 31, 6, '2026-04-18', '2026-04-19', 2000.00, 0.00, 100.00, 'cancelled', '2026-04-17 03:26:25', 'pending', '', '2026-04-17 03:16:25', '2026-04-17 07:13:42', NULL, NULL, 'Automated: Payment hold expired (10-minute limit exceeded)', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(14, 23, 33, 6, '2026-04-18', '2026-04-19', 123.00, 0.00, 0.00, 'cancelled', '2026-04-17 03:28:08', 'pending', '', '2026-04-17 03:18:08', '2026-04-17 07:13:42', NULL, NULL, 'Automated: Payment hold expired (10-minute limit exceeded)', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(15, 23, 34, 4, '2026-04-17', '2026-04-18', 5000.00, 0.00, 0.00, 'confirmed', '2026-04-17 07:24:00', 'pending', '', '2026-04-17 07:14:00', '2026-04-17 07:18:23', NULL, NULL, NULL, 24, '2026-04-17 15:18:23', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(16, 9, 34, 4, '2026-04-18', '2026-04-19', 5000.00, 0.00, 0.00, 'cancelled', '2026-04-17 07:28:12', 'pending', '', '2026-04-17 07:18:12', '2026-04-17 07:19:21', NULL, NULL, 'di ka pasok sa standards ko.', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00);

--
-- Triggers `reservations`
--
DROP TRIGGER IF EXISTS `trg_reservations_audit`;
DELIMITER $$
CREATE TRIGGER `trg_reservations_audit` AFTER UPDATE ON `reservations` FOR EACH ROW BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NULL, 'status_change', 'reservation', NEW.reservation_id, OLD.status, NEW.status, CONCAT('Reservation status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
    
    IF OLD.payment_status <> NEW.payment_status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NULL, 'payment_status_change', 'reservation', NEW.reservation_id, OLD.payment_status, NEW.payment_status, CONCAT('Payment status changed from ', OLD.payment_status, ' to ', NEW.payment_status));
    END IF;
END
$$
DELIMITER ;

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
-- Table structure for table `search_logs`
--

DROP TABLE IF EXISTS `search_logs`;
CREATE TABLE IF NOT EXISTS `search_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `search_query` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`,`created_at`),
  KEY `user_id` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seasons`
--

DROP TABLE IF EXISTS `seasons`;
CREATE TABLE IF NOT EXISTS `seasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('weekend','holiday','custom') NOT NULL DEFAULT 'custom',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `adjustment_type` enum('fixed','percentage') NOT NULL,
  `adjustment_value` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_cache`
--

DROP TABLE IF EXISTS `system_cache`;
CREATE TABLE IF NOT EXISTS `system_cache` (
  `cache_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cache_value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cache_key`),
  KEY `idx_expiry` (`expires_at`),
  KEY `idx_expiry_tag` (`expires_at`,`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_cache`
--

INSERT INTO `system_cache` (`cache_key`, `cache_value`, `expires_at`, `updated_at`) VALUES
('admin_dashboard_stats', 'a:7:{s:10:\"totalUsers\";i:23;s:13:\"totalBranches\";i:7;s:10:\"totalUnits\";i:20;s:17:\"totalReservations\";i:15;s:12:\"totalRevenue\";d:75223;s:11:\"bookedUnits\";i:5;s:11:\"bookingRate\";d:25;}', '2026-04-20 01:01:04', '2026-04-20 01:00:34');

-- --------------------------------------------------------

--
-- Table structure for table `system_errors`
--

DROP TABLE IF EXISTS `system_errors`;
CREATE TABLE IF NOT EXISTS `system_errors` (
  `error_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `error_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_context` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line_number` int DEFAULT NULL,
  `stack_trace` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `is_resolved` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`error_id`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_errors`
--

INSERT INTO `system_errors` (`error_id`, `user_id`, `error_type`, `message`, `file_context`, `line_number`, `stack_trace`, `ip_address`, `user_agent`, `created_at`, `severity`, `is_resolved`) VALUES
(1, NULL, 'Application Error', 'RATE LIMIT LOCKOUT: User/IP throttled on login', NULL, NULL, '{\"ip\":\"::1\",\"user_id\":null,\"limit\":5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 13:43:43', 'high', 0);

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
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(23, 'primary_color', '#09324e', 'color', 'Primary theme color', '2026-04-19 13:58:11'),
(24, 'secondary_color', '#b5cfe8', 'color', 'Secondary theme color', '2026-04-19 13:58:11'),
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
(35, 'custom_message', 'welcome to our system', NULL, NULL, '2026-04-19 13:58:11'),
(42, 'revenue_share_host', '90', 'number', 'Percentage of total amount that goes to the host', '2026-04-19 01:58:59'),
(43, 'revenue_share_admin', '10', 'number', 'Percentage of total amount that stays as platform fee', '2026-04-19 01:58:59'),
(44, 'min_downpayment_pct', '50', 'number', 'Minimum downpayment percentage required to hold a booking', '2026-04-19 01:58:59'),
(45, 'last_cron_run', '2026-04-19 09:58:59', 'datetime', 'Timestamp of the last successful automation run', '2026-04-19 01:58:59'),
(46, 'system_defense_level', 'high', 'select', 'Current security posture (low/medium/high)', '2026-04-19 01:58:59'),
(47, 'min_search_length', '3', 'number', 'Minimum characters required for search queries', '2026-04-19 02:01:41'),
(48, 'max_search_results', '50', 'number', 'Pagination limit for search to prevent memory exhaustion', '2026-04-19 02:01:41'),
(49, 'admin_health_threshold', '95', 'number', 'Percentage of uptime/success required for Green health status', '2026-04-19 02:01:41');

-- --------------------------------------------------------

--
-- Table structure for table `throttling`
--

DROP TABLE IF EXISTS `throttling`;
CREATE TABLE IF NOT EXISTS `throttling` (
  `throttle_id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int DEFAULT '1',
  `last_hit` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lockout_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`throttle_id`),
  UNIQUE KEY `type_identifier` (`type`,`identifier`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `throttling`
--

INSERT INTO `throttling` (`throttle_id`, `type`, `identifier`, `hits`, `last_hit`, `lockout_until`) VALUES
(1, 'search', 'ip_', 6, '2026-04-19 02:18:55', '2026-04-19 02:28:55'),
(2, 'search', 'user_23', 1, '2026-04-19 11:55:57', NULL),
(3, 'search', 'ip_::1', 1, '2026-04-19 16:03:29', NULL),
(4, 'search', 'user_17', 3, '2026-04-19 17:25:26', NULL),
(5, 'search', 'user_9', 1, '2026-04-19 17:52:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `unit_id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `property_type` varchar(50) DEFAULT 'Condo',
  `bed_config` varchar(50) DEFAULT 'Studio',
  `bed_details` text,
  `bathroom_count` decimal(3,1) DEFAULT '1.0',
  `floor_area` decimal(10,2) DEFAULT NULL,
  `check_in_time` time DEFAULT '14:00:00',
  `check_out_time` time DEFAULT '12:00:00',
  `min_stay` int DEFAULT '1',
  `max_stay` int DEFAULT '30',
  `unit_number` varchar(20) NOT NULL,
  `unit_type` varchar(50) NOT NULL,
  `floor_number` int DEFAULT NULL,
  `monthly_rate` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) DEFAULT '0.00',
  `house_rules` text,
  `utility_info` text,
  `parking_info` varchar(100) DEFAULT 'None',
  `booking_type` enum('instant','manual') DEFAULT 'instant',
  `status_visibility` enum('active','hidden','maintenance') DEFAULT 'active',
  `is_available` tinyint(1) DEFAULT '1',
  `description` text,
  `price_per_night` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_per_month` decimal(10,2) NOT NULL DEFAULT '0.00',
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
  `pricing_type` enum('nightly','monthly') NOT NULL DEFAULT 'nightly',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by_role` varchar(20) DEFAULT NULL,
  `updated_by_id` int DEFAULT NULL,
  `extra_guests_allowed` int DEFAULT '0',
  `extra_guest_fee` decimal(10,2) DEFAULT '0.00',
  `max_capacity` int DEFAULT '2',
  PRIMARY KEY (`unit_id`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_unit_name` (`unit_name`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_id`, `branch_id`, `property_type`, `bed_config`, `bed_details`, `bathroom_count`, `floor_area`, `check_in_time`, `check_out_time`, `min_stay`, `max_stay`, `unit_number`, `unit_type`, `floor_number`, `monthly_rate`, `security_deposit`, `house_rules`, `utility_info`, `parking_info`, `booking_type`, `status_visibility`, `is_available`, `description`, `price_per_night`, `price_per_month`, `building_name`, `street_address`, `city`, `address_hash`, `latitude`, `longitude`, `max_occupancy`, `created_at`, `bedrooms`, `host_id`, `unit_name`, `instant_booking`, `approval_status`, `rejection_reason`, `sqm`, `bed_type`, `num_beds`, `num_bathrooms`, `pricing_type`, `updated_at`, `updated_by_role`, `updated_by_id`, `extra_guests_allowed`, `extra_guest_fee`, `max_capacity`) VALUES
(1, 1, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'A101', 'Studio', NULL, 15000.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 0, NULL, 0.00, 15000.00, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2026-01-21 01:45:52', NULL, NULL, NULL, 1, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(2, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'B101', 'Studio', NULL, 18000.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 0, NULL, 0.00, 18000.00, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2026-01-21 01:45:52', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(3, 1, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, '142', '3 Bedrooms', 6, 2500.00, 15.00, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 2500.00, NULL, NULL, NULL, NULL, NULL, NULL, 4, '2026-03-10 11:32:02', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(4, 1, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, '142', '3 Bedrooms', 6, 2500.00, 15.00, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 2500.00, NULL, NULL, NULL, NULL, NULL, NULL, 4, '2026-03-10 11:35:19', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(5, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 11:50:57', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(6, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 11:51:10', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(7, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 11:51:56', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(8, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:09:18', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(9, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:13:09', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(10, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:13:29', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(11, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:13:55', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(12, 2, 'Condo', 'Studio', NULL, 1.0, NULL, '14:00:00', '12:00:00', 1, 30, 'w202', '3 Bedrooms', 1, 422.00, 600.01, NULL, NULL, 'None', 'instant', 'active', 0, '', 0.00, 422.00, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-03-10 12:29:43', NULL, NULL, NULL, 0, 'approved', NULL, NULL, NULL, 1, 1, 'monthly', '2026-04-12 19:01:21', NULL, NULL, 0, 0.00, 2),
(29, 6, 'Condo', 'Studio', NULL, 10.0, 80.00, '14:00:00', '12:00:00', 1, 30, 'f20', '', NULL, 5000.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 1, '', 0.00, 5000.00, NULL, '153 sanciangco street', 'malabon', NULL, 14.67087800, 120.96074500, 4, '2026-03-23 06:55:09', NULL, 10, 'Penthouse', 0, 'approved', NULL, 80.00, 'double deck', 4, 10, 'monthly', '2026-04-20 00:37:59', NULL, NULL, 0, 0.00, 2),
(30, 6, 'Condo', 'Studio', NULL, 2.0, 40.00, '14:00:00', '12:00:00', 1, 30, 'suite10', '', NULL, 4000.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 1, '', 0.00, 4000.00, NULL, '555 sanciangco street, catmon', 'malabon city', NULL, 14.66819300, 120.95915300, 11, '2026-04-02 10:25:32', NULL, 10, 'Unit 101', 0, 'approved', NULL, 40.00, 'double deck', 4, 2, 'monthly', '2026-04-20 00:37:59', NULL, NULL, 0, 0.00, 2),
(31, 6, 'Condo', 'Studio', NULL, 2.0, 60.00, '14:00:00', '12:00:00', 1, 30, 'm307', 'Executive Suite', 5, 2000.00, 100.00, NULL, NULL, 'None', 'instant', 'active', 1, 'lklklkl', 2000.00, 4000.00, NULL, 'Balut', 'Malabón', NULL, 14.65740300, 120.95914500, 5, '2026-04-09 13:17:24', NULL, 0, 'unit302', 0, 'approved', NULL, 60.00, 'queen', 2, 2, 'nightly', '2026-04-20 00:37:59', 'admin', 1, 0, 0.00, 2),
(32, 6, 'Condo', 'Studio', NULL, 1.0, 35.00, '14:00:00', '12:00:00', 1, 30, 'unit 103', 'Studio', 2, 0.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 1, '', 0.00, 1000.00, NULL, 'Emerald Avenue', 'Pásig', NULL, 14.58926500, 121.06258500, 11, '2026-04-12 19:42:35', NULL, 10, 'equiste exclusive unit', 0, 'approved', NULL, 35.00, 'queen', 1, 1, 'monthly', '2026-04-20 00:37:59', NULL, NULL, 0, 0.00, 2),
(33, 6, 'Condo', 'Studio', NULL, 1.0, 15.00, '14:00:00', '12:00:00', 1, 30, 'gsgsgs', '', NULL, 0.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 1, '', 123.00, 0.00, NULL, 'Doctor Lucio Chua Tan Senior Avenue', 'Pásig', NULL, 14.60049500, 121.08351600, 6, '2026-04-16 11:56:07', NULL, 10, 'faf', 0, 'approved', NULL, 15.00, 'single', 1, 1, 'nightly', '2026-04-20 00:37:59', NULL, NULL, 0, 0.00, 2),
(34, 4, 'Condo', 'Studio', NULL, 1.0, 34.50, '14:00:00', '12:00:00', 1, 30, '303', '', NULL, 0.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 1, '', 5000.00, 0.00, NULL, 'Yakal Street', 'Quezon City', NULL, 14.62328500, 121.01177200, 15, '2026-04-17 07:10:12', NULL, 24, 'Unit', 0, 'approved', NULL, 34.50, 'single', 1, 1, 'nightly', '2026-04-20 00:37:59', NULL, NULL, 0, 0.00, 2),
(35, 6, 'Condo', 'Studio', NULL, 1.0, 56.00, '14:00:00', '12:00:00', 1, 30, '506', '', NULL, 0.00, 0.00, NULL, NULL, 'None', 'instant', 'active', 0, '0', 5600.00, 0.00, NULL, '0', '', NULL, 14.59800300, 121.06275600, 8, '2026-04-19 11:52:37', NULL, 10, 'unit506', 0, 'pending', NULL, 56.00, '0', 8, 1, '', '2026-04-20 00:37:59', NULL, NULL, 0, 0.00, 2),
(36, 1, 'Room', 'Shared', '1', 1.0, NULL, '14:00:00', '12:00:00', 1, 30, '5g', '', NULL, 0.00, 0.00, '', '', 'None', 'manual', 'active', 1, 'n', 0.00, 0.00, NULL, 'san nicolas', '', NULL, 14.59903307, 120.97085381, 1, '2026-04-20 00:46:34', NULL, 10, 'sample terminal', 0, 'pending', NULL, NULL, NULL, 1, 1, 'nightly', '2026-04-20 00:46:34', NULL, NULL, 0, 0.00, 2);

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `unit_addons`
--

INSERT INTO `unit_addons` (`addon_id`, `unit_id`, `name`, `price`, `is_active`, `created_at`) VALUES
(1, 29, 'pins', 50.00, 1, '2026-04-19 18:34:33');

-- --------------------------------------------------------

--
-- Table structure for table `unit_amenities`
--

DROP TABLE IF EXISTS `unit_amenities`;
CREATE TABLE IF NOT EXISTS `unit_amenities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `amenity_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_id` (`unit_id`),
  KEY `amenity_id` (`amenity_id`)
) ENGINE=MyISAM AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `unit_amenities`
--

INSERT INTO `unit_amenities` (`id`, `unit_id`, `amenity_id`) VALUES
(1, 31, 1),
(2, 31, 2),
(3, 31, 3),
(4, 31, 4),
(5, 31, 5),
(15, 32, 5),
(14, 32, 4),
(13, 32, 3),
(12, 32, 2),
(11, 32, 1),
(16, 29, 2),
(17, 29, 4),
(18, 33, 1),
(19, 33, 2),
(20, 33, 3),
(21, 33, 4),
(22, 33, 5),
(23, 34, 1),
(24, 34, 2),
(25, 34, 3),
(26, 34, 4),
(27, 34, 5),
(42, 35, 5),
(41, 35, 4),
(40, 35, 3),
(39, 35, 2),
(38, 35, 1),
(43, 36, 1),
(44, 36, 2),
(45, 36, 3),
(46, 36, 4),
(47, 36, 5);

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `unit_blackouts`
--

INSERT INTO `unit_blackouts` (`blackout_id`, `unit_id`, `start_date`, `end_date`, `reason`, `created_at`) VALUES
(1, 31, '2026-04-09', '2026-04-25', 'maintenance', '2026-04-09 13:20:01'),
(2, 29, '2026-04-01', '2026-04-24', '', '2026-04-19 18:33:50'),
(3, 29, '2026-04-01', '2026-04-25', 'maintenance', '2026-04-19 18:34:13');

-- --------------------------------------------------------

--
-- Table structure for table `unit_blocked_dates`
--

DROP TABLE IF EXISTS `unit_blocked_dates`;
CREATE TABLE IF NOT EXISTS `unit_blocked_dates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(108, 31, '../uploads/unit_images/unit_31_1775740683_5148.jpg', NULL, NULL, '2026-04-09 13:18:03', 0, NULL, '2026-04-09 13:18:03'),
(109, 32, '../uploads/unit_images/unit_32_1776044803_6410.png', NULL, NULL, '2026-04-13 01:46:43', 0, NULL, '2026-04-13 01:46:43'),
(110, 32, '../uploads/unit_images/unit_32_1776044803_5569.png', NULL, NULL, '2026-04-13 01:46:43', 0, NULL, '2026-04-13 01:46:43'),
(111, 32, '../uploads/unit_images/unit_32_1776044803_3923.jpg', NULL, NULL, '2026-04-13 01:46:43', 0, NULL, '2026-04-13 01:46:43'),
(112, 32, '../uploads/unit_images/unit_32_1776044803_2545.jpg', NULL, NULL, '2026-04-13 01:46:43', 0, NULL, '2026-04-13 01:46:43'),
(113, 32, '../uploads/unit_images/unit_32_1776044803_2028.png', NULL, NULL, '2026-04-13 01:46:43', 0, NULL, '2026-04-13 01:46:43'),
(114, 34, '../uploads/unit_images/unit_34_1776409812_4956.jpg', NULL, NULL, '2026-04-17 07:10:12', 0, NULL, '2026-04-17 07:10:12'),
(115, 34, '../uploads/unit_images/unit_34_1776409812_9474.jpg', NULL, NULL, '2026-04-17 07:10:12', 0, NULL, '2026-04-17 07:10:12'),
(116, 34, '../uploads/unit_images/unit_34_1776409812_7531.jpg', NULL, NULL, '2026-04-17 07:10:12', 0, NULL, '2026-04-17 07:10:12'),
(117, 34, '../uploads/unit_images/unit_34_1776409812_1215.jpg', NULL, NULL, '2026-04-17 07:10:12', 0, NULL, '2026-04-17 07:10:12'),
(118, 35, '../uploads/unit_images/unit_35_1776599557_5196.jpg', NULL, NULL, '2026-04-19 11:52:37', 0, NULL, '2026-04-19 11:52:37'),
(119, 35, '../uploads/unit_images/unit_35_1776599557_5415.jpg', NULL, NULL, '2026-04-19 11:52:37', 0, NULL, '2026-04-19 11:52:37'),
(120, 35, '../uploads/unit_images/unit_35_1776599557_9162.jpg', NULL, NULL, '2026-04-19 11:52:37', 0, NULL, '2026-04-19 11:52:37'),
(121, 35, '../uploads/unit_images/unit_35_1776599557_6698.jpg', NULL, NULL, '2026-04-19 11:52:37', 0, NULL, '2026-04-19 11:52:37'),
(122, 35, '../uploads/unit_images/unit_35_1776599557_5653.jpg', NULL, NULL, '2026-04-19 11:52:37', 0, NULL, '2026-04-19 11:52:37');

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
  `role` enum('admin','host','renter','manager','pending_host') DEFAULT 'renter',
  `branch_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `terms_accepted` tinyint(1) DEFAULT '0',
  `is_suspended` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `address` text,
  `profile_picture` varchar(255) DEFAULT NULL,
  `login_method` varchar(20) DEFAULT 'email',
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `verification_status` enum('none','pending','verified','rejected') DEFAULT 'none',
  `can_message` tinyint(1) DEFAULT '1',
  `last_urgent_popup_shown` timestamp NULL DEFAULT NULL,
  `message_window_start` timestamp NULL DEFAULT NULL,
  `message_window_count` int DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_full_name` (`full_name`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `role`, `branch_id`, `is_active`, `terms_accepted`, `is_suspended`, `created_at`, `last_login`, `updated_at`, `address`, `profile_picture`, `login_method`, `reset_token`, `token_expiry`, `verification_status`, `can_message`, `last_urgent_popup_shown`, `message_window_start`, `message_window_count`) VALUES
(1, 'System Administrator', 'admin@bookit.com', '$2y$12$AYpgLYJaWCzNyNyL.2lbDeJcOi/TkaTfmOywIDemsndYSb2p8SYaC', NULL, 'admin', NULL, 1, 0, 0, '2026-01-21 01:45:52', NULL, '2026-01-21 01:45:52', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(3, 'Test User 1', 'jersonedanao2002@gmail.com', '$2y$10$tAebel8lIeI8nR9nyyslCOF6LlZpqMUflF28lG758Wsm.dElN5a6S', '09123456789', 'renter', NULL, 1, 0, 0, '2026-01-21 03:43:59', NULL, '2026-01-24 07:31:35', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(4, 'Test 1', 'onedummy53@gmail.com', '$2y$10$bWTCGqvDp0mZAY6bf8uinuLBFX7ydNAI6okAHSKQEKJdjIUx99JRi', '09123456789', '', 1, 0, 0, 0, '2026-01-21 04:29:27', NULL, '2026-01-31 14:54:00', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(5, 'Host 1', 'host1@bookit.com', '$2y$10$tmltabYVkk02FcKKuiMAXutUN3innt69rU9nom9x8Jtjy0gOkvZZO', '09123456789', 'host', 1, 1, 0, 0, '2026-01-27 14:52:17', NULL, '2026-03-22 10:10:42', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(6, 'jackie lou pascual', 'jackieloupascual@gmail.com', '$2y$10$Vd.k0JLQJ4yW/QpdoBAY7.6rUsqljPdESD.7KfUUuUjC2npgMPHh.', '09054289264', '', NULL, 1, 0, 0, '2026-02-01 01:31:27', NULL, '2026-02-01 10:43:01', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(7, 'jackie lou pascual', 'paullexxusantonio@gmail.com', '$2y$10$fbAw5Z0N1L60gTrG52DZI.dwcQS.OkXrnKDpmj78tAZCYNEXX2Q5W', '09054289264', '', 2, 1, 0, 0, '2026-02-01 10:45:19', NULL, '2026-03-23 07:03:40', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(8, 'jackie lou pascual', 'paullexxusantonio20@gmail.com', '$2y$10$64n0KiFSYsJ6sTGQw1aycOoVihENSLYqvrdie9L4QSf4FxKFUZwny', '09054289264', 'admin', NULL, 1, 0, 0, '2026-02-01 10:45:50', NULL, '2026-02-22 02:23:36', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(9, 'Antonio, Paul Lexxus B.', 'p2235361@gmail.com', '$2y$10$fAZ.R874fvFM/re280G85uf9FRapmj9ZFxhgYo7zFxVLNnKx0B4wm', '1230456789', 'renter', NULL, 1, 0, 0, '2026-02-22 02:25:58', NULL, '2026-04-16 13:46:46', '', 'profile_9_69e0e8420ec0c.jpg', 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(10, 'jackie', 'jackiepascual@bookit.com', '$2y$10$ymI/EQN2g1UnX.naHJYJhuTKMDjymtdUAac5EdZsfqI4UN6U8DiO2', '123456789', 'host', 6, 1, 0, 0, '2026-02-22 04:14:49', NULL, '2026-04-19 17:54:53', NULL, 'host_10_69e516ed93212.jpg', 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(11, 'jackie', 'jackie@bookit.com', '$2y$10$gFE7a8xZ5NiGCsgd1CWOfu/4Ez1cjpIq.mB1LnB1v5eHfttg..vim', '789456123', 'host', 2, 1, 0, 0, '2026-02-22 04:15:32', NULL, '2026-03-22 03:56:39', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(12, 'paul', 'paul1234@bookit.com', '$2y$10$T9Oezy91p2mRVjT73miy7u6L80/koJ5H97IdNylqYaUWgUNMx/k4G', '88944556456', 'host', 3, 1, 0, 0, '2026-03-07 06:54:39', NULL, '2026-03-22 04:03:20', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(13, 'kunwari lang', 'adminhost@bookit.com', '$2y$10$TMg3x.zSqVLr8axCTIdUMuhv5lEmej6J4WUcxTicisG6M5COLx6T.', '0908543307762', 'host', 5, 1, 0, 0, '2026-03-22 06:08:56', NULL, '2026-03-22 10:10:55', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(14, 'Caps Of all trades', 'capsofalltrades@gmail.com', '$2y$10$L60Z3IlGB3nqfiMDbW0oaOyWNvmCIKVW1HWNvjvlfU7PYKsUpOM3W', '09123456789', 'renter', NULL, 1, 0, 0, '2026-04-08 17:16:01', NULL, '2026-04-08 17:16:01', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(15, 'Vanessa Hudges', 'vanessahudges@gmail.com', '$2y$10$ypu0GnWV5Yb1fgFDQBN87OwFq9UW60T48qs7pqqcWMQQB0FzKftY.', '09054289261', 'host', NULL, 1, 0, 0, '2026-04-09 18:02:56', NULL, '2026-04-09 18:03:49', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(16, 'Peter Benjamin Parker', 'peterparker@gmail.com', '$2y$10$n.k9Nn.XAyBw99HOgHHA1.20fb1fIDhjhCA2mUaVoVelg1GLGsHl.', '09876543210', 'host', NULL, 1, 0, 0, '2026-04-09 18:22:12', NULL, '2026-04-10 02:56:58', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(17, 'Antonio, Paul Lexxus B.', 'pao@gmail.com', '$2y$10$7ld2f6.Fog.yZH0vMsmcUevqX.DPNmduTNPNpe8G0HBmaAscC108u', '89456', 'renter', NULL, 1, 0, 0, '2026-04-09 19:13:52', NULL, '2026-04-19 13:43:28', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(18, 'Antonio, Paul Lexxus B.', 'yg@gmail.com', '$2y$10$FtKrd13hZtEFurMdqi4EGOowxesvioYFKTC9cZ2iXrDN3Z2z4n7eC', '09054289264', 'host', NULL, 1, 0, 0, '2026-04-09 19:38:12', NULL, '2026-04-09 19:39:43', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(19, 'Anthony Stark', 'tonystark@gmail.com', '$2y$10$kRFmYdr/ZXhdRBVMrNGv/el82bU5VyEN8yXK7fqC4nmckGHIZFvtC', '09123457698', 'renter', NULL, 1, 0, 0, '2026-04-10 03:01:35', NULL, '2026-04-10 03:01:35', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(20, 'founder of all', 'founnder@gmail.com', '$2y$10$jd49h1oTnkxlWR.HaPE6AOhoO1PBbsiVOEQDHiPaTxnntmGr1rU3u', '09616613640', 'host', NULL, 1, 0, 0, '2026-04-11 16:00:15', NULL, '2026-04-11 16:03:11', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(21, 'Leonardo Cabajar', 'cabajar@gmail.com', '$2y$10$aDvHx2.J4DfHuhDxwBw4du35WM36kPQCKdPtO6JOs55LSTuE2EXvy', '0987654321', 'host', NULL, 1, 0, 0, '2026-04-13 06:09:33', NULL, '2026-04-13 06:21:39', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(22, 'Mark Herras', 'caliuagking@gmail.com', '$2y$10$r6eReMPensaSbhj2JN/yHuW4IwjFV/pT6VSdTAbZmLNFzHpE66Rwa', '09271402822', 'renter', NULL, 1, 0, 0, '2026-04-16 13:29:18', NULL, '2026-04-16 13:29:18', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(23, 'Antonio, Paul Lexxus B.', 'p261@gmail.com', '$2y$10$h.0VEFtoruueb4laxsRCfeu0u9mNoZVOy6Q4aNc6mS8wRvzeuTJvm', '88944556456', 'renter', NULL, 1, 0, 0, '2026-04-17 03:09:17', NULL, '2026-04-17 03:09:17', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(24, 'Monkey D. Luffy', 'monkeydluffy@gmail.com', '$2y$10$DDBtsIky1/tQiIEG0TqV1.Qzy8shkYheiJ0dHuXYqSv2qv2Uxwfbi', '09123456789', 'host', NULL, 1, 0, 0, '2026-04-17 07:05:19', NULL, '2026-04-17 07:06:50', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0),
(25, 'Test User', 'testuser99@test.com', '$2y$10$dMwKSXQ1jmzT.21Hxr2k4OMBnEIZ3ZVGKZusrgB39FXjR33hYUHtG', '091111111111', 'renter', NULL, 1, 1, 0, '2026-04-19 12:22:07', NULL, '2026-04-19 12:22:07', NULL, NULL, 'email', NULL, NULL, 'none', 1, NULL, NULL, 0);

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
-- Table structure for table `user_blocks`
--

DROP TABLE IF EXISTS `user_blocks`;
CREATE TABLE IF NOT EXISTS `user_blocks` (
  `block_id` int NOT NULL AUTO_INCREMENT,
  `blocker_id` int NOT NULL,
  `blocked_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`block_id`)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `messages`
--
ALTER TABLE `messages` ADD FULLTEXT KEY `message` (`message`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `fk_branch_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_booking_id` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_receiver_id` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_sender_id` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `message_logs`
--
ALTER TABLE `message_logs`
  ADD CONSTRAINT `fk_messagelogs_message_id` FOREIGN KEY (`message_id`) REFERENCES `messages` (`message_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD CONSTRAINT `fk_templates_host_id` FOREIGN KEY (`host_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
-- Constraints for table `seasons`
--
ALTER TABLE `seasons`
  ADD CONSTRAINT `fk_seasons_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `fk_unit_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE;

--
-- Constraints for table `unit_blocked_dates`
--
ALTER TABLE `unit_blocked_dates`
  ADD CONSTRAINT `fk_blocked_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
