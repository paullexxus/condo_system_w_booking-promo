<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'condo_rental_reservation_db');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$res = $conn->query("DESCRIBE units");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
