<?php
/**
 * BookIT Enhanced Schema Upgrade
 * This script handles database migrations for revenue tracking, payouts, 
 * notification priority, and financial snapshots.
 */

// Define constants if not defined (for standalone execution)
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/constants.php';
}
require_once __DIR__ . '/../config/db.php';

// Skip session check if running from CLI or if a special flag is set for this session
if (php_sapi_name() === 'cli' || $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
    // Allow local access
} else {
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die("Unauthorized access. Admin privileges required.");
    }
}

$conn->begin_transaction();

try {
    echo "Starting migration...\n";

    // 1. Create host_earnings table
    $sql = "CREATE TABLE IF NOT EXISTS `host_earnings` (
        `id` int NOT NULL AUTO_INCREMENT,
        `host_id` int NOT NULL,
        `reservation_id` int NOT NULL,
        `total_booking_amount` decimal(10,2) NOT NULL,
        `platform_fee` decimal(10,2) NOT NULL,
        `host_amount` decimal(10,2) NOT NULL,
        `status` enum('pending_review', 'pending_release', 'available', 'paid_out', 'cancelled') NOT NULL DEFAULT 'pending_review',
        `available_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `host_id` (`host_id`),
        KEY `reservation_id` (`reservation_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->query($sql);
    echo "✔ Table 'host_earnings' created/checked.\n";

    // 2. Create payout_accounts table
    $sql = "CREATE TABLE IF NOT EXISTS `payout_accounts` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `method` varchar(50) NOT NULL,
        `account_name` varchar(100) NOT NULL,
        `account_details` varchar(255) NOT NULL,
        `is_default` tinyint(1) DEFAULT '0',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->query($sql);
    echo "✔ Table 'payout_accounts' created/checked.\n";

    // 3. Create payout_requests table
    $sql = "CREATE TABLE IF NOT EXISTS `payout_requests` (
        `id` int NOT NULL AUTO_INCREMENT,
        `host_id` int NOT NULL,
        `payout_account_id` int NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `status` enum('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
        `admin_notes` text,
        `idempotency_key` varchar(100) NOT NULL UNIQUE,
        `processed_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `host_id` (`host_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->query($sql);
    echo "✔ Table 'payout_requests' created/checked.\n";

    // 4. Update notifications table
    $columns_to_add = [
        ['priority', "enum('normal', 'urgent', 'overdue') DEFAULT 'normal'"],
        ['redirect_url', "varchar(255) DEFAULT NULL"],
        ['related_id', "int DEFAULT NULL"],
        ['is_archived', "tinyint(1) DEFAULT '0'"]
    ];

    foreach ($columns_to_add as $col) {
        $check = $conn->query("SHOW COLUMNS FROM `notifications` LIKE '{$col[0]}'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `{$col[0]}` {$col[1]}");
            echo "✔ Added '{$col[0]}' to 'notifications'.\n";
        }
    }

    // 5. Update reservations table for snapshots
    $columns_to_add = [
        ['base_amount_snapshot', "decimal(10,2) NOT NULL DEFAULT '0.00'"],
        ['amenities_amount_snapshot', "decimal(10,2) NOT NULL DEFAULT '0.00'"],
        ['extra_guest_amount_snapshot', "decimal(10,2) NOT NULL DEFAULT '0.00'"],
        ['platform_fee_snapshot', "decimal(10,2) NOT NULL DEFAULT '0.00'"],
        ['host_amount_snapshot', "decimal(10,2) NOT NULL DEFAULT '0.00'"]
    ];

    foreach ($columns_to_add as $col) {
        $check = $conn->query("SHOW COLUMNS FROM `reservations` LIKE '{$col[0]}'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `reservations` ADD COLUMN `{$col[0]}` {$col[1]}");
            echo "✔ Added '{$col[0]}' to 'reservations'.\n";
        }
    }

    // 6. Update units table
    $columns_to_add = [
        ['extra_guest_fee', "decimal(10,2) NOT NULL DEFAULT '0.00'"],
        ['max_capacity', "int DEFAULT '2'"]
    ];

    foreach ($columns_to_add as $col) {
        $check = $conn->query("SHOW COLUMNS FROM `units` LIKE '{$col[0]}'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `units` ADD COLUMN `{$col[0]}` {$col[1]}");
            echo "✔ Added '{$col[0]}' to 'units'.\n";
        }
    }

    // 7. Update amenities table
    $columns_to_add = [
        ['is_paid', "tinyint(1) DEFAULT '0'"],
        ['price', "decimal(10,2) NOT NULL DEFAULT '0.00'"]
    ];

    foreach ($columns_to_add as $col) {
        $check = $conn->query("SHOW COLUMNS FROM `amenities` LIKE '{$col[0]}'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `amenities` ADD COLUMN `{$col[0]}` {$col[1]}");
            echo "✔ Added '{$col[0]}' to 'amenities'.\n";
        }
    }

    // 8. Financial Immutability Triggers
    $conn->query("DROP TRIGGER IF EXISTS `protect_host_earnings_paid_out` ");
    $conn->query("
        CREATE TRIGGER `protect_host_earnings_paid_out` 
        BEFORE UPDATE ON `host_earnings`
        FOR EACH ROW 
        BEGIN
            IF OLD.status = 'paid_out' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCIAL INTEGRITY ERROR: Paid out earnings are immutable.';
            END IF;
        END;
    ");
    $conn->query("DROP TRIGGER IF EXISTS `prevent_earnings_delete` ");
    $conn->query("
        CREATE TRIGGER `prevent_earnings_delete` 
        BEFORE DELETE ON `host_earnings`
        FOR EACH ROW 
        BEGIN
            IF OLD.status = 'paid_out' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCIAL INTEGRITY ERROR: Cannot delete paid out logs.';
            END IF;
        END;
    ");
    echo "✔ Financial immutability triggers installed.\n";

    $conn->commit();
    echo "🚀 Migration successfully completed!\n";

} catch (Exception $e) {
    if ($conn->connect_errno == 0 && $conn->ping()) {
        $conn->rollback();
    }
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
