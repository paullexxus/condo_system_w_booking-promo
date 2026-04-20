<?php
$conn = new mysqli('localhost', 'root', '', 'condo_rental_reservation_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 4. Update Amenities Table
$sql5 = "ALTER TABLE `amenities` ADD COLUMN `is_paid` tinyint(1) DEFAULT 0;";

// 5. Update Units Table
$sql6 = "ALTER TABLE `units` ADD COLUMN `extra_guests_allowed` int(11) DEFAULT 0;";
$sql7 = "ALTER TABLE `units` ADD COLUMN `extra_guest_fee` decimal(10,2) DEFAULT 0.00;";

$queries = [$sql5, $sql6, $sql7];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Success: " . substr($q, 0, 50) . "...\n";
    } else {
        echo "Error: " . $conn->error . " | Query: " . substr($q, 0, 50) . "...\n";
    }
}
echo "Done!";
?>
