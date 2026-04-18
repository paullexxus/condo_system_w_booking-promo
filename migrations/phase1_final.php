<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'condo_rental_reservation_db');

try {
    $conn->query("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending','confirmed','checked_in','checked_out','cancelled','expired','completed') DEFAULT 'pending'");
    
    // Check if hold_expiry already exists, if not add it
    $res = $conn->query("SHOW COLUMNS FROM reservations LIKE 'hold_expiry'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN hold_expiry DATETIME NULL AFTER status");
    }

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
        }
    }

    echo "Phase 1 Complete!";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
