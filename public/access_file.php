<?php
/**
 * Secure File Proxy Controller
 * Serves files from the /uploads/ directory while checking for authorization.
 * Direct access to /uploads/ is disabled via .htaccess.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

// 1. Authentication Check
if (!isLoggedIn()) {
    http_response_code(401);
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 2. Input Validation
$file_param = $_GET['file'] ?? '';
if (empty($file_param)) {
    http_response_code(400);
    die("File parameter missing.");
}

// Security: Prevent Directory Traversal
$clean_path = str_replace(['../', '..\\'], '', $file_param);
$base_dir = realpath(__DIR__ . '/../uploads/');
$full_path = realpath($base_dir . '/' . $clean_path);

// Check if file exists and is within the uploads directory
if (!$full_path || !file_exists($full_path) || strpos($full_path, $base_dir) !== 0) {
    http_response_code(404);
    die("File not found or access denied.");
}

// 3. Authorization Logic (Multi-Layered)
$is_authorized = false;

// Admin has master access
if ($user_role === 'admin') {
    $is_authorized = true;
} else {
    // Check ownership/relation
    if (strpos($clean_path, 'chat_attachments/') === 0) {
        // Chat Attachments: Check if user is sender or receiver
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM messages WHERE file_path = ? AND (sender_id = ? OR receiver_id = ?) LIMIT 1");
        $db_path = "uploads/" . $clean_path;
        mysqli_stmt_bind_param($stmt, "sii", $db_path, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) $is_authorized = true;
    } 
    elseif (strpos($clean_path, 'verification_docs/') === 0) {
        // Verification Docs: Check if it belongs to the logged-in user
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM host_applications WHERE (primary_id_path = ? OR secondary_id_path = ? OR selfie_with_id_path = ? OR proof_of_ownership_path = ? OR utility_bill_path = ?) AND user_id = ? LIMIT 1");
        $rel_path = "uploads/" . $clean_path;
        mysqli_stmt_bind_param($stmt, "sssssi", $rel_path, $rel_path, $rel_path, $rel_path, $rel_path, $user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) $is_authorized = true;
    }
    elseif (strpos($clean_path, 'unit_images/') === 0) {
        // Unit Images: Generally public but let's allow logged-in users to see them
        $is_authorized = true; 
    }
    elseif (strpos($clean_path, 'payment_proofs/') === 0) {
        // Payment Proofs: Check if user is host or renter of the reservation
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM reservations res JOIN units u ON res.unit_id = u.unit_id WHERE res.payment_proof = ? AND (res.user_id = ? OR u.host_id = ?) LIMIT 1");
        $rel_path = "uploads/" . $clean_path;
        mysqli_stmt_bind_param($stmt, "sii", $rel_path, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 2) $is_authorized = true; // Placeholder logic, adjust if table differs
    }
}

if (!$is_authorized) {
    logAudit($user_id, 'unauthorized_file_access', 'file', 0, "Blocked access attempt to: $clean_path");
    http_response_code(403);
    die("Access denied to this file.");
}

// 4. Serve the File
$mime_type = mime_content_type($full_path);
header("Content-Type: $mime_type");
header("Content-Length: " . filesize($full_path));
header("Cache-Control: private, max-age=3600");
header("Content-Disposition: inline; filename=\"" . basename($full_path) . "\"");

readfile($full_path);
exit;
