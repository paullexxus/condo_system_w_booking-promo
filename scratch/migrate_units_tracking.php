<?php
include_once __DIR__ . '/../config/db.php';

$queries = [
    "ALTER TABLE units ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;",
    "ALTER TABLE units ADD COLUMN updated_by_role VARCHAR(20) DEFAULT NULL;",
    "ALTER TABLE units ADD COLUMN updated_by_id INT DEFAULT NULL;"
];

foreach ($queries as $sql) {
    try {
        if ($conn->query($sql) === TRUE) {
            echo "Success: $sql\n";
        } else {
            // Check if column already exists error
            if (strpos($conn->error, "Duplicate column name") !== false) {
                echo "Column already exists: $sql\n";
            } else {
                echo "Error: " . $conn->error . "\n";
            }
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            echo "Column already exists: $sql\n";
        } else {
            echo "Exception: " . $e->getMessage() . "\n";
        }
    }
}
