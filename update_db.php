<?php
require_once __DIR__ . '/config/db.php';

$queries = [
    "ALTER TABLE units ADD COLUMN sqm DECIMAL(10,2) DEFAULT NULL",
    "ALTER TABLE units ADD COLUMN bed_type VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE units ADD COLUMN num_beds INT DEFAULT 1",
    "ALTER TABLE units ADD COLUMN num_bathrooms INT DEFAULT 1"
];

$success_count = 0;
foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Success: " . $sql . "<br>";
        $success_count++;
    } else {
        echo "Error: " . $conn->error . " for query: " . $sql . "<br>";
    }
}
echo "Completed $success_count queries.";
?>
