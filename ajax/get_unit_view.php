<?php
// Get unit view details (host dashboard-style layout)
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

if (!$unit_id) {
    echo '<div class="alert alert-danger">Invalid unit ID</div>';
    exit;
}

$unit = get_single_result("
    SELECT u.*, b.branch_name 
    FROM units u
    INNER JOIN branches b ON u.branch_id = b.branch_id
    WHERE u.unit_id = ? AND u.host_id = ?
", [$unit_id, $host_id]);

if (!$unit) {
    echo '<div class="alert alert-danger">Unit not found</div>';
    exit;
}

$images = get_multiple_results("SELECT * FROM unit_images WHERE unit_id = ?", [$unit_id]);
$unit_amenities = getUnitAmenities($unit_id);
$bookings = get_single_result("SELECT COUNT(*) as total FROM reservations WHERE unit_id = ?", [$unit_id]);
$completed_stays = get_single_result(
    "SELECT COUNT(*) as total FROM reservations WHERE unit_id = ? AND status = 'completed'",
    [$unit_id]
);
$active_stays = get_single_result(
    "SELECT COUNT(*) as c FROM reservations WHERE unit_id = ? AND status IN ('confirmed', 'checked_in')",
    [$unit_id]
);

$is_occupied = ((int) ($active_stays['c'] ?? 0)) > 0;
$is_listed_available = !empty($unit['is_available']);

$pricing_monthly = (($unit['pricing_type'] ?? 'nightly') === 'monthly');
$price_period_label = $pricing_monthly ? 'Monthly rate' : 'Nightly rate';
$price_highlight = $pricing_monthly
    ? '₱' . number_format((float) ($unit['price_per_month'] ?? 0)) . ' <span class="uvm-dash-price-period">/ month</span>'
    : '₱' . number_format((float) ($unit['price_per_night'] ?? 0)) . ' <span class="uvm-dash-price-period">/ night</span>';

$city = $unit['city'] ?? '';
$branch_name = $unit['branch_name'] ?? '';
$unit_title = htmlspecialchars($unit['unit_name'] ?? 'Unit');
$unit_name_js = json_encode($unit['unit_name'] ?? 'Unit', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$lat = isset($unit['latitude']) ? (float) $unit['latitude'] : null;
$lng = isset($unit['longitude']) ? (float) $unit['longitude'] : null;
$has_coords = $lat !== null && $lng !== null && $lat != 0 && $lng != 0;

if (!function_exists('bookit_uvm_amenity_visuals')) {
    /**
     * @return array{0:string,1:string} [fontawesome class, emoji]
     */
    function bookit_uvm_amenity_visuals(string $name): array
    {
        $n = strtolower(trim($name));
        $map = [
            ['wifi', 'fas fa-wifi', '📶'],
            ['air conditioning', 'fas fa-snowflake', '❄️'],
            ['swimming pool', 'fas fa-swimming-pool', '🏊'],
            ['pool', 'fas fa-swimming-pool', '🏊'],
            ['parking', 'fas fa-car', '🚗'],
            ['tv', 'fas fa-tv', '📺'],
            ['kitchen', 'fas fa-utensils', '🍳'],
            ['gym', 'fas fa-dumbbell', '💪'],
            ['washing machine', 'fas fa-tshirt', '🧺'],
        ];
        foreach ($map as $row) {
            if ($n === $row[0] || strpos($n, $row[0]) !== false) {
                return [$row[1], $row[2]];
            }
        }
        return ['fas fa-check-circle', '✓'];
    }
}
?>

<div class="unit-view-modal uvm-dash" data-unit-id="<?php echo (int) $unit_id; ?>">
    <div class="uvm-dash-top">
        <div class="uvm-dash-gallery uvm-dash-card uvm-dash-card--flush">
            <?php if (!empty($images)): ?>
                <?php $hero = $images[0]; ?>
                <img class="uvm-dash-gallery-hero" src="<?php echo htmlspecialchars($hero['image_path']); ?>"
                     alt="<?php echo $unit_title; ?>">
                <?php if (count($images) > 1): ?>
                    <div class="uvm-dash-gallery-thumbs">
                        <?php foreach ($images as $img): ?>
                            <button type="button" class="uvm-dash-thumb" aria-label="Show photo">
                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt=""
                                     data-full="<?php echo htmlspecialchars($img['image_path']); ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="uvm-dash-gallery-empty">
                    <i class="fas fa-images"></i>
                    <p>No photos yet</p>
                    <span class="small text-muted">Add images when you edit this unit.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="uvm-dash-side">
            <div class="uvm-dash-hero uvm-dash-card">
                <h2 class="uvm-dash-title"><?php echo $unit_title; ?></h2>
                <p class="uvm-dash-meta">
                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                    <?php echo htmlspecialchars(trim($branch_name . ($city !== '' ? ', ' . $city : ''))); ?>
                </p>
                <div class="uvm-dash-badges">
                    <?php if ($is_occupied): ?>
                        <span class="uvm-dash-badge uvm-dash-badge--occupied"><i class="fas fa-door-open me-1"></i>Occupied</span>
                    <?php elseif ($is_listed_available): ?>
                        <span class="uvm-dash-badge uvm-dash-badge--live"><i class="fas fa-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i>Available</span>
                    <?php else: ?>
                        <span class="uvm-dash-badge uvm-dash-badge--paused"><i class="fas fa-pause-circle me-1"></i>Unavailable</span>
                    <?php endif; ?>
                </div>
                <div class="uvm-dash-actions" id="uvm-actions-<?php echo (int) $unit_id; ?>">
                    <button type="button" class="btn btn-primary btn-sm uvm-dash-btn" onclick="fromUnitViewOpenEdit(<?php echo (int) $unit_id; ?>)">
                        <i class="fas fa-edit"></i> Edit unit
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm uvm-dash-btn" onclick="fromUnitViewOpenDelete(<?php echo (int) $unit_id; ?>, <?php echo $unit_name_js; ?>)">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </div>
            </div>

            <div class="uvm-dash-price uvm-dash-card uvm-dash-price-card">
                <div class="uvm-dash-price-label"><?php echo htmlspecialchars($price_period_label); ?></div>
                <div class="uvm-dash-price-value"><?php echo $price_highlight; ?></div>
            </div>

            <div class="uvm-dash-stats">
                <div class="uvm-dash-stat">
                    <span class="uvm-dash-stat-ic" aria-hidden="true">👥</span>
                    <span class="uvm-dash-stat-label">Capacity</span>
                    <span class="uvm-dash-stat-val"><?php echo (int) ($unit['max_occupancy'] ?? 0); ?> guests</span>
                </div>
                <div class="uvm-dash-stat">
                    <span class="uvm-dash-stat-ic" aria-hidden="true">🏢</span>
                    <span class="uvm-dash-stat-label">Unit number</span>
                    <span class="uvm-dash-stat-val"><?php echo htmlspecialchars($unit['unit_number'] ?? '—'); ?></span>
                </div>
                <div class="uvm-dash-stat">
                    <span class="uvm-dash-stat-ic" aria-hidden="true">📍</span>
                    <span class="uvm-dash-stat-label">Location</span>
                    <span class="uvm-dash-stat-val"><?php echo htmlspecialchars($city !== '' ? $city : '—'); ?></span>
                </div>
                <div class="uvm-dash-stat">
                    <span class="uvm-dash-stat-ic" aria-hidden="true">📊</span>
                    <span class="uvm-dash-stat-label">Bookings</span>
                    <span class="uvm-dash-stat-val"><?php echo (int) ($bookings['total'] ?? 0); ?> total<?php if ((int) ($completed_stays['total'] ?? 0) > 0): ?> · <?php echo (int) $completed_stays['total']; ?> completed<?php endif; ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($unit['description'])): ?>
    <div class="uvm-dash-card uvm-dash-desc">
        <div class="uvm-dash-card-title"><i class="fas fa-align-left text-primary"></i> About this listing</div>
        <p class="uvm-dash-desc-text"><?php echo nl2br(htmlspecialchars($unit['description'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="uvm-dash-card">
        <div class="uvm-dash-card-title"><i class="fas fa-clipboard-list text-primary"></i> Amenities</div>
        <?php if (!empty($unit_amenities)): ?>
            <div class="uvm-dash-amenity-grid">
                <?php foreach ($unit_amenities as $a):
                    [, $emoji] = bookit_uvm_amenity_visuals((string) $a['amenity_name']);
                    ?>
                    <div class="uvm-dash-amenity-item">
                        <span class="uvm-dash-amenity-emoji" aria-hidden="true"><?php echo htmlspecialchars($emoji); ?></span>
                        <span class="uvm-dash-amenity-name"><?php echo htmlspecialchars($a['amenity_name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0 small">No amenities linked to this unit yet.</p>
        <?php endif; ?>
    </div>

    <?php if ($has_coords): ?>
    <div class="uvm-dash-card">
        <div class="uvm-dash-card-title d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span><i class="fas fa-map text-primary"></i> Map</span>
            <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
               href="https://www.openstreetmap.org/?mlat=<?php echo (string)$lat; ?>&mlon=<?php echo (string)$lng; ?>#map=16/<?php echo (string)$lat; ?>/<?php echo (string)$lng; ?>">
                <i class="fas fa-external-link-alt"></i> View on OpenStreetMap
            </a>
        </div>
        <div id="uvm-map-<?php echo (int) $unit_id; ?>"
             class="uvm-dash-map"
             data-lat="<?php echo htmlspecialchars((string) $lat); ?>"
             data-lng="<?php echo htmlspecialchars((string) $lng); ?>"></div>
        <div class="uvm-dash-coords-foot small text-muted mt-2">
            <code class="uvm-dash-coord-pill"><?php echo htmlspecialchars((string) $lat); ?></code>
            <code class="uvm-dash-coord-pill"><?php echo htmlspecialchars((string) $lng); ?></code>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var root = document.querySelector('.uvm-dash[data-unit-id="<?php echo (int) $unit_id; ?>"]');
    if (!root) return;
    var hero = root.querySelector('.uvm-dash-gallery-hero');
    root.querySelectorAll('.uvm-dash-thumb img').forEach(function (img) {
        img.closest('.uvm-dash-thumb').addEventListener('click', function () {
            if (hero && img.dataset.full) hero.src = img.dataset.full;
        });
    });
})();
</script>
