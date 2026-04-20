-- Migration: Create Geocoding Cache Table
-- Purpose: Store resolved addresses to reduce external API calls and improve resilience.
-- Standard: WGS84 Coordinates

CREATE TABLE IF NOT EXISTS `geocoding_cache` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `address` VARCHAR(500) NOT NULL,
    `address_hash` CHAR(64) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `provider` VARCHAR(50) DEFAULT 'nominatim',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_address_hash` (`address_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
