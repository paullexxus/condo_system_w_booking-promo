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
    } elseif ($action === 'reserve' || $action === 'book_confirm') {
        // 🔒 1. Create New Reservation with Safety Layer
        $data = $_SESSION['pending_booking_data'] ?? $_POST;
        if (empty($data['unit_id'])) throw new Exception('Booking data expired. Please try again.');

        $unitId = (int)$data['unit_id'];
        $checkIn = sanitize_input($data['check_in_date']);
        $checkOut = sanitize_input($data['check_out_date']);

        // Check availability strictly one last time
        if (!isUnitAvailable($unitId, $checkIn, $checkOut)) {
            throw new Exception('Unit was just booked by someone else. Please try another unit.');
        }

        // Calculate Snapshots (Requirement 2: PLATFORM FEE CONSISTENCY)
        $unit = getUnitWithDefaults($unitId);
        $totalDays = calculateDays($checkIn, $checkOut);
        
        $pricing_type = $unit['pricing_type'] ?? 'nightly';
        $dailyRate = in_array($pricing_type, ['nightly', 'daily']) ? (float)$unit['price_per_night'] : (float)$unit['price_per_month'] / 30;
        
        $baseTotal = $dailyRate * $totalDays;
        
        // Extra Guests
        $adults = (int)($data['num_adults'] ?? 1);
        $children = (int)($data['num_children'] ?? 0);
        $totalGuests = $adults + $children;
        $maxBase = (int)($unit['max_occupancy'] ?? 1);
        $extraGuests = max(0, $totalGuests - $maxBase);
        $extraFeeTotal = $extraGuests * (float)($unit['extra_guest_fee'] ?? 0) * $totalDays;
        
        // Amenities Snapshot
        $amenityTotal = 0;
        $selectedAmenities = $data['amenities'] ?? $data['addon_ids'] ?? [];
        foreach ($selectedAmenities as $aid) {
            $am = get_single_result("SELECT hourly_rate FROM amenities WHERE amenity_id = ?", [(int)$aid]);
            if ($am) $amenityTotal += (float)$am['hourly_rate'] * $totalDays;
        }

        // Total Booking Amount for Host cut calculation (90/10)
        $totalGross = $baseTotal + $extraFeeTotal + $amenityTotal;
        $platformFee = $totalGross * 0.10;
        $hostAmount = $totalGross * 0.90;

        // Security Deposit & Fees (Non-revenue for Host)
        $cleaning = (float)($unit['cleaning_fee'] ?? 0);
        $service = (float)($unit['service_fee'] ?? 0);
        $deposit = (float)($unit['security_deposit'] ?? 0);
        
        $grandTotal = $totalGross + $cleaning + $service + $deposit;

        // INSERT Reservation with Hold Expiry (Requirement 2: Reservation Expiry Safety Layer)
        $holdExpiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        $sql = "INSERT INTO reservations (
                    user_id, unit_id, branch_id, check_in_date, check_out_date, 
                    num_adults, num_children, status, payment_status, total_amount, 
                    security_deposit, special_requests, hold_expiry,
                    base_amount_snapshot, amenities_amount_snapshot, extra_guest_amount_snapshot,
                    platform_fee_snapshot, host_amount_snapshot
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        execute_query($sql, [
            $_SESSION['user_id'], $unitId, (int)$data['branch_id'], $checkIn, $checkOut,
            $adults, $children, $grandTotal, $deposit, $data['special_requests'] ?? '', $holdExpiry,
            $baseTotal, $amenityTotal, $extraFeeTotal, $platformFee, $hostAmount
        ]);

        $newID = $conn->insert_id;
        unset($_SESSION['pending_booking_data']);

        if ($action === 'book_confirm') {
            header("Location: ../renter/payment.php?reservation_id=" . $newID);
            exit;
        } else {
            $_SESSION['flash_success'] = 'Unit held for 30 minutes. Please complete payment.';
            header("Location: ../renter/my_bookings.php");
            exit;
        }
    } else {
        throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . $redirectAfter);
exit;
