<?php
include 'c:/wamp64/www/BookIT/config/db.php';
$result = $conn->query("DESCRIBE units");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
