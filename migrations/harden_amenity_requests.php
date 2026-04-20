<?php
include_once __DIR__ . '/../config/db.php';

$sql = "ALTER TABLE booking_addons 
ADD COLUMN status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending' AFTER price,
ADD COLUMN approved_price DECIMAL(10,2) NULL AFTER status,
ADD COLUMN admin_id INT NULL AFTER approved_price,
ADD COLUMN admin_note TEXT NULL AFTER admin_id,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD UNIQUE KEY `unique_res_addon` (`booking_id`, `addon_id`)";

try {
    if ($conn->query($sql)) {
        echo "booking_addons table hardened for approval workflow.\n";
    } else {
        if (strpos($conn->error, "Duplicate column name") !== false || strpos($conn->error, "Duplicate key name") !== false) {
            echo "booking_addons table already hardened.\n";
        } else {
            echo "Migration Error: " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
