<?php
require_once 'config/constants.php';
echo "Step 1: Constants loaded\n";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection Error: " . $conn->connect_error);
    }
    echo "Step 2: DB connected\n";
} catch (Exception $e) {
    die("DB Exception: " . $e->getMessage());
}

try {
    session_start();
    echo "Step 3: Session started\n";
} catch (Exception $e) {
    die("Session Exception: " . $e->getMessage());
}

try {
    require 'config/db.php';
    echo "Step 4: DB functions loaded\n";
} catch (Exception $e) {
    die("DB Functions Exception: " . $e->getMessage());
}

try {
    require 'includes/security.php';
    echo "Step 5: Security loaded\n";
} catch (Exception $e) {
    die("Security Exception: " . $e->getMessage());
}

echo "All includes successful!\n";
?>