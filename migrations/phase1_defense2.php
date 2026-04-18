<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'condo_rental_reservation_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

try {
    $conn->query("ALTER TABLE reservations ADD COLUMN hold_expiry DATETIME NULL AFTER end_date");
    $conn->query("ALTER TABLE reservations ADD COLUMN reservation_status ENUM('pending', 'confirmed', 'cancelled', 'expired', 'completed') DEFAULT 'pending' AFTER status");
    
    $conn->query("ALTER TABLE payments ADD COLUMN payment_proof VARCHAR(255) NULL AFTER payment_method");
    $conn->query("ALTER TABLE payments ADD COLUMN verified_by_admin TINYINT(1) DEFAULT 0 AFTER payment_proof");
    $conn->query("ALTER TABLE payments ADD COLUMN verified_at DATETIME NULL AFTER verified_by_admin");
    $conn->query("ALTER TABLE payments ADD COLUMN transaction_reference VARCHAR(100) NULL AFTER verified_at");

    echo "Migration OK";
} catch(Exception $e) {
    echo "Error (Maybe exists?): " . $e->getMessage();
}
