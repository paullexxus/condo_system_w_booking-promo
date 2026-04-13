<?php
// BookIT Unit Reservation System
// Multi-branch Condo Rental Reservation System

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';
checkRole(['renter']); // Tanging renters lang ang pwede

$message = '';
$error = '';
$availableUnits = [];
$selectedBranch = '';
$checkInDate = '';
$checkOutDate = '';

// Handle search for available units
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_units'])) {
    $selectedBranch = $_POST['branch_id'];
    $checkInDate = $_POST['check_in_date'];
    $checkOutDate = $_POST['check_out_date'];
    
    // I-validate ang date range
    if (!validateDateRange($checkInDate, $checkOutDate)) {
        $error = "Invalid date range. Please select future dates.";
    } else {
        // Kumuha ng available units - FIXED: use getAvailableUnits function
        $availableUnits = getAvailableUnits($selectedBranch, $checkInDate, $checkOutDate);
        
        if (empty($availableUnits)) {
            $message = "No available units found for the selected dates.";
        }
    }
}

// Handle unit reservation with amenities
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserve_unit'])) {
    // FIXED: Validate CSRF token first
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    }
    // ENHANCED: Check for duplicate submission (nonce protection) - 3 second window
    else if (isset($_SESSION['last_booking_submission'])) {
        $time_diff = time() - $_SESSION['last_booking_submission'];
        if ($time_diff < 3) {
            // Prevent duplicate submissions within 3 seconds
            $error = "Please wait a moment before submitting another booking.";
        } else {
            unset($_SESSION['last_booking_submission']);
        }
    }
    
    if (empty($error)) {
        // FIXED: Input validation for CRITICAL #8
        $unitId = (int)$_POST['unit_id'];
        $branchId = (int)$_POST['branch_id'];
        $checkInDate = sanitize_input($_POST['check_in_date']);
        $checkOutDate = sanitize_input($_POST['check_out_date']);
        $specialRequests = sanitize_input($_POST['special_requests'] ?? '');
        
        // Validate inputs before database queries
        if ($unitId <= 0 || $branchId <= 0) {
            $error = "Invalid unit or branch selected.";
        } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOutDate)) {
            $error = "Invalid date format.";
        } else {
            // Kumuha ng unit details para sa pricing - FIXED: CRITICAL #2 SQL Injection
            $unit = get_single_result("SELECT * FROM units WHERE unit_id = ? AND branch_id = ? AND (approval_status = 'approved' OR approval_status IS NULL)", [$unitId, $branchId]);
            $branch = get_single_result("SELECT * FROM branches WHERE branch_id = ?", [$branchId]);
            
            if ($unit && $branch) {
            // I-calculate ang unit amount
            $totalDays = calculateDays($checkInDate, $checkOutDate);
            
            // Unified Pricing Logic: fetch rate directly from units table
            $pricing_type = $unit['pricing_type'] ?? 'nightly';
            if ($pricing_type === 'nightly' || $pricing_type === 'daily') {
                $dailyRate = (float)($unit['price_per_night'] ?? 0);
            } else {
                $dailyRate = (float)($unit['price_per_month'] ?? 0) / 30;
            }
            $dailyRate = max(0, $dailyRate);
            
            $unitAmount = $dailyRate * $totalDays;
            $securityDeposit = $unit['security_deposit'];
            $cleaningFee = isset($unit['cleaning_fee']) ? (float)$unit['cleaning_fee'] : 0.0;
            $serviceFee = isset($unit['service_fee']) ? (float)$unit['service_fee'] : 0.0;
            
            // I-calculate ang amenity costs - FIXED: CRITICAL #3 SQL Injection
            $amenityCosts = 0;
            $selectedAmenities = [];
            if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
                foreach ($_POST['amenities'] as $amenityId) {
                    $amenityId = (int)$amenityId;  // Validate as integer
                    if ($amenityId > 0) {
                        // FIXED: Use prepared statement instead of direct concatenation
                        $amenity = get_single_result(
                            "SELECT * FROM amenities WHERE amenity_id = ? AND branch_id = ?",
                            [$amenityId, $branchId]
                        );
                        if ($amenity) {
                            $amenityCosts += $amenity['hourly_rate'] * $totalDays; // Assuming daily rate
                            $selectedAmenities[] = $amenity;
                        }
                    }
                }
            }
            $totalAmount = $unitAmount + $amenityCosts + $cleaningFee + $serviceFee;
            
            // Apply Promo Code if provided
            $promo_code = isset($_POST['promo_code']) ? sanitize_input($_POST['promo_code']) : '';
            $discountAmount = 0;
            if (!empty($promo_code)) {
                $promo = get_single_result("
                    SELECT p.*, u.role as creator_role 
                    FROM promos p 
                    LEFT JOIN users u ON p.created_by = u.user_id 
                    WHERE p.code = ? AND p.is_active = 1 LIMIT 1
                ", [$promo_code]);
                
                if ($promo) {
                    $valid_promo = true;
                    
                    if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < time()) {
                        $valid_promo = false;
                    }
                    if (!empty($promo['usage_limit']) && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
                        $valid_promo = false;
                    }
                    
                    if (isset($promo['creator_role']) && in_array($promo['creator_role'], ['host', 'manager'])) {
                        if (!isset($unit['host_id']) || (int) $promo['created_by'] !== (int) $unit['host_id']) {
                            $valid_promo = false;
                        }
                    }
                    
                    if ($valid_promo) {
                        $value = (float)$promo['value'];
                        if ($promo['type'] === 'percentage') {
                            $discountAmount = $unitAmount * ($value / 100.0);
                        } else {
                            $discountAmount = $value;
                        }
                    }
                }
            }
            
            $totalAmount = max(0, $totalAmount - $discountAmount);
            
            // Read extra guest info and booking type
            $bookingType = sanitize_input($_POST['booking_type'] ?? 'request');
            $guestFullname = sanitize_input($_POST['guest_fullname'] ?? ($_SESSION['fullname'] ?? ''));
            $guestPhone = sanitize_input($_POST['guest_phone'] ?? ($_SESSION['phone'] ?? ''));
            $purposeOfStay = sanitize_input($_POST['purpose_of_stay'] ?? '');
            $numAdults = max(1, (int)($_POST['num_adults'] ?? 1));
            $numChildren = max(0, (int)($_POST['num_children'] ?? 0));
            $maxOcc = max(1, (int)($unit['max_occupancy'] ?? 10));
            if ($numAdults + $numChildren > $maxOcc) {
                $error = 'Guest count exceeds this unit\'s maximum occupancy (' . $maxOcc . ').';
            }

            // I-create ang reservation
            $reservationId = empty($error) ? createReservation(
                $_SESSION['user_id'], 
                $unitId, 
                $branchId, 
                $checkInDate, 
                $checkOutDate, 
                $totalAmount, 
                $securityDeposit, 
                $specialRequests,
                $numAdults,
                $numChildren
            ) : false;
            
            if ($reservationId) {
                // Mark submission time IMMEDIATELY to prevent race conditions
                $_SESSION['last_booking_submission'] = time();
                
                // I-create ang amenity bookings kung may selected amenities
                if (!empty($selectedAmenities)) {
                    foreach ($selectedAmenities as $amenity) {
                        $amenityBookingId = bookAmenity(
                            $_SESSION['user_id'],
                            $amenity['amenity_id'],
                            $branchId,
                            $checkInDate,
                            '00:00:00',
                            '23:59:59',
                            $amenity['hourly_rate'] * $totalDays
                        );
                    }
                }
                
                // Mag-send ng notification
                $amenityText = !empty($selectedAmenities) ? " with amenities: " . implode(', ', array_column($selectedAmenities, 'amenity_name')) : "";
                sendNotification(
                    $_SESSION['user_id'],
                    "Reservation Created",
                    "Your reservation for Unit " . $unit['unit_number'] . $amenityText . " has been created successfully. Reservation ID: " . $reservationId,
                    'booking',
                    'system'
                );

                // Handle government ID upload if provided
                if (isset($_FILES['government_id']) && $_FILES['government_id']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__FILE__, 2) . '/uploads/ids/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $origName = basename($_FILES['government_id']['name']);
                    $ext = pathinfo($origName, PATHINFO_EXTENSION);
                    $allowed = ['jpg','jpeg','png','pdf'];
                    if (in_array(strtolower($ext), $allowed) && $_FILES['government_id']['size'] <= 5 * 1024 * 1024) {
                        $targetName = 'reservation_' . $reservationId . '_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . $targetName;
                        if (move_uploaded_file($_FILES['government_id']['tmp_name'], $targetPath)) {
                            // Try to store path in reservations table (if column exists)
                            $relativePath = 'uploads/ids/' . $targetName;
                            try {
                                execute_query("UPDATE reservations SET government_id_path = ? WHERE reservation_id = ?", [$relativePath, $reservationId]);
                            } catch (Exception $ex) {
                                // ignore if column doesn't exist
                            }
                        }
                    }
                }

                $message = "Reservation created successfully! Reservation ID: " . $reservationId;
                // Mark booking as Pending Payment for manual receipt flow
                try {
                    execute_query("UPDATE reservations SET status = 'pending', payment_status = 'pending' WHERE reservation_id = ?", [$reservationId]);
                } catch (Exception $ex) {
                    // ignore if columns differ in DB
                }
                $availableUnits = []; // I-clear ang search results

                // If instant booking requested, mark approved and redirect to checkout to pay
                if ($bookingType === 'instant') {
                    // Try to set reservation to approved so checkout will allow payment
                    try {
                        execute_query("UPDATE reservations SET reservation_status = 'approved', approved_by = ? , approved_at = NOW() WHERE reservation_id = ?", [$_SESSION['user_id'], $reservationId]);
                    } catch (Exception $ex) {
                        // ignore
                    }

                    // Redirect renter to checkout to complete payment
                    header('Location: ../renter/checkout.php?type=reservation&id=' . $reservationId);
                    exit;
                }
            } else {
                // I-check kung may existing pending reservation - FIXED: CRITICAL #4 SQL Injection
                $existingReservation = get_single_result(
                    "SELECT reservation_id FROM reservations 
                    WHERE user_id = ? 
                    AND status = 'pending' 
                    AND (
                        (check_in_date <= ? AND check_out_date > ?) OR
                        (check_in_date < ? AND check_out_date >= ?) OR
                        (check_in_date >= ? AND check_out_date <= ?)
                    )",
                    [$_SESSION['user_id'], $checkOutDate, $checkInDate, $checkOutDate, $checkInDate, $checkInDate, $checkOutDate]
                );
                
                if ($existingReservation) {
                    $error = "You already have a pending reservation for overlapping dates. Please remove your existing booking first or choose different dates.";
                } else {
                    $error = "Failed to create reservation. Unit may no longer be available.";
                }
            }
        } else {
            $error = "Invalid unit or branch selected.";
        }
        }
    }
}

