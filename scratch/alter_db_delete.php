<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

global $conn;

$sql = "ALTER TABLE messages 
        ADD COLUMN deleted_by_sender TINYINT(1) DEFAULT 0,
        ADD COLUMN deleted_by_receiver TINYINT(1) DEFAULT 0,
        ADD COLUMN is_deleted_everyone TINYINT(1) DEFAULT 0,
        ADD COLUMN deleted_by_admin TINYINT(1) DEFAULT 0";
        
if ($conn->query($sql) === TRUE) {
    echo "Columns added successfully!\n";
} else {
    echo "Error adding columns: " . $conn->error . "\n";
}
?>
