<?php
// simulate_forgot_post.php
// Simulate POSTing to public/forgot_password.php from CLI

// Run from public/ to match web include paths
chdir(__DIR__ . '/public');
// Buffer output immediately to prevent "headers already sent" when the
// included web page calls header()/session_start().
ob_start();
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email_integration.php';
// Start session early to allow CSRF/token functions to work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prepare environment similar to web
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Create a test user if not exists
$test_email = getenv('SIM_TEST_EMAIL') ?: 'cli_test_user@example.com';
$test_name = 'CLI Test User';

// Check user
$existing = null;
try {
    $existing = get_single_result("SELECT user_id, email, full_name, role, is_active FROM users WHERE email = ?", [$test_email]);
} catch (Exception $e) {
    echo "DB error checking user: " . $e->getMessage() . "\n";
}

if (!$existing) {
    // Insert test user
    $pwd = password_hash('Password123!', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (email, full_name, password, role, is_active) VALUES (?, ?, ?, 'renter', 1)";
    try {
        execute_query($sql, [$test_email, $test_name, $pwd]);
        $uid = $conn->insert_id;
        echo "Inserted test user: $test_email (id $uid)\n";
    } catch (Exception $e) {
        echo "Failed to insert test user: " . $e->getMessage() . "\n";
    }
} else {
    echo "Test user exists: " . $existing['email'] . " (id " . $existing['user_id'] . ")\n";
}

// Set POST data for forgot_password flow

// Note: all output is buffered to avoid header/session warnings.
echo "Simulating POST to forgot_password.php for $test_email\n";

// Prepare POST
$_POST['send_otp'] = '1';
$_POST['csrf_token'] = generateCSRFToken();
$_POST['email'] = $test_email;

// Include the page logic (it will redirect on success; buffer headers)
// Include the page logic (it may call header()); capture buffered output
include 'public/forgot_password.php';
$output = ob_get_clean();

// Show result message from session or output
if (!empty($_SESSION['reset_user_id'])) {
    echo "OTP flow set session reset_user_id=" . $_SESSION['reset_user_id'] . "\n";
}

echo "Output excerpt:\n";
echo substr(strip_tags($output), 0, 800) . "\n";

?>