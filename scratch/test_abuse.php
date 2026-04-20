<?php
/**
 * Test script for Abuse Protection
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Testing checkAbuse('search')...\n";

// Reset for test
mysqli_query($conn, "DELETE FROM throttling WHERE type = 'search' AND identifier = 'test_unit'");

// 1. First hit
$res1 = checkAbuse('search', 'test_unit');
echo "Hit 1: " . ($res1['allowed'] ? "Allowed" : "Blocked") . " hits: " . ($res1['hits'] ?? 'N/A') . "\n";

// 2. Multiple hits until limit (default 10)
for ($i = 2; $i <= 10; $i++) {
    $res = checkAbuse('search', 'test_unit');
}
echo "Sent 10 hits total.\n";

// 3. 11th hit should block
$res11 = checkAbuse('search', 'test_unit');
echo "Hit 11: " . ($res11['allowed'] ? "Allowed" : "Blocked") . " Message: " . $res11['message'] . "\n";

// Check DB
$row = get_single_result("SELECT * FROM throttling WHERE type = 'search' AND identifier = 'test_unit'");
echo "DB Status: Hits=" . $row['hits'] . " LockoutUntil=" . ($row['lockout_until'] ?: 'NULL') . "\n";

echo "Test complete.\n";
