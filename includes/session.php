<?php
// Ensure session settings from constants are applied before starting session
require_once __DIR__ . '/../config/constants.php';

// Configure secure session cookies before starting session
if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Fingerprint the current session to prevent hijacking
 * Uses IP (first 3 octets) and User-Agent
 */
function enforceSessionFingerprint() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'];
    
    // Extract first 3 octets for subnet tolerance
    $ip_parts = explode('.', $ip);
    $subnet = (count($ip_parts) >= 3) ? "$ip_parts[0].$ip_parts[1].$ip_parts[2]" : $ip;
    
    $current_fingerprint = md5($subnet . $ua);

    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $current_fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $current_fingerprint) {
        // Potential hijacking alert
        error_log("SESSION HIJACKING DETECTED: IP: $ip | UA: $ua | Expected Fingerprint mismatch.");
        
        // Fail-safe: Destroy session and log out
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: " . SITE_URL . "/public/login.php?error=session_expired");
        exit();
    }
}

// 1. INACTIVITY TIMEOUT (Phase 3.3 Refinement)
$timeout_duration = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    // Session expired due to inactivity
    error_log("SESSION EXPIRED: Inactivity timeout reached for User ID: " . ($_SESSION['user_id'] ?? 'GUEST'));
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: " . SITE_URL . "/public/login.php?error=timeout");
    exit();
}

// 2. ACTIVITY HEARTBEAT (Phase 3.3 Refinement)
// Update last_activity on every valid request to keep session alive during active usage
$_SESSION['last_activity'] = time();

// CHECK IF NO SESSION (user not logged in)
if (!isset($_SESSION['user_id'])) {
    // If it's an AJAX request, return 401 instead of redirecting
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    header("Location: " . SITE_URL . "/public/login.php");
    exit();
}

// Enforce fingerprinting for active sessions
enforceSessionFingerprint();

// ROLE RESTRICTIONS with better error handling
function checkRole($allowedRoles = []) {
    // Ensure allowedRoles is an array
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    // Check if user's role is allowed
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        // Log unauthorized access attempt
        error_log("UNAUTHORIZED ACCESS ATTEMPT: User ID: " . ($_SESSION['user_id'] ?? 'UNKNOWN') . 
                 " attempted to access page requiring roles: " . implode(', ', $allowedRoles) . 
                 " (Current role: " . ($_SESSION['role'] ?? 'UNKNOWN') . ") at " . $_SERVER['REQUEST_URI']);
        
        // Redirect to appropriate page based on role
        $current_role = $_SESSION['role'] ?? 'guest';
        
        switch($current_role) {
            case 'admin':
                header("Location: " . SITE_URL . "/admin/admin_dashboard.php");
                break;
            case 'host':
            case 'manager':
                header("Location: " . SITE_URL . "/host/host_dashboard.php");
                break;
            case 'renter':
                header("Location: " . SITE_URL . "/renter/my_bookings.php");
                break;
            default:
                header("Location: " . SITE_URL . "/public/login.php");
        }
        exit();
    }
}

// FUNCTION: Get user's role for role-based redirects
function getUserDashboardURL($role) {
    switch($role) {
        case 'admin':
            return SITE_URL . "/admin/admin_dashboard.php";
        case 'host':
        case 'manager':
            return SITE_URL . "/host/host_dashboard.php";
        case 'renter':
            return SITE_URL . "/renter/reserve_unit.php";
        default:
            return SITE_URL . "/public/login.php";
    }
}
?>