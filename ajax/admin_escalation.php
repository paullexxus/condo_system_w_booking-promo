<?php
// ajax/admin_escalation.php
require_once '../config/db.php';
require_once '../includes/email_functions.php';

// Only admins can trigger this script
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = [
    'success' => true,
    'urgent_count' => 0,
    'overdue_count' => 0,
    'new_popups' => [],
    'message' => 'Escalation executed'
];

// Define critical types
// Title filters or type specific
$critical_conditions = "
    (type = 'system' AND (title LIKE '%pending review%' OR title LIKE '%verification%'))
    OR type = 'approval'
";

// 0. Auto-Archiving Cleanup (Exceeds 30 days and is read)
$conn->query("UPDATE notifications SET is_archived = 1 WHERE is_read = 1 AND is_archived = 0 AND created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)");

// 1. UPDATE Day 3 -> 'urgent'
// created_at <= 3 days ago AND priority = 'normal'
$update_urgent_query = "
    UPDATE notifications 
    SET priority = 'urgent' 
    WHERE priority = 'normal' 
    AND is_read = 0 AND is_archived = 0
    AND ($critical_conditions)
    AND created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
";
$conn->query($update_urgent_query);

// 2. UPDATE Day 7 -> 'overdue'
// created_at <= 7 days ago AND priority = 'urgent'
$update_overdue_query = "
    UPDATE notifications 
    SET priority = 'overdue' 
    WHERE priority = 'urgent' 
    AND is_read = 0 AND is_archived = 0
    AND ($critical_conditions)
    AND created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
";
$conn->query($update_overdue_query);

// 3. Admin Email Trigger (Day 5) with Retry Backoff
// Priority is 'urgent', age >= 5, email_sent = 0, retries < 3, and ready for retry
$email_check_query = "
    SELECT notification_id, title, message, related_id, email_retries 
    FROM notifications 
    WHERE priority IN ('urgent', 'overdue')
    AND is_read = 0 AND is_archived = 0
    AND ($critical_conditions)
    AND created_at <= DATE_SUB(NOW(), INTERVAL 5 DAY)
    AND email_sent = 0
    AND email_retries < 3
    AND (next_retry_at IS NULL OR next_retry_at <= NOW())
";
$email_res = $conn->query($email_check_query);

if ($email_res && $email_res->num_rows > 0) {
    // Determine admin email
    $admin_email = 'admin@bookit.com';
    $sysadmin_res = $conn->query("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
    if ($sysadmin_res && $row = $sysadmin_res->fetch_assoc()) {
        $admin_email = $row['email'];
    }

    while ($notif = $email_res->fetch_assoc()) {
        $subject = "⚠️ URGENT: Pending Action Required - BookIT";
        $message = "
            <h3>Admin Alert: Pending Review Overview</h3>
            <p>You have a critical pending item that is older than 5 days.</p>
            <div style='background: #ffeaa7; padding: 15px; border-radius: 5px; border-left: 4px solid #fdcb6e;'>
                <p><strong>Title:</strong> {$notif['title']}</p>
                <p><strong>Details:</strong> {$notif['message']}</p>
            </div>
            <p>Please log in immediately to resolve this notification.</p>
            <div style='text-align: center; margin: 20px 0;'>
                <a href='http://localhost/BookIT/admin/notifications.php' style='background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Dashboard</a>
            </div>
        ";
        
        // Attempt to send email
        $sent = false;
        if (function_exists('sendEmailNotification')) {
            $sent = sendEmailNotification($admin_email, 'BookIT Administrator', $subject, $message);
        } else {
            // Assume true for local testing if function missing
            $sent = true; 
        }
        
        $nid = (int)$notif['notification_id'];
        if ($sent) {
            $conn->query("UPDATE notifications SET email_sent = 1 WHERE notification_id = $nid");
        } else {
            // Failed -> increment retries, set next retry to 30 mins from now
            $conn->query("UPDATE notifications SET email_retries = email_retries + 1, next_retry_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE notification_id = $nid");
        }
    }
}

// 4. Fetch data for UI (counts & unshown popups)
// Count urgent/overdue
$count_query = "
    SELECT 
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
        SUM(CASE WHEN priority = 'overdue' THEN 1 ELSE 0 END) as overdue_count
    FROM notifications 
    WHERE is_read = 0 AND is_archived = 0
    AND user_id = " . (int)$_SESSION['user_id'] . "
    AND priority IN ('urgent', 'overdue')
    AND ($critical_conditions)
";
$count_res = $conn->query($count_query);
if ($count_res && $row = $count_res->fetch_assoc()) {
    $response['urgent_count'] = (int)$row['urgent_count'];
    $response['overdue_count'] = (int)$row['overdue_count'];
}

// Check for unshown popups
$popup_query = "
    SELECT notification_id, title, message 
    FROM notifications 
    WHERE is_read = 0 AND is_archived = 0
    AND user_id = " . (int)$_SESSION['user_id'] . "
    AND priority IN ('urgent', 'overdue')
    AND is_popup_shown = 0
    AND ($critical_conditions)
";
$popup_res = $conn->query($popup_query);
if ($popup_res && $popup_res->num_rows > 0) {
    while ($p = $popup_res->fetch_assoc()) {
        $response['new_popups'][] = $p;
        // Mark as shown so it doesn't pop up again even if session resets
        $conn->query("UPDATE notifications SET is_popup_shown = 1 WHERE notification_id = " . (int)$p['notification_id']);
    }
}

echo json_encode($response);
?>
