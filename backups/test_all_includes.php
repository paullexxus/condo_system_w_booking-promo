<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== STEP-BY-STEP INCLUDE TEST ===\n\n";

// Save current directory
$orig_dir = getcwd();
chdir(__DIR__);

// Step 1
echo "[1/8] Requiring config/constants.php\n";
try {
    require_once __DIR__ . '/../config/constants.php';
    echo "     OK\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

// Step 2
echo "[2/8] Starting session\n";
try {
    session_start();
    echo "     OK\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

// Step 3
echo "[3/8] Including ../config/db.php\n";
try {
    include_once '../config/db.php';
    echo "     OK\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

// Step 4
echo "[4/8] Including ../includes/security.php\n";
try {
    include_once '../includes/security.php';
    echo "     OK\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

// Step 5
echo "[5/8] Calling clearLoginPageCache()\n";
try {
    clearLoginPageCache();
    echo "     OK\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

// Step 6
echo "[6/8] Including ../includes/components/form_errors.php\n";
try {
    require_once '../includes/components/form_errors.php';
    echo "     OK\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

// Step 7
echo "[7/8] Including ../includes/functions.php\n";
try {
    require_once '../includes/functions.php';
    echo "     OK\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

// Step 8
echo "[8/8] Including ../config/OAuth.php\n";
try {
    $oauth_config = include '../config/OAuth.php';
    if (!is_array($oauth_config)) {
        throw new Exception("OAuth.php did not return array");
    }
    echo "     OK - returned array with keys: " . implode(", ", array_keys($oauth_config)) . "\n";
} catch (Throwable $e) {
    echo "     FAILED: " . $e->getMessage() . "\n";
    chdir($orig_dir);
    exit(1);
}

echo "\nALL INCLUDES SUCCESSFUL!\n";
chdir($orig_dir);
?>
