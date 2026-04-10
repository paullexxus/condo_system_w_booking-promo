<?php
require_once __DIR__ . '/../config/db.php';

echo "Running Migration: 007_create_bookings_promos_pricing.sql\n";

$sqlFile = __DIR__ . '/../migrations/007_create_bookings_promos_pricing.sql';
if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at {$sqlFile}\n");
}

$sql = file_get_contents($sqlFile);
$queries = array_filter(array_map('trim', explode(';', $sql)));

foreach ($queries as $query) {
    if (empty($query)) continue;
    
    // Check if the query is just a comment block
    $lines = explode("\n", $query);
    $is_only_comments = true;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '' && substr($line, 0, 2) !== '--') {
            $is_only_comments = false;
            break;
        }
    }
    if ($is_only_comments) continue;

    if ($conn->query($query) === TRUE) {
        echo "Successfully executed query fragment.\n";
    } else {
        echo "Error creating table/executing query: " . $conn->error . "\n";
        echo "Query was: " . substr($query, 0, 100) . "...\n";
    }
}

echo "Creating booking_addons table...\n";
$bookingAddonsSql = "
CREATE TABLE IF NOT EXISTS booking_addons (
  booking_id INT UNSIGNED NOT NULL,
  addon_id INT UNSIGNED NOT NULL,
  addon_name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (booking_id, addon_id),
  KEY booking_addons_booking_id_index (booking_id),
  CONSTRAINT fk_booking_addons_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($bookingAddonsSql) === TRUE) {
    echo "Successfully created booking_addons table.\n";
} else {
    echo "Error creating booking_addons table: " . $conn->error . "\n";
}

echo "Migration script completed.\n";
