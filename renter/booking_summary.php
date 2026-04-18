<?php
include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';

checkRole(['renter']);

// This page MUST be accessed via POST from a booking action
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['unit_id'])) {
    header('Location: reserve_unit.php');
    exit;
}

// Ensure it's legitimately a book action
if (isset($_POST['action_type']) && $_POST['action_type'] !== 'book') {
    header('Location: reserve_unit.php');
    exit;
}

// Capture POST securely into session to prevent tampering between steps
$_SESSION['pending_booking_data'] = $_POST;

$unitId = (int)$_POST['unit_id'];
$branchId = (int)$_POST['branch_id'];
$checkInDate = sanitize_input($_POST['check_in_date']);
$checkOutDate = sanitize_input($_POST['check_out_date']);

$unit = getUnitWithDefaults($unitId);
$branch = get_single_result("SELECT * FROM branches WHERE branch_id = ?", [$branchId]);

if (!$unit || (int)$unit['branch_id'] !== $branchId) {
    $_SESSION['flash_error'] = "Invalid unit or branch.";
    header('Location: reserve_unit.php');
    exit;
}

// Calculate days and rates
$totalDays = calculateDays($checkInDate, $checkOutDate);
$pricing_type = $unit['pricing_type'] ?? 'nightly';
if ($pricing_type === 'nightly' || $pricing_type === 'daily') {
    $dailyRate = (float)($unit['price_per_night'] ?? 0);
} else {
    $dailyRate = (float)($unit['price_per_month'] ?? 0) / 30;
}
$dailyRate = max(0, $dailyRate);

$unitAmount = $dailyRate * $totalDays;
$securityDeposit = (float)$unit['security_deposit'];
$cleaningFee = isset($unit['cleaning_fee']) ? (float)$unit['cleaning_fee'] : 0.0;
$serviceFee = isset($unit['service_fee']) ? (float)$unit['service_fee'] : 0.0;

// Amenities
$amenityCosts = 0;
$selectedAmenities = [];
if (isset($_POST['amenities']) && is_array($_POST['amenities']) || isset($_POST['addon_ids']) && is_array($_POST['addon_ids'])) {
    $addonArray = $_POST['amenities'] ?? $_POST['addon_ids'];
    foreach ($addonArray as $amenityId) {
        $amenityId = (int)$amenityId;
        if ($amenityId > 0) {
            // Check amenities (legacy) or unit_addons
            $amenity = get_single_result("SELECT * FROM amenities WHERE amenity_id = ? AND branch_id = ?", [$amenityId, $branchId]);
            if ($amenity) {
                $amenityCosts += (float)$amenity['hourly_rate'] * $totalDays;
                $selectedAmenities[] = ['name' => $amenity['amenity_name'], 'cost' => (float)$amenity['hourly_rate'] * $totalDays];
            } else {
                $addon = get_single_result("SELECT * FROM unit_addons WHERE addon_id = ? AND unit_id = ?", [$amenityId, $unitId]);
                if ($addon) {
                    $amenityCosts += (float)$addon['price'];
                    $selectedAmenities[] = ['name' => $addon['name'], 'cost' => (float)$addon['price']];
                }
            }
        }
    }
}

$totalAmount = $unitAmount + $amenityCosts + $cleaningFee + $serviceFee;

