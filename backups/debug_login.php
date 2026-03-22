<?php
// Direct test that mimics Apache behavior for login.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Mimic being in the public directory
chdir(__DIR__);

// Capture any output or errors
ob_start();

try {
    // This is what Apache would do - just include the file
    include 'login.php';
} catch (Throwable $e) {
    echo "FATAL ERROR CAUGHT:\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nTrace:\n";
    echo $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();

// If we got here, show the output
if (!empty($output)) {
    // Just show first 500 chars
    echo substr($output, 0, 500);
    if (strlen($output) > 500) {
        echo "\n... (" . (strlen($output) - 500) . " more bytes)\n";
    }
} else {
    echo "No output generated\n";
}
?>
