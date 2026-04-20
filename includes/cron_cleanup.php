<?php
// cron_cleanup.php - Automated cleanup for expired reservations
// This can be set up as a real cron job or called on page loads (Pseudo-cron)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

function runCleanup() {
    global $conn;
    
    // 1. Cancel expired pending reservations (Hold Expiry)
    $sql = "UPDATE reservations 
            SET status = 'cancelled', 
                cancellation_reason = 'Automated: Payment hold expired (30-minute limit exceeded)' 
            WHERE status = 'pending' 
            AND hold_expiry IS NOT NULL 
            AND hold_expiry < NOW()";
    $conn->query($sql);

    // 2. Automate 'Completed' status for checked-out bookings
    $sql_complete = "UPDATE reservations 
                     SET status = 'completed' 
                     WHERE status IN ('confirmed', 'checked_in') 
                     AND check_out_date < CURDATE()";
    $conn->query($sql_complete);

    // 💰 3. Host Fund Release (Check-out + 1 day)
    $sql_release = "UPDATE host_earnings he
                    JOIN reservations r ON he.reservation_id = r.reservation_id
                    SET he.status = 'available', he.available_at = NOW()
                    WHERE he.status = 'pending_release'
                    AND r.status = 'completed'
                    AND DATE_ADD(r.check_out_date, INTERVAL 1 DAY) <= CURDATE()";
    
    if ($conn->query($sql_release)) {
        $released = $conn->affected_rows;
        if ($released > 0) {
            error_log("CRON: Released $released items to host available balance.");
        }
    }

    // 🔔 4. Handle Pending Review Alerts for Admin
    execute_query("UPDATE notifications SET priority = 'urgent' 
                   WHERE type = 'approval' AND is_read = 0 
                   AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

// If called directly, run it
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    runCleanup();
    echo "Cleanup complete.";
}
?>
