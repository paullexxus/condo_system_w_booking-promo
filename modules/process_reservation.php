<?php
// Process reservation actions: approve, reject, update status
include_once dirname(__FILE__) . '/../includes/session.php';
include_once dirname(__FILE__) . '/../includes/functions.php';
include_once dirname(__FILE__) . '/../config/db.php';

// Only hosts/managers/admins or the reservation owner (for certain actions) can perform actions
checkRole(['host','manager','admin','renter']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reservations.php');
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['flash_error'] = 'Security validation failed.';
    header('Location: reservations.php');
    exit;
}

$action = $_POST['action'] ?? '';
$reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;

if ($reservationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid reservation ID.';
    header('Location: reservations.php');
    exit;
}

try {
    if ($action === 'approve') {
        // Only host/manager/admin can approve
        if (!in_array($_SESSION['role'], ['host','manager','admin'])) {
            throw new Exception('Unauthorized');
        }

        execute_query("UPDATE reservations SET reservation_status = 'approved', approved_by = ?, approved_at = NOW() WHERE reservation_id = ?", [$_SESSION['user_id'], $reservationId]);

        // Notify renter
        $res = get_single_result("SELECT user_id FROM reservations WHERE reservation_id = ?", [$reservationId]);
        if ($res && $res['user_id']) {
            sendNotification($res['user_id'], 'Booking Approved', 'Your booking has been approved by the host. Please complete payment to confirm your reservation.', 'booking', 'system');
        }

        $_SESSION['flash_success'] = 'Reservation approved.';
    } elseif ($action === 'reject') {
        if (!in_array($_SESSION['role'], ['host','manager','admin'])) {
            throw new Exception('Unauthorized');
        }

        $reason = sanitize_input($_POST['reason'] ?? '');
        execute_query("UPDATE reservations SET reservation_status = 'rejected', admin_notes = ? WHERE reservation_id = ?", [$reason, $reservationId]);

        $res = get_single_result("SELECT user_id FROM reservations WHERE reservation_id = ?", [$reservationId]);
        if ($res && $res['user_id']) {
            sendNotification($res['user_id'], 'Booking Rejected', 'Your booking request was rejected by the host.' . ($reason ? ' Reason: ' . $reason : ''), 'booking', 'system');
        }

        $_SESSION['flash_success'] = 'Reservation rejected.';
    } elseif ($action === 'update_status') {
        $newStatus = sanitize_input($_POST['new_status'] ?? '');

        // Hosts/admins can update many statuses; renters can only mark checked-in/checked-out for their own bookings
        $allowedByRole = false;
        if (in_array($_SESSION['role'], ['host','manager','admin'])) {
            $allowedByRole = true;
        } elseif ($_SESSION['role'] === 'renter') {
            // Only allow renter to set checked-out or checked-in on their own reservation
            $allowedByRole = in_array($newStatus, ['checked-in','checked-out']);
        }

        if (!$allowedByRole) throw new Exception('Unauthorized status change');

        // Verify renter owns the reservation if role is renter
        if ($_SESSION['role'] === 'renter') {
            $own = get_single_result("SELECT reservation_id FROM reservations WHERE reservation_id = ? AND user_id = ?", [$reservationId, $_SESSION['user_id']]);
            if (!$own) throw new Exception('Reservation not found or unauthorized');
        }

        // Update status and optional timestamp
        $timestampColumn = '';
        if ($newStatus === 'checked-in') $timestampColumn = ', checked_in_at = NOW()';
        if ($newStatus === 'checked-out') $timestampColumn = ', checked_out_at = NOW()';

        execute_query("UPDATE reservations SET reservation_status = ? $timestampColumn WHERE reservation_id = ?", [$newStatus, $reservationId]);

        // Notify appropriate parties
        $r = get_single_result("SELECT user_id, branch_id FROM reservations WHERE reservation_id = ?", [$reservationId]);
        if ($r) {
            if ($newStatus === 'checked-in') {
                // Notify host that guest checked in
                $branch = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$r['branch_id']]);
                if ($branch && $branch['host_id']) sendNotification($branch['host_id'], 'Guest Checked-In', 'Guest has checked in for reservation #' . $reservationId, 'booking', 'system');
            }
            if ($newStatus === 'checked-out') {
                $branch = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$r['branch_id']]);
                if ($branch && $branch['host_id']) sendNotification($branch['host_id'], 'Guest Checked-Out', 'Guest has checked out for reservation #' . $reservationId, 'booking', 'system');
            }
        }

        $_SESSION['flash_success'] = 'Reservation status updated.';
    } else {
        throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: reservations.php');
exit;

?>
