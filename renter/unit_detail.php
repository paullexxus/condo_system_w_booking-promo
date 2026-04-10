<?php
include_once '../includes/functions.php';
include_once '../includes/auth.php';
include_once '../includes/session.php';

$unitId = isset($_GET['unit_id']) ? (int) $_GET['unit_id'] : 0;
$branchId = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$checkIn = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$checkOut = isset($_GET['check_out']) ? $_GET['check_out'] : '';

if ($unitId <= 0) {
    header('Location: reserve_unit.php');
    exit;
}

$unit = get_single_result("SELECT * FROM units WHERE unit_id = ? AND (approval_status = 'approved' OR approval_status IS NULL)", [$unitId]);
if (!$unit) {
    header('Location: reserve_unit.php');
    exit;
}

$branch = get_single_result("SELECT * FROM branches WHERE branch_id = ?", [$unit['branch_id']]);

// Host lookup (best-effort)
$hostName = '';
if (!empty($unit['host_id'])) {
    $host = get_single_result("SELECT full_name FROM users WHERE user_id = ?", [$unit['host_id']]);
    if ($host)
        $hostName = $host['full_name'];
}
if (empty($hostName) && !empty($branch['manager_name'])) {
    $hostName = $branch['manager_name'];
}
if (empty($hostName)) {
    $hostName = 'Host';
}

$images = get_multiple_results("SELECT image_path FROM unit_images WHERE unit_id = ? ORDER BY created_at DESC", [$unitId]);
$main_image = !empty($images) ? $images[0]['image_path'] : 'https://via.placeholder.com/900x600?text=Unit+' . urlencode($unit['unit_number']);

$amenities = getBranchAmenities($unit['branch_id']);

$reviews = get_multiple_results("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.unit_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC", [$unitId]);

$unit_addons = get_multiple_results("SELECT * FROM unit_addons WHERE unit_id = ? AND is_active = 1", [$unitId]);
$blackouts = get_multiple_results("SELECT start_date, end_date, reason FROM unit_blackouts WHERE unit_id = ? ORDER BY start_date ASC", [$unitId]);
$pricing_rules = get_multiple_results("SELECT rule_type, adjustment_type, adjustment_value, start_date, end_date FROM unit_pricing_rules WHERE unit_id = ? AND is_active = 1", [$unitId]);

