<?php
require_once '../config/db.php';

try {
    $conn->query("ALTER TABLE reviews ADD COLUMN reservation_id INT");
    echo "reservation_id added.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
