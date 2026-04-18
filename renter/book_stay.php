<?php
// Renter booking creation (from unit details page)

include_once __DIR__ . '/../includes/session.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/auth.php';
include_once __DIR__ . '/../config/db.php';

checkRole(['renter']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['book_now'])) {
    header('Location: reserve_unit.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Security validation failed. Please try again.';
    header('Location: my_bookings.php');
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$unit_id = (int) ($_POST['unit_id'] ?? 0);
$branch_id = (int) ($_POST['branch_id'] ?? 0);
$check_in = sanitize_input($_POST['check_in_date'] ?? '');
$check_out = sanitize_input($_POST['check_out_date'] ?? '');
$promo_code = strtoupper(trim($_POST['promo_code'] ?? ''));
$addon_ids = $_POST['addon_ids'] ?? [];

if ($user_id <= 0 || $unit_id <= 0 || $branch_id <= 0) {
    $_SESSION['error_message'] = 'Invalid booking request.';
    header('Location: my_bookings.php');
    exit;
}

// Date validation (YYYY-MM-DD)
if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $check_in) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $check_out)) {
    $_SESSION['error_message'] = 'Invalid date format.';
    header('Location: unit_detail.php?unit_id=' . $unit_id);
    exit;
}

$today = new DateTime('today');
$in_dt = DateTime::createFromFormat('Y-m-d', $check_in);
$out_dt = DateTime::createFromFormat('Y-m-d', $check_out);
if (!$in_dt || !$out_dt || $in_dt < $today || $out_dt <= $in_dt) {
    $_SESSION['error_message'] = 'Please select valid future dates.';
    header('Location: unit_detail.php?unit_id=' . $unit_id);
    exit;
}

$nights = (int) $out_dt->diff($in_dt)->days;
if ($nights <= 0) {
    $_SESSION['error_message'] = 'Check-out must be after check-in.';
    header('Location: unit_detail.php?unit_id=' . $unit_id);
    exit;
}

// Ensure unit is approved & available
$unit = get_single_result(
    "SELECT u.*, b.is_active as branch_active
     FROM units u
     JOIN branches b ON u.branch_id = b.branch_id
     WHERE u.unit_id = ? AND u.branch_id = ? AND u.is_available = 1
       AND b.is_active = 1
       AND (u.approval_status = 'approved' OR u.approval_status IS NULL)",
    [$unit_id, $branch_id]
);
if (!$unit) {
    $_SESSION['error_message'] = 'Unit not available.';
    header('Location: reserve_unit.php');
    exit;
}

// Overlap checks: bookings + reservations
$overlapBookings = get_single_result(
    "SELECT booking_id FROM bookings
     WHERE unit_id = ?
       AND status IN ('pending','confirmed')
       AND NOT (check_out_date <= ? OR check_in_date >= ?)
     LIMIT 1",
    [$unit_id, $check_in, $check_out]
);
if ($overlapBookings) {
    $_SESSION['error_message'] = 'Selected dates overlap with an existing booking.';
    header('Location: unit_detail.php?unit_id=' . $unit_id);
    exit;
}

$overlapReservations = get_single_result(
    "SELECT reservation_id FROM reservations
     WHERE unit_id = ?
       AND (status IN ('confirmed','checked_in') OR (status = 'pending' AND (hold_expiry IS NULL OR hold_expiry > NOW())))
       AND NOT (check_out_date <= ? OR check_in_date >= ?)
     LIMIT 1",
    [$unit_id, $check_in, $check_out]
);
if ($overlapReservations) {
    $_SESSION['error_message'] = 'Selected dates overlap with an existing reservation.';
    header('Location: unit_detail.php?unit_id=' . $unit_id);
    exit;
}

// Blackouts
$blackout = get_single_result(
    "SELECT blackout_id FROM unit_blackouts
     WHERE unit_id = ?
       AND NOT (end_date < ? OR start_date > ?)
     LIMIT 1",
    [$unit_id, $check_in, $check_out]
);
if ($blackout) {
    $_SESSION['error_message'] = 'Selected dates include blocked dates.';
    header('Location: unit_detail.php?unit_id=' . $unit_id);
    exit;
}

// Pricing
$base_rate = (($unit['pricing_type'] ?? 'nightly') === 'monthly') ? (($unit['price_per_month'] ?? 0) / 30) : ($unit['price_per_night'] ?? 0);
$base_rate = max(0, (float) $base_rate);

$rules = get_multiple_results(
    "SELECT rule_type, adjustment_type, adjustment_value, start_date, end_date
     FROM unit_pricing_rules
     WHERE unit_id = ? AND is_active = 1",
    [$unit_id]
);

