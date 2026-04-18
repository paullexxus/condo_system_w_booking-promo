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

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative shadow-sm">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['flash_error']); ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative shadow-sm">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['flash_success']); ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

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
                                    <div class="mt-6 flex flex-col gap-3">
                                        <button type="submit" name="action_type" value="book" formaction="booking_summary.php" class="btn-modern btn-luxury-primary w-full justify-center text-lg">
                                            <i class="fas fa-bolt"></i> Book Now
                                        </button>
                                        <button type="submit" name="action_type" value="reserve" formaction="process_reservation.php" class="btn-modern !bg-white !text-gray-700 border border-gray-300 hover:!bg-gray-50 w-full justify-center text-lg">
                                            <i class="fas fa-clock"></i> Reserve Unit (10 Min Hold)
                                        </button>
                                    </div>
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