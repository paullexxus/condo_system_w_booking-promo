<?php
require_once 'config/db.php';

// Check payments table columns
$result = $conn->query("DESCRIBE payments");
echo "Payments Table Columns:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Try the query
echo "\n\nTesting Query:\n";
$sql = "
    SELECT p.*, r.reservation_id, r.check_in_date, r.check_out_date, u.full_name
    FROM payments p
    LEFT JOIN reservations r ON p.reservation_id = r.reservation_id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY p.payment_date DESC
    LIMIT 20
";

if ($result = $conn->query($sql)) {
    echo "Query successful! Rows: " . $result->num_rows . "\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}
?>
