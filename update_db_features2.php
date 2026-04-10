<?php
require_once __DIR__ . '/config/db.php';

$queries = [
    // unit_blocked_dates
    "CREATE TABLE IF NOT EXISTS `unit_blocked_dates` (
        `id` int NOT NULL AUTO_INCREMENT,
        `unit_id` int NOT NULL,
        `start_date` date NOT NULL,
        `end_date` date NOT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `unit_id` (`unit_id`),
        CONSTRAINT `fk_blocked_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // pricing_rules
    "CREATE TABLE IF NOT EXISTS `pricing_rules` (
        `rule_id` int NOT NULL AUTO_INCREMENT,
        `unit_id` int NOT NULL,
        `rule_type` enum('weekend','holiday') NOT NULL,
        `adjustment_type` enum('fixed_addition','percentage_increase') NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `rule_name` varchar(100) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`rule_id`),
        KEY `unit_id` (`unit_id`),
        CONSTRAINT `fk_pricing_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // host_addons
    "CREATE TABLE IF NOT EXISTS `host_addons` (
        `addon_id` int NOT NULL AUTO_INCREMENT,
        `host_id` int NOT NULL,
        `name` varchar(100) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `is_active` tinyint(1) DEFAULT '1',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`addon_id`),
        KEY `host_id` (`host_id`),
        CONSTRAINT `fk_addon_host` FOREIGN KEY (`host_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // promo_codes
    "CREATE TABLE IF NOT EXISTS `promo_codes` (
        `promo_id` int NOT NULL AUTO_INCREMENT,
        `code` varchar(50) NOT NULL UNIQUE,
        `discount_type` enum('percentage','fixed') NOT NULL,
        `discount_amount` decimal(10,2) NOT NULL,
        `expiration_date` date NOT NULL,
        `usage_limit` int DEFAULT NULL,
        `times_used` int DEFAULT '0',
        `is_active` tinyint(1) DEFAULT '1',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`promo_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // reservation_addons
    "CREATE TABLE IF NOT EXISTS `reservation_addons` (
        `id` int NOT NULL AUTO_INCREMENT,
        `reservation_id` int NOT NULL,
        `addon_id` int NOT NULL,
        `price_at_booking` decimal(10,2) NOT NULL,
        `quantity` int DEFAULT '1',
        PRIMARY KEY (`id`),
        KEY `reservation_id` (`reservation_id`),
        KEY `addon_id` (`addon_id`),
        CONSTRAINT `fk_res_addon_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_res_addon_addon` FOREIGN KEY (`addon_id`) REFERENCES `host_addons` (`addon_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Alter reservations
    "ALTER TABLE `reservations` ADD COLUMN `base_amount` decimal(10,2) DEFAULT '0.00' AFTER `total_amount`",
    "ALTER TABLE `reservations` ADD COLUMN `promo_id` int DEFAULT NULL AFTER `base_amount`",
    "ALTER TABLE `reservations` ADD COLUMN `discount_amount` decimal(10,2) DEFAULT '0.00' AFTER `promo_id`",
    "ALTER TABLE `reservations` ADD COLUMN `addons_total` decimal(10,2) DEFAULT '0.00' AFTER `discount_amount`",
    "ALTER TABLE `reservations` ADD CONSTRAINT `fk_res_promo` FOREIGN KEY (`promo_id`) REFERENCES `promo_codes` (`promo_id`) ON DELETE SET NULL",

    // Alter users
    "ALTER TABLE `users` ADD COLUMN `approval_status` enum('pending','approved','rejected') DEFAULT 'approved' AFTER `is_active`"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Success: " . substr($sql, 0, 50) . "...<br>\n";
    } else {
        echo "Error: " . $conn->error . " for query: " . substr($sql, 0, 50) . "...<br>\n";
    }
}
echo "Migration complete.\n";
?>
