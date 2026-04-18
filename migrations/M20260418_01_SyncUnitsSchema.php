<?php
/**
 * Migration M20260418_01_SyncUnitsSchema
 * Syncs the units table with application-level expectations.
 */

return [
    'description' => 'Adds missing cancellation_policy and other core columns to units table for production-grade schema alignment.',
    
    'up' => function($db) {
        $columns = [
            'cancellation_policy' => "VARCHAR(255) DEFAULT 'Strict' AFTER description",
            'instant_booking' => "TINYINT(1) DEFAULT 0 AFTER cancellation_policy",
            'building_name' => "VARCHAR(255) NULL AFTER unit_number",
            'street_address' => "VARCHAR(255) NULL AFTER building_name",
            'capacity' => "INT DEFAULT 1 AFTER max_occupancy"
        ];

        foreach ($columns as $column => $definition) {
            // Safe existence check
            $check = $db->query("SHOW COLUMNS FROM `units` LIKE '$column'");
            if ($check->num_rows == 0) {
                echo "Adding column '$column' to units...\n";
                $db->query("ALTER TABLE `units` ADD COLUMN `$column` $definition");
            } else {
                echo "Column '$column' already exists in units. Skipping.\n";
            }
        }
    }
];
