<?php
// cron/automation_engine.php
// This script simulates a CRON task to process automated communications (e.g. reminders)

// Allow execution from CLI or via HTTP (admin dashboard trigger)
// In production, limit HTTP access using a secret key or restrict to CLI only.
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli) {
    include '../includes/session.php';
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die("Unauthorized access.");
    }
}

// Adjust include paths based on execution context
$doc_root = dirname(__DIR__);
include_once $doc_root . '/config/db.php';
include_once $doc_root . '/includes/functions.php';
include_once $doc_root . '/includes/encryption.php';

echo "<h2>Starting Automation Engine...</h2>";
ob_flush(); flush();

$logs = [];

function add_log($text) {
    global $logs;
    $text = "[" . date('Y-m-d H:i:s') . "] " . $text;
    $logs[] = $text;
    echo $text . "<br>";
    ob_flush(); flush();
}

add_log("Checking for triggers: 24_hours_before_checkin");

// Find all confirmed reservations that are exactly 1 day away from checking in
// We assume check_in is tomorrow.
$sql = "SELECT r.reservation_id, r.user_id as renter_id, r.unit_id, r.branch_id, r.check_in_date, 
               u.unit_name, b.host_id, us.full_name as renter_name 
        FROM reservations r 
        INNER JOIN units u ON r.unit_id = u.unit_id
        INNER JOIN branches b ON r.branch_id = b.branch_id
        INNER JOIN users us ON r.user_id = us.user_id
        WHERE r.status = 'confirmed' 
        AND r.check_in_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";

$upcoming_reservations = get_multiple_results($sql);

if (empty($upcoming_reservations)) {
    add_log("No upcoming reservations found for tomorrow.");
} else {
    foreach ($upcoming_reservations as $res) {
        add_log("Found reservation #{$res['reservation_id']} (Unit: {$res['unit_name']}) starting tomorrow.");
        
        // Check if the host has a 24_hours_before_checkin template
        $template = get_single_result("SELECT * FROM notification_templates WHERE host_id = ? AND trigger_event = '24_hours_before_checkin'", [$res['host_id']]);
        
        if ($template) {
            // Check if this specific reminder was already sent (to avoid spamming if cron runs multiple times)
            // We can look into message_logs but since we don't store "context" easily, we check messages directly 
            // by looking for messages sent by host to this booking within the last 24hrs matching the text...
            // Or better, just add a simple check in `messages`.
            $msg_check = get_single_result("SELECT message_id FROM messages WHERE sender_id = ? AND receiver_id = ? AND booking_id = ? AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)", [$res['host_id'], $res['renter_id'], $res['reservation_id']]);
            
            if (!$msg_check) {
                // Personalize the message
                $message_body = str_replace(['{guest_name}', '{unit_name}', '{check_in_date}'], [$res['renter_name'], $res['unit_name'], date('M d, Y', strtotime($res['check_in_date']))], $template['message']);
                
                // Send automated system notification + chat message
                $encrypted_msg = encrypt_message($message_body);
                
                $insert_sql = "INSERT INTO messages (sender_id, receiver_id, booking_id, message, is_encrypted) VALUES (?, ?, ?, ?, 1)";
                if (execute_query($insert_sql, [$res['host_id'], $res['renter_id'], $res['reservation_id'], $encrypted_msg])) {
                    global $conn;
                    $message_id = $conn->insert_id;
                    execute_query("INSERT INTO message_logs (message_id, action) VALUES (?, 'sent')", [$message_id]);
                    
                    // Also notify the renter using the system notification
                    sendNotification($res['renter_id'], $template['title'], "You have a new automated message from your host regarding your check-in tomorrow.", 'reminder', 'system');
                    
                    add_log("SUCCESS: Automated message sent to Renter #{$res['renter_id']} from Host #{$res['host_id']}.");
                } else {
                    add_log("ERROR: Failed to save automated message to database.");
                }
            } else {
                add_log("SKIP: Reminder already sent for Reservation #{$res['reservation_id']} within the last 24 hours.");
            }
        } else {
            add_log("SKIP: Host #{$res['host_id']} does not have a '24_hours_before_checkin' template.");
        }
    }
}

add_log("Cleaning up old messages (7-Day Auto Delete)...");

// Implement 7-Day Auto-Cleanup feature for old messages
$cleanup_sql = "DELETE FROM messages WHERE sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
if (execute_query($cleanup_sql)) {
    global $conn;
    $deleted_rows = mysqli_affected_rows($conn);
    if ($deleted_rows > 0) {
        add_log("SUCCESS: Deleted {$deleted_rows} messages older than 7 days.");
    } else {
        add_log("SKIP: No old messages to delete.");
    }
} else {
    add_log("ERROR: Failed to run cleanup query.");
}

add_log("Automation Engine scan completed.");
echo "<h3>Finished!</h3>";

// If via HTTP, return simple json response optionally
if (!$is_cli && isset($_GET['type']) && $_GET['type'] == 'json') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'logs' => $logs]);
    exit;
}
?>