// Promo
$promoCode = isset($_POST['promo_code']) ? sanitize_input($_POST['promo_code']) : '';
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
            $value = (float)$promo['discount_value'];
            if ($promo['discount_type'] === 'percentage') {
                $discountAmount = $unitAmount * ($value / 100.0);
                if (!empty($promo['max_discount']) && $promo['max_discount'] > 0) {
                    $discountAmount = min($discountAmount, (float)$promo['max_discount']);
                }
            } else {
                $discountAmount = $value;
            }
        }
    }
}
$totalAmount = max(0, $totalAmount - $discountAmount);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Summary — BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b border-gray-200 px-4 py-4 mb-8">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-800"><i class="fas fa-file-invoice mr-2"></i> Booking Summary</h1>
            <a href="reserve_unit.php" class="text-gray-500 hover:text-gray-900"><i class="fas fa-times fa-lg"></i> Cancel</a>
        </div>
    </nav>
    
    <div class="max-w-4xl mx-auto px-4 pb-12">
        <!-- Safety Alert -->
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg flex items-start gap-3 mb-6 shadow-sm">
            <i class="fas fa-shield-alt mt-1 text-blue-500"></i>
            <div>
                <p class="font-bold">Review Your Details Strictly</p>
                <p class="text-sm">You have chosen to "Book Now". Please confirm your choices below. Pressing Confirm will commit this record to the booking engine and proceed to secure payment.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6 sm:p-8">
                
                <!-- Unit Info -->
                <div class="flex items-start gap-4 pb-6 border-b border-gray-100">
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-semibold mb-1"><?php echo htmlspecialchars($branch['branch_name']); ?></p>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Unit <?php echo htmlspecialchars($unit['unit_number']); ?> - <?php echo htmlspecialchars($unit['unit_type']); ?></h2>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li><i class="fas fa-calendar-alt w-5"></i> <?php echo formatDate($checkInDate); ?> to <?php echo formatDate($checkOutDate); ?> (<?php echo $totalDays; ?> days)</li>
                            <li><i class="fas fa-user-friends w-5"></i> Adults: <?php echo max(1, (int)($_POST['num_adults'] ?? 1)); ?>, Children: <?php echo max(0, (int)($_POST['num_children'] ?? 0)); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <div class="py-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Price Breakdown</h3>
                    <div class="space-y-3 text-gray-600">
                        <div class="flex justify-between">
                            <span>Base Stay Rate (₱<?php echo number_format($dailyRate, 2); ?> x <?php echo $totalDays; ?>)</span>
                            <span class="font-medium text-gray-800">₱<?php echo number_format($unitAmount, 2); ?></span>
                        </div>
                        
                        <?php if (!empty($selectedAmenities)): ?>
                        <?php foreach($selectedAmenities as $sa): ?>
                            <div class="flex justify-between text-sm pl-4">
                                <span>+ <?php echo htmlspecialchars($sa['name']); ?></span>
                                <span>₱<?php echo number_format($sa['cost'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($cleaningFee > 0): ?>
                        <div class="flex justify-between">
                            <span>Cleaning Fee</span>
                            <span>₱<?php echo number_format($cleaningFee, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($serviceFee > 0): ?>
                        <div class="flex justify-between">
                            <span>Service Fee</span>
                            <span>₱<?php echo number_format($serviceFee, 2); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($discountAmount > 0): ?>
                        <div class="flex justify-between text-green-600">
                            <span>Promo Discount (<?php echo htmlspecialchars($promoCode); ?>)</span>
                            <span class="font-medium">-₱<?php echo number_format($discountAmount, 2); ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Refundable Deposit -->
                        <?php if ($securityDeposit > 0): ?>
                        <div class="flex justify-between text-sm text-gray-500 mt-2">
                            <span>Security Deposit (Refundable)</span>
                            <span>₱<?php echo number_format($securityDeposit, 2); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Total -->
                <div class="pt-6 flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-500">Total required instantly</p>
                        <p class="text-xs text-gray-400">Exclusive of physical deposit if partial plan selected</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-black text-orange-600">₱<?php echo number_format($totalAmount + $securityDeposit, 2); ?></p>
                    </div>
                </div>
                
                <!-- Action -->
                <div class="mt-8 pt-6 border-t border-gray-100 flex gap-4">
                    <a href="javascript:history.back()" class="flex-1 text-center py-3 px-6 rounded-lg font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                        Back to Edit
                    </a>
                    <form method="POST" action="process_reservation.php" class="flex-1">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" name="action_type" value="book_confirm" class="w-full text-center py-3 px-6 rounded-lg font-bold bg-green-600 text-white shadow hover:bg-green-700 transition">
                            <i class="fas fa-check-circle mr-1"></i> Confirm & Pay
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
