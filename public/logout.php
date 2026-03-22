<?php
// Start session to clear it
session_start();

// Clear all session data
session_unset();
session_destroy();

// Delete session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear any custom session cookies
foreach ($_COOKIE as $key => $value) {
    if (strpos($key, 'BOOKIT') !== false || strpos($key, 'PHPSESSID') !== false) {
        setcookie($key, '', time() - 42000, '/');
    }
}

// Redirect to login page with absolute path
require_once '../config/constants.php';
header("Location: " . SITE_URL . "/public/login.php");
exit();
?>