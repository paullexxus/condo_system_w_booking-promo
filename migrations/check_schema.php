<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'condo_rental_reservation_db');
$res = $conn->query("SHOW COLUMNS FROM reservations");
$cols = [];
while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
echo "Reservations: " . implode(', ', $cols) . "<br>";

$res = $conn->query("SHOW COLUMNS FROM payments");
$cols = [];
while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
echo "Payments: " . implode(', ', $cols);
