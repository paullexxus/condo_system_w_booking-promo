<?php
// Browse Units - High-End Real Estate Interface
// Dynamic property listing with split-screen layout, filtering, and map integration

include '../includes/public_session.php';
include '../includes/functions.php';
include '../includes/auth.php';

// Get all active branches
$branches = getAllBranches();

// Initialize variables
$selectedBranch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
$priceMin = isset($_GET['price_min']) ? (int)$_GET['price_min'] : null;
$priceMax = isset($_GET['price_max']) ? (int)$_GET['price_max'] : null;
$propertyType = isset($_GET['property_type']) ? $_GET['property_type'] : null;
$bedrooms = isset($_GET['bedrooms']) ? (int)$_GET['bedrooms'] : null;
$checkIn = isset($_GET['check_in']) ? trim((string)$_GET['check_in']) : '';
$checkOut = isset($_GET['check_out']) ? trim((string)$_GET['check_out']) : '';
$guests = isset($_GET['guests']) ? (int)$_GET['guests'] : null;

// Search Abuse Protection (Phase 4.3 Hardening)
$is_searching = !empty($_GET);
if ($is_searching) {
    $abuse_check = checkAbuse('search');
    if (!$abuse_check['allowed']) {
        die("<!DOCTYPE html><html lang='en'><body style='font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; background:#f8f9fa;'>
            <div style='text-align:center; padding:40px; background:white; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:500px;'>
                <h1 style='color:#e74c3c; margin-bottom:16px;'>⚠️ Slow Down!</h1>
                <p style='color:#636e72; line-height:1.6;'>" . htmlspecialchars($abuse_check['message']) . "</p>
                <div style='margin-top:24px;'><a href='index.php' style='color:#3498db; text-decoration:none; font-weight:bold;'>Return to Home</a></div>
            </div>
        </body></html>");
    }
}

// If search came from homepage without branch selection,
// auto-select first branch that currently has approved available units.
if (!$selectedBranch && $checkIn !== '' && $checkOut !== '') {
    $defaultBranch = get_single_result(
        "SELECT b.branch_id
         FROM branches b
         JOIN units u ON u.branch_id = b.branch_id
         WHERE b.is_active = 1
         AND u.is_available = 1
         AND u.approval_status = 'approved'
         ORDER BY b.branch_name ASC
         LIMIT 1"
    );

    if (!empty($defaultBranch['branch_id'])) {
        $qs = $_GET;
        $qs['branch_id'] = (int)$defaultBranch['branch_id'];
        header('Location: browse_units.php?' . http_build_query($qs));
        exit;
    }
}

$availableUnits = [];
$branchDetails = null;
$unitCount = 0;
$unitsForMap = [];

if ($selectedBranch) {
    // Get branch details
    $branchDetails = getBranchById($selectedBranch);
    $branchAmenities = getBranchAmenities($selectedBranch);
    $amenityNames = array_map(function($a) { return $a['amenity_name']; }, $branchAmenities);
    
    // Build dynamic SQL with filters
    $sql = "SELECT u.*, b.branch_name, b.address as branch_address, b.city as branch_city, b.latitude as branch_lat, b.longitude as branch_lng, b.host_id as branch_host_id
            FROM units u 
            JOIN branches b ON u.branch_id = b.branch_id 
            WHERE u.branch_id = ? AND u.is_available = 1
            AND u.approval_status = 'approved'";
    $params = [$selectedBranch];
    
    // Add filters
    if ($priceMin) {
        $sql .= " AND IF(u.pricing_type IN ('nightly', 'daily'), u.price_per_night * 30, u.price_per_month) >= ?";
        $params[] = $priceMin;
    }
    if ($priceMax) {
        $sql .= " AND IF(u.pricing_type IN ('nightly', 'daily'), u.price_per_night * 30, u.price_per_month) <= ?";
        $params[] = $priceMax;
    }
    if ($propertyType) {
        $sql .= " AND u.unit_type = ?";
        $params[] = $propertyType;
    }
    if ($bedrooms) {
        $sql .= " AND (u.num_beds = ? OR u.num_beds >= ?)";
        $params[] = $bedrooms;
        $params[] = $bedrooms;
    }
    if ($guests > 1) {
        $sql .= " AND u.max_occupancy >= ?";
        $params[] = $guests;
    }
    
    $sql .= " ORDER BY IF(u.pricing_type IN ('nightly', 'daily'), u.price_per_night * 30, u.price_per_month) ASC";
    $availableUnits = get_multiple_results($sql, $params);
    $unitCount = count($availableUnits);
    
    // Prepare units data for map - Grouped by location for Price Ranges
    if (!empty($availableUnits)) {
        $locationGroups = [];
        foreach ($availableUnits as $u) {
            $lat = !empty($u['latitude']) ? (float)$u['latitude'] : (float)($u['branch_lat'] ?? 0);
            $lng = !empty($u['longitude']) ? (float)$u['longitude'] : (float)($u['branch_lng'] ?? 0);
            
            if ($lat == 0 && $lng == 0) continue;
            
            $key = "{$lat}_{$lng}";
            if (!isset($locationGroups[$key])) {
                $locationGroups[$key] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'units' => [],
                    'minPrice' => PHP_INT_MAX,
                    'maxPrice' => 0
                ];
            }

            $ptype = $u['pricing_type'] ?? 'nightly';
            $unitPrice = in_array($ptype, ['nightly', 'daily']) ? (float)$u['price_per_night'] : (!empty($u['price_per_month']) ? round((float)$u['price_per_month'] / 30) : 0);
            
            $locationGroups[$key]['units'][] = $u['unit_id'];
            $locationGroups[$key]['minPrice'] = min($locationGroups[$key]['minPrice'], $unitPrice);
            $locationGroups[$key]['maxPrice'] = max($locationGroups[$key]['maxPrice'], $unitPrice);
            
            // Standard metadata for the first unit in the group (or the most important)
            if (!isset($locationGroups[$key]['title'])) {
                $unit_images = get_multiple_results("SELECT image_path FROM unit_images WHERE unit_id = ? ORDER BY created_at DESC LIMIT 1", [$u['unit_id']]);
                $locationGroups[$key]['title'] = (!empty($u['unit_name']) ? $u['unit_name'] : ($u['unit_number'] ?? 'Unit ' . $u['unit_id']));
                $locationGroups[$key]['image'] = !empty($unit_images) ? $unit_images[0]['image_path'] : null;
                $locationGroups[$key]['unit_id'] = $u['unit_id'];
                $locationGroups[$key]['host_id'] = !empty($u['host_id']) ? $u['host_id'] : $u['branch_host_id'];
            }
        }

        foreach ($locationGroups as $group) {
            $label = "₱" . number_format($group['minPrice']);
            if ($group['minPrice'] < $group['maxPrice']) {
                $label = "₱" . number_format($group['minPrice']) . " - ₱" . number_format($group['maxPrice']);
            }

            $unitsForMap[] = [
                'unit_id' => $group['unit_id'],
                'host_id' => $group['host_id'],
                'title' => $group['title'],
                'lat' => $group['lat'],
                'lng' => $group['lng'],
                'price' => $group['minPrice'], // Used for sorting/clustering
                'displayPrice' => $label,
                'image' => $group['image'],
                'count' => count($group['units'])
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Properties - BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <!-- Custom Map Styles -->
    <link rel="stylesheet" href="../assets/css/map-styles.css">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        body {
            background-color: #f8f9fa;
        }

        .soft-shadow {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .soft-shadow-hover:hover {
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }

        .property-card {
            border-radius: 18px;
            overflow: hidden;
            background: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 2px solid transparent;
        }

        .property-card:hover {
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12);
            transform: translateY(-6px);
        }

        .property-card.active {
            border-color: #e74c3c;
            box-shadow: 0 20px 50px rgba(231, 76, 60, 0.2);
        }

        .property-image {
            width: 100%;
            height: 240px;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .property-card:hover .property-image {
            transform: scale(1.08);
        }

        .price-badge {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        #map, #mapViewContainer {
            border-radius: 18px;
            overflow: hidden;
            height: 700px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .filter-pill {
            border-radius: 50px;
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .filter-pill:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }

        .filter-pill.active {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }

        .unit-card-skeleton {
            background: white;
            border-radius: 18px;
            padding: 16px;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @media (max-width: 1024px) {
            #mapContainer {
                height: 400px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Modern Navigation -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="index.php" class="flex items-center gap-3 text-2xl font-bold gradient-text hover:opacity-80 smooth-transition">
                    <img src="../assets/images/logo/bookit.png" alt="BookIT Logo" class="w-16 h-16 rounded-xl object-cover hover:shadow-lg smooth-transition">
                    BookIT
                </a>
                <div class="hidden md:flex items-center gap-8">
                    <a href="index.php" class="text-gray-600 hover:text-gray-900 transition">Home</a>
                    <a href="browse_units.php" class="text-orange-600 font-semibold border-b-2 border-orange-600">Browse</a>
                </div>
                <div class="flex items-center gap-4">
                    <?php if (isLoggedIn()): ?>
                        <div class="relative group">
                            <button class="flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                                <i class="fas fa-user-circle text-xl text-gray-600"></i>
                                <span class="text-sm text-gray-700"><?php echo htmlspecialchars(substr($_SESSION['fullname'], 0, 12)); ?></span>
                            </button>
                            <div class="absolute right-0 mt-0 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                                <a href="../renter/my_bookings.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-100 border-b">
                                    <i class="fas fa-calendar-check text-blue-500 mr-2"></i>My Bookings
                                </a>
                                <a href="../modules/notifications.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-100 border-b">
                                    <i class="fas fa-bell text-orange-500 mr-2"></i>Notifications
                                </a>
                                <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="px-6 py-2 bg-gradient-to-r from-orange-500 to-red-500 text-white rounded-lg hover:opacity-95 transition font-semibold">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="font-poppins text-4xl md:text-5xl font-bold text-white mb-2 flex items-center gap-3">
                <i class="fas fa-search"></i> Find Your Perfect Property
            </h1>
            <p class="text-blue-100 text-lg">Explore condos in great locations</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Branch/Location Selector -->
        <div class="bg-white rounded-2xl soft-shadow p-8 mb-8">
            <h2 class="font-poppins text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                <i class="fas fa-map-marker-alt text-orange-500"></i> Select Your Preferred Location
            </h2>
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <select name="branch_id" class="flex-1 px-6 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none">
                    <option value="">Choose a location...</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['branch_id']; ?>" <?php echo ($selectedBranch == $branch['branch_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['branch_name']); ?> - <?php echo htmlspecialchars($branch['city']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-8 py-3 bg-gradient-to-r from-orange-500 to-red-500 text-white font-semibold rounded-xl hover:opacity-95 transition whitespace-nowrap">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </form>
        </div>

        <!-- Split Screen Layout -->
        <?php if ($selectedBranch && $branchDetails): ?>
            <!-- Filter Bar -->
            <div class="bg-white rounded-2xl soft-shadow p-6 mb-8">
                <h3 class="font-poppins text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-orange-500"></i> Filters
                </h3>
                <form method="GET" id="filterForm" class="flex flex-wrap gap-4 items-center">
                    <input type="hidden" name="branch_id" value="<?php echo $selectedBranch; ?>">
                    
                    <!-- Price Slider -->
                    <div class="flex flex-col gap-1 w-48">
                        <label class="text-sm font-semibold text-gray-700 flex justify-between">
                            <span>Max Price (Monthly)</span>
                            <span id="priceDisplay" class="text-orange-500 font-bold">₱<?php echo number_format($priceMax ?: 100000); ?></span>
                        </label>
                        <input type="range" name="price_max" id="priceRange" min="5000" max="250000" step="5000" 
                               value="<?php echo $priceMax ?: 100000; ?>" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-orange-500" oninput="document.getElementById('priceDisplay').innerText = '₱' + parseInt(this.value).toLocaleString()">
                    </div>

                    <!-- Capacity (Guests) -->
                    <div class="flex flex-col gap-1 w-48">
                        <label class="text-sm font-semibold text-gray-700 flex justify-between">
                            <span>Capacity (Guests)</span>
                            <span id="guestDisplay" class="text-orange-500 font-bold"><?php echo ($guests && $guests > 1) ? $guests . '+' : 'Any'; ?></span>
                        </label>
                        <input type="range" name="guests" id="guestRange" min="1" max="15" step="1" 
                               value="<?php echo $guests ?: 1; ?>" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-orange-500" oninput="document.getElementById('guestDisplay').innerText = this.value == 1 ? 'Any' : this.value + '+'">
                    </div>

                    <!-- Property Type -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Type:</label>
                        <select name="property_type" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">All</option>
                            <option value="Studio" <?php echo ($propertyType == 'Studio') ? 'selected' : ''; ?>>Studio</option>
                            <option value="1 Bedroom" <?php echo ($propertyType == '1 Bedroom') ? 'selected' : ''; ?>>1 Bedroom</option>
                            <option value="2 Bedroom" <?php echo ($propertyType == '2 Bedroom') ? 'selected' : ''; ?>>2 Bedroom</option>
                            <option value="3 Bedroom" <?php echo ($propertyType == '3 Bedroom') ? 'selected' : ''; ?>>3+ Bedroom</option>
                        </select>
                    </div>

                    <!-- Bedrooms -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Beds:</label>
                        <select name="bedrooms" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">Any</option>
                            <option value="1" <?php echo ($bedrooms == 1) ? 'selected' : ''; ?>>1+</option>
                            <option value="2" <?php echo ($bedrooms == 2) ? 'selected' : ''; ?>>2+</option>
                            <option value="3" <?php echo ($bedrooms == 3) ? 'selected' : ''; ?>>3+</option>
                        </select>
                    </div>

                    <button type="submit" class="px-6 py-2 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition whitespace-nowrap">
                        Apply Filters
                    </button>
                    <a href="browse_units.php?branch_id=<?php echo $selectedBranch; ?>" class="px-6 py-2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition whitespace-nowrap">
                        Clear
                    </a>
                </form>
            </div>

            <!-- Quick Filters (Bubbles) -->
            <div class="mb-6 flex gap-3 overflow-x-auto pb-2 hide-scrollbar">
                <button type="button" onclick="applyQuickFilter('price_max', 10000)" class="px-4 py-2 border border-gray-300 rounded-full text-sm font-semibold hover:bg-orange-50 hover:border-orange-200 transition whitespace-nowrap text-gray-700"><i class="fas fa-tags text-orange-500 mr-2"></i>Under ₱10k</button>
                <button type="button" onclick="applyQuickFilter('property_type', 'Studio')" class="px-4 py-2 border border-gray-300 rounded-full text-sm font-semibold hover:bg-blue-50 hover:border-blue-200 transition whitespace-nowrap text-gray-700"><i class="fas fa-home text-blue-500 mr-2"></i>Studio</button>
                <button type="button" onclick="applyQuickFilter('bedrooms', 2)" class="px-4 py-2 border border-gray-300 rounded-full text-sm font-semibold hover:bg-green-50 hover:border-green-200 transition whitespace-nowrap text-gray-700"><i class="fas fa-bed text-green-500 mr-2"></i>2+ Beds</button>
                <button type="button" onclick="applyQuickFilter('guests', 4)" class="px-4 py-2 border border-gray-300 rounded-full text-sm font-semibold hover:bg-purple-50 hover:border-purple-200 transition whitespace-nowrap text-gray-700"><i class="fas fa-users text-purple-500 mr-2"></i>Family Size (4+)</button>
            </div>

            <!-- Results Count & View Toggle -->
            <div class="mb-4 flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl soft-shadow">
                <div>
                    <h3 class="font-poppins text-xl font-bold text-gray-900">
                        <i class="fas fa-home text-orange-500 mr-2"></i>Search results <span class="text-orange-600">(<?php echo $unitCount; ?>)</span>
                    </h3>
                    <p class="text-gray-600 text-sm mt-1">
                        Available properties in <strong><?php echo htmlspecialchars($branchDetails['branch_name']); ?></strong>
                    </p>
                </div>
                <div class="mt-4 md:mt-0 flex bg-gray-100 p-1 rounded-lg">
                    <button id="listViewBtn" class="px-4 py-2 bg-white text-gray-900 font-bold rounded-md shadow-sm transition-all" onclick="toggleView('list')"><i class="fas fa-th-large mr-2"></i>List View</button>
                    <button id="mapViewBtn" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-bold rounded-md transition-all" onclick="toggleView('map')"><i class="fas fa-map-marked-alt mr-2"></i>Map View</button>
                </div>
            </div>

            <!-- Full Width Layout: Cards & Map -->
            <div class="w-full relative">
                <!-- Property Cards Grid (List View) -->
                <div id="listViewContainer" class="w-full transition-opacity duration-300">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (!empty($availableUnits)): ?>
                            <?php foreach ($availableUnits as $unit):
                                $unit_images = get_multiple_results(
                                    "SELECT image_path FROM unit_images WHERE unit_id = ? ORDER BY created_at DESC LIMIT 1",
                                    [$unit['unit_id']]
                                );
                                $image_path = !empty($unit_images) ? $unit_images[0]['image_path'] : null;
                                $ptype = $unit['pricing_type'] ?? 'nightly';
                                $unitPricePerNight = in_array($ptype, ['nightly', 'daily']) ? $unit['price_per_night'] : (!empty($unit['price_per_month']) ? round($unit['price_per_month'] / 30) : null);
                            ?>
                            <div class="property-card soft-shadow cursor-pointer group transition-all" data-unit-id="<?php echo $unit['unit_id']; ?>" onclick="selectUnit(<?php echo $unit['unit_id']; ?>)">
                                <!-- Image -->
                                <div class="relative overflow-hidden">
                                    <?php if ($image_path): ?>
                                        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($unit['unit_number']); ?>" class="property-image w-full">
                                    <?php else: ?>
                                        <div class="w-full h-60 bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-image text-gray-400 text-3xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Price Badge -->
                                    <div class="absolute top-4 left-4 price-badge text-white px-4 py-2 rounded-lg font-bold text-lg">
                                        ₱<?php echo in_array($ptype, ['nightly', 'daily']) ? number_format((float)($unit['price_per_night'] ?? 0)) . '/night' : number_format((float)($unit['price_per_month'] ?? 0)) . '/month'; ?>
                                    </div>
                                    <div class="absolute top-4 right-4 bg-white/90 text-gray-800 px-3 py-1 rounded-lg font-bold text-xs shadow-sm uppercase tracking-wide">
                                        <?php echo in_array($ptype, ['nightly', 'daily']) ? 'Nightly Stay' : 'Monthly Rental'; ?>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="p-5">
                                    <h4 class="font-poppins font-bold text-gray-900 text-lg mb-1">
                                        <?php echo htmlspecialchars(!empty($unit['unit_name']) ? $unit['unit_name'] : $unit['unit_number']); ?>
                                    </h4>
                                    <p class="text-gray-600 text-sm mb-3 flex items-center gap-1">
                                        <i class="fas fa-map-marker-alt text-orange-500"></i>
                                        <?php echo htmlspecialchars(!empty($unit['unit_name']) ? (!empty($unit['unit_type']) ? $unit['unit_type'] . ' · ' . $unit['unit_number'] : $unit['unit_number']) : ($unit['unit_type'] ?? 'Unit')); ?>
                                    </p>

                                    <!-- Features -->
                                    <div class="flex gap-4 text-sm text-gray-600 border-t border-gray-100 pt-3">
                                        <?php if (!empty($unit['sqm'])): ?>
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-ruler-combined text-blue-500"></i>
                                                <span><?php echo $unit['sqm']; ?> sqm</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($unit['num_beds'])): ?>
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-bed text-green-500"></i>
                                                <span><?php echo $unit['num_beds']; ?> bed</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($unit['num_bathrooms'])): ?>
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-bath text-yellow-500"></i>
                                                <span><?php echo $unit['num_bathrooms']; ?> bath</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-full bg-white rounded-2xl soft-shadow p-12 text-center">
                                <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                                <h4 class="font-poppins font-bold text-gray-900 mb-2">No properties found</h4>
                                <p class="text-gray-600 text-lg">Try adjusting your filters to see more results</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Map View Container -->
                <div id="mapViewContainer" class="hidden w-full h-[640px] rounded-2xl overflow-hidden soft-shadow border border-gray-200 relative">
                    <div id="map" class="w-full h-full z-10 map-loading"></div>
                    
                    <!-- Search this area button -->
                    <button id="searchAreaBtn" onclick="searchThisArea()" class="search-this-area bg-white text-gray-800 soft-shadow px-4 py-2 rounded-full font-bold text-sm hover:bg-gray-50 flex items-center gap-2">
                        <i class="fas fa-sync-alt text-orange-500"></i> Search this area
                    </button>

                    <!-- Use My Location Button -->
                    <button onclick="useMyLocation()" class="absolute bottom-6 right-6 z-20 bg-white w-12 h-12 rounded-full shadow-lg flex items-center justify-center text-gray-700 hover:text-blue-500 transition-all active:scale-95" title="Use my current location">
                        <i class="fas fa-location-arrow"></i>
                    </button>
                    
                    <!-- Fallback UI Container -->
                    <div id="mapError" class="hidden absolute inset-0 bg-gray-50 flex flex-col items-center justify-center p-6 text-center z-30">
                        <i class="fas fa-exclamation-triangle text-red-400 text-5xl mb-4"></i>
                        <h3 class="font-poppins text-xl font-bold text-gray-900 mb-2">Map unavailable</h3>
                        <p class="text-gray-600 mb-6">We couldn't load the interactive map. You can still browse the properties in the list view.</p>
                        <button onclick="toggleView('list')" class="bg-primary-600 text-black px-6 py-2 rounded-xl font-bold hover:bg-primary-700 transition-colors">Switch to list view</button>
                    </div>
                </div>

                <!-- Modal Container -->
                <div>

                    <!-- Preview Modal -->
                    <div id="previewModal" class="fixed inset-0 z-[100] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 transition-opacity duration-300" onclick="if(event.target === this) closePreview()">
                        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto transform scale-95 transition-transform duration-300 relative" id="previewModalContent">
                            <div class="flex items-start justify-between mb-4">
                                <div class="w-full pr-4">
                                    <h3 class="font-poppins font-bold text-2xl text-gray-900" id="previewTitle"></h3>
                                    <p class="text-gray-600 text-sm flex flex-col gap-1 mt-1">
                                        <span id="previewType"></span>
                                        <span id="previewAddress" class="text-gray-500"><i class="fas fa-map-marker-alt text-orange-500 w-4"></i> </span>
                                    </p>
                                </div>
                                <button onclick="closePreview()" class="text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full w-8 h-8 flex items-center justify-center transition-colors flex-shrink-0">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <div id="previewImage" class="mb-5 rounded-xl overflow-hidden h-64 bg-gray-100 flex items-center justify-center shadow-inner"></div>

                            <div class="grid grid-cols-3 gap-4 mb-5 pb-5 border-b border-gray-100">
                                <div class="bg-blue-50/50 rounded-xl p-3 text-center transition-colors hover:bg-blue-50">
                                    <div class="text-2xl font-bold text-blue-600 mb-1" id="previewSqm">-</div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">sqm</p>
                                </div>
                                <div class="bg-green-50/50 rounded-xl p-3 text-center transition-colors hover:bg-green-50">
                                    <div class="text-2xl font-bold text-green-600 mb-1" id="previewBeds">-</div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Beds</p>
                                </div>
                                <div class="bg-yellow-50/50 rounded-xl p-3 text-center transition-colors hover:bg-yellow-50">
                                    <div class="text-2xl font-bold text-yellow-600 mb-1" id="previewBaths">-</div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Baths</p>
                                </div>
                            </div>

                            <p class="text-gray-700 text-sm mb-4" id="previewDesc"></p>

                            <div class="mb-5">
                                <h4 class="text-sm font-bold text-gray-900 mb-2">Amenities</h4>
                                <div id="previewAmenities" class="flex flex-wrap gap-2 text-xs"></div>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-500 font-medium">Nightly Rate</span>
                                        <span class="font-bold text-gray-900 text-lg" id="previewPrice">-</span>
                                    </div>
                                    <div class="h-px bg-gray-200 w-full"></div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-500 font-medium">Monthly Rate</span>
                                        <span class="font-bold text-gray-900 text-lg" id="previewMonthly">-</span>
                                    </div>
                                </div>
                            </div>

                            <button onclick="contactHost()" class="w-full py-3 bg-white text-orange-500 border-2 border-orange-500 font-bold rounded-xl hover:bg-orange-50 transition-all duration-300 flex items-center justify-center gap-2 mb-3">
                                <i class="fas fa-comment text-lg"></i> 
                                <span>Contact Host</span>
                            </button>
                            <button onclick="bookUnit()" class="w-full py-4 bg-gradient-to-r from-orange-500 to-red-500 text-white font-bold rounded-xl hover:shadow-lg hover:from-orange-600 hover:to-red-600 transition-all duration-300 flex items-center justify-center gap-2 transform hover:-translate-y-0.5">
                                <i class="fas fa-calendar-check text-lg"></i> 
                                <span>Proceed to Booking</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- No Branch Selected -->
            <div class="bg-white rounded-2xl soft-shadow p-12 text-center">
                <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                <h3 class="font-poppins text-2xl font-bold text-gray-900 mb-2">Select a location to get started</h3>
                <p class="text-gray-600">Choose a branch from the dropdown above to view available properties</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- Elite Map Configuration (Hardened with cache-busting) -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="../assets/js/map.js?v=<?php echo time(); ?>"></script>

    <script>
        const unitsData = <?php echo json_encode($unitsForMap, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
        let mapInitialized = false;
        let selectedUnitData = null;

        /**
         * [CORE] Hardened Map Initialization
         * Includes race-condition protection and graceful degradation fallbacks.
         */
        function initMap() {
            if (mapInitialized) return;
            
            const mapContainer = document.getElementById('map');
            const mapError = document.getElementById('mapError');

            if (typeof BookIT === 'undefined' || !BookIT.Map) {
                console.error("BookIT.Map Engine missing. Porting to fallback mode.");
                if (mapError) mapError.classList.remove('hidden');
                return;
            }

            BookIT.Map.loadScript(null, () => {
                const branchLat = <?php echo isset($branchDetails['latitude']) ? (float)$branchDetails['latitude'] : (defined('DEFAULT_LAT') ? DEFAULT_LAT : '14.5995'); ?>;
                const branchLng = <?php echo isset($branchDetails['longitude']) ? (float)$branchDetails['longitude'] : (defined('DEFAULT_LNG') ? DEFAULT_LNG : '120.9842'); ?>;

                try {
                    window.bookitMap = BookIT.Map.init('map', {
                        center: [branchLat, branchLng],
                        zoom: 13
                    });

                    if (window.bookitMap) {
                        BookIT.Map.addMarkers(unitsData, {
                            type: 'pricing',
                            cluster: true,
                            fitBounds: true,
                            onClick: (unit) => selectUnit(unit.unit_id)
                        });
                        mapInitialized = true;
                        if (mapError) mapError.classList.add('hidden');
                    } else {
                        throw new Error("Map initialization returned null.");
                    }
                } catch (e) {
                    console.error("Critical Mapping Error:", e);
                    if (mapError) mapError.classList.remove('hidden');
                }
            });
        }

        function initMapIfVisible() {
            if (!document.getElementById('mapViewContainer').classList.contains('hidden')) {
                initMap();
            }
        }

        function useMyLocation() {
            if (typeof BookIT !== 'undefined' && BookIT.Map) {
                BookIT.Map.getUserLocation((pos, error) => {
                    if (error) alert(error);
                });
            } else {
                console.warn("BookIT.Map not ready.");
            }
        }

        function searchThisArea() {
            const btn = document.getElementById('searchAreaBtn');
            if (btn) btn.style.display = 'none';
            alert("New search triggered for this area!");
            // In a real implementation: window.location.href = `?branch_id=<?php echo $selectedBranch; ?>&lat=${lat}&lng=${lng}...`;
        }



        function toggleView(viewType) {
            const listBtn = document.getElementById('listViewBtn');
            const mapBtn = document.getElementById('mapViewBtn');
            const listContainer = document.getElementById('listViewContainer');
            const mapContainer = document.getElementById('mapViewContainer');

            if (viewType === 'list') {
                listBtn.classList.replace('text-gray-600', 'text-gray-900');
                listBtn.classList.add('bg-white', 'shadow-sm');
                mapBtn.classList.replace('text-gray-900', 'text-gray-600');
                mapBtn.classList.remove('bg-white', 'shadow-sm');
                mapContainer.classList.add('hidden');
                listContainer.classList.remove('hidden');
            } else {
                mapBtn.classList.replace('text-gray-600', 'text-gray-900');
                mapBtn.classList.add('bg-white', 'shadow-sm');
                listBtn.classList.replace('text-gray-900', 'text-gray-600');
                listBtn.classList.remove('bg-white', 'shadow-sm');
                listContainer.classList.add('hidden');
                mapContainer.classList.remove('hidden');

                if (!mapInitialized) {
                    initMap();
                } else if (window.bookitMap) {
                    // Critical: Resize map when container becomes visible
                    setTimeout(() => window.bookitMap.invalidateSize(), 100);
                }
            }
        }

        function selectUnit(unitId) {
            const unit = unitsData.find(u => u.unit_id == unitId);
            if (!unit) return;
            selectedUnitData = unit;

            document.querySelectorAll('.property-card').forEach(el => {
                el.classList.remove('active');
                if (parseInt(el.dataset.unitId) === unitId) el.classList.add('active');
            });

            const modal = document.getElementById('previewModal');
            document.getElementById('previewTitle').textContent = unit.title;
            document.getElementById('previewType').innerHTML = '<i class="fas fa-home text-orange-500 w-4"></i> ' + (unit.type || 'Unit');
            
            document.getElementById('previewAddress').innerHTML = '<i class="fas fa-map-marker-alt text-orange-500 w-4"></i> ' + (unit.address ? (unit.address + (unit.city ? ', ' + unit.city : '')) : 'Address not available');
            
            const amenitiesContainer = document.getElementById('previewAmenities');
            amenitiesContainer.innerHTML = '';
            if (unit.amenities && unit.amenities.length > 0) {
                unit.amenities.forEach(amenity => {
                    const span = document.createElement('span');
                    span.className = 'px-2 py-1 bg-gray-100 text-gray-600 rounded-md border border-gray-200';
                    span.innerHTML = '<i class="fas fa-check text-green-500 mr-1"></i> ' + amenity;
                    amenitiesContainer.appendChild(span);
                });
            } else {
                amenitiesContainer.innerHTML = '<span class="text-gray-500 italic">No amenities specified</span>';
            }
            
            const imgContainer = document.getElementById('previewImage');
            if (unit.image) {
                imgContainer.innerHTML = `<img src="../uploads/unit_images/${unit.image}" class="w-full h-full object-cover rounded-xl shadow-inner" onerror="this.src='../assets/images/placeholder-unit.jpg'; this.onerror=null;">`;
            } else {
                imgContainer.innerHTML = `<div class="w-full h-full bg-gray-100 flex items-center justify-center rounded-xl"><i class="fas fa-image text-gray-400 text-4xl"></i></div>`;
            }
            
            document.getElementById('previewDesc').textContent = unit.description || '';
            document.getElementById('previewSqm').textContent = unit.sqm || '-';
            document.getElementById('previewBeds').textContent = unit.beds || '-';
            document.getElementById('previewBaths').textContent = unit.baths || '-';
            document.getElementById('previewPrice').textContent = unit.price ? '₱' + unit.price.toLocaleString() + '/ni' : '-';
            document.getElementById('previewMonthly').textContent = unit.monthlyPrice ? '₱' + unit.monthlyPrice.toLocaleString() + '/mo' : '-';
            
            modal.classList.remove('hidden');
            void modal.offsetWidth;
            modal.classList.remove('opacity-0');
            document.getElementById('previewModalContent').classList.remove('scale-95');
            document.getElementById('previewModalContent').classList.add('scale-100');
        }

        function closePreview() {
            const modal = document.getElementById('previewModal');
            modal.classList.add('opacity-0');
            document.getElementById('previewModalContent').classList.remove('scale-100', 'scale-95');
            document.getElementById('previewModalContent').classList.add('scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
            selectedUnitData = null;
            document.querySelectorAll('.property-card').forEach(el => el.classList.remove('active'));
        }

        function bookUnit() {
            if (!selectedUnitData) return;
            const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
            if (!isLoggedIn) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return;
            }
            if ('<?php echo $_SESSION['role'] ?? ''; ?>' !== 'renter') {
                alert('Only renter accounts can make reservations.');
                return;
            }
            window.location.href = '../renter/unit_detail.php?unit_id=' + selectedUnitData.unit_id + '&branch_id=<?php echo $selectedBranch ?? ''; ?>';
        }

        function contactHost() {
            if (!selectedUnitData) return;
            const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
            if (!isLoggedIn) {
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return;
            }
            if ('<?php echo $_SESSION['role'] ?? ''; ?>' !== 'renter') {
                alert('Only renter accounts can message hosts.');
                return;
            }
            if (!selectedUnitData.host_id) {
                alert('Host information not available for this unit.');
                return;
            }
            window.location.href = '../renter/messages.php?host_id=' + selectedUnitData.host_id;
        }

        function applyQuickFilter(key, value) {
            const form = document.getElementById('filterForm');
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = value;
                form.submit();
            }
        }
    </script>
</body>
</html>