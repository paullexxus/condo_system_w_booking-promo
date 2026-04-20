<?php
include_once __DIR__ . '/../config/db.php';
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($res->num_rows == 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER email")) {
        echo "Column 'profile_picture' added successfully.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "Column 'profile_picture' already exists.\n";
}
?>
