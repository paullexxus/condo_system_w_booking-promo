<?php
/**
 * Admin Notification Escalation System
 * TRACKS notification age:
 * Day 3 -> urgent
 * Day 5 -> email reminder to admin
 * Day 7 -> overdue strike
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_integration.php';

echo "Starting Admin Notification Escalation Process...\n";

// Fetch unread approval/system notifications targeting admins
$query = "SELECT n.*, u.email as admin_email, u.full_name as admin_name 
          FROM notifications n
          JOIN users u ON n.user_id = u.user_id
          WHERE u.role = 'admin' 
          AND n.is_read = 0 
          AND n.type IN ('approval', 'system')
          AND n.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)";

$result = mysqli_query($conn, $query);
$escalated_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $notif_id = $row['notification_id'];
    $admin_email = $row['admin_email'];
    $admin_name = $row['admin_name'];
    $created_at = strtotime($row['created_at']);
    $days_old = floor((time() - $created_at) / 86400);
    $current_priority = $row['priority'];
    
    echo "Processing Notification ID: $notif_id ($days_old days old)\n";

    // 1. Escalation: Day 7 -> OVERDUE
    if ($days_old >= 7 && $current_priority !== 'overdue') {
        mysqli_query($conn, "UPDATE notifications SET priority = 'overdue' WHERE notification_id = $notif_id");
        logAudit(null, 'notification_escalation', 'notification', $notif_id, "Notification #$notif_id escalated to OVERDUE (7+ days old)");
        $escalated_count++;
    } 
    // 2. Escalation: Day 5 -> Email Reminder
    else if ($days_old >= 5 && $row['email_sent'] == 0) {
        $subject = "⚠️ URGENT: Pending Administrative Action Required (#$notif_id)";
        $body = "<h2>Administrative Escalation</h2>
                 <p>Dear $admin_name,</p>
                 <p>This is an automated reminder that a pending request (ID: #{$row['related_id']}) requires your attention. It has been unread for <strong>$days_old days</strong>.</p>
                 <p><strong>Title:</strong> {$row['title']}</p>
                 <p><strong>Message:</strong> {$row['message']}</p>
                 <p>Please log in to the dashboard to process this request immediately.</p>
                 <br><p>BookIT Automation System</p>";
        
        if (sendEmailViaPhpMail($admin_email, $admin_name, $subject, $body)) {
            mysqli_query($conn, "UPDATE notifications SET email_sent = 1 WHERE notification_id = $notif_id");
            echo "Email reminder sent to $admin_email\n";
        }
    }
    // 3. Escalation: Day 3 -> URGENT
    else if ($days_old >= 3 && $current_priority === 'normal') {
        mysqli_query($conn, "UPDATE notifications SET priority = 'urgent' WHERE notification_id = $notif_id");
        logAudit(null, 'notification_escalation', 'notification', $notif_id, "Notification #$notif_id escalated to URGENT (3+ days old)");
        $escalated_count++;
    }
}

echo "Process completed. Escalated $escalated_count notifications.\n";
