<?php
// Host Reservations Management
// View, approve, cancel, and track reservations

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle reservation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['reservation_id'])) {
        $reservation_id = (int)sanitize_input($_POST['reservation_id']);
        $action = sanitize_input($_POST['action']);
        
        // Verify the reservation belongs to this host
        $res = get_single_result(
            "SELECT r.* FROM reservations r 
             INNER JOIN units u ON r.unit_id = u.unit_id 
             INNER JOIN branches b ON r.branch_id = b.branch_id
             WHERE r.reservation_id = ? AND (u.host_id = ? OR b.host_id = ?)",
            [$reservation_id, $host_id, $host_id]
        );
        
        if ($res) {
            if ($action === 'approve') {
                // FIXED: Use prepared statement; record host approval for audit trail
                $updateSql = "UPDATE reservations SET status = 'confirmed', approved_by = ?, approved_at = NOW() WHERE reservation_id = ?";
                execute_query($updateSql, [$host_id, $reservation_id]);
                $action_message = "Reservation approved successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Reservation Approved",
                    "Your reservation #" . $reservation_id . " has been approved by the host! You can now proceed to payment.",
                    'booking',
                    'system'
                );
            } else if ($action === 'cancel') {
                $reason = sanitize_input($_POST['reason'] ?? 'Host cancelled');
                // FIXED: Use prepared statement
                $updateSql = "UPDATE reservations SET status = 'cancelled', cancellation_reason = ? WHERE reservation_id = ?";
                execute_query($updateSql, [$reason, $reservation_id]);
                $action_message = "Reservation cancelled successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Reservation Cancelled",
                    "Your reservation #" . $reservation_id . " has been cancelled. Reason: " . $reason,
                    'booking',
                    'system'
                );
            } else if ($action === 'checkin') {
                // FIXED: Use prepared statement
                $updateSql = "UPDATE reservations SET status = 'checked_in', checked_in_by = ? WHERE reservation_id = ?";
                execute_query($updateSql, [$host_id, $reservation_id]);
                $action_message = "Guest checked in successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Checked In",
                    "You have been checked in to reservation #" . $reservation_id . ".",
                    'booking',
                    'system'
                );
            } else if ($action === 'checkout') {
                // FIXED: Use prepared statement
                $updateSql = "UPDATE reservations SET status = 'completed', checked_out_by = ? WHERE reservation_id = ?";
                execute_query($updateSql, [$host_id, $reservation_id]);
                $action_message = "Guest checked out successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Checked Out",
                    "Your checkout for reservation #" . $reservation_id . " has been completed.",
                    'booking',
                    'system'
                );
            }
        } else {
            $action_message = "Reservation not found or access denied";
        }
    }
}

// Reservation table columns (optional UI fields / audit)
$reservation_col = [];
$rc = mysqli_query($conn, "SHOW COLUMNS FROM reservations");
if ($rc) {
    while ($row = mysqli_fetch_assoc($rc)) {
        $reservation_col[$row['Field']] = true;
    }
    mysqli_free_result($rc);
}
$has_guest_cols = !empty($reservation_col['num_adults']);
$has_check_cols = !empty($reservation_col['checked_in_by']);

// Get all reservations for this host - FIXED: Use prepared statement
if ($has_check_cols) {
    $all_reservations = get_multiple_results(
        <<<'SQL'
        SELECT 
            r.*,
            u.unit_number,
            u.unit_type,
            u.max_occupancy,
            u.price_per_night,
            u.price_per_month,
            u.pricing_type,
            us.full_name as renter_name,
            us.email as renter_email,
            us.phone as renter_phone,
            ap.full_name AS approver_name,
            ci.full_name AS checked_in_by_name,
            co.full_name AS checked_out_by_name,
            COALESCE(pay.paid_total, 0) AS payments_paid_total,
            pay.last_paid_at,
            pay.last_payment_method
        FROM reservations r
        INNER JOIN units u ON r.unit_id = u.unit_id
        INNER JOIN branches b ON r.branch_id = b.branch_id
        INNER JOIN users us ON r.user_id = us.user_id
        LEFT JOIN users ap ON r.approved_by = ap.user_id
        LEFT JOIN users ci ON r.checked_in_by = ci.user_id
        LEFT JOIN users co ON r.checked_out_by = co.user_id
        LEFT JOIN (
            SELECT 
                reservation_id,
                SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN amount ELSE 0 END) AS paid_total,
                MAX(CASE WHEN payment_status IN ('completed', 'paid') THEN payment_date END) AS last_paid_at,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(payment_method ORDER BY payment_date DESC SEPARATOR ','),
                    ',',
                    1
                ) AS last_payment_method
            FROM payments
            WHERE reservation_id IS NOT NULL
            GROUP BY reservation_id
        ) pay ON pay.reservation_id = r.reservation_id
        WHERE (u.host_id = ? OR b.host_id = ?)
        ORDER BY r.check_in_date DESC
        SQL,
        [$host_id, $host_id]
    );
} else {
    $all_reservations = get_multiple_results(
        <<<'SQL'
        SELECT 
            r.*,
            u.unit_number,
            u.unit_type,
            u.max_occupancy,
            u.price_per_night,
            u.price_per_month,
            u.pricing_type,
            us.full_name as renter_name,
            us.email as renter_email,
            us.phone as renter_phone,
            ap.full_name AS approver_name,
            COALESCE(pay.paid_total, 0) AS payments_paid_total,
            pay.last_paid_at,
            pay.last_payment_method
        FROM reservations r
        INNER JOIN units u ON r.unit_id = u.unit_id
        INNER JOIN branches b ON r.branch_id = b.branch_id
        INNER JOIN users us ON r.user_id = us.user_id
        LEFT JOIN users ap ON r.approved_by = ap.user_id
        LEFT JOIN (
            SELECT 
                reservation_id,
                SUM(CASE WHEN payment_status IN ('completed', 'paid') THEN amount ELSE 0 END) AS paid_total,
                MAX(CASE WHEN payment_status IN ('completed', 'paid') THEN payment_date END) AS last_paid_at,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(payment_method ORDER BY payment_date DESC SEPARATOR ','),
                    ',',
                    1
                ) AS last_payment_method
            FROM payments
            WHERE reservation_id IS NOT NULL
            GROUP BY reservation_id
        ) pay ON pay.reservation_id = r.reservation_id
        WHERE (u.host_id = ? OR b.host_id = ?)
        ORDER BY r.check_in_date DESC
        SQL,
        [$host_id, $host_id]
    );
}

