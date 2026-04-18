<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'condo_rental_reservation_db');
$res = $conn->query("SHOW COLUMNS FROM reservations LIKE 'status'");
$row = $res->fetch_assoc();
echo "status currently: " . $row['Type'] . "\n";
