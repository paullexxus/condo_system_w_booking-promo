<?php
header('Content-Type: text/plain');

$files_to_check = [
    'includes/functions.php',
    'config/db.php',
    'includes/session.php',
    'includes/security.php',
    'includes/public_session.php',
    'public/login.php',
    'public/index.php'
];

echo "=== CRITICAL FILE CHECK ===\n\n";

foreach ($files_to_check as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "✗ MISSING: $file\n";
        continue;
    }
    
    $first_line = trim(file_get_contents($path, false, null, 0, 30));
    if (strpos($first_line, '<?php') === 0) {
        echo "✓ OK: $file\n";
    } else {
        echo "✗ WRONG START: $file - starts with: " . substr($first_line, 0, 20) . "\n";
    }
}

echo "\n=== TRYING TO LOAD HOMEPAGE ===\n\n";

ob_start();
try {
    include __DIR__ . '/public/index.php';
    $output = ob_get_clean();
    if (strpos($output, '<!DOCTYPE') !== false || strpos($output, '<html') !== false) {
        echo "✓ Homepage loaded successfully (" . strlen($output) . " bytes)\n";
    } else {
        echo "! Homepage may have output issues\n";
    }
} catch (Throwable $e) {
    $output = ob_get_clean();
    echo "✗ Error loading homepage:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n";
}
?>
