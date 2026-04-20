<?php
require_once __DIR__ . '/../config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS `geocoding_cache` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `address` VARCHAR(500) NOT NULL,
    `address_hash` CHAR(64) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `provider` VARCHAR(50) DEFAULT 'nominatim',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_address_hash` (`address_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "SUCCESS: geocoding_cache table created or already exists.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
