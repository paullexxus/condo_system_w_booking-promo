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

// Host lookup
$hostName = 'Host';
if (!empty($unit['host_id'])) {
    $host = get_single_result("SELECT full_name FROM users WHERE user_id = ?", [$unit['host_id']]);
    if ($host) $hostName = $host['full_name'];
} elseif (!empty($branch['manager_name'])) {
    $hostName = $branch['manager_name'];
}

$images = get_multiple_results("SELECT image_path FROM unit_images WHERE unit_id = ? ORDER BY created_at DESC", [$unitId]);
$main_image = !empty($images) ? $images[0]['image_path'] : 'https://via.placeholder.com/900x600?text=Unit+' . urlencode($unit['unit_number']);

$amenities = getUnitAmenities($unitId);
$reviews = get_multiple_results("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.unit_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC", [$unitId]);
$unit_addons = get_multiple_results("SELECT * FROM unit_addons WHERE unit_id = ? AND is_active = 1", [$unitId]);
$blackouts = get_multiple_results("SELECT start_date, end_date FROM unit_blackouts WHERE unit_id = ?", [$unitId]);
$pricing_rules = get_multiple_results("SELECT * FROM unit_pricing_rules WHERE unit_id = ? AND is_active = 1", [$unitId]);

$pricing_type = $unit['pricing_type'] ?? 'nightly';
$nightly = in_array($pricing_type, ['nightly', 'daily']) ? (float)($unit['price_per_night'] ?? 0) : (float)($unit['price_per_month'] / 30);
$cleaningFee = (float)($unit['cleaning_fee'] ?? 0);
$serviceFee = (float)($unit['service_fee'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($unit['unit_name'] ?? $unit['unit_type'] . ' #' . $unit['unit_number']); ?> — BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .map-container { height: 350px; border-radius: 12px; z-index: 10; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    </style>
</head>
<body class="bg-slate-50">
    <!-- Navigation (Simplified for focus) -->
    <nav class="sticky top-0 bg-white border-b z-50 py-4"><div class="max-w-7xl mx-auto px-4 flex justify-between items-center"><a href="../public/index.php"><img src="../assets/images/logo/bookit.png" class="h-10"></a></div></nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($unit['unit_name'] ?? $unit['unit_type'] . ' #' . $unit['unit_number']); ?></h1>
                <p class="text-slate-600 mb-6 tracking-tight">
                    <i class="fas fa-map-marker-alt text-red-500 mr-1"></i> 
                    <?php echo htmlspecialchars($unit['street_address'] ? $unit['street_address'] . ', ' : ''); ?>
                    <?php echo htmlspecialchars($branch['branch_name'] . ', ' . $branch['city']); ?>
                </p>
                
                <img id="mainPhoto" src="<?php echo htmlspecialchars($main_image); ?>" class="w-full h-[450px] object-cover rounded-2xl shadow-lg mb-8">
                
                <div class="mb-10">
                    <h2 class="text-xl font-bold mb-3">About this unit</h2>
                    <p class="text-slate-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($unit['description'] ?? 'No description.')); ?></p>
                </div>

                <div class="mb-10">
                    <h2 class="text-xl font-bold mb-4">Location</h2>
                    <div id="unitMap" class="map-container shadow-sm border mb-4"></div>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $unit['latitude'].','.$unit['longitude']; ?>" target="_blank" class="inline-block px-6 py-2 border border-slate-300 rounded-full text-slate-700 hover:bg-slate-50 transition"><i class="fas fa-directions mr-2"></i> Get Directions</a>
                </div>

                <div class="mb-10">
                    <h2 class="text-xl font-bold mb-4">Reviews (<?php echo count($reviews); ?>)</h2>
                    <?php foreach($reviews as $rev): ?>
                        <div class="mb-4 p-4 bg-white rounded-xl shadow-sm border border-slate-100">
                            <div class="flex items-center gap-2 mb-2"><div class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center font-bold text-xs"><?php echo substr($rev['full_name'],0,1); ?></div><span class="font-semibold text-sm"><?php echo htmlspecialchars($rev['full_name']); ?></span></div>
                            <p class="text-sm text-slate-700"><?php echo htmlspecialchars($rev['comment']); ?></p>
                        </div>
                    <?php endforeach; if(empty($reviews)) echo '<p class="text-slate-400 italic">No reviews yet.</p>'; ?>
                </div>
            </div>

            <!-- Booking Column -->
            <div class="lg:col-span-1">
                <div class="sticky top-24 bg-white p-6 rounded-2xl border shadow-xl">
                    <div class="flex justify-between items-end mb-6">
                        <div><span class="text-2xl font-bold">₱<?php echo number_format($nightly, 2); ?></span> <span class="text-slate-500">/ night</span></div>
                        <div class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded font-bold"><?php echo $unit['max_occupancy']; ?> Max Guests</div>
                    </div>

                    <form action="booking_summary.php" method="POST" class="space-y-4">
                        <input type="hidden" name="unit_id" value="<?php echo $unitId; ?>">
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Check-in</label><input type="text" id="checkin" name="check_in_date" class="w-full p-2 border rounded-lg text-sm" value="<?php echo $checkIn; ?>" required placeholder="Select date"></div>
                            <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Check-out</label><input type="text" id="checkout" name="check_out_date" class="w-full p-2 border rounded-lg text-sm" value="<?php echo $checkOut; ?>" required placeholder="Select date"></div>
                        </div>

                        <?php if (!empty($unit_addons)): ?>
                        <div class="py-2">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Available Add-ons <span class="text-blue-500">(Requires Approval)</span></label>
                            <div class="space-y-2">
                                <?php foreach($unit_addons as $addon): ?>
                                <label class="flex items-center justify-between p-2 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer transition">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="addon_ids[]" value="<?php echo $addon['addon_id']; ?>" class="addon-checkbox w-4 h-4 text-blue-600">
                                        <div>
                                            <span class="text-xs text-slate-700 font-medium block"><?php echo htmlspecialchars($addon['name']); ?></span>
                                            <span class="text-[9px] text-orange-500 font-bold uppercase"><i class="fas fa-user-shield mr-1"></i> Admin Approved Only</span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-500">₱<?php echo number_format($addon['price'], 0); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="py-3 border-t border-b border-dashed space-y-2">
                            <div class="flex justify-between text-sm"><span>Stay Duration</span> <span id="nights-count" class="text-slate-500">0 nights</span></div>
                            <div class="flex justify-between text-sm"><span>Accommodation</span> <span id="subtotal" class="font-medium">₱0.00</span></div>
                            <div id="addons-summary-row" class="hidden flex justify-between text-sm"><span>Add-ons</span> <span id="addons-total" class="font-medium text-green-600">+₱0.00</span></div>
                            <div class="flex justify-between text-sm"><span>Cleaning & Service</span> <span id="fees" class="text-slate-500">₱<?php echo number_format($cleaningFee + $serviceFee, 2); ?></span></div>
                            <div id="discount-row" class="hidden flex justify-between text-sm font-semibold text-green-600"><span>Discount</span> <span id="discount-total">-₱0.00</span></div>
                            <div class="flex justify-between font-bold pt-2 text-xl border-t border-slate-100"><span>Total</span> <span id="total" class="text-blue-600">₱0.00</span></div>
                        </div>
                        <button type="submit" class="w-full py-4 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">Reserve Unit</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Modern Mapping Infrastructure -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../assets/js/map.js?v=<?php echo time(); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Standardized BookIT Map Integration
        const lat = <?php echo (float)($unit['latitude'] ?: ($branch['latitude'] ?: 14.5995)); ?>;
        const lng = <?php echo (float)($unit['longitude'] ?: ($branch['longitude'] ?: 120.9842)); ?>;
        const unitName = <?php echo json_encode($unit['unit_name'] ?: 'Property Location'); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof BookIT !== 'undefined' && BookIT.Map) {
                BookIT.Map.loadScript(null, () => {
                    const map = BookIT.Map.init('unitMap', {
                        center: [lat, lng],
                        zoom: 15,
                        scrollWheelZoom: false
                    });

                    if (map) {
                        BookIT.Map.addMarkers([{
                            lat: lat,
                            lng: lng,
                            title: unitName,
                            unit_name: unitName
                        }], {
                            fitBounds: true
                        });
                    }
                });
            }
        });

        // Calendar & Calculation
        // authoritative Pricing Logic
        const fixedFees = <?php echo $cleaningFee + $serviceFee; ?>;
        const blackouts = <?php echo json_encode($blackouts); ?>;
        const blackoutRanges = blackouts.map(b => ({ from: b.start_date, to: b.end_date }));

        const updatePrices = async () => {
            const inDate = document.getElementById('checkin').value;
            const outDate = document.getElementById('checkout').value;
            const selectedAddons = Array.from(document.querySelectorAll('.addon-checkbox:checked')).map(cb => cb.value);

            if(inDate && outDate) {
                try {
                    const response = await fetch('../ajax/calculate_price.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            unit_id: <?php echo $unitId; ?>,
                            check_in_date: inDate,
                            check_out_date: outDate,
                            addon_ids: selectedAddons
                        })
                    });
                    const data = await response.json();

                    if(data.success) {
                        document.getElementById('nights-count').textContent = data.nights + ' night' + (data.nights > 1 ? 's' : '');
                        document.getElementById('subtotal').textContent = '₱' + data.subtotal.toLocaleString(undefined, {minimumFractionDigits: 2});
                        
                        if(data.addons_total > 0) {
                            document.getElementById('addons-summary-row').classList.remove('hidden');
                            document.getElementById('addons-total').textContent = '+₱' + data.addons_total.toLocaleString(undefined, {minimumFractionDigits: 2});
                        } else {
                            document.getElementById('addons-summary-row').classList.add('hidden');
                        }

                        if(data.discount > 0) {
                            document.getElementById('discount-row').classList.remove('hidden');
                            document.getElementById('discount-total').textContent = '-₱' + data.discount.toLocaleString(undefined, {minimumFractionDigits: 2});
                        } else {
                            document.getElementById('discount-row').classList.add('hidden');
                        }

                        document.getElementById('total').textContent = '₱' + data.total.toLocaleString(undefined, {minimumFractionDigits: 2});
                    }
                } catch (e) { console.error("Price update failed", e); }
            }
        };

        // Initialize Callbacks
        document.querySelectorAll('.addon-checkbox').forEach(cb => cb.addEventListener('change', updatePrices));

        const fpIn = flatpickr("#checkin", { 
            minDate: "today", 
            disable: blackoutRanges,
            onChange: (sd) => { fpOut.set('minDate', sd[0]); updatePrices(); } 
        });
        const fpOut = flatpickr("#checkout", { 
            minDate: "today", 
            disable: blackoutRanges,
            onChange: updatePrices 
        });
        
        if (document.getElementById('checkin').value) updatePrices();
    </script>
</body>
</html>