<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tests = [];

// Test 1: Constants
try {
    require_once 'config/constants.php';
    $tests[] = "✓ Constants loaded";
} catch (Throwable $e) {
    $tests[] = "✗ Constants: " . $e->getMessage();
    exit(implode("\n", $tests));
}

// Test 2: DB
try {
    require 'config/db.php';
    if (!function_exists('get_single_result')) {
        throw new Exception("DB functions not defined");
    }
    $tests[] = "✓ DB functions loaded";
} catch (Throwable $e) {
    $tests[] = "✗ DB: " . $e->getMessage();
    exit(implode("\n", $tests));
}

// Test 3: Session
try {
    session_start();
    $tests[] = "✓ Session started";
} catch (Throwable $e) {
    $tests[] = "✗ Session: " . $e->getMessage();
    exit(implode("\n", $tests));
}

// Test 4: Functions
try {
    require 'includes/functions.php';
    if (!function_exists('registerValidation')) {
        throw new Exception("Functions not loaded");
    }
    $tests[] = "✓ Functions loaded";
} catch (Throwable $e) {
    $tests[] = "✗ Functions: " . $e->getMessage();
    exit(implode("\n", $tests));
}

// Test 5: Security
try {
    require 'includes/security.php';
    $tests[] = "✓ Security loaded";
} catch (Throwable $e) {
    $tests[] = "✗ Security: " . $e->getMessage();
    exit(implode("\n", $tests));
}

// Test 6: Database connection
try {
    $result = $conn->query("SELECT 1");
    if (!$result) {
        throw new Exception($conn->error);
    }
    $tests[] = "✓ Database connection works";
} catch (Throwable $e) {
    $tests[] = "✗ Database: " . $e->getMessage();
    exit(implode("\n", $tests));
}

$tests[] = "\n✓✓✓ ALL SYSTEMS OK ✓✓✓";
echo implode("\n", $tests);
?>