<?php
// setup_features_db.php
require_once 'config/db.php';

try {
    // 1. unit_blocked_dates
    $query1 = "CREATE TABLE IF NOT EXISTS `unit_blocked_dates` (
        `id` int NOT NULL AUTO_INCREMENT,
        `unit_id` int NOT NULL,
        `start_date` date NOT NULL,
        `end_date` date NOT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `unit_id` (`unit_id`),
        CONSTRAINT `fk_blocked_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($query1) === TRUE) echo "Table unit_blocked_dates created/verified.<br>\n";
    else echo "Error creating unit_blocked_dates: " . $conn->error . "<br>\n";

    // 2. seasons
    $query2 = "CREATE TABLE IF NOT EXISTS `seasons` (
        `id` int NOT NULL AUTO_INCREMENT,
        `unit_id` int NOT NULL,
        `name` varchar(100) NOT NULL,
        `type` enum('weekend', 'holiday', 'custom') NOT NULL DEFAULT 'custom',
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `adjustment_type` enum('fixed', 'percentage') NOT NULL,
        `adjustment_value` decimal(10,2) NOT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `unit_id` (`unit_id`),
        CONSTRAINT `fk_seasons_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($query2) === TRUE) echo "Table seasons created/verified.<br>\n";
    else echo "Error creating seasons: " . $conn->error . "<br>\n";

    // 3. add_ons
    $query3 = "CREATE TABLE IF NOT EXISTS `add_ons` (
        `id` int NOT NULL AUTO_INCREMENT,
        `host_id` int NOT NULL,
        `name` varchar(100) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `description` text,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `host_id` (`host_id`),
        CONSTRAINT `fk_addons_host` FOREIGN KEY (`host_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($query3) === TRUE) echo "Table add_ons created/verified.<br>\n";
    else echo "Error creating add_ons: " . $conn->error . "<br>\n";

    // 4. promo_codes
    $query4 = "CREATE TABLE IF NOT EXISTS `promo_codes` (
        `id` int NOT NULL AUTO_INCREMENT,
        `code` varchar(50) NOT NULL,
        `type` enum('fixed', 'percentage') NOT NULL,
        `discount_value` decimal(10,2) NOT NULL,
        `expiration_date` date DEFAULT NULL,
        `usage_limit` int DEFAULT NULL,
        `times_used` int DEFAULT 0,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($query4) === TRUE) echo "Table promo_codes created/verified.<br>\n";
    else echo "Error creating promo_codes: " . $conn->error . "<br>\n";

    // 5. reservation_add_ons
    $query5 = "CREATE TABLE IF NOT EXISTS `reservation_add_ons` (
        `id` int NOT NULL AUTO_INCREMENT,
        `reservation_id` int NOT NULL,
        `add_on_id` int NOT NULL,
        `price_at_booking` decimal(10,2) NOT NULL,
        `quantity` int DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `reservation_id` (`reservation_id`),
        KEY `add_on_id` (`add_on_id`),
        CONSTRAINT `fk_res_addons_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_res_addons_addon` FOREIGN KEY (`add_on_id`) REFERENCES `add_ons` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($query5) === TRUE) echo "Table reservation_add_ons created/verified.<br>\n";
    else echo "Error creating reservation_add_ons: " . $conn->error . "<br>\n";

    // 6. Alter reservations table
    $conn->query("ALTER TABLE `reservations` ADD COLUMN `promo_code_id` int DEFAULT NULL AFTER `security_deposit`;");
    $conn->query("ALTER TABLE `reservations` ADD COLUMN `discount_amount` decimal(10,2) DEFAULT '0.00' AFTER `promo_code_id`;");
    $conn->query("ALTER TABLE `reservations` ADD CONSTRAINT `fk_res_promo` FOREIGN KEY (`promo_code_id`) REFERENCES `promo_codes`(`id`) ON DELETE SET NULL;");
    echo "Reservations altered/verified (errors suppressed if already exist).<br>\n";

    echo "Database setup complete!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
