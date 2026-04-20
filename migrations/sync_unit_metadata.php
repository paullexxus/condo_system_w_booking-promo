<?php
/**
 * BookIT Unit Metadata Synchronization Script
 * 
 * Safely adds missing high-value property metadata columns to the units table.
 * Idempotent execution: Checks for column existence before attempting ALTER.
 */

include_once __DIR__ . '/../config/db.php';

$required_columns = [
    'property_type' => "VARCHAR(50) DEFAULT 'Condo' AFTER branch_id",
    'bed_config' => "VARCHAR(50) DEFAULT 'Studio' AFTER property_type",
    'bed_details' => "TEXT NULL AFTER bed_config",
    'bathroom_count' => "DECIMAL(3,1) DEFAULT 1.0 AFTER bed_details",
    'floor_area' => "DECIMAL(10,2) NULL AFTER bathroom_count",
    'check_in_time' => "TIME DEFAULT '14:00:00' AFTER floor_area",
    'check_out_time' => "TIME DEFAULT '12:00:00' AFTER check_in_time",
    'min_stay' => "INT DEFAULT 1 AFTER check_out_time",
    'max_stay' => "INT DEFAULT 30 AFTER min_stay",
    'security_deposit' => "DECIMAL(10,2) DEFAULT 0.00 AFTER max_stay",
    'house_rules' => "TEXT NULL AFTER security_deposit",
    'utility_info' => "TEXT NULL AFTER house_rules",
    'parking_info' => "VARCHAR(100) DEFAULT 'None' AFTER utility_info",
    'booking_type' => "ENUM('instant', 'manual') DEFAULT 'instant' AFTER parking_info",
    'status_visibility' => "ENUM('active', 'hidden', 'maintenance') DEFAULT 'active' AFTER booking_type"
];

echo "<h3>Unit Schema Sync Log:</h3><ul>";

$existing_columns = [];
$res = $conn->query("DESCRIBE units");
while($row = $res->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

foreach ($required_columns as $col => $definition) {
    if (!in_array($col, $existing_columns)) {
        $sql = "ALTER TABLE units ADD COLUMN $col $definition";
        if ($conn->query($sql)) {
            echo "<li style='color: green;'>Added column: <strong>$col</strong></li>";
        } else {
            echo "<li style='color: red;'>Error adding $col: " . $conn->error . "</li>";
        }
    } else {
        echo "<li style='color: blue;'>Column already exists: <strong>$col</strong></li>";
    }
}

// Special case: Data migration for legacy columns
if (in_array('sqm', $existing_columns)) {
    $conn->query("UPDATE units SET floor_area = sqm WHERE floor_area IS NULL AND sqm IS NOT NULL");
    echo "<li>Migrated legacy <strong>sqm</strong> data to <strong>floor_area</strong>.</li>";
}
if (in_array('num_bathrooms', $existing_columns)) {
    $conn->query("UPDATE units SET bathroom_count = num_bathrooms WHERE bathroom_count = 1.0 AND num_bathrooms IS NOT NULL");
    echo "<li>Migrated legacy <strong>num_bathrooms</strong> data to <strong>bathroom_count</strong>.</li>";
}

echo "</ul><p>Sync complete. <a href='../host/unit_management.php'>Return to Unit Management</a></p>";
?>
