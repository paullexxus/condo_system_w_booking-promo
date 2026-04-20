<?php
/**
 * BookIT - Fix Payout Schema Migration
 * Standardizes column names in payout_accounts and payouts tables.
 */

require_once __DIR__ . '/../config/db.php';

$conn->begin_transaction();

try {
    echo "Starting schema fix migration...\n";

    // 1. Fix payout_accounts table
    $payout_accounts_check = $conn->query("SHOW COLUMNS FROM `payout_accounts` LIKE 'account_number'");
    if ($payout_accounts_check && $payout_accounts_check->num_rows > 0) {
        echo "Updating payout_accounts: renaming account_number to account_details...\n";
        $conn->query("ALTER TABLE `payout_accounts` CHANGE `account_number` `account_details` VARCHAR(255) NOT NULL");
    } else {
        // Ensure account_details exists if account_number didn't
        $check = $conn->query("SHOW COLUMNS FROM `payout_accounts` LIKE 'account_details'");
        if ($check && $check->num_rows == 0) {
            echo "Updating payout_accounts: adding account_details...\n";
            $conn->query("ALTER TABLE `payout_accounts` ADD COLUMN `account_details` VARCHAR(255) NOT NULL AFTER `account_name`");
        }
    }

    // 2. Fix payouts table
    // Check if host_id exists and needs to be renamed to user_id
    $payouts_host_id = $conn->query("SHOW COLUMNS FROM `payouts` LIKE 'host_id'");
    $payouts_user_id = $conn->query("SHOW COLUMNS FROM `payouts` LIKE 'user_id'");

    if ($payouts_host_id && $payouts_host_id->num_rows > 0 && $payouts_user_id && $payouts_user_id->num_rows == 0) {
        echo "Updating payouts: renaming host_id to user_id...\n";
        $conn->query("ALTER TABLE `payouts` CHANGE `host_id` `user_id` INT NOT NULL");
    } else if ($payouts_user_id && $payouts_user_id->num_rows == 0) {
        echo "Updating payouts: adding user_id...\n";
        $conn->query("ALTER TABLE `payouts` ADD COLUMN `user_id` INT NOT NULL FIRST");
    }

    // Ensure payouts has account_details if needed (some parts of code expect it)
    $payouts_details = $conn->query("SHOW COLUMNS FROM `payouts` LIKE 'account_details'");
    if ($payouts_details && $payouts_details->num_rows == 0) {
        echo "Updating payouts: adding account_details...\n";
        $conn->query("ALTER TABLE `payouts` ADD COLUMN `account_details` TEXT DEFAULT NULL AFTER `amount`");
    }

    $conn->commit();
    echo "✔ Migration completed successfully!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
