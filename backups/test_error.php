<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to capture any output before headers
ob_start();

try {
    require_once __DIR__ . '/config/constants.php';
    echo "✓ Constants loaded<br>";
    
    session_start();
    echo "✓ Session started<br>";
    
    include_once './config/db.php';
    echo "✓ DB included<br>";
    
    include_once './includes/security.php';
    echo "✓ Security included<br>";
    
    echo "✓ All includes successful<br>";
    
} catch (Throwable $e) {
    echo "FATAL ERROR:<br>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Get output
$output = ob_get_clean();
echo $output;
?>
