<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'condo_rental_reservation_db');
if ($conn->connect_error) die("Connection failed");

echo "VERIFICATION REPORT:\n";

// 1. Table Checks
$tables = ['units', 'unit_flags', 'audit_logs'];
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    echo "Table '$t': " . ($res->num_rows > 0 ? "OK" : "MISSING") . "\n";
}

// 2. Column Checks for Units
$cols = ['review_locked_by', 'review_locked_at', 'reviewed_at'];
foreach ($cols as $c) {
    $res = $conn->query("SHOW COLUMNS FROM units LIKE '$c'");
    echo "Column 'units.$c': " . ($res->num_rows > 0 ? "OK" : "MISSING") . "\n";
}

// 3. Status Check (Pending check)
$res = $conn->query("SELECT COUNT(*) as count FROM units WHERE approval_status = 'pending'");
$row = $res->fetch_assoc();
echo "Units Pending Moderation: " . $row['count'] . "\n";

echo "\nVerification Finished.\n";
?>
