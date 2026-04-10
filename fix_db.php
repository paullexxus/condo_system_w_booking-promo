<?php
$file = 'c:/wamp64/www/BookIT/condo_rental_reservation_db.sql';
$content = file_get_contents($file);

if ($content === false) {
    die("Error reading file.");
}

// 1. Change MyISAM to InnoDB
$content = str_replace('ENGINE=MyISAM', 'ENGINE=InnoDB', $content);

// 2. Fix variable type mismatch for foreign keys
$content = str_replace('`host_id` int UNSIGNED DEFAULT NULL', '`host_id` int DEFAULT NULL', $content);
$content = str_replace('`approved_by` int UNSIGNED DEFAULT NULL', '`approved_by` int DEFAULT NULL', $content);

// 3. Optional: Add missing foreign key constraints for basic tables if the user wanted relationships
// We will just do the engine & type fixes first which corrects the primary "fix the issue" problem in phpMyAdmin.

file_put_contents($file, $content);
echo "SQL file updated successfully.";