// Kumuha ng lahat ng branches
$branches = mysqli_query($conn, "SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve Unit - BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        :root {
            --primary: #2c3e50;
            --secondary: #e74c3c;
            --accent: #3498db;
            --gold: #f39c12;
            --success: #27ae60;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .shadow-soft {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .btn-modern {
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-luxury-primary {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 10px 20px rgba(243, 156, 18, 0.2);
        }

        .btn-luxury-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(243, 156, 18, 0.35);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Modern Premium Navigation -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-soft">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="../public/index.php" class="flex items-center gap-3 text-2xl font-bold text-gray-900 hover:opacity-80 smooth-transition">
                    <img src="../assets/images/logo/bookit.png" alt="BookIT Logo" class="w-14 h-14 rounded-xl object-cover">
                    BookIT
                </a>

                <div class="hidden md:flex items-center gap-10">
                    <a href="../public/index.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Home</a>
                    <a href="../public/browse_units.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Browse</a>
                    <a href="my_bookings.php" class="text-blue-600 font-600 border-b-2 border-blue-600 pb-2">Reserve</a>
                </div>

                <div class="flex items-center gap-4">
                    <?php if (isLoggedIn()): ?>
                        <div class="relative group">
                            <button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 smooth-transition">
                                <i class="fas fa-user-circle text-2xl"></i>
                                <span class="hidden sm:inline text-sm font-500"><?php echo htmlspecialchars(substr($_SESSION['fullname'], 0, 15)); ?></span>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                                <a href="../modules/notifications.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 first:rounded-t-lg smooth-transition">
                                    <i class="fas fa-bell mr-2 text-blue-500"></i> Notifications
                                </a>
                                <a href="my_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 smooth-transition">
                                    <i class="fas fa-calendar-check mr-2 text-orange-500"></i> My Bookings
                                </a>
                                <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 smooth-transition">
                                    <i class="fas fa-cog mr-2 text-gray-500"></i> Settings
                                </a>
                                <hr class="my-2">
                                <a href="../public/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 last:rounded-b-lg smooth-transition font-500">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-blue-600 to-blue-800 py-16 mb-8">
      <div class="max-w-3xl mx-auto text-center px-4">
        <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4 flex items-center justify-center gap-3">
          <i class="fas fa-calendar-plus text-gold"></i> Reserve Your Perfect Condo
        </h1>
        <p class="text-lg md:text-xl text-blue-100">Find and book luxury condominium units across our premium locations</p>
      </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 pb-16">
        <!-- Search Card -->
        <div class="card-modern shadow-soft p-8 mb-8 -mt-8 relative z-10">
            <h3 class="text-2xl font-bold text-center mb-2"><i class="fas fa-search text-blue-500"></i> Find Available Units</h3>
            <p class="text-center text-gray-500 mb-6">Search for available condo units across our branches</p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Select Branch</label>
                        <select class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none" name="branch_id" required>
                            <option value="">Choose Branch</option>
                            <?php 
                                // Re-query branches since we consumed the result earlier
                                $branches = mysqli_query($conn, "SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name");
                                while ($branch = mysqli_fetch_assoc($branches)): 
                            ?>
                                <option value="<?php echo $branch['branch_id']; ?>" 
                                        <?php echo $selectedBranch == $branch['branch_id'] ? 'selected' : ''; ?>>
                                    <?php echo $branch['branch_name']; ?> - <?php echo $branch['city']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Check-in Date</label>
                        <input type="date" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none" name="check_in_date" 
                               value="<?php echo $checkInDate; ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Check-out Date</label>
                        <input type="date" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none" name="check_out_date" 
                               value="<?php echo $checkOutDate; ?>" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">&nbsp;</label>
                        <button type="submit" name="search_units" class="btn-modern btn-luxury-primary w-full">
                            <i class="fas fa-search"></i> Search Units
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                <div class="flex-1">
                    <p class="text-green-700 font-semibold"><?php echo $message; ?></p>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                <div class="flex-1">
                    <p class="text-red-700 font-semibold"><?php echo $error; ?></p>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Available Units -->
        <?php if (!empty($availableUnits)): ?>
            <div class="mt-10">
                <h2 class="text-3xl font-bold mb-8 flex items-center justify-center gap-2"><i class="fas fa-home text-blue-500"></i> Available Units</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($availableUnits as $unit): 
                    // Get unit images (overview + gallery)
                    $unit_images = get_multiple_results(
                        "SELECT image_path FROM unit_images WHERE unit_id = ? ORDER BY created_at DESC",
                        [$unit['unit_id']]
                    );
                    $main_image = !empty($unit_images) ? $unit_images[0]['image_path'] : 'https://via.placeholder.com/600x300/667eea/ffffff?text=Unit+' . urlencode($unit['unit_number']);
                ?>
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col h-full">
                        <div class="relative h-56 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($main_image); ?>" alt="Unit <?php echo $unit['unit_number']; ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-0 left-0 right-0 bg-gradient-to-b from-blue-600 to-transparent p-4">
                                <h5 class="text-white text-lg font-bold">
                                    <?php echo !empty($unit['unit_name']) ? htmlspecialchars($unit['unit_name']) : 'Unit ' . htmlspecialchars($unit['unit_number']); ?>
                                </h5>
                            </div>
                            <?php $ptype = $unit['pricing_type'] ?? 'nightly'; ?>
                            <div class="absolute bottom-3 left-3 bg-orange-500 text-white px-3 py-1 rounded-lg font-bold shadow-sm">
                                ₱<?php echo in_array($ptype, ['nightly', 'daily']) ? number_format((float)($unit['price_per_night'] ?? 0)) . '/night' : number_format((float)($unit['price_per_month'] ?? 0)) . '/month'; ?>
                            </div>
                            <div class="absolute bottom-3 right-3 bg-white/90 text-gray-800 px-2 py-1 rounded-lg font-bold text-xs shadow-sm uppercase tracking-wide">
                                <?php echo in_array($ptype, ['nightly', 'daily']) ? 'Nightly Stay' : 'Monthly Rental'; ?>
                            </div>
                        </div>
                        <div class="p-6 flex-1 flex flex-col">
                            <h5 class="text-lg font-bold mb-2">
                                <?php echo htmlspecialchars(!empty($unit['unit_name']) ? (!empty($unit['unit_type']) ? $unit['unit_type'] . ' · ' . $unit['unit_number'] : $unit['unit_number']) : ($unit['unit_type'] ?? 'Unit')); ?>
                            </h5>
                            <div class="text-sm text-gray-600 mb-3 flex items-center gap-1">
                                <i class="fas fa-map-marker-alt text-blue-500"></i>
                                <span><?php echo esc($unit['branch_address'] ?? $unit['branch_name'] ?? ''); ?>
                                    <?php if (!empty($unit['branch_city'])): ?>, <?php echo esc($unit['branch_city']); ?><?php endif; ?></span>
                            </div>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-semibold flex items-center gap-1">
                                    <i class="fas fa-ruler-combined"></i> <?php echo $unit['sqm'] ?? 'N/A'; ?> sqm
                                </span>
                                <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-semibold flex items-center gap-1">
                                    <i class="fas fa-user-friends"></i> <?php echo $unit['max_occupancy']; ?> guests
                                </span>
                                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-semibold flex items-center gap-1">
                                    <i class="fas fa-door-open"></i> Floor <?php echo $unit['floor_number']; ?>
                                </span>
                            </div>
                            
                            <?php if ($unit['description']): ?>
                                <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo $unit['description']; ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-auto">
                                <!-- Unit Overview Button -->
                                <a href="unit_detail.php?unit_id=<?php echo $unit['unit_id']; ?>&branch_id=<?php echo $unit['branch_id']; ?>&check_in=<?php echo urlencode($checkInDate); ?>&check_out=<?php echo urlencode($checkOutDate); ?>" class="btn-modern bg-blue-500 text-white w-full mb-2">
                                    <i class="fas fa-eye"></i> View Details & Reviews
                                </a>
                                
                                <!-- Amenity Selection -->
                                <?php 
                                // Kumuha ng amenities sa selected branch
                                $amenities = getBranchAmenities($unit['branch_id']);
                                if ($amenities && !empty($amenities)): 
                                ?>
                                    <div class="mt-4 mb-4">
                                        <label class="block text-sm font-bold text-gray-700 mb-2"><i class="fas fa-swimming-pool text-blue-500"></i> Select Amenities (Optional)</label>
                                        <div class="space-y-2">
                                            <?php foreach ($amenities as $amenity): ?>
                                                <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors" for="amenity_<?php echo $amenity['amenity_id']; ?>">
                                                    <input class="mt-1 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 amenity-checkbox" type="checkbox" 
                                                           name="amenities[]" value="<?php echo $amenity['amenity_id']; ?>" 
                                                           id="amenity_<?php echo $amenity['amenity_id']; ?>"
                                                           data-rate="<?php echo $amenity['hourly_rate']; ?>">
                                                    <div>
                                                        <strong class="text-gray-900 block"><?php echo $amenity['amenity_name']; ?></strong>
                                                        <span class="text-sm text-gray-500">
                                                            ₱<?php echo number_format($amenity['hourly_rate'], 2); ?>/day
                                                        </span>
                                                    </div>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Reservation Form -->
                                <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4" data-max-occ="<?php echo (int)($unit['max_occupancy'] ?? 20); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="unit_id" value="<?php echo $unit['unit_id']; ?>">
                                    <input type="hidden" name="branch_id" value="<?php echo $unit['branch_id']; ?>">
                                    <input type="hidden" name="check_in_date" value="<?php echo $checkInDate; ?>">
                                    <input type="hidden" name="check_out_date" value="<?php echo $checkOutDate; ?>">
                                    <input type="hidden" name="booking_type" value="request">

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Booking Type</label>
                                        <select name="booking_type" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none bg-white">
                                            <option value="request" <?php echo empty($unit['instant_booking']) ? 'selected' : ''; ?>>Request to Book (Host approval)</option>
                                            <option value="instant" <?php echo !empty($unit['instant_booking']) ? 'selected' : ''; ?>>Instant Booking (Pay & confirm immediately)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Full Name</label>
                                        <input type="text" name="guest_fullname" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none" value="<?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?>" required>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Phone Number</label>
                                        <input type="text" name="guest_phone" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none" value="<?php echo htmlspecialchars($_SESSION['phone'] ?? ''); ?>" required>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-1">Adults</label>
                                            <input type="number" name="num_adults" min="1" max="<?php echo (int)($unit['max_occupancy'] ?? 20); ?>" value="1" required class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-gray-700 mb-1">Children</label>
                                            <input type="number" name="num_children" min="0" max="<?php echo (int)($unit['max_occupancy'] ?? 20); ?>" value="0" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none">
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 -mt-2">Total guests cannot exceed this unit's max occupancy (<?php echo (int)($unit['max_occupancy'] ?? 0); ?>).</p>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Government ID <span class="text-gray-400 font-normal">(Optional)</span></label>
                                        <input type="file" name="government_id" accept="image/*,.pdf" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <p class="mt-1 text-xs text-gray-500">Allowed: jpg, png, pdf. Max 5MB.</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Purpose of Stay</label>
                                        <input type="text" name="purpose_of_stay" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none" placeholder="e.g. Vacation, Business">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Special Requests</label>
                                        <textarea class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-400 outline-none resize-none" name="special_requests" rows="2" 
                                                  placeholder="Any special requirements..."></textarea>
                                    </div>
                                    
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 mt-6">
                                        <h6 class="font-bold text-gray-900 mb-3 border-b border-gray-200 pb-2">Pricing Breakdown</h6>
                                        <div class="flex justify-between items-center text-sm text-gray-600 mb-2">
                                            <span>Unit (<?php echo calculateDays($checkInDate, $checkOutDate); ?> days):</span>
                                            <span class="font-bold text-gray-900">
                                            <?php 
                                            $ptype = $unit['pricing_type'] ?? 'nightly';
                                            $drate = in_array($ptype, ['nightly', 'daily']) ? (float)($unit['price_per_night'] ?? 0) : (float)($unit['price_per_month'] ?? 0) / 30;
                                            $drate = max(0, $drate);
                                            echo '₱' . number_format($drate * calculateDays($checkInDate, $checkOutDate), 2);
                                            ?>
                                            </span>
                                        </div>
                                        <div class="amenity-cost flex justify-between items-center text-sm text-gray-600 mb-2" style="display: none;">
                                            <span>Amenities:</span>
                                            <span class="font-bold text-gray-900" id="amenity-total-<?php echo $unit['unit_id']; ?>">₱0.00</span>
                                        </div>
                                        <div class="flex justify-between items-center text-sm text-gray-600 mb-2">
                                            <span>Security Deposit:</span>
                                            <span class="text-gray-900">₱<?php echo number_format($unit['security_deposit'], 2); ?></span>
                                        </div>
                                        <?php if (!empty($unit['cleaning_fee'])): ?>
                                        <div class="flex justify-between items-center text-sm text-gray-600 mb-2">
                                            <span>Cleaning Fee:</span>
                                            <span class="text-gray-900">₱<?php echo number_format($unit['cleaning_fee'], 2); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($unit['service_fee'])): ?>
                                        <div class="flex justify-between items-center text-sm text-gray-600 mb-2">
                                            <span>Service Fee:</span>
                                            <span class="text-gray-900">₱<?php echo number_format($unit['service_fee'], 2); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="border-t border-gray-200 mt-3 pt-3 flex justify-between items-center">
                                            <span class="font-bold text-gray-900 text-lg">Total Amount:</span>
                                            <span class="text-xl font-bold text-green-600" id="total-cost-<?php echo $unit['unit_id']; ?>">
                                                ₱<?php 
                                                $ptype = $unit['pricing_type'] ?? 'nightly';
                                                $drate = in_array($ptype, ['nightly', 'daily']) ? (float)($unit['price_per_night'] ?? 0) : (float)($unit['price_per_month'] ?? 0) / 30;
                                                $drate = max(0, $drate);
                                                echo number_format((($drate * calculateDays($checkInDate, $checkOutDate)) + $unit['security_deposit'] + ($unit['cleaning_fee'] ?? 0) + ($unit['service_fee'] ?? 0)), 2); 
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="reserve_unit" class="btn-modern btn-luxury-primary w-full mt-6 justify-center text-lg">
                                        <i class="fas fa-calendar-plus"></i> Reserve This Unit
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            

            
        <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_units'])): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5>No Available Units</h5>
                <p class="text-muted">No units are available for the selected dates and branch.</p>
                <button class="btn btn-luxury" onclick="window.location.reload()">
                    <i class="fas fa-refresh"></i> Try Different Dates
                </button>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-home fa-3x text-muted mb-3"></i>
                <h5>Find Your Perfect Unit</h5>
                <p class="text-muted">Select a branch and dates to see available units.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-10 mt-16">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col md:flex-row md:justify-between gap-8">
          <div>
            <h5 class="text-xl font-bold mb-2 flex items-center gap-2"><i class="fas fa-building"></i> BookIT</h5>
            <p class="text-gray-400 max-w-xs">Multi-branch condo rental reservation system designed to streamline your rental management operations.</p>
          </div>
          <div>
            <h6 class="text-lg font-bold mb-2">Quick Links</h6>
            <div class="flex flex-col gap-2">
              <a href="../public/index.php" class="text-gray-300 hover:text-white">Home</a>
              <a href="my_bookings.php" class="text-gray-300 hover:text-white">My Bookings</a>
              <a href="profile.php" class="text-gray-300 hover:text-white">Profile</a>
            </div>
          </div>
        </div>
        <hr class="my-6 border-gray-700">
        <div class="text-center text-gray-500">&copy; 2025 BookIT. All rights reserved.</div>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../assets/js/renter/ui.js"></script>
    <script>
    (function () {
        document.addEventListener('input', function (e) {
            if (!e.target.matches('input[name="num_adults"], input[name="num_children"]')) return;
            var form = e.target.closest('form[data-max-occ]');
            if (!form) return;
            var maxO = parseInt(form.getAttribute('data-max-occ'), 10);
            if (!maxO || maxO < 1) maxO = 99;
            var aEl = form.querySelector('input[name="num_adults"]');
            var cEl = form.querySelector('input[name="num_children"]');
            if (!aEl || !cEl) return;
            var a = parseInt(aEl.value, 10) || 1;
            var c = parseInt(cEl.value, 10) || 0;
            if (a < 1) { a = 1; aEl.value = 1; }
            if (c < 0) { c = 0; cEl.value = 0; }
            if (a + c > maxO) {
                if (e.target.name === 'num_children') {
                    cEl.value = String(Math.max(0, maxO - a));
                } else {
                    aEl.value = String(Math.max(1, maxO - c));
                }
            }
        });
    })();
    </script>
</body>
</html>