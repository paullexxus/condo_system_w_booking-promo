<?php
/**
 * BookIT Reservation Processing Handler
 * 
 * Handles bookings securely based on Defense-In-Depth protocols:
 * 1. Checks action source (Reserve vs Book)
 * 2. Fetches matching Data Source securely
 * 3. Utilizes `createReservation` function which handles transactional Double Booking locks
 * 4. Routes correctly to payment.php or my_bookings.php
 */

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';
include_once '../includes/auth.php';
include_once '../includes/renter_functions.php';
include_once '../includes/pricing_engine.php';

checkRole(['renter']); // Only renters can book

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reserve_unit.php');
    exit;
}

$action_type = $_POST['action_type'] ?? '';

if ($action_type !== 'reserve' && $action_type !== 'book_confirm') {
    error_log("process_reservation: action_type mismatch. Got: " . $action_type);
    $_SESSION['flash_error'] = "Invalid booking action.";
    header('Location: reserve_unit.php');
    exit;
}

// Extract data securely 
// 'book_confirm' requires data from the validated session
// 'reserve' takes direct post from unit_detail
if ($action_type === 'book_confirm') {
    if (!isset($_SESSION['pending_booking_data'])) {
        error_log("process_reservation: pending_booking_data not in session.");
        $_SESSION['flash_error'] = "Booking session expired or invalid. Please start again.";
        header('Location: reserve_unit.php');
        exit;
    }
    $dataSource = $_SESSION['pending_booking_data'];
} else {
    $dataSource = $_POST;
}

// Validate CSRF token (always from current POST)
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    error_log("process_reservation: CSRF validation failed.");
    $_SESSION['flash_error'] = "Security validation failed. Please try again.";
    header('Location: reserve_unit.php');
    exit;
}

// Prevent duplicate submissions (3 second window)
if (isset($_SESSION['last_booking_submission']) && (time() - $_SESSION['last_booking_submission']) < 3) {
    error_log("process_reservation: duplicate submission block.");
    $_SESSION['flash_error'] = "Please wait a moment before submitting another booking.";
    header('Location: reserve_unit.php');
    exit;
}

// Input Map
$unitId = isset($dataSource['unit_id']) ? (int)$dataSource['unit_id'] : 0;
$branchId = isset($dataSource['branch_id']) ? (int)$dataSource['branch_id'] : 0;
$checkInDate = sanitize_input($dataSource['check_in_date'] ?? '');
$checkOutDate = sanitize_input($dataSource['check_out_date'] ?? '');
$specialRequests = sanitize_input($dataSource['special_requests'] ?? '');
$numAdults = max(1, (int)($dataSource['num_adults'] ?? 1));
$numChildren = max(0, (int)($dataSource['num_children'] ?? 0));

if ($unitId <= 0 || $branchId <= 0 || !validateDateRange($checkInDate, $checkOutDate)) {
    error_log("process_reservation: validation failed. branchID: $branchId, unitID: $unitId, dates: $checkInDate-$checkOutDate");
    $_SESSION['flash_error'] = "Invalid unit, branch, or date range selected.";
    header('Location: reserve_unit.php');
    exit;
}

// Verify Database Entities
$unit = get_single_result("SELECT * FROM units WHERE unit_id = ? AND branch_id = ?", [$unitId, $branchId]);
if (!$unit || (isset($unit['approval_status']) && $unit['approval_status'] !== 'approved' && $unit['approval_status'] !== null)) {
    error_log("process_reservation: Unit not found or not approved.");
    $_SESSION['flash_error'] = "Invalid unit selected or unit is not available.";
    header('Location: reserve_unit.php');
    exit;
}

$maxOcc = max(1, (int)($unit['max_occupancy'] ?? 10));
$extra_allowed = (int)($unit['extra_guests_allowed'] ?? 0);
if ($numAdults + $numChildren > ($maxOcc + $extra_allowed)) {
    $_SESSION['flash_error'] = "Guest count exceeds this unit's maximum occupancy.";
    header('Location: reserve_unit.php');
    exit;
}

