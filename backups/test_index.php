<?php
// Test if index.php works
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');
echo "Testing public/index.php...\n\n";

chdir(__DIR__);

ob_start();
try {
    include 'index.php';
    $output = ob_get_clean();
    echo "SUCCESS - Got " . strlen($output) . " bytes\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
