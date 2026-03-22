<?php
// Quick test to see if login.php can be included without errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

try {
    include 'public/login.php';
    $output = ob_get_clean();
    echo "✓ Login.php included successfully";
    if (!empty($output)) {
        echo "\nOutput length: " . strlen($output) . " bytes";
    }
} catch (Throwable $e) {
    $output = ob_get_clean();
    echo "✗ Error: " . $e->getMessage();
    echo "\nFile: " . $e->getFile();
    echo "\nLine: " . $e->getLine();
}
?>