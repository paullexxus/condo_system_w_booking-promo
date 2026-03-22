<?php
// Ensure session settings from constants are applied before starting session
require_once __DIR__ . '/../config/constants.php';

// Start session only if one isn't already active to avoid duplicate session warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CHECK IF NO SESSION (user not logged in)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

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