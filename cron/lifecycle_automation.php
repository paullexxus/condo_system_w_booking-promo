<?php
/**
 * BookIT Lifecycle Automation Cron
 * Run this via scheduled task every 5 minutes
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Prepare Audit Logging
function logAudit($action, $table, $recordId, $oldData, $newData, $userId = null) {
    global $conn;
    // Ensure table exists first!
    $chk = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if ($chk->num_rows == 0) {
        $conn->query("CREATE TABLE audit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(50) NOT NULL,
            table_name VARCHAR(50) NOT NULL,
            record_id INT NOT NULL,
            old_data TEXT NULL,
            new_data TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)";
    execute_query($sql, [$userId, $action, $table, $recordId, json_encode($oldData), json_encode($newData)]);
}

$conn->begin_transaction();
try {
    // 1. Release expired 10-minute holds (where status = 'pending' and hold_expiry < NOW())
    $expiredHolds = get_multiple_results("SELECT reservation_id, user_id FROM reservations WHERE status = 'pending' AND hold_expiry < NOW()");
    
    if (!empty($expiredHolds)) {
        foreach ($expiredHolds as $hold) {
            // Update to expired
            execute_query("UPDATE reservations SET status = 'expired' WHERE reservation_id = ?", [$hold['reservation_id']]);
            
            // Log it
            logAudit('booking_expired', 'reservations', $hold['reservation_id'], ['status' => 'pending'], ['status' => 'expired']);
            
            // Refund/zero-charge (cancel any pending payments associated)
            execute_query("UPDATE payments SET payment_status = 'failed' WHERE reservation_id = ? AND payment_status = 'pending'", [$hold['reservation_id']]);
            
            // Notify user
            sendNotification($hold['user_id'], 'Reservation Expired', "Your 10-minute reservation hold (#{$hold['reservation_id']}) has expired due to lack of payment.", 'payment', 'system');
        }
        echo "Expired " . count($expiredHolds) . " holds.\n";
    }
    
    // 2. Notification Reminders (e.g. checkin_reminder)
    // Send check-in reminder for bookings that start tomorrow
    $tomorrowBookings = get_multiple_results("SELECT reservation_id, user_id FROM reservations WHERE status = 'confirmed' AND DATE(check_in_date) = DATE(DATE_ADD(NOW(), INTERVAL 1 DAY))");
    
    if (!empty($tomorrowBookings)) {
        foreach ($tomorrowBookings as $booking) {
            // Check if reminder was already sent (prevent spam)
            $checkLog = get_single_result("SELECT log_id FROM audit_logs WHERE action = 'checkin_reminder' AND record_id = ?", [$booking['reservation_id']]);
            if (!$checkLog) {
                sendNotification($booking['user_id'], 'Check-in Reminder', "Get ready! Your reservation (#{$booking['reservation_id']}) starts tomorrow. Please review your booking details.", 'booking', 'system');
                logAudit('checkin_reminder', 'reservations', $booking['reservation_id'], null, null);
            }
        }
        echo "Sent " . count($tomorrowBookings) . " check-in reminders.\n";
    }
    
    // 3. Payment Reminder
    // Send reminder for bookings that are confirmed but partial_paid and nearing check-in (e.g. 3 days before)
    $paymentReminders = get_multiple_results("SELECT reservation_id, user_id FROM reservations WHERE status = 'confirmed' AND payment_status = 'partial_paid' AND DATE(check_in_date) = DATE(DATE_ADD(NOW(), INTERVAL 3 DAY))");
    if (!empty($paymentReminders)) {
        foreach ($paymentReminders as $booking) {
            $checkLog = get_single_result("SELECT log_id FROM audit_logs WHERE action = 'payment_reminder' AND record_id = ?", [$booking['reservation_id']]);
            if (!$checkLog) {
                sendNotification($booking['user_id'], 'Payment Reminder', "Friendly reminder! Your reservation (#{$booking['reservation_id']}) starts in 3 days. Please settle your remaining balance prior to check-in.", 'payment', 'system');
                logAudit('payment_reminder', 'reservations', $booking['reservation_id'], null, null);
            }
        }
        echo "Sent " . count($paymentReminders) . " payment reminders.\n";
    }

    $conn->commit();
    echo "Lifecycle automation completed successfully.\n";
    
} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    echo "Error processing lifecycle automation: " . $e->getMessage() . "\n";
}
