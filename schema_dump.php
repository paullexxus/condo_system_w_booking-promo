<?php
require_once 'config/db.php';

$tables = ['units', 'branches', 'reservations', 'amenities'];
$schema = [];

foreach ($tables as $table) {
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        $cols = [];
        while($row = $result->fetch_assoc()) {
            $cols[] = $row;
        }
        $schema[$table] = $cols;
    } else {
        $schema[$table] = "Table not found";
    }
}

// Check if blocked_dates, seasonal_rates, booking_addons exist
$custom_tables = ['blocked_dates', 'seasonal_rates', 'booking_addons', 'reservation_addons'];
foreach ($custom_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $schema['exists_' . $table] = ($result && $result->num_rows > 0);
}

header('Content-Type: application/json');
echo json_encode($schema, JSON_PRETTY_PRINT);
?>
