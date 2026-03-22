<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "TEST 1: Require constants\n";
require_once 'config/constants.php';

echo "TEST 2: Create DB connection\n";
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("DB ERROR: " . $conn->connect_error);
}
echo "DB: OK\n";

echo "TEST 3: Include config/db.php\n";
include 'config/db.php';

echo "All tests passed!\n";
?>