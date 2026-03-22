<?php
require 'config/db.php';
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'host', 'renter', 'manager') DEFAULT 'renter'");
echo "Database role column updated.";
