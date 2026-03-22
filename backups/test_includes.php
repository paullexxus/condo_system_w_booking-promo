<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing login.php includes step by step...\n\n";

// Test 1
echo "1. Testing config/constants.php...\n";
try {
    require_once __DIR__ . '/../config/constants.php';
    echo "   OK\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2
echo "2. Testing session_start()...\n";
try {
    session_start();
    echo "   OK\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3
echo "3. Testing config/db.php...\n";
try {
    include_once '../config/db.php';
    echo "   OK\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4
echo "4. Testing includes/security.php...\n";
try {
    include_once '../includes/security.php';
    echo "   OK\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5
echo "5. Testing clearLoginPageCache()...\n";
try {
    if (function_exists('clearLoginPageCache')) {
        clearLoginPageCache();
        echo "   OK\n";
    } else {
        echo "   Function not found (this is OK if not defined)\n";
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6
echo "6. Testing includes/components/form_errors.php...\n";
try {
    require_once '../includes/components/form_errors.php';
    echo "   OK\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7
echo "7. Testing includes/functions.php...\n";
try {
    require_once '../includes/functions.php';
    echo "   OK\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 8
echo "8. Testing config/OAuth.php...\n";
try {
    $oauth_config = include '../config/OAuth.php';
    if (!is_array($oauth_config)) {
        throw new Exception("OAuth.php did not return an array");
    }
    echo "   OK\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n===== ALL INCLUDES SUCCESSFUL =====\n";
?>
