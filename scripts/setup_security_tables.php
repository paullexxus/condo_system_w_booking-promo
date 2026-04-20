<?php
/**
 * Security Tables Setup
 * Ensures all tables required for the advanced security hardening exist.
 */

require_once __DIR__ . '/../config/db.php';

echo "Initializing security tables...\n";

// 1. Simple queries
$queries = [
    "CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `throttling` (
        `throttle_id` int NOT NULL AUTO_INCREMENT,
        `type` varchar(50) NOT NULL,
        `identifier` varchar(255) NOT NULL,
        `hits` int DEFAULT 1,
        `last_hit` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `lockout_until` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`throttle_id`),
        UNIQUE KEY `type_identifier` (`type`, `identifier`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `idempotency_keys` (
        `idempotency_key` varchar(255) NOT NULL,
        `user_id` int NOT NULL,
        `payload_hash` varchar(64) NOT NULL,
        `response_json` longtext,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idempotency_key`),
        KEY `user_payload` (`user_id`, `payload_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `system_errors` (
        `error_id` int NOT NULL AUTO_INCREMENT,
        `error_type` varchar(100) DEFAULT 'General',
        `message` text NOT NULL,
        `file` varchar(255) DEFAULT NULL,
        `line` int DEFAULT NULL,
        `trace` longtext,
        `status` enum('new','investigating','resolved','ignored') DEFAULT 'new',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`error_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $q) {
    if (mysqli_query($conn, $q)) {
        echo "Success: Table check completed.\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}

// 2. Column checks
$columns_to_check = [
    'users' => [
        'terms_accepted' => "ALTER TABLE `users` ADD COLUMN `terms_accepted` tinyint(1) DEFAULT 0 AFTER `is_active`"
    ],
    'notifications' => [
        'email_sent' => "ALTER TABLE `notifications` ADD COLUMN `email_sent` tinyint(1) DEFAULT 0"
    ]
];

foreach ($columns_to_check as $table => $cols) {
    foreach ($cols as $col => $alter_sql) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
        if (mysqli_num_rows($check) == 0) {
            if (mysqli_query($conn, $alter_sql)) {
                echo "Success: Column '$col' added to '$table'.\n";
            } else {
                echo "Error adding column '$col' to '$table': " . mysqli_error($conn) . "\n";
            }
        } else {
            echo "Column '$col' already exists in '$table'.\n";
        }
    }
}

// 3. Triggers
$trigger1 = "CREATE TRIGGER IF NOT EXISTS `before_audit_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW 
             BEGIN 
               SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable and cannot be updated.'; 
             END;";
$trigger2 = "CREATE TRIGGER IF NOT EXISTS `before_audit_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW 
             BEGIN 
               SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are immutable and cannot be deleted.'; 
             END;";

@mysqli_query($conn, "DROP TRIGGER IF EXISTS `before_audit_update` ");
@mysqli_query($conn, "DROP TRIGGER IF EXISTS `before_audit_delete` ");

if (mysqli_query($conn, $trigger1)) echo "Success: Audit update trigger active.\n";
if (mysqli_query($conn, $trigger2)) echo "Success: Audit delete trigger active.\n";

echo "Security table initialization complete.\n";