// Pricing calculation & Availability verification (Authoritative Engine)
$addon_ids = $dataSource['amenities'] ?? ($dataSource['addon_ids'] ?? []);
$promo_code = sanitize_input($dataSource['promo_code'] ?? '');

$pricing_result = BookIT_PricingEngine::calculatePrice($unitId, $checkInDate, $checkOutDate, $numAdults + $numChildren, $addon_ids, $promo_code);

if (!$pricing_result['success']) {
    error_log("process_reservation: Pricing/Availability engine rejected request. Error: " . $pricing_result['error']);
    $_SESSION['flash_error'] = $pricing_result['error'];
    header('Location: reserve_unit.php?unit_id=' . $unitId);
    exit;
}

$totalAmount = $pricing_result['total'];
$security_deposit = (float)($unit['security_deposit'] ?? 0);
$discountAmount = $pricing_result['discount'];
$appliedPromoCode = $pricing_result['promo'];
$selectedAmenities = $pricing_result['addons'];
$_SESSION['last_booking_submission'] = time();

// execute transactional insert (with overlap locks)
$reservationId = createReservation(
    $_SESSION['user_id'], $unitId, $branchId, $checkInDate, $checkOutDate,
    $totalAmount, $security_deposit, $specialRequests, $numAdults, $numChildren
);

if (!$reservationId) {
    error_log("process_reservation: createReservation failed.");
    $_SESSION['flash_error'] = "Failed to create reservation. Unit may no longer be available or you have existing reservations for these dates.";
    header('Location: reserve_unit.php');
    exit;
}

// Clean up session if it was a book_confirm
if ($action_type === 'book_confirm') unset($_SESSION['pending_booking_data']);

// Log Promo
if (!empty($appliedPromo) && $discountAmount > 0) {
    try {
        execute_query("INSERT INTO promo_usage (promo_id, user_id, reservation_id) VALUES (?, ?, ?)", [$appliedPromo['promo_id'], $_SESSION['user_id'], $reservationId]);
        execute_query("UPDATE promo_codes SET used_count = used_count + 1 WHERE promo_id = ?", [$appliedPromo['promo_id']]);
        execute_query("UPDATE reservations SET promo_code = ?, discount_amount = ? WHERE reservation_id = ?", [$appliedPromo['code'], $discountAmount, $reservationId]);
    } catch (Exception $e) {}
}

// Attach Paid Amenities (Awaiting Approval)
if (!empty($selectedAmenities)) {
    foreach ($selectedAmenities as $addon) {
        $addon_id = (int)$addon['addon_id'];
        $price = (float)$addon['price'];
        
        // [DEFENSE] Price Consistency Lock & Duplicate Prevention
        // We use INSERT IGNORE and snapshot the current catalog price
        execute_query(
            "INSERT IGNORE INTO booking_addons (booking_id, addon_id, price, status) VALUES (?, ?, ?, 'pending')", 
            [$reservationId, $addon_id, $price]
        );
    }
}

// Notifications
$hostIds = array_unique(array_filter([
    (int)(get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$branchId])['host_id'] ?? 0),
    (int)($unit['host_id'] ?? 0)
]));

foreach ($hostIds as $hid) {
    if ($hid > 0 && $hid !== (int)$_SESSION['user_id']) {
        sendNotification($hid, 'New Booking Request - Awaiting Payment', "New reservation #$reservationId has been created and is awaiting payment confirmation.", 'booking', 'system');
    }
}

sendNotification($_SESSION['user_id'], "Reservation Created", "Your unit is reserved. ID: $reservationId", 'booking', 'system');

// Routing Magic
if ($action_type === 'book_confirm') {
    // Proceed to Payment immediately
    header("Location: payment.php?type=reservation&id={$reservationId}");
} else {
    // Escalate back to my_bookings to enforce "Temporary Hold" UX
    $_SESSION['flash_success'] = "Unit successfully placed on hold! You have 10 minutes to pay and secure this transaction.";
    header("Location: my_bookings.php");
}
exit;
?>