if (($unit['pricing_type'] ?? 'monthly') === 'daily') {
    $nightly = (float) ($unit['monthly_rate'] ?? 0);
} else {
    $nightly = isset($unit['monthly_rate']) ? round($unit['monthly_rate'] / 30, 2) : 0;
}
$cleaningFee = isset($unit['cleaning_fee']) ? (float) $unit['cleaning_fee'] : 0.0;
$serviceFee = isset($unit['service_fee']) ? (float) $unit['service_fee'] : 0.0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars(!empty($unit['unit_name']) ? $unit['unit_name'] : $unit['unit_type'] . ' - Unit ' . $unit['unit_number']); ?>
        — BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .font-poppins {
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary-gradient {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-primary-gradient:hover {
            opacity: 0.95;
        }
        .calendar-legend { font-size: 12px; color: #6b7280; }
        .legend-chip { display:inline-flex; align-items:center; gap:6px; margin-right:10px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 9999px; display:inline-block; }
        .flatpickr-day.blocked-date { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
        .flatpickr-day.fixed-rate-date { background: #dbeafe; border-color: #bfdbfe; color: #1e3a8a; }
        .calendar-note { margin-top: 8px; font-size: 12px; color: #374151; }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="fixed top-0 w-full bg-white border-b border-gray-200 shadow-sm z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <a href="../public/index.php" class="flex items-center gap-2 hover:opacity-80 transition">
                    <img src="../assets/images/logo/bookit.png" alt="BookIT" class="h-12 w-auto">
                </a>

                <!-- Right Menu -->
                <div class="flex items-center gap-4">
                    <?php if (isLoggedIn() && in_array($_SESSION['role'], ['host', 'manager', 'admin'])): ?>
                        <a href="../public/manager_register.php"
                            class="flex items-center gap-2 text-gray-700 hover:text-gray-900 transition">
                            <i class="fas fa-handshake"></i> Be a Host
                        </a>
                    <?php else: ?>
                        <a href="../public/be_host.php"
                            class="flex items-center gap-2 text-gray-700 hover:text-gray-900 transition">
                            <i class="fas fa-handshake"></i> Be a Host
                        </a>
                    <?php endif; ?>

                    <?php if (isLoggedIn()): ?>
                        <div class="relative group">
                            <button class="flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                                <i class="fas fa-user-circle text-xl text-gray-600"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <i class="fas fa-chevron-down text-xs text-gray-600"></i>
                            </button>
                            <div
                                class="absolute right-0 mt-0 w-48 bg-white border border-gray-200 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                                <a href="../modules/notifications.php"
                                    class="flex items-center gap-2 px-4 py-3 text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-bell text-orange-500"></i> Notifications
                                </a>
                                <a href="my_bookings.php"
                                    class="flex items-center gap-2 px-4 py-3 text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-calendar-check text-blue-500"></i> My Bookings
                                </a>
                                <a href="profile.php"
                                    class="flex items-center gap-2 px-4 py-3 text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog text-gray-600"></i> Settings
                                </a>
                                <hr class="my-2">
                                <a href="../public/logout.php"
                                    class="flex items-center gap-2 px-4 py-3 text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-24 pb-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Back Button -->
            <a href="reserve_unit.php"
                class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-6 transition">
                <i class="fas fa-arrow-left"></i> Back to Search
            </a>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Title & Location -->
                    <div class="mb-8">
                        <h1 class="font-poppins text-4xl font-bold text-gray-900 mb-2">
                            <?php echo !empty($unit['unit_name']) ? htmlspecialchars($unit['unit_name']) : htmlspecialchars($unit['unit_type']) . ' · Unit ' . htmlspecialchars($unit['unit_number']); ?>
                        </h1>
                        <p class="text-gray-600 text-lg mb-2">
                            <i class="fas fa-map-marker-alt text-orange-500"></i>
                            <?php echo htmlspecialchars($branch['branch_name'] ?? '') . ', ' . htmlspecialchars($branch['city'] ?? ''); ?>
                        </p>
                        <p class="text-gray-700">
                            <strong>Hosted by:</strong> <?php echo htmlspecialchars($hostName); ?>
                        </p>
                    </div>

                    <!-- Photo Gallery -->
                    <div class="mb-8">
                        <div class="mb-4">
                            <img id="mainPhoto" src="<?php echo htmlspecialchars($main_image); ?>"
                                class="w-full h-96 object-cover rounded-lg shadow-lg" alt="Main Photo">
                        </div>
                        <div class="flex gap-2 overflow-x-auto pb-2">
                            <?php if (!empty($images)):
                                foreach ($images as $img): ?>
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>"
                                        class="h-24 w-32 object-cover rounded-lg cursor-pointer hover:opacity-80 transition flex-shrink-0 thumb"
                                        alt="thumb">
                                <?php endforeach; else: ?>
                                <img src="https://via.placeholder.com/160x110" class="h-24 w-32 object-cover rounded-lg"
                                    alt="thumb">
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- About Section -->
                    <div class="mb-8">
                        <h2 class="font-poppins text-2xl font-bold text-gray-900 mb-4">About this unit</h2>
                        <p class="text-gray-700 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($unit['description'] ?? 'No description provided.')); ?>
                        </p>
                    </div>

                    <!-- Amenities Section -->
                    <div class="mb-8">
                        <h2 class="font-poppins text-2xl font-bold text-gray-900 mb-4">Amenities</h2>
                        <?php if (!empty($amenities)): ?>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($amenities as $a): ?>
                                    <span
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg border border-gray-200">
                                        <i class="fas fa-check text-green-500"></i>
                                        <?php echo htmlspecialchars($a['amenity_name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600">No amenities listed.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Location Section -->
                    <div class="mb-8">
                        <h2 class="font-poppins text-2xl font-bold text-gray-900 mb-4">Location</h2>
                        <p class="text-gray-700 mb-4">
                            <i class="fas fa-map-marker-alt text-orange-500"></i>
                            <?php echo htmlspecialchars(!empty($unit['street_address']) ? $unit['street_address'] : ($branch['address'] ?? 'Address not available')); ?>,
                            <?php echo htmlspecialchars(!empty($unit['city']) ? $unit['city'] : ($branch['city'] ?? '')); ?>
                        </p>
                        <div id="unitMap" class="w-full h-80 rounded-lg overflow-hidden shadow-md mb-4"></div>
                        <div class="flex gap-3">
                            <?php
                            $locLat = !empty($unit['latitude']) ? $unit['latitude'] : ($branch['latitude'] ?? null);
                            $locLng = !empty($unit['longitude']) ? $unit['longitude'] : ($branch['longitude'] ?? null);
                            $locStr = !empty($unit['street_address']) ? trim($unit['street_address'] . ', ' . $unit['city']) : trim(($branch['address'] ?? '') . ', ' . ($branch['city'] ?? ''));
                            if ($locLat && $locLng):
                                ?>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($locLat . ',' . $locLng); ?>"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 px-6 py-3 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-50 transition">
                                    <i class="fas fa-directions"></i> Get Directions
                                </a>
                            <?php else: ?>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($locStr); ?>"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 px-6 py-3 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-50 transition">
                                    <i class="fas fa-directions"></i> Get Directions
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reviews Section -->
                    <div class="mb-8">
                        <h2 class="font-poppins text-2xl font-bold text-gray-900 mb-4">Reviews</h2>
                        <?php if (!empty($reviews)): ?>
                            <div class="space-y-4">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="p-4 bg-white border border-gray-200 rounded-lg">
                                        <div class="flex items-start gap-3 mb-2">
                                            <img src="https://via.placeholder.com/48" alt="avatar"
                                                class="w-12 h-12 rounded-full">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($review['full_name']); ?></h4>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo formatDate($review['created_at']); ?></p>
                                            </div>
                                        </div>
                                        <p class="text-gray-700"><?php echo htmlspecialchars($review['comment']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600">No reviews yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Booking Card (Sidebar) -->
                <div class="lg:col-span-1">
                    <div class="sticky top-24 bg-white border border-gray-200 rounded-lg shadow-lg p-6">
                        <!-- Price -->
                        <div class="mb-6">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="text-3xl font-bold text-gray-900">
                                        ₱<?php echo number_format($nightly, 2); ?></h3>
                                    <p class="text-gray-600">per night</p>
                                </div>
                                <span
                                    class="inline-block px-3 py-1 bg-blue-100 text-blue-700 text-sm font-semibold rounded-lg">
                                    <?php echo htmlspecialchars($unit['max_occupancy'] ?? '1'); ?> guests
                                </span>
                            </div>
                        </div>

                        <form method="POST" action="reserve_unit.php" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="unit_id" value="<?php echo $unitId; ?>">
                            <input type="hidden" name="branch_id" value="<?php echo $unit['branch_id']; ?>">

                            <!-- Check-in & Check-out -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-2">Check-in</label>
                                <input id="checkin" name="check_in_date"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required value="<?php echo htmlspecialchars($checkIn); ?>">
                                <div id="checkin_note" class="calendar-note"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-2">Check-out</label>
                                <input id="checkout" name="check_out_date"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    required value="<?php echo htmlspecialchars($checkOut); ?>">
                                <div id="checkout_note" class="calendar-note"></div>
                            </div>
                            <div class="calendar-legend">
                                <span class="legend-chip"><span class="legend-dot" style="background:#fee2e2;"></span>Blocked dates</span>
                                <span class="legend-chip"><span class="legend-dot" style="background:#dbeafe;"></span>Fixed/date pricing</span>
                            </div>

                            <!-- Guests -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-3">Guests</label>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-700">Adults</span>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg hover:border-gray-400 transition"
                                                data-action="decrement" data-target="adults">−</button>
                                            <span id="adults" class="w-6 text-center">1</span>
                                            <button type="button"
                                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg hover:border-gray-400 transition"
                                                data-action="increment" data-target="adults">+</button>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-700">Children</span>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg hover:border-gray-400 transition"
                                                data-action="decrement" data-target="children">−</button>
                                            <span id="children" class="w-6 text-center">0</span>
                                            <button type="button"
                                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg hover:border-gray-400 transition"
                                                data-action="increment" data-target="children">+</button>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-700">Infants</span>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg hover:border-gray-400 transition"
                                                data-action="decrement" data-target="infants">−</button>
                                            <span id="infants" class="w-6 text-center">0</span>
                                            <button type="button"
                                                class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg hover:border-gray-400 transition"
                                                data-action="increment" data-target="infants">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($unit_addons)): ?>
                                <div class="pt-2">
                                    <label class="block text-sm font-semibold text-gray-900 mb-2">Optional Add-ons</label>
                                    <div class="space-y-2">
                                        <?php foreach ($unit_addons as $addon): ?>
                                            <label
                                                class="flex items-center gap-2 cursor-pointer text-gray-700 hover:text-gray-900">
                                                <input type="checkbox" name="addon_ids[]"
                                                    value="<?php echo (int) $addon['addon_id']; ?>"
                                                    class="addon-checkbox w-4 h-4 text-orange-500 rounded focus:ring-orange-500 border-gray-300"
                                                    onchange="updateTotals()">
                                                <span class="flex-1"><?php echo htmlspecialchars($addon['name']); ?></span>
                                                <span
                                                    class="font-medium">+₱<?php echo number_format((float) $addon['price'], 2); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="pt-2 pb-2">
                                <label class="block text-sm font-semibold text-gray-900 mb-2">Promo Code</label>
                                <div class="flex gap-2">
                                    <input type="text" id="promo_code_input" name="promo_code"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent uppercase text-sm"
                                        placeholder="SUMMER20">
                                    <button type="button"
                                        class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-200 transition text-sm"
                                        onclick="updateTotals()">Apply</button>
                                </div>
                                <div id="promo_success" class="text-sm text-green-600 font-medium mt-2 hidden"><i
                                        class="fas fa-check-circle mr-1"></i> Promo applied successfully</div>
                                <div id="promo_error" class="text-sm text-red-500 font-medium mt-2 hidden"><i
                                        class="fas fa-exclamation-circle mr-1"></i> </div>
                            </div>

                            <!-- Price Breakdown -->
                            <div class="space-y-2 py-4 border-t border-b border-gray-200" id="pricing_breakdown">
                                <div class="flex justify-between text-gray-700">
                                    <span>Stay Subtotal</span>
                                    <span id="subtotal">₱0.00</span>
                                </div>
                                <div class="flex justify-between text-gray-700 hidden" id="addons_row">
                                    <span>Add-ons</span>
                                    <span id="addons_total">₱0.00</span>
                                </div>
                                <div class="flex justify-between text-green-600 font-semibold hidden" id="discount_row">
                                    <span>Promo Discount</span>
                                    <span id="discount_total">-₱0.00</span>
                                </div>
                                <div class="flex justify-between text-gray-700">
                                    <span>Cleaning Fee</span>
                                    <span>₱<?php echo number_format($cleaningFee, 2); ?></span>
                                </div>
                                <div class="flex justify-between text-gray-700">
                                    <span>Service Fee</span>
                                    <span>₱<?php echo number_format($serviceFee, 2); ?></span>
                                </div>
                            </div>

                            <div class="flex justify-between items-center pt-4 relative">
                                <span class="font-semibold text-gray-900">Total</span>
                                <span id="total" class="text-2xl font-bold text-orange-600">₱0.00</span>
                            </div>

                            <!-- Buttons -->
                            <div class="space-y-2 pt-4">
                                <button type="submit" name="book_now"
                                    class="w-full px-6 py-3 bg-gradient-to-r from-orange-500 to-red-500 text-white font-semibold rounded-lg hover:opacity-95 transition shadow-md">
                                    Book Now
                                </button>
                                <button type="submit" name="reserve_unit"
                                    class="w-full px-6 py-3 border border-gray-300 text-gray-900 font-semibold rounded-lg hover:bg-gray-50 transition">
                                    Reserve
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : ''; ?>&callback=initUnitMap"
        async defer></script>
    <script>
        // Map initialization for unit detail
        function initUnitMap() {
            var mapEl = document.getElementById('unitMap');
            if (!mapEl) return;

            // Use unit coordinates if available, otherwise branch
            var lat = <?php echo !empty($unit['latitude']) ? json_encode((float) $unit['latitude']) : (isset($branch['latitude']) ? json_encode((float) $branch['latitude']) : 'null'); ?>;
            var lng = <?php echo !empty($unit['longitude']) ? json_encode((float) $unit['longitude']) : (isset($branch['longitude']) ? json_encode((float) $branch['longitude']) : 'null'); ?>;

            if (lat !== null && lng !== null && typeof google !== 'undefined' && google.maps) {
                var center = { lat: parseFloat(lat), lng: parseFloat(lng) };
                var map = new google.maps.Map(mapEl, { center: center, zoom: 15, disableDefaultUI: true });
                var marker = new google.maps.Marker({ position: center, map: map, title: <?php echo json_encode(!empty($unit['unit_name']) ? $unit['unit_name'] : ($branch['branch_name'] ?? 'Unit location')); ?> });
            } else {
                // if no coords, fallback to embed iframe using encoded address
                var address = <?php echo json_encode(!empty($unit['street_address']) ? trim($unit['street_address'] . ', ' . $unit['city']) : trim(($branch['address'] ?? '') . ', ' . ($branch['city'] ?? ''))); ?>;
                if (address && address.trim() !== '') {
                    var iframe = document.createElement('iframe');
                    iframe.width = '100%';
                    iframe.height = '320';
                    iframe.style.border = '0';
                    iframe.style.borderRadius = '0.5rem';
                    iframe.loading = 'lazy';
                    iframe.referrerPolicy = 'no-referrer-when-downgrade';
                    iframe.src = 'https://www.google.com/maps?q=' + encodeURIComponent(address) + '&output=embed';
                    mapEl.parentNode.replaceChild(iframe, mapEl);
                } else {
                    mapEl.innerHTML = '<div class="p-3 text-center text-gray-500">Location not available</div>';
                }
            }
        }

        // wire thumbnails to main photo
        document.querySelectorAll('.thumb').forEach(function (el) {
            el.addEventListener('click', function () {
                document.getElementById('mainPhoto').src = this.src;
            });
        });

        // guest buttons
        document.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var target = document.getElementById(this.dataset.target);
                var val = parseInt(target.textContent, 10);
                if (this.dataset.action === 'increment') val++;
                else val = Math.max(0, val - 1);
                target.textContent = val;
                updateTotals();
            });
        });

        const blackouts = <?php echo json_encode($blackouts ?: [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
        const pricingRules = <?php echo json_encode($pricing_rules ?: [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
        const baseNightlyRate = <?php echo json_encode((float)$nightly); ?>;
        const blackoutRanges = blackouts.map(function (b) { return { from: b.start_date, to: b.end_date }; });

        function findBlackout(dateObj) {
            const stamp = dateObj.getTime();
            return blackouts.find(function (b) {
                const s = new Date(b.start_date + 'T00:00:00').getTime();
                const e = new Date(b.end_date + 'T23:59:59').getTime();
                return stamp >= s && stamp <= e;
            }) || null;
        }

        function computeDateRate(dateObj) {
            const stamp = dateObj.getTime();
            let rule = null;
            for (const r of pricingRules) {
                if (r.rule_type === 'date_range' && r.start_date && r.end_date) {
                    const s = new Date(r.start_date + 'T00:00:00').getTime();
                    const e = new Date(r.end_date + 'T23:59:59').getTime();
                    if (stamp >= s && stamp <= e) { rule = r; break; }
                }
            }
            if (!rule) return null;
            const val = parseFloat(rule.adjustment_value || 0);
            const rate = rule.adjustment_type === 'percentage'
                ? baseNightlyRate + (baseNightlyRate * val / 100)
                : baseNightlyRate + val;
            return Math.max(0, rate);
        }

        const checkinFp = flatpickr('#checkin', {
            minDate: 'today',
            disable: blackoutRanges,
            onDayCreate: function(_, __, ___, dayElem) {
                const b = findBlackout(dayElem.dateObj);
                if (b) {
                    dayElem.classList.add('blocked-date');
                    dayElem.title = 'Blocked: ' + (b.reason || 'Unavailable');
                    return;
                }
                const rate = computeDateRate(dayElem.dateObj);
                if (rate !== null) {
                    dayElem.classList.add('fixed-rate-date');
                    dayElem.title = 'Special rate: ₱' + rate.toFixed(2);
                }
            },
            onChange: function(selectedDates) {
                if (selectedDates.length) {
                    checkOutFp.set('minDate', new Date(selectedDates[0].getTime() + 86400000));
                    const b = findBlackout(selectedDates[0]);
                    const rate = computeDateRate(selectedDates[0]);
                    const note = document.getElementById('checkin_note');
                    if (b) note.textContent = 'Blocked: ' + (b.reason || 'Unavailable');
                    else if (rate !== null) note.textContent = 'Special nightly rate: ₱' + rate.toFixed(2);
                    else note.textContent = '';
                }
                updateTotals();
            }
        });

        const checkOutFp = flatpickr('#checkout', {
            minDate: new Date().fp_incr(1),
            disable: blackoutRanges,
            onDayCreate: function(_, __, ___, dayElem) {
                const b = findBlackout(dayElem.dateObj);
                if (b) {
                    dayElem.classList.add('blocked-date');
                    dayElem.title = 'Blocked: ' + (b.reason || 'Unavailable');
                    return;
                }
                const rate = computeDateRate(dayElem.dateObj);
                if (rate !== null) {
                    dayElem.classList.add('fixed-rate-date');
                    dayElem.title = 'Special rate: ₱' + rate.toFixed(2);
                }
            },
            onChange: function(selectedDates) {
                if (selectedDates.length) {
                    const b = findBlackout(selectedDates[0]);
                    const rate = computeDateRate(selectedDates[0]);
                    const note = document.getElementById('checkout_note');
                    if (b) note.textContent = 'Blocked: ' + (b.reason || 'Unavailable');
                    else if (rate !== null) note.textContent = 'Special nightly rate: ₱' + rate.toFixed(2);
                    else note.textContent = '';
                }
                updateTotals();
            }
        });

        function daysBetween(a, b) {
            var d1 = new Date(a); var d2 = new Date(b);
            if (isNaN(d1) || isNaN(d2)) return 0;
            var diff = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));
            return diff > 0 ? diff : 0;
        }

        let isCalculating = false;
        async function updateTotals() {
            var inDate = document.getElementById('checkin').value;
            var outDate = document.getElementById('checkout').value;
            var nights = daysBetween(inDate, outDate);

            if (nights <= 0) {
                document.getElementById('subtotal').textContent = '₱0.00';
                document.getElementById('total').textContent = '₱0.00';
                return;
            }

            if (isCalculating) return;
            isCalculating = true;

            const addonsInput = document.querySelectorAll('.addon-checkbox:checked');
            let addon_ids = Array.from(addonsInput).map(cb => cb.value);
            let promoCode = document.getElementById('promo_code_input').value.trim();

            try {
                const response = await fetch('../ajax/calculate_price.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        unit_id: <?php echo $unitId; ?>,
                        check_in_date: inDate,
                        check_out_date: outDate,
                        addon_ids: addon_ids,
                        promo_code: promoCode
                    })
                });

                const data = await response.json();

                var cleaning = <?php echo json_encode($cleaningFee); ?>;
                var service = <?php echo json_encode($serviceFee); ?>;

                if (data.success) {
                    document.getElementById('subtotal').textContent = '₱' + parseFloat(data.subtotal).toFixed(2);

                    if (parseFloat(data.addons_total) > 0) {
                        document.getElementById('addons_row').classList.remove('hidden');
                        document.getElementById('addons_total').textContent = '₱' + parseFloat(data.addons_total).toFixed(2);
                    } else {
                        document.getElementById('addons_row').classList.add('hidden');
                    }

                    if (parseFloat(data.discount) > 0) {
                        document.getElementById('discount_row').classList.remove('hidden');
                        document.getElementById('discount_total').textContent = '-₱' + parseFloat(data.discount).toFixed(2);
                        document.getElementById('promo_error').classList.add('hidden');
                        if (promoCode !== '') {
                            document.getElementById('promo_success').classList.remove('hidden');
                        }
                    } else {
                        document.getElementById('discount_row').classList.add('hidden');
                        document.getElementById('promo_success').classList.add('hidden');
                        if (promoCode !== '' && data.promo_error) {
                            document.getElementById('promo_error').innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> ' + data.promo_error;
                            document.getElementById('promo_error').classList.remove('hidden');
                        } else {
                            document.getElementById('promo_error').classList.add('hidden');
                        }
                    }

                    var grandTotal = parseFloat(data.total) + cleaning + service;
                    document.getElementById('total').textContent = '₱' + grandTotal.toFixed(2);
                } else {
                    console.error('Pricing Error:', data.error);
                }
            } catch (err) {
                console.error(err);
            } finally {
                isCalculating = false;
            }
        }

        // initial calc
        updateTotals();
    </script>
</body>

</html>