$unit_ids = [];
foreach ($all_reservations as $r) {
    $unit_ids[(int)$r['unit_id']] = true;
}
$amenity_by_unit = [];
if (!empty($unit_ids)) {
    $ids = array_keys($unit_ids);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $am_rows = get_multiple_results(
        "SELECT ua.unit_id, a.name AS amenity_name FROM unit_amenities ua INNER JOIN amenities a ON a.id = ua.amenity_id WHERE ua.unit_id IN ($ph)",
        $ids
    );
    if ($am_rows) {
        foreach ($am_rows as $ar) {
            $uid = (int)$ar['unit_id'];
            if (!isset($amenity_by_unit[$uid])) {
                $amenity_by_unit[$uid] = [];
            }
            $amenity_by_unit[$uid][] = $ar['amenity_name'];
        }
    }
}

if (!is_array($all_reservations)) {
    $all_reservations = [];
}

function host_res_payment_method_label($code) {
    if ($code === null || $code === '') {
        return '—';
    }
    $map = [
        'gcash' => 'GCash',
        'paymaya' => 'PayMaya',
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'credit_card' => 'Credit / debit card',
    ];
    $k = strtolower((string)$code);
    return $map[$k] ?? ucfirst(str_replace('_', ' ', $k));
}

function host_res_amenity_icon_class($name) {
    $n = strtolower((string)$name);
    if (strpos($n, 'wifi') !== false || strpos($n, 'wi-fi') !== false) {
        return 'fa-wifi';
    }
    if (strpos($n, 'air') !== false || strpos($n, 'ac') !== false) {
        return 'fa-snowflake';
    }
    if (strpos($n, 'pool') !== false) {
        return 'fa-swimming-pool';
    }
    if (strpos($n, 'park') !== false) {
        return 'fa-parking';
    }
    if (strpos($n, 'tv') !== false) {
        return 'fa-tv';
    }
    if (strpos($n, 'kitchen') !== false) {
        return 'fa-utensils';
    }
    return 'fa-circle-check';
}

function host_res_timeline_level($status) {
    $s = strtolower((string)$status);
    if ($s === 'cancelled') {
        return -1;
    }
    if ($s === 'pending') {
        return 1;
    }
    if ($s === 'approved' || $s === 'confirmed') {
        return 2;
    }
    if ($s === 'checked_in') {
        return 3;
    }
    if ($s === 'checked_out') {
        return 4;
    }
    if ($s === 'completed') {
        return 5;
    }
    return 0;
}

