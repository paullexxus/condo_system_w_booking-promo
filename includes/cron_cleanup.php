<?php
// cron_cleanup.php - Automated cleanup for expired reservations
// This can be set up as a real cron job or called on page loads (Pseudo-cron)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

function runCleanup() {
    global $conn;
    
    // 1. Cancel expired pending reservations (Hold Expiry)
    // Formula: Status is pending AND hold_expiry < NOW
    $sql = "UPDATE reservations 
            SET status = 'cancelled', 
                cancellation_reason = 'Automated: Payment hold expired (10-minute limit exceeded)' 
            WHERE status = 'pending' 
            AND hold_expiry IS NOT NULL 
            AND hold_expiry < NOW()";
    
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            error_log("CRON: Cancelled $affected expired reservations.");
            // Optional: Send notifications to affected users here if needed
        }
    } else {
        error_log("CRON ERROR: " . $conn->error);
    }

    // 2. Release units that were cancelled (already handled by status update, but double check is_available if your logic uses it)
    // In this system, availability checks ignore 'cancelled' status automatically.
}

// If called directly, run it
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    runCleanup();
    echo "Cleanup complete.";
}
?>
