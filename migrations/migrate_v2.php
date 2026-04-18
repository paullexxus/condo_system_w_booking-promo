<?php
// migrate_v2.php - Standardize database according to professional refined prompt
require_once '../config/db.php';

echo "Starting Migration v2...\n";

// 1. Update reservations table
echo "Updating reservations table...\n";
// Update payment_status ENUM
$q_status = "ALTER TABLE reservations MODIFY COLUMN payment_status ENUM('pending', 'partial_paid', 'paid', 'failed', 'refunded') DEFAULT 'pending'";
if ($conn->query($q_status)) {
    echo "Successfully updated payment_status ENUM.\n";
} else {
    echo "Error updating payment_status: " . $conn->error . "\n";
}

// Rename expires_at to hold_expiry if needed
if (column_exists('reservations', 'expires_at')) {
    if (!column_exists('reservations', 'hold_expiry')) {
        if ($conn->query("ALTER TABLE reservations CHANGE expires_at hold_expiry TIMESTAMP NULL DEFAULT NULL")) {
            echo "Successfully renamed expires_at to hold_expiry.\n";
        } else {
            echo "Error renaming expires_at: " . $conn->error . "\n";
        }
    } else {
        echo "hold_expiry already exists. Removing redundant expires_at.\n";
        $conn->query("ALTER TABLE reservations DROP COLUMN expires_at");
    }
} else if (!column_exists('reservations', 'hold_expiry')) {
    echo "Creating missing hold_expiry column...\n";
    $conn->query("ALTER TABLE reservations ADD COLUMN hold_expiry TIMESTAMP NULL DEFAULT NULL AFTER status");
}

// 2. Update payments table
echo "Updating payments table...\n";
$queries = [
    "ALTER TABLE payments MODIFY COLUMN payment_method ENUM('gcash', 'paymaya', 'paypal') NOT NULL",
    "ALTER TABLE payments MODIFY COLUMN payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending'",
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Successfully executed: $q\n";
    } else {
        echo "Error executing: $q - " . $conn->error . "\n";
    }
}

// Add transaction_reference if missing
if (!column_exists('payments', 'transaction_reference')) {
    $conn->query("ALTER TABLE payments ADD COLUMN transaction_reference VARCHAR(100) DEFAULT NULL AFTER payment_status");
}

// Remove bank fields if they exist (based on prompt)
if (column_exists('payments', 'bank_name')) {
    $conn->query("ALTER TABLE payments DROP COLUMN bank_name");
}
if (column_exists('payments', 'bank_account')) {
    $conn->query("ALTER TABLE payments DROP COLUMN bank_account");
}

// 3. Update users table roles
echo "Updating users table roles...\n";
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'host', 'renter', 'manager', 'pending_host') DEFAULT 'renter'");

echo "Migration v2 complete.\n";
?>