function is_weekend(DateTime $d): bool
{
    $day = (int) $d->format('w'); // 0 Sun .. 6 Sat
    return $day === 0 || $day === 6;
}
function apply_adjustment(float $amount, string $type, float $value): float
{
    if ($type === 'fixed')
        return $amount + $value;
    return $amount + ($amount * ($value / 100.0));
}
function rule_applies(array $rule, DateTime $d): bool
{
    if (($rule['rule_type'] ?? '') === 'weekend')
        return is_weekend($d);
    if (($rule['rule_type'] ?? '') === 'date_range') {
        if (empty($rule['start_date']) || empty($rule['end_date']))
            return false;
        $s = DateTime::createFromFormat('Y-m-d', $rule['start_date']);
        $e = DateTime::createFromFormat('Y-m-d', $rule['end_date']);
        if (!$s || !$e)
            return false;
        return $d >= $s && $d <= $e;
    }
    return false;
}

// STRICT PRICE LOGIC: Always nightly_rate * nights
$subtotal = $base_rate * $nights;

// Add Add-ons
$addons_total = 0.0;
$valid_addons = [];
if (!empty($addon_ids) && is_array($addon_ids)) {
    $ids = array_map('intval', $addon_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$unit_id], $ids);
    $selectedAddons = get_multiple_results("SELECT addon_id, price, name FROM unit_addons WHERE unit_id = ? AND addon_id IN ($placeholders) AND is_active = 1", $params);
    foreach ($selectedAddons as $a) {
        $addons_total += (float) $a['price'];
        $valid_addons[] = [
            'addon_id' => (int) $a['addon_id'],
            'name' => $a['name'],
            'price' => (float) $a['price']
        ];
    }
}
$subtotal += $addons_total;

// Promo validation & application
$promo_id = null;
$discount = 0.0;
if ($promo_code !== '') {
    $promo = get_single_result("
        SELECT p.*, u.role as creator_role 
        FROM promos p 
        LEFT JOIN users u ON p.created_by = u.user_id 
        WHERE p.code = ? AND p.is_active = 1 LIMIT 1
    ", [$promo_code]);
    if (!$promo) {
        $_SESSION['error_message'] = 'Invalid promo code.';
        header('Location: unit_detail.php?unit_id=' . $unit_id);
        exit;
    }

    if (isset($promo['creator_role']) && in_array($promo['creator_role'], ['host', 'manager'])) {
        if (!isset($unit['host_id']) || (int) $promo['created_by'] !== (int) $unit['host_id']) {
            $_SESSION['error_message'] = 'This promo code is not valid for this unit.';
            header('Location: unit_detail.php?unit_id=' . $unit_id);
            exit;
        }
    }

    if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < time()) {
        $_SESSION['error_message'] = 'Promo code is expired.';
        header('Location: unit_detail.php?unit_id=' . $unit_id);
        exit;
    }
    if (!empty($promo['usage_limit']) && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
        $_SESSION['error_message'] = 'Promo code usage limit reached.';
        header('Location: unit_detail.php?unit_id=' . $unit_id);
        exit;
    }
    $promo_id = (int) $promo['promo_id'];
    $type = $promo['type'];
    $value = (float) $promo['value'];
    if ($type === 'percentage') {
        $discount = $subtotal * ($value / 100.0);
    } else {
        $discount = $value;
    }
    $discount = min(max(0.0, $discount), $subtotal);
}

$total = ($subtotal - $discount);

// Persist booking + promo usage (transaction)
try {
    $conn->begin_transaction();

    $ok = execute_query(
        "INSERT INTO bookings (user_id, unit_id, branch_id, check_in_date, check_out_date, nights, base_nightly_rate, subtotal, discount_amount, total_amount, promo_id, promo_code, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
        [
            $user_id,
            $unit_id,
            $branch_id,
            $check_in,
            $check_out,
            $nights,
            $base_rate,
            $subtotal,
            $discount,
            $total,
            $promo_id,
            $promo_code !== '' ? $promo_code : null
        ]
    );
    if (!$ok) {
        throw new Exception('Failed to create booking.');
    }
    $booking_id = $conn->insert_id;

    if (!empty($valid_addons)) {
        foreach ($valid_addons as $va) {
            execute_query(
                "INSERT INTO booking_addons (booking_id, addon_id, addon_name, price) VALUES (?, ?, ?, ?)",
                [$booking_id, $va['addon_id'], $va['name'], $va['price']]
            );
        }
    }

    if ($promo_id) {
        $ok2 = execute_query("UPDATE promos SET used_count = used_count + 1 WHERE promo_id = ?", [$promo_id]);
        if (!$ok2)
            throw new Exception('Failed to update promo usage.');
    }

    $conn->commit();
    $_SESSION['success_message'] = 'Booking created! Your booking is now visible in the admin bookings list.';
    header('Location: my_bookings.php');
    exit;
} catch (Throwable $e) {
    if ($conn && $conn->errno === 0) {
        // ignore
    }
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    $_SESSION['error_message'] = 'Booking failed: ' . $e->getMessage();
    header('Location: unit_detail.php?unit_id=' . $unit_id);
    exit;
}

