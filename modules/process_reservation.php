<?php
// Process reservation actions: approve, reject, update status
include_once dirname(__FILE__) . '/../includes/session.php';
include_once dirname(__FILE__) . '/../includes/functions.php';
include_once dirname(__FILE__) . '/../config/db.php';

checkRole(['host', 'manager', 'admin', 'renter']);

$redirectAfter = ($_SESSION['role'] ?? '') === 'renter' ? '../renter/my_bookings.php' : 'reservations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectAfter);
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['flash_error'] = 'Security validation failed.';
    header('Location: ' . $redirectAfter);
    exit;
}

$action = $_POST['action'] ?? '';
$reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;

if ($reservationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid reservation ID.';
    header('Location: ' . $redirectAfter);
    exit;
}

/** Normalize UI hyphenated statuses to DB underscores */
function normalize_reservation_status($raw) {
    $s = strtolower(str_replace('-', '_', trim((string)$raw)));
    $allowed = ['pending', 'approved', 'confirmed', 'checked_in', 'checked_out', 'completed', 'cancelled'];
    return in_array($s, $allowed, true) ? $s : '';
}

try {
    if ($action === 'approve') {
        if (!in_array($_SESSION['role'], ['host', 'manager', 'admin'], true)) {
            throw new Exception('Unauthorized');
        }

        execute_query(
            "UPDATE reservations SET status = 'confirmed', approved_by = ?, approved_at = NOW() WHERE reservation_id = ?",
            [$_SESSION['user_id'], $reservationId]
        );

        $res = get_single_result("SELECT user_id FROM reservations WHERE reservation_id = ?", [$reservationId]);
        if ($res && $res['user_id']) {
            sendNotification(
                (int)$res['user_id'],
                'Booking Approved',
                'Your booking has been approved by the host. Please complete payment to confirm your reservation.',
                'booking',
                'system'
            );
        }

        $_SESSION['flash_success'] = 'Reservation approved.';
    } elseif ($action === 'reject') {
        if (!in_array($_SESSION['role'], ['host', 'manager', 'admin'], true)) {
            throw new Exception('Unauthorized');
        }

        $reason = sanitize_input($_POST['reason'] ?? '');
        execute_query(
            "UPDATE reservations SET status = 'cancelled', cancellation_reason = ? WHERE reservation_id = ?",
            [$reason, $reservationId]
        );

        $res = get_single_result("SELECT user_id FROM reservations WHERE reservation_id = ?", [$reservationId]);
        if ($res && $res['user_id']) {
            sendNotification(
                (int)$res['user_id'],
                'Booking Rejected',
                'Your booking request was rejected by the host.' . ($reason ? ' Reason: ' . $reason : ''),
                'booking',
                'system'
            );
        }

        $_SESSION['flash_success'] = 'Reservation rejected.';
    } elseif ($action === 'update_status') {
        $newStatus = normalize_reservation_status($_POST['new_status'] ?? '');
        if ($newStatus === '') {
            throw new Exception('Invalid status.');
        }

        $allowedByRole = false;
        if (in_array($_SESSION['role'], ['host', 'manager', 'admin'], true)) {
            $allowedByRole = true;
        } elseif ($_SESSION['role'] === 'renter') {
            $allowedByRole = in_array($newStatus, ['checked_in', 'checked_out'], true);
        }

        if (!$allowedByRole) {
            throw new Exception('Unauthorized status change');
        }

        if ($_SESSION['role'] === 'renter') {
            $own = get_single_result(
                "SELECT reservation_id FROM reservations WHERE reservation_id = ? AND user_id = ?",
                [$reservationId, $_SESSION['user_id']]
            );
            if (!$own) {
                throw new Exception('Reservation not found or unauthorized');
            }
        }

        execute_query("UPDATE reservations SET status = ? WHERE reservation_id = ?", [$newStatus, $reservationId]);

        $r = get_single_result("SELECT user_id, branch_id, unit_id FROM reservations WHERE reservation_id = ?", [$reservationId]);
        if ($r) {
            $notifyIds = [];
            $br = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$r['branch_id']]);
            if ($br && !empty($br['host_id'])) {
                $notifyIds[] = (int)$br['host_id'];
            }
            $uh = get_single_result("SELECT host_id FROM units WHERE unit_id = ?", [$r['unit_id']]);
            if ($uh && !empty($uh['host_id'])) {
                $notifyIds[] = (int)$uh['host_id'];
            }
            $notifyIds = array_values(array_unique(array_filter($notifyIds)));
            foreach ($notifyIds as $hid) {
                if ($newStatus === 'checked_in') {
                    sendNotification($hid, 'Guest Checked-In', 'Guest has checked in for reservation #' . $reservationId, 'booking', 'system');
                }
                if ($newStatus === 'checked_out') {
                    sendNotification($hid, 'Guest Checked-Out', 'Guest has checked out for reservation #' . $reservationId, 'booking', 'system');
                }
            }
        }

        $_SESSION['flash_success'] = 'Reservation status updated.';
    } else {
        throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . $redirectAfter);
exit;
