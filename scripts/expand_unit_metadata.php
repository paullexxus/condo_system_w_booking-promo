<?php
include_once __DIR__ . '/../config/db.php';

$sql = "ALTER TABLE units 
ADD COLUMN property_type VARCHAR(50) DEFAULT 'Condo' AFTER branch_id,
ADD COLUMN bed_config VARCHAR(50) DEFAULT 'Studio' AFTER property_type,
ADD COLUMN bed_details TEXT NULL AFTER bed_config,
ADD COLUMN bathroom_count DECIMAL(3,1) DEFAULT 1.0 AFTER bed_details,
ADD COLUMN floor_area DECIMAL(10,2) NULL AFTER bathroom_count,
ADD COLUMN check_in_time TIME DEFAULT '14:00:00' AFTER floor_area,
ADD COLUMN check_out_time TIME DEFAULT '12:00:00' AFTER check_in_time,
ADD COLUMN min_stay INT DEFAULT 1 AFTER check_out_time,
ADD COLUMN max_stay INT DEFAULT 30 AFTER min_stay,
ADD COLUMN security_deposit DECIMAL(10,2) DEFAULT 0.00 AFTER max_stay,
ADD COLUMN house_rules TEXT NULL AFTER security_deposit,
ADD COLUMN utility_info TEXT NULL AFTER house_rules,
ADD COLUMN parking_info VARCHAR(100) DEFAULT 'None' AFTER utility_info,
ADD COLUMN booking_type ENUM('instant', 'manual') DEFAULT 'instant' AFTER parking_info,
ADD COLUMN status_visibility ENUM('active', 'hidden', 'maintenance') DEFAULT 'active' AFTER booking_type";

try {
    if ($conn->query($sql)) {
        echo "Units table expanded successfully with 15 new metadata fields.\n";
    } else {
        // Check if already expanded to avoid erroring if rerun
        if (strpos($conn->error, "Duplicate column name") !== false) {
            echo "Units table columns already exist.\n";
        } else {
            echo "Error: " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
