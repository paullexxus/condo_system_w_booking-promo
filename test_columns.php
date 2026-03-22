<?php
$root_dir = 'c:/wamp64/www/BookIT';
include $root_dir . '/config/db.php';
$conn->query("UPDATE units SET city = 'Manila' WHERE unit_id = 0");
if ($conn->error) {
    echo "Error: " . $conn->error;
} else {
    echo "Columns exist!";
}
?>
