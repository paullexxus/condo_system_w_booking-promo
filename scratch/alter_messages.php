<?php
$conn = new mysqli("localhost", "root", "", "BookIT");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE messages 
        ADD COLUMN file_path VARCHAR(500) NULL AFTER message,
        ADD COLUMN file_type VARCHAR(50) NULL AFTER file_path,
        ADD COLUMN original_file_name VARCHAR(255) NULL AFTER file_type";
        
if ($conn->query($sql) === TRUE) {
    echo "Table `messages` altered successfully!\n";
} else {
    echo "Error altering table: " . $conn->error . "\n";
}
$conn->close();
?>
