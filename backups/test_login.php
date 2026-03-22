<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/test_error.log');

echo "Starting login.php test...\n";

try {
    echo "Step 1: Including constants\n";
    require_once __DIR__ . '/../config/constants.php';
    echo "OK\n";
    
    echo "Step 2: Starting session\n";
    session_start();
    echo "OK\n";
    
    echo "Step 3: Including db.php\n";
    include_once __DIR__ . '/../config/db.php';
    echo "OK\n";
    
    echo "Step 4: Including security.php\n";
    include_once __DIR__ . '/../includes/security.php';
    echo "OK\n";
    
    echo "Step 5: Including functions.php\n";
    require_once __DIR__ . '/../includes/functions.php';
    echo "OK\n";
    
    echo "Step 6: Including form_errors.php\n";
    require_once __DIR__ . '/../includes/components/form_errors.php';
    echo "OK\n";
    
    echo "SUCCESS: All includes loaded!\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    die();
}

echo "\nNow testing actual login.php...\n";
ob_start();
try {
    include __DIR__ . '/login.php';
    $output = ob_get_clean();
    if (strlen($output) > 100) {
        echo "Login.php loaded (" . strlen($output) . " bytes)\n";
    } else {
        echo "Login.php output:\n" . $output . "\n";
    }
} catch (Throwable $e) {
    $output = ob_get_clean();
    echo "ERROR loading login.php: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
