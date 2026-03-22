<?php
// includes/security.php
function preventBackButton() {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // For logged-in users, prevent caching
    if (isset($_SESSION['user_id'])) {
        header("Cache-Control: private, no-cache, no-store, must-revalidate");
    }
}

function clearLoginPageCache() {
    if (isset($_SESSION['user_id'])) {
        // Redirect based on user role to their dashboard
        $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
        
        if ($role == 'admin') {
            header("Location: /BookIT/admin/admin_dashboard.php");
        } elseif ($role == 'host') {
            header("Location: /BookIT/host/host_dashboard.php");
        } elseif ($role == 'renter') {
            header("Location: /BookIT/public/index.php");
        } else {
            header("Location: /BookIT/public/index.php");
        }
        exit;
    }
}
?>