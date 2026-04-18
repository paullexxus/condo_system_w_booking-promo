<?php
header('Content-Type: text/plain');
require_once '../config/db.php';

$queries = [
    "ALTER TABLE units MODIFY COLUMN pricing_type ENUM('daily', 'nightly', 'monthly') NOT NULL DEFAULT 'nightly'"
];

if (!column_exists('units', 'price_per_night')) {
    $queries[] = "ALTER TABLE units ADD COLUMN price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER description";
}
if (!column_exists('units', 'price_per_month')) {
    $queries[] = "ALTER TABLE units ADD COLUMN price_per_month DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price_per_night";
}

$queries = array_merge($queries, [
    "UPDATE units SET price_per_night = monthly_rate WHERE pricing_type IN ('daily', 'nightly')",
    "UPDATE units SET price_per_month = monthly_rate WHERE pricing_type = 'monthly'",
    "UPDATE units SET pricing_type = 'nightly' WHERE pricing_type = 'daily'",
    "ALTER TABLE units MODIFY COLUMN pricing_type ENUM('nightly', 'monthly') NOT NULL DEFAULT 'nightly'"
]);

foreach ($queries as $sql) {
    if (!$conn->query($sql)) {
        echo "Error: $sql\n" . $conn->error . "\n";
    } else {
        echo "Success: $sql\n";
    }
}
echo "Migration complete.\n";
