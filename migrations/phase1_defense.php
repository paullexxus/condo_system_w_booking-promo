<?php
require_once '../config/db.php';
$conn->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

try {
    echo "Starting Phase 1 Defense Migration...\n";
    
    // Reservations Table
    // Add hold_expiry (if not exists)
    $res = $conn->query("SHOW COLUMNS FROM reservations LIKE 'hold_expiry'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN hold_expiry DATETIME NULL AFTER end_date");
        echo "Added hold_expiry to reservations.\n";
    }
    
    // Add reservation_status ENUM (if not exists)
    $res = $conn->query("SHOW COLUMNS FROM reservations LIKE 'reservation_status'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN reservation_status ENUM('pending', 'confirmed', 'cancelled', 'expired', 'completed') DEFAULT 'pending' AFTER status");
        echo "Added reservation_status to reservations.\n";
    } else {
        // Modify existing enum to ensure right values
        $conn->query("ALTER TABLE reservations MODIFY COLUMN reservation_status ENUM('pending', 'confirmed', 'cancelled', 'expired', 'completed') DEFAULT 'pending'");
        echo "Modified reservation_status to ensure correct attributes.\n";
    }

    // Payments Table
    // Add payment_proof, verified_by_admin, verified_at, transaction_reference
    $colsToAdd = [
        "payment_proof" => "VARCHAR(255) NULL AFTER payment_method",
        "verified_by_admin" => "TINYINT(1) DEFAULT 0 AFTER payment_proof",
        "verified_at" => "DATETIME NULL AFTER verified_by_admin",
        "transaction_reference" => "VARCHAR(100) NULL AFTER verified_at"
    ];
    
    foreach ($colsToAdd as $col => $def) {
        $res = $conn->query("SHOW COLUMNS FROM payments LIKE '$col'");
        if ($res->num_rows == 0) {
            $conn->query("ALTER TABLE payments ADD COLUMN $col $def");
            echo "Added $col to payments.\n";
        }
    }
    
    echo "Success: Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
