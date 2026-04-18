<?php
/**
 * Migration M20260418_02_SyncReservationsSchema
 * Adds missing guest count columns to reservations table.
 */

return [
    'description' => 'Adds num_adults and num_children to reservations table for guest count tracking.',
    
    'up' => function($db) {
        $columns = [
            'num_adults' => "INT DEFAULT 1 AFTER special_requests",
            'num_children' => "INT DEFAULT 0 AFTER num_adults"
        ];

        foreach ($columns as $column => $definition) {
            $check = $db->query("SHOW COLUMNS FROM `reservations` LIKE '$column'");
            if ($check->num_rows == 0) {
                echo "Adding column '$column' to reservations...\n";
                $db->query("ALTER TABLE `reservations` ADD COLUMN `$column` $definition");
            } else {
                echo "Column '$column' already exists in reservations. Skipping.\n";
            }
        }
    }
];
