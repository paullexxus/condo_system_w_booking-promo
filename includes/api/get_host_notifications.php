<?php
/**
 * Host Notifications API
 * Returns real-time notifications for host including pending bookings
 */

include_once '../../config/db.php';
include_once '../functions.php';
include_once '../session.php';

header('Content-Type: application/json');

// Verify host is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'host') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$host_id = $_SESSION['user_id'];

try {
    // Get pending approval bookings
    $pending_reservations = get_multiple_results(
        "SELECT r.reservation_id, r.total_amount, r.check_in_date, r.status,
                u.unit_number, u.unit_id,
                renter.full_name as renter_name, renter.email as renter_email
         FROM reservations r
         INNER JOIN units u ON r.unit_id = u.unit_id
         INNER JOIN users renter ON r.user_id = renter.user_id
         WHERE u.host_id = ? AND r.status = 'awaiting_approval'
         ORDER BY r.created_at DESC",
        [$host_id]
    );
    
    // Get unread notifications
    $notifications = get_multiple_results(
        "SELECT * FROM notifications 
         WHERE user_id = ? AND is_read = 0
         ORDER BY created_at DESC
         LIMIT 10",
        [$host_id]
    );
    
    // Get upcoming check-ins (next 7 days)
    $upcoming_checkins = get_multiple_results(
        "SELECT r.reservation_id, r.check_in_date, 
                u.unit_number, 
                renter.full_name as renter_name
         FROM reservations r
         INNER JOIN units u ON r.unit_id = u.unit_id
         INNER JOIN users renter ON r.user_id = renter.user_id
         WHERE u.host_id = ? 
         AND r.status = 'confirmed'
         AND DATE(r.check_in_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY r.check_in_date ASC",
        [$host_id]
    );
    
    // Return data
    echo json_encode([
        'success' => true,
        'pending_count' => count($pending_reservations),
        'notification_count' => count($notifications),
        'upcoming_checkins_count' => count($upcoming_checkins),
        'pending_reservations' => $pending_reservations,
        'notifications' => $notifications,
        'upcoming_checkins' => $upcoming_checkins
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching notifications'
    ]);
}
?>
