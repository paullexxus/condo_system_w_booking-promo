<?php
$conn = new mysqli("localhost", "root", "", "BookIT");
$res = $conn->query("SHOW COLUMNS FROM messages LIKE 'file_path'");
if($res->num_rows > 0) {
    echo "COLUMNS EXIST";
} else {
    echo "COLUMNS MISSING";
    $conn->query("ALTER TABLE messages ADD COLUMN file_path VARCHAR(500) NULL AFTER message, ADD COLUMN file_type VARCHAR(50) NULL AFTER file_path, ADD COLUMN original_file_name VARCHAR(255) NULL AFTER file_type");
    echo " - ATTEMPTED TO ADD";
}
?>
