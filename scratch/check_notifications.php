<?php
$conn = new mysqli('localhost', 'root', '', 'condo_rental_reservation_db');
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows > 0) {
    echo "Table notifications exists.\n";
    $columns = $conn->query("DESCRIBE notifications");
    while($row = $columns->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Table notifications does NOT exist.\n";
}
$conn->close();
?>
