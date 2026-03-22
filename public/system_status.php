<?php
// Final System Status Check
header('Content-Type: text/plain');
echo "=== BOOKIT SYSTEM STATUS ===\n\n";

// Check 1: Can we load all core files?
echo "1. CORE FILES CHECK:\n";
$files = [
    'config/constants.php' => 'Constants',
    'config/db.php' => 'Database Config',
    'includes/functions.php' => 'Functions',
    'includes/security.php' => 'Security',
    'includes/session.php' => 'Session'
];

$all_ok = true;
foreach ($files as $file => $name) {
    if (file_exists($file)) {
        echo "  OK: $name\n";
    } else {
        echo "  FAIL: $name - NOT FOUND\n";
        $all_ok = false;
    }
}

// Check 2: Database Connection
echo "\n2. DATABASE CHECK:\n";
try {
    require_once 'config/constants.php';
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        echo "  FAIL: Connection Error: " . $conn->connect_error . "\n";
        $all_ok = false;
    } else {
        echo "  OK: Connected to: " . DB_NAME . "\n";
        // Count tables
        $result = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "  OK: Tables found: " . $row['cnt'] . "\n";
        }
        $conn->close();
    }
} catch (Exception $e) {
    echo "  FAIL: Exception: " . $e->getMessage() . "\n";
    $all_ok = false;
}

// Check 3: Key Pages
echo "\n3. KEY PAGES CHECK:\n";
$pages = [
    'public/login.php',
    'public/index.php',
    'admin/admin_dashboard.php',
    'host/host_dashboard.php',
    'renter/reserve_unit.php'
];

foreach ($pages as $page) {
    if (file_exists($page)) {
        echo "  OK: $page\n";
    } else {
        echo "  FAIL: $page - NOT FOUND\n";
        $all_ok = false;
    }
}

echo "\n" . ($all_ok ? "SUCCESS: SYSTEM READY\n" : "FAIL: SYSTEM HAS ISSUES\n");
echo "\nAccess: http://localhost/BookIT/public/login.php\n";
?>
