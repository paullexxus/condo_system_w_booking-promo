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
include_once '../includes/renter_functions.php';

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
if ($numAdults + $numChildren > $maxOcc) {
    $_SESSION['flash_error'] = "Guest count exceeds this unit's maximum occupancy ($maxOcc).";
    header('Location: reserve_unit.php');
    exit;
}

// Pricing calculation verification (Defensive re-calculation)
$totalDays = calculateDays($checkInDate, $checkOutDate);
$pricing_type = $unit['pricing_type'] ?? 'nightly';
$dailyRate = in_array($pricing_type, ['nightly', 'daily']) ? (float)($unit['price_per_night'] ?? 0) : (float)($unit['price_per_month'] ?? 0) / 30;
$dailyRate = max(0, $dailyRate);

$unitAmount = $dailyRate * $totalDays;
$securityDeposit = (float)$unit['security_deposit'];
$cleaningFee = isset($unit['cleaning_fee']) ? (float)$unit['cleaning_fee'] : 0.0;
$serviceFee = isset($unit['service_fee']) ? (float)$unit['service_fee'] : 0.0;

$amenityCosts = 0;
$selectedAmenities = [];
$addonArray = $dataSource['amenities'] ?? ($dataSource['addon_ids'] ?? []);

if (is_array($addonArray)) {
    foreach ($addonArray as $amenityId) {
        $amenityId = (int)$amenityId;
        if ($amenityId > 0) {
            $amenity = get_single_result("SELECT * FROM amenities WHERE amenity_id = ?", [$amenityId]);
            if ($amenity) {
                $amenityCosts += (float)$amenity['hourly_rate'] * $totalDays;
                $selectedAmenities[] = $amenity;
            } else {
                $addon = get_single_result("SELECT * FROM unit_addons WHERE addon_id = ? AND unit_id = ?", [$amenityId, $unitId]);
                if ($addon) {
                    $amenityCosts += (float)$addon['price'];
                    $selectedAmenities[] = ['amenity_id' => $addon['addon_id'], 'amenity_name' => $addon['name'], 'hourly_rate' => $addon['price'] / $totalDays];
                }
            }
        }
    }
}

$totalAmount = $unitAmount + $amenityCosts + $cleaningFee + $serviceFee;

$promoCode = sanitize_input($dataSource['promo_code'] ?? '');
$discountAmount = 0;
if (!empty($promoCode)) {
    $dateToday = date('Y-m-d');
    $promo = get_single_result(
        "SELECT * FROM promo_codes WHERE code = ? AND (status = 'active' OR is_active = 1) AND valid_from <= ? AND valid_until >= ?",
        [$promoCode, $dateToday, $dateToday]
    );
    
    if ($promo) {
        $validPromo = true;
        if ($promo['scope'] === 'host' && (int)$promo['host_id'] !== (int)($unit['host_id'] ?? 0)) $validPromo = false;
        elseif ($promo['scope'] === 'branch' && (int)$promo['branch_id'] !== (int)$branchId) $validPromo = false;
        
        if ($validPromo && $unitAmount >= (float)$promo['min_booking_amount']) {
            if (isset($promo['usage_limit']) && $promo['usage_limit'] > 0 && $promo['used_count'] >= $promo['usage_limit']) {
                $validPromo = false;
            }
            if ($validPromo) {
                $value = (float)$promo['discount_value'];
                $discountAmount = ($promo['discount_type'] === 'percentage') ? $unitAmount * ($value / 100.0) : $value;
                if (!empty($promo['max_discount']) && $promo['max_discount'] > 0) $discountAmount = min($discountAmount, (float)$promo['max_discount']);
                $appliedPromo = $promo;
            }
        }
    }
}

$totalAmount = max(0, $totalAmount - $discountAmount);
$_SESSION['last_booking_submission'] = time();

// execute transactional insert (with overlap locks)
$reservationId = createReservation(
    $_SESSION['user_id'], $unitId, $branchId, $checkInDate, $checkOutDate,
    $totalAmount, $securityDeposit, $specialRequests, $numAdults, $numChildren
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

// Attach amenities
if (!empty($selectedAmenities)) {
    foreach ($selectedAmenities as $amenity) {
        if (isset($amenity['amenity_name'])) {
            bookAmenity($_SESSION['user_id'], $amenity['amenity_id'], $branchId, $checkInDate, '00:00:00', '23:59:59', $amenity['hourly_rate'] * $totalDays);
        }
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
