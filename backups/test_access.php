<?php
// Test direct file access
echo "Testing direct access to files...\n\n";

$test_files = [
    '/BookIT/public/login.php',
    '/BookIT/public/index.php', 
    '/BookIT/admin/admin_dashboard.php',
    '/BookIT/host/host_dashboard.php',
    '/BookIT/index.php'
];

foreach ($test_files as $uri) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . $uri;
    if (file_exists($file_path)) {
        echo "✓ File exists: $uri\n";
        // Try to read first line
        $first_line = file_get_contents($file_path, false, null, 0, 20);
        if (strpos($first_line, '<?php') !== false || strpos($first_line, '<?') !== false) {
            echo "  + PHP file, starts correctly\n";
        }
    } else {
        echo "✗ File NOT found: $uri (path: $file_path)\n";
    }
}

echo "\n\nTesting database and includes...\n";
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/BookIT/config/constants.php';
    echo "✓ Constants loaded\n";
} catch (Throwable $e) {
    echo "✗ Constants error: " . $e->getMessage() . "\n";
}
?>
