<?php
// Minimal test - just show what happens
echo "200 OK - This works\n";

echo "Now trying to include login.php...\n";
echo "============\n\n";

// Try to see what happens when we try the full login flow
ob_start();
try {
    include 'login.php';
    $output = ob_get_contents();
    if (!empty($output)) {
        echo "Got output from login.php: " . strlen($output) . " bytes\n";
    }
} catch (Throwable $e) {
    $output = ob_get_contents();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} finally {
    ob_end_flush();
}

echo "\nDone\n";
?>
