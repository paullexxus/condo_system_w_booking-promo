<?php
/**
 * Migration: Add address fields to units table
 * Adds columns needed for duplicate detection: building_name, street_address, city, address_hash, latitude, longitude
 */

// Determine the correct path
$root_dir = dirname(dirname(__FILE__));
include $root_dir . '/config/db.php';

echo "<h2>Adding Address Fields to Units Table</h2>";

$migrations = [
    "ALTER TABLE units ADD COLUMN building_name VARCHAR(255) AFTER description",
    "ALTER TABLE units ADD COLUMN street_address VARCHAR(255) AFTER building_name",
    "ALTER TABLE units ADD COLUMN city VARCHAR(100) AFTER street_address",
    "ALTER TABLE units ADD COLUMN address_hash VARCHAR(64) AFTER city",
    "ALTER TABLE units ADD COLUMN latitude DECIMAL(10,8) AFTER address_hash",
    "ALTER TABLE units ADD COLUMN longitude DECIMAL(11,8) AFTER latitude"
];

foreach ($migrations as $sql) {
    try {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ " . htmlspecialchars($sql) . "</p>";
        } else {
            // Check if error is about column already existing
            if (strpos($conn->error, 'Duplicate column name') !== false) {
                echo "<p style='color: blue;'>ℹ Column already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
            }
        }
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color: blue;'>ℹ Column already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<p><strong>Migration completed!</strong></p>";
$conn->close();
?>