function host_res_id_file_url($path) {
    if (!$path) {
        return '';
    }
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

// Get counts by status - FIXED: Use prepared statement
$status_counts = [];
$statuses = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled'];
foreach ($statuses as $status) {
    if ($status === 'confirmed') {
        $count_result = get_single_result(
            "SELECT COUNT(*) as cnt FROM reservations r 
             INNER JOIN units u ON r.unit_id = u.unit_id 
             INNER JOIN branches b ON r.branch_id = b.branch_id
             WHERE (u.host_id = ? OR b.host_id = ?) AND r.status IN ('confirmed','approved')",
            [$host_id, $host_id]
        );
    } else {
        $count_result = get_single_result(
            "SELECT COUNT(*) as cnt FROM reservations r 
             INNER JOIN units u ON r.unit_id = u.unit_id 
             INNER JOIN branches b ON r.branch_id = b.branch_id
             WHERE (u.host_id = ? OR b.host_id = ?) AND r.status = ?",
            [$host_id, $host_id, $status]
        );
    }
    $status_counts[$status] = $count_result ? (int)$count_result['cnt'] : 0;
}

// Get upcoming check-ins - FIXED: Use prepared statement
$upcoming_checkins = get_multiple_results(
    <<<'SQL'
    SELECT 
        r.*,
        u.unit_number,
        u.unit_type,
        us.full_name as renter_name
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN branches b ON r.branch_id = b.branch_id
    INNER JOIN users us ON r.user_id = us.user_id
    WHERE (u.host_id = ? OR b.host_id = ?)
      AND r.status IN ('confirmed','approved')
      AND DATE(r.check_in_date) = CURDATE()
    ORDER BY r.check_in_date ASC
    SQL,
    [$host_id, $host_id]
);

$calendar_payload = [];
foreach ($all_reservations as $cr) {
    $calendar_payload[] = [
        'id' => (int)$cr['reservation_id'],
        'status' => $cr['status'],
        'check_in' => $cr['check_in_date'],
        'check_out' => $cr['check_out_date'],
        'label' => '#' . ($cr['unit_number'] ?? '') . ' · ' . ($cr['renter_name'] ?? ''),
    ];
}

$page_title = 'Bookings & Reservations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | BookIT Host</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content { padding: 30px; }
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-filter {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .status-filter .btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-filter .btn:hover {
            border-color: #3498db;
            background: #ecf0f1;
        }
        
        .status-filter .btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .reservations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .reservation-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .reservation-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .reservation-unit {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .reservation-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed, .status-approved { background: #cfe2ff; color: #084298; }
        .status-checked_in { background: #d1e7dd; color: #0f5132; }
        .status-checked_out { background: #e7f1ff; color: #055160; }
        .status-completed { background: #cfe2ff; color: #084298; }
        .status-cancelled { background: #f8d7da; color: #842029; }
        
        .view-mode-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin: 10px 0 0 0;
        }
        .view-mode-bar .toggle-pair {
            display: inline-flex;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        .view-mode-bar .toggle-pair button {
            border: none;
            background: transparent;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
        }
        .view-mode-bar .toggle-pair button.active {
            background: #3498db;
            color: #fff;
        }
        
        .reservation-card.card-alert-checkin {
            border-color: #f4a261;
            box-shadow: 0 0 0 3px rgba(244, 162, 97, 0.35);
        }
        .reservation-card.card-alert-payment {
            border-color: #e76f51;
            box-shadow: 0 0 0 3px rgba(231, 111, 81, 0.25);
        }
        .card-alert-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }
        .card-alert-badges span {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .badge-alert-checkin { background: #fff3cd; color: #856404; }
        .badge-alert-pay { background: #fdecea; color: #c0392b; }
        
        .arrival-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 6px;
        }
        .arrival-notyet { background: #fff8e1; color: #b8860b; }
        .arrival-checked_in { background: #d4edda; color: #155724; }
        .arrival-checked_out { background: #e7f1ff; color: #055160; }
        .arrival-completed { background: #e2e3f5; color: #3730a3; }
        
        .timeline-wrap { margin: 12px 0 14px 0; }
        .timeline-labels {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 6px;
            gap: 2px;
        }
        .timeline-labels span { flex: 1; text-align: center; line-height: 1.15; }
        .timeline-track {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .timeline-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #e9ecef;
            border: 2px solid #dee2e6;
            z-index: 1;
            flex-shrink: 0;
        }
        .timeline-dot.done { background: #28a745; border-color: #28a745; }
        .timeline-dot.current {
            background: #ffc107;
            border-color: #e0a800;
            box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.35);
        }
        .timeline-dot.cancel { background: #dc3545; border-color: #dc3545; }
        .timeline-line {
            flex: 1;
            height: 3px;
            background: #e9ecef;
            margin: 0 -2px;
            margin-top: 0;
            align-self: center;
        }
        .timeline-line.fill { background: #28a745; }
        
        .unit-quick {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            font-size: 12px;
            color: #495057;
            background: #f8f9fa;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
        }
        .unit-quick .amen-icons { display: flex; flex-wrap: wrap; gap: 6px; }
        .unit-quick .amen-icons i {
            color: #3498db;
            font-size: 14px;
        }
        
        .contact-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .contact-actions a {
            flex: 1;
            min-width: 88px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            padding: 8px 6px;
            border-radius: 6px;
            text-decoration: none;
            border: 1px solid #dee2e6;
            color: #2c3e50;
            background: #fff;
            transition: background 0.2s, border-color 0.2s;
        }
        .contact-actions a:hover { background: #eef6fc; border-color: #3498db; color: #1a5276; }
        .contact-actions a.ca-phone { color: #0d6efd; }
        .contact-actions a.ca-sms { color: #6f42c1; }
        .contact-actions a.ca-mail { color: #198754; }
        .contact-actions a.ca-chat { color: #fd7e14; }
        
        .payment-block {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .payment-block .info-row { margin-bottom: 6px; }
        .payment-block .info-row:last-child { margin-bottom: 0; }
        
        .notes-block {
            font-size: 12px;
            color: #495057;
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 8px 10px;
            border-radius: 4px;
            margin-bottom: 12px;
        }
        .audit-block {
            font-size: 11px;
            color: #6c757d;
            border-top: 1px dashed #dee2e6;
            margin-top: 10px;
            padding-top: 10px;
            line-height: 1.45;
        }
        .audit-block strong { color: #495057; }
        
        .rating-snippet {
            margin-top: 10px;
            padding: 10px;
            background: #fffbea;
            border-radius: 6px;
            font-size: 13px;
        }
        .rating-snippet .stars { color: #f4b400; letter-spacing: 2px; }
        
        .file-links { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .file-links a {
            font-size: 12px;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 6px;
            background: #e9ecef;
            color: #212529;
            text-decoration: none;
        }
        .file-links a:hover { background: #dee2e6; }
        
        #calendar-host-wrap {
            display: none;
            margin-top: 20px;
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
        }
        #calendar-host-wrap.visible { display: block; }
        .cal-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .cal-nav button {
            border: 1px solid #ced4da;
            background: #fff;
            border-radius: 6px;
            padding: 6px 12px;
            cursor: pointer;
            font-weight: 600;
        }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            font-size: 12px;
        }
        .cal-grid .cal-dow {
            font-weight: 700;
            text-align: center;
            color: #6c757d;
            padding: 4px 0;
        }
        .cal-cell {
            min-height: 72px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 4px;
            background: #fafafa;
            vertical-align: top;
        }
        .cal-cell.other { opacity: 0.45; }
        .cal-cell .d-num { font-weight: 700; color: #2c3e50; }
        .cal-chip {
            display: block;
            font-size: 9px;
            padding: 2px 3px;
            margin-top: 2px;
            border-radius: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            background: #cfe2ff;
            color: #084298;
        }
        
        .reservation-info {
            margin-bottom: 15px;
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .renter-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .renter-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .renter-contact {
            font-size: 12px;
            color: #666;
        }
        
        .dates-section {
            background: #ecf0f1;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .date-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .date-row:last-child {
            margin-bottom: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .action-buttons .btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #c82333;
        }
        
        .btn-checkin {
            background: #17a2b8;
            color: white;
        }
        
        .btn-checkin:hover {
            background: #138496;
        }
        
        .btn-checkout {
            background: #6f42c1;
            color: white;
        }
        
        .btn-checkout:hover {
            background: #5a32a3;
        }
        
        .btn-disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .upcoming-section {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        
        .upcoming-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        
        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .upcoming-item {
            background: white;
            padding: 12px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1><i class="fas fa-calendar-check"></i> Bookings & Reservations</h1>
            </div>
            
            <!-- Success/Error Message -->
            <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>" style="margin-bottom: 20px;">
                <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Check-ins Alert -->
            <?php if (!empty($upcoming_checkins)): ?>
            <div class="upcoming-section">
                <h3><i class="fas fa-clock"></i> Today's Check-ins</h3>
                <div class="upcoming-list">
                    <?php foreach ($upcoming_checkins as $checkin): ?>
                    <div class="upcoming-item">
                        <div>
                            <strong><?php echo htmlspecialchars($checkin['renter_name']); ?></strong>
                            <small style="display: block; color: #666;">Unit: <?php echo htmlspecialchars($checkin['unit_type']); ?> (#<?php echo $checkin['unit_number']; ?>)</small>
                        </div>
                        <button onclick="quickCheckin(<?php echo $checkin['reservation_id']; ?>)" class="btn btn-checkin" style="margin: 0;">
                            <i class="fas fa-sign-in-alt"></i> Check In Now
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Status Filter -->
            <div class="status-filter">
                <button type="button" class="btn active" onclick="filterByStatus('all', this)">
                    All (<?php echo array_sum($status_counts); ?>)
                </button>
                <?php foreach ($statuses as $status): ?>
                <button type="button" class="btn" onclick="filterByStatus('<?php echo htmlspecialchars($status); ?>', this)">
                    <?php echo str_replace('_', ' ', ucfirst($status)); ?> (<?php echo (int)$status_counts[$status]; ?>)
                </button>
                <?php endforeach; ?>
            </div>
            <div class="view-mode-bar">
                <span style="font-size:13px;color:#6c757d;font-weight:600;">View</span>
                <div class="toggle-pair" role="group" aria-label="View mode">
                    <button type="button" class="active" id="btnViewGrid" onclick="setHostResView('grid')">Grid</button>
                    <button type="button" id="btnViewCal" onclick="setHostResView('calendar')">Calendar</button>
                </div>
            </div>
            
            <!-- Reservations Grid -->
            <div class="reservations-grid" id="reservations-container">
                <?php if (empty($all_reservations)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-inbox"></i>
                        <p>No reservations yet</p>
                    </div>
                <?php else: ?>
                    <?php
                    $today = date('Y-m-d');
                    foreach ($all_reservations as $res):
                        $nights = max(1, (int)((strtotime($res['check_out_date']) - strtotime($res['check_in_date'])) / 86400));
                        $ptype = $res['pricing_type'] ?? 'nightly';
                        if (in_array($ptype, ['nightly', 'daily'], true)) {
                            $nightlyRate = (float)($res['price_per_night'] ?? 0);
                        } else {
                            $nightlyRate = ((float)($res['price_per_month'] ?? 0)) / 30;
                        }
                        $subLine = $nightlyRate * $nights;
                        $paidRecorded = (float)($res['payments_paid_total'] ?? 0);
                        $totalAmt = (float)($res['total_amount'] ?? 0);
                        $balance = max(0, $totalAmt - $paidRecorded);
                        $rawStatus = strtolower((string)($res['status'] ?? ''));
                        $filterStatus = ($rawStatus === 'approved') ? 'confirmed' : $rawStatus;
                        $tl = host_res_timeline_level($res['status']);
                        $phoneDigits = preg_replace('/\D+/', '', (string)($res['renter_phone'] ?? ''));
                        $smsHref = $phoneDigits !== '' ? 'sms:' . $phoneDigits : '';
                        $telHref = $phoneDigits !== '' ? 'tel:' . $phoneDigits : '';
                        $mailHref = 'mailto:' . rawurlencode((string)($res['renter_email'] ?? ''));
                        $msgHref = 'messages.php?renter_id=' . (int)$res['user_id'];
                        $govUrl = host_res_id_file_url($res['government_id_path'] ?? '');
                        $alerts = [];
                        if (in_array($rawStatus, ['confirmed', 'approved'], true) && ($res['check_in_date'] ?? '') === $today) {
                            $alerts[] = 'checkin_today';
                        }
                        $paySt = strtolower((string)($res['payment_status'] ?? ''));
                        if ($balance > 0.02 && !in_array($rawStatus, ['cancelled', 'completed'], true) && ($res['check_in_date'] ?? '') <= $today && in_array($paySt, ['pending', 'partial', 'not_paid'], true)) {
                            $alerts[] = 'payment_overdue';
                        }
                        $cardClass = 'reservation-card';
                        if (in_array('checkin_today', $alerts, true)) {
                            $cardClass .= ' card-alert-checkin';
                        }
                        if (in_array('payment_overdue', $alerts, true)) {
                            $cardClass .= ' card-alert-payment';
                        }
                        $amenities = $amenity_by_unit[(int)$res['unit_id']] ?? [];
                        $adults = $has_guest_cols ? (int)($res['num_adults'] ?? 1) : null;
                        $children = $has_guest_cols ? (int)($res['num_children'] ?? 0) : null;
                        $arrivalClass = 'arrival-notyet';
                        $arrivalLabel = 'Not yet arrived';
                        if ($rawStatus === 'checked_in') {
                            $arrivalClass = 'arrival-checked_in';
                            $arrivalLabel = 'Checked in';
                        } elseif ($rawStatus === 'checked_out') {
                            $arrivalClass = 'arrival-checked_out';
                            $arrivalLabel = 'Checked out';
                        } elseif ($rawStatus === 'completed') {
                            $arrivalClass = 'arrival-completed';
                            $arrivalLabel = 'Stay completed';
                        } elseif ($rawStatus === 'cancelled') {
                            $arrivalLabel = '—';
                        }
                        ?>
                    <div class="<?php echo htmlspecialchars($cardClass); ?>" id="reservation-<?php echo (int)$res['reservation_id']; ?>" data-status="<?php echo htmlspecialchars($rawStatus); ?>" data-filter-status="<?php echo htmlspecialchars($filterStatus); ?>">
                        <?php if (!empty($alerts)): ?>
                        <div class="card-alert-badges">
                            <?php if (in_array('checkin_today', $alerts, true)): ?><span class="badge-alert-checkin"><i class="fas fa-bell"></i> Check-in today</span><?php endif; ?>
                            <?php if (in_array('payment_overdue', $alerts, true)): ?><span class="badge-alert-pay"><i class="fas fa-exclamation-circle"></i> Payment overdue</span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="reservation-header">
                            <div class="reservation-unit">
                                <?php echo htmlspecialchars($res['unit_type'] ?: 'Unit'); ?>
                                <small style="display: block; color: #999; font-weight: normal;">Unit #<?php echo htmlspecialchars($res['unit_number']); ?></small>
                                <span class="arrival-pill <?php echo htmlspecialchars($arrivalClass); ?>"><i class="fas fa-circle" style="font-size:8px;"></i> <?php echo htmlspecialchars($arrivalLabel); ?></span>
                            </div>
                            <span class="reservation-status status-<?php echo htmlspecialchars(preg_replace('/[^a-z0-9_]/i', '_', $rawStatus)); ?>">
                                <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($rawStatus))); ?>
                            </span>
                        </div>
                        
                        <div class="timeline-wrap">
                            <div class="timeline-labels">
                                <span>Requested</span><span>Approved</span><span>Checked in</span><span>Checked out</span><span>Completed</span>
                            </div>
                            <?php if ($tl < 0): ?>
                            <div style="font-size:12px;color:#842029;font-weight:600;"><i class="fas fa-ban"></i> Cancelled<?php if (!empty($res['cancellation_reason'])): ?>: <?php echo htmlspecialchars($res['cancellation_reason']); ?><?php endif; ?></div>
                            <?php else: ?>
                            <div class="timeline-track">
                                <?php
                                for ($ti = 1; $ti <= 5; $ti++) {
                                    $dotClass = 'timeline-dot';
                                    if ($tl === 5) {
                                        $dotClass .= ' done';
                                    } elseif ($ti < $tl) {
                                        $dotClass .= ' done';
                                    } elseif ($ti === $tl) {
                                        $dotClass .= ' current';
                                    }
                                    echo '<div class="' . htmlspecialchars($dotClass) . '"></div>';
                                    if ($ti < 5) {
                                        $lineClass = 'timeline-line' . ($tl > $ti ? ' fill' : '');
                                        echo '<div class="' . htmlspecialchars($lineClass) . '"></div>';
                                    }
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="unit-quick">
                            <span><i class="fas fa-users text-secondary"></i> Capacity: <strong><?php echo (int)($res['max_occupancy'] ?? 0); ?></strong></span>
                            <span><i class="fas fa-moon text-secondary"></i> <?php echo in_array($ptype, ['nightly', 'daily'], true) ? '₱' . number_format($nightlyRate, 2) . '/night' : '₱' . number_format((float)($res['price_per_month'] ?? 0), 2) . '/mo'; ?></span>
                            <?php if (!empty($amenities)): ?>
                            <span class="amen-icons" title="<?php echo htmlspecialchars(implode(', ', $amenities)); ?>">
                                <?php foreach (array_slice($amenities, 0, 6) as $an): ?>
                                    <i class="fas <?php echo htmlspecialchars(host_res_amenity_icon_class($an)); ?>" title="<?php echo htmlspecialchars($an); ?>"></i>
                                <?php endforeach; ?>
                                <?php if (count($amenities) > 6): ?><span style="font-size:11px;color:#6c757d;">+<?php echo count($amenities) - 6; ?></span><?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="renter-info">
                            <div class="renter-name"><?php echo htmlspecialchars($res['renter_name']); ?></div>
                            <div class="renter-contact" style="margin-bottom:0;">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($res['renter_email'] ?? ''); ?><br>
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($res['renter_phone'] ?: 'N/A'); ?>
                            </div>
                            <div class="contact-actions">
                                <?php if ($telHref): ?><a class="ca-phone" href="<?php echo htmlspecialchars($telHref); ?>"><i class="fas fa-phone"></i> Call</a><?php endif; ?>
                                <?php if ($smsHref): ?><a class="ca-sms" href="<?php echo htmlspecialchars($smsHref); ?>"><i class="fas fa-sms"></i> SMS</a><?php endif; ?>
                                <a class="ca-mail" href="<?php echo htmlspecialchars($mailHref); ?>"><i class="fas fa-envelope"></i> Email</a>
                                <a class="ca-chat" href="<?php echo htmlspecialchars($msgHref); ?>"><i class="fas fa-comments"></i> Message</a>
                            </div>
                        </div>
                        
                        <?php if ($has_guest_cols && ($adults !== null || $children !== null)): ?>
                        <div class="reservation-info" style="border-top:none;padding-top:8px;">
                            <div class="info-row">
                                <span class="info-label">Guests</span>
                                <span class="info-value"><?php echo (int)$adults; ?> adults<?php if ((int)$children > 0): ?>, <?php echo (int)$children; ?> kids<?php endif; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty(trim((string)($res['special_requests'] ?? '')))): ?>
                        <div class="notes-block">
                            <strong>Special requests</strong><br>
                            <?php echo nl2br(htmlspecialchars($res['special_requests'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($govUrl): ?>
                        <div class="file-links">
                            <a href="<?php echo htmlspecialchars($govUrl); ?>" target="_blank" rel="noopener"><i class="fas fa-id-card"></i> View valid ID</a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="dates-section">
                            <div class="date-row">
                                <span><i class="fas fa-sign-in-alt"></i> Check-in:</span>
                                <strong><?php echo date('M d, Y', strtotime($res['check_in_date'])); ?></strong>
                            </div>
                            <div class="date-row">
                                <span><i class="fas fa-sign-out-alt"></i> Check-out:</span>
                                <strong><?php echo date('M d, Y', strtotime($res['check_out_date'])); ?></strong>
                            </div>
                        </div>
                        
                        <div class="reservation-info">
                            <div class="info-row">
                                <span class="info-label">Duration</span>
                                <span class="info-value"><?php echo $nights; ?> nights</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Rate × nights</span>
                                <span class="info-value">₱<?php echo number_format($nightlyRate, 2); ?> × <?php echo $nights; ?> = ₱<?php echo number_format($subLine, 2); ?></span>
                            </div>
                            <?php if ((float)($res['discount_amount'] ?? 0) > 0): ?>
                            <div class="info-row">
                                <span class="info-label">Discount</span>
                                <span class="info-value">−₱<?php echo number_format((float)$res['discount_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Total due</span>
                                <span class="info-value">₱<?php echo number_format($totalAmt, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="payment-block">
                            <div class="info-row">
                                <span class="info-label">Reservation payment</span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $paySt))); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Method (latest)</span>
                                <span class="info-value"><?php echo htmlspecialchars(host_res_payment_method_label($res['last_payment_method'] ?? '')); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Paid to date</span>
                                <span class="info-value">₱<?php echo number_format($paidRecorded, 2); ?> <?php echo $paidRecorded <= 0 ? '' : ($paidRecorded + 0.01 < $totalAmt ? '(partial)' : '(full)'); ?></span>
                            </div>
                            <?php if (!empty($res['last_paid_at'])): ?>
                            <div class="info-row">
                                <span class="info-label">Last payment</span>
                                <span class="info-value"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($res['last_paid_at']))); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Balance</span>
                                <span class="info-value"><?php echo $balance < 0.02 ? '₱0.00' : '₱' . number_format($balance, 2); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($rawStatus === 'completed' && (isset($res['renter_rating']) && $res['renter_rating'] !== null && $res['renter_rating'] !== '')): ?>
                        <div class="rating-snippet">
                            <span class="stars"><?php echo str_repeat('★', (int)$res['renter_rating']) . str_repeat('☆', max(0, 5 - (int)$res['renter_rating'])); ?></span>
                            <?php if (!empty(trim((string)($res['renter_feedback'] ?? '')))): ?>
                            <?php
                            $__fb = (string)($res['renter_feedback'] ?? '');
                            $__fb = strlen($__fb) > 220 ? substr($__fb, 0, 217) . '…' : $__fb;
                            ?>
                            <div style="margin-top:6px;color:#444;"><?php echo nl2br(htmlspecialchars($__fb)); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="audit-block">
                            <strong>Activity</strong><br>
                            Booked <?php echo !empty($res['created_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($res['created_at']))) : '—'; ?>
                            <?php if (!empty($res['approved_at'])): ?>
                            <br>Approved<?php if (!empty($res['approver_name'])): ?> by <?php echo htmlspecialchars($res['approver_name']); ?><?php endif; ?> at <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($res['approved_at']))); ?>
                            <?php endif; ?>
                            <?php if ($has_check_cols && !empty($res['checked_in_by_name']) && in_array($rawStatus, ['checked_in', 'checked_out', 'completed'], true)): ?>
                            <br>Checked in by <?php echo htmlspecialchars($res['checked_in_by_name']); ?>
                            <?php endif; ?>
                            <?php if ($has_check_cols && !empty($res['checked_out_by_name']) && in_array($rawStatus, ['completed'], true)): ?>
                            <br>Checked out by <?php echo htmlspecialchars($res['checked_out_by_name']); ?>
                            <?php endif; ?>
                            <?php if ($rawStatus === 'cancelled' && !empty($res['cancellation_reason'])): ?>
                            <br>Cancelled: <?php echo htmlspecialchars($res['cancellation_reason']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="action-buttons">
                            <?php if ($rawStatus === 'pending'): ?>
                                <form method="POST" style="display:inline; flex: 1;">
                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Are you sure you want to APPROVE this reservation? The renter will be notified.')" style="width: 100%;">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-cancel" onclick="showRejectModal(<?php echo (int)$res['reservation_id']; ?>)" style="flex: 1; margin-left: 8px;">
                                    <i class="fas fa-ban"></i> Reject
                                </button>
                            <?php elseif (in_array($rawStatus, ['confirmed', 'approved'], true)): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="checkin">
                                    <button type="submit" class="btn btn-checkin" onclick="return confirm('Check in guest now?')">
                                        <i class="fas fa-sign-in-alt"></i> Check In
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="reason" value="Host cancelled">
                                    <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this reservation?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </form>
                            <?php elseif ($rawStatus === 'checked_in'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="checkout">
                                    <button type="submit" class="btn btn-checkout" onclick="return confirm('Check out guest now?')">
                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-disabled" disabled>
                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($rawStatus))); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div id="calendar-host-wrap" aria-hidden="true">
                <div class="cal-nav">
                    <button type="button" id="calPrev" aria-label="Previous month">&larr;</button>
                    <strong id="calTitle"></strong>
                    <button type="button" id="calNext" aria-label="Next month">&rarr;</button>
                </div>
                <div class="cal-grid" id="calGrid"></div>
            </div>
        </div>
    </div>
    
    <!-- Rejection Modal -->
    <style>
        #rejectModal.modal {
            display: none !important;
        }
        #rejectModal.show {
            display: block !important;
        }
    </style>
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border: 2px solid #dc3545; border-radius: 10px; box-shadow: 0 5px 25px rgba(220, 53, 69, 0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; border-radius: 8px 8px 0 0;">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="fas fa-ban"></i> Reject Reservation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body" style="padding: 25px;">
                        <input type="hidden" id="rejectReservationId" name="reservation_id">
                        <input type="hidden" name="action" value="cancel">
                        
                        <div class="alert alert-warning" style="border-left: 4px solid #ffc107;">
                            <i class="fas fa-exclamation-triangle"></i> This action cannot be undone. The renter will be notified of the rejection.
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label fw-bold">Reason for Rejection <span style="color: #dc3545;">*</span></label>
                            <textarea class="form-control" id="rejectionReason" name="reason" placeholder="Provide a reason for rejecting this booking..." rows="4" required style="border: 1px solid #dee2e6; border-radius: 5px;"></textarea>
                            <small class="form-text text-muted d-block mt-2">The renter will see this reason in their notification.</small>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-danger" style="background: #dc3545; border-color: #dc3545; padding: 8px 20px;">
                            <i class="fas fa-ban"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.HOST_RES_CAL = <?php echo json_encode($calendar_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window._hostCalState = { y: new Date().getFullYear(), m: new Date().getMonth() };
        
        function filterByStatus(status, btn) {
            const cards = document.querySelectorAll('.reservation-card');
            cards.forEach(card => {
                const fs = card.dataset.filterStatus || card.dataset.status;
                if (status === 'all' || fs === status) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
            
            document.querySelectorAll('.status-filter .btn').forEach(b => b.classList.remove('active'));
            if (btn) {
                btn.classList.add('active');
            }
        }
        
        function setHostResView(mode) {
            const grid = document.getElementById('reservations-container');
            const cal = document.getElementById('calendar-host-wrap');
            const bGrid = document.getElementById('btnViewGrid');
            const bCal = document.getElementById('btnViewCal');
            if (!grid || !cal) return;
            if (mode === 'calendar') {
                grid.style.display = 'none';
                cal.classList.add('visible');
                cal.setAttribute('aria-hidden', 'false');
                bGrid.classList.remove('active');
                bCal.classList.add('active');
                if (typeof window.renderHostCal === 'function') {
                    window.renderHostCal();
                }
            } else {
                grid.style.display = '';
                cal.classList.remove('visible');
                cal.setAttribute('aria-hidden', 'true');
                bGrid.classList.add('active');
                bCal.classList.remove('active');
            }
        }
        
        function showRejectModal(reservationId) {
            document.getElementById('rejectReservationId').value = reservationId;
            document.getElementById('rejectionReason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }
        
        function quickCheckin(reservationId) {
            if (confirm('Check in guest now?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="reservation_id" value="${reservationId}">
                    <input type="hidden" name="action" value="checkin">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function hostCalPad(n) { return n < 10 ? '0' + n : '' + n; }
        function hostCalYmd(y, m0, d) { return y + '-' + hostCalPad(m0 + 1) + '-' + hostCalPad(d); }
        function hostCalEsc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
        }
        window.renderHostCal = function() {
            const grid = document.getElementById('calGrid');
            const title = document.getElementById('calTitle');
            if (!grid || !title || !window._hostCalState) return;
            const y = window._hostCalState.y;
            const m0 = window._hostCalState.m;
            title.textContent = new Date(y, m0, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            const dowNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            let html = '';
            dowNames.forEach(function(d) { html += '<div class="cal-dow">' + d + '</div>'; });
            const iter = new Date(y, m0, 1);
            iter.setDate(iter.getDate() - iter.getDay());
            for (let c = 0; c < 42; c++) {
                const cy = iter.getFullYear();
                const cm = iter.getMonth();
                const cd = iter.getDate();
                const inMonth = (cm === m0);
                const iso = hostCalYmd(cy, cm, cd);
                const hits = (window.HOST_RES_CAL || []).filter(function(b) {
                    return iso >= b.check_in && iso < b.check_out;
                });
                html += '<div class="cal-cell' + (inMonth ? '' : ' other') + '">';
                html += '<div class="d-num">' + cd + '</div>';
                hits.slice(0, 3).forEach(function(h) {
                    const t = hostCalEsc((h.label || '').substring(0, 28));
                    html += '<span class="cal-chip" title="' + t + '">' + t + '</span>';
                });
                if (hits.length > 3) {
                    html += '<span class="cal-chip" style="background:#e2e3e5;color:#333;">+' + (hits.length - 3) + '</span>';
                }
                html += '</div>';
                iter.setDate(iter.getDate() + 1);
            }
            grid.innerHTML = html;
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            var p = document.getElementById('calPrev');
            var n = document.getElementById('calNext');
            if (p) {
                p.addEventListener('click', function() {
                    window._hostCalState.m--;
                    if (window._hostCalState.m < 0) { window._hostCalState.m = 11; window._hostCalState.y--; }
                    window.renderHostCal();
                });
            }
            if (n) {
                n.addEventListener('click', function() {
                    window._hostCalState.m++;
                    if (window._hostCalState.m > 11) { window._hostCalState.m = 0; window._hostCalState.y++; }
                    window.renderHostCal();
                });
            }
            // Check if there's a view parameter in URL
            const urlParams = new URLSearchParams(window.location.search);
            const viewId = urlParams.get('view');
            
            if (viewId) {
                const card = document.getElementById('reservation-' + viewId);
                if (card) {
                    // Highlight the card
                    card.style.boxShadow = '0 0 15px rgba(52, 152, 219, 0.6)';
                    card.style.border = '2px solid #3498db';
                    card.style.transform = 'translateY(-5px)';
                    card.style.transition = 'all 0.4s ease';
                    
                    // Scroll to it
                    setTimeout(() => {
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 500);
                    
                    // Remove the highlight after a few seconds
                    setTimeout(() => {
                        card.style.boxShadow = '';
                        card.style.border = '';
                        card.style.transform = '';
                    }, 4000);
                }
            }
        });
    </script>
</body>
</html>
