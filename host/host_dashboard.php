<?php
// BookIT Host Dashboard
// Main dashboard for hosts/managers to manage their units and bookings

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']); // Host or Manager can access

$host_id = $_SESSION['user_id'];
$branch_id = $_SESSION['branch_id'] ?? null;

// Get host information
$host_data = get_single_result("SELECT * FROM users WHERE user_id = ?", [$host_id]);

// Get total units managed by this host
$result = get_single_result("SELECT COUNT(*) as count FROM units WHERE host_id = ? AND is_available = 1", [$host_id]);
$total_units = $result ? (int)$result['count'] : 0;

// Get total reservations
$result = get_single_result("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = ?
", [$host_id]);
$reservations_stats = $result ? $result : ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0];

// Get total revenue
$result = get_single_result("
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END), 0) as completed_revenue
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = ?
", [$host_id]);
$revenue_stats = $result ? $result : ['total_revenue' => 0, 'completed_revenue' => 0];

// Get upcoming reservations (next 7 days)
$upcoming_reservations = get_multiple_results("
    SELECT r.*, u.unit_name, u.unit_number, u.unit_type, rs.full_name as renter_name
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN users rs ON r.user_id = rs.user_id
    WHERE u.host_id = $host_id 
    AND r.check_in_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND r.status IN ('confirmed', 'checked_in')
    ORDER BY r.check_in_date ASC
    LIMIT 5
");

// Get occupancy rate for all units
$result = get_single_result("
    SELECT 
        COUNT(*) as total_units,
        SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_units,
        SUM(CASE WHEN is_available = 0 THEN 1 ELSE 0 END) as occupied_units
    FROM units
    WHERE host_id = ?
", [$host_id]);
$occupancy_data = $result ? $result : ['total_units' => 0, 'available_units' => 0, 'occupied_units' => 0];
$occupancy_rate = $occupancy_data['total_units'] > 0 ? round(($occupancy_data['occupied_units'] / $occupancy_data['total_units']) * 100, 1) : 0;

// Get monthly revenue data for chart
$monthly_revenue = get_multiple_results("
    SELECT DATE_FORMAT(r.check_in_date, '%b') as month, SUM(r.total_amount) as revenue
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = $host_id AND YEAR(r.check_in_date) = YEAR(CURDATE()) AND r.payment_status = 'completed'
    GROUP BY MONTH(r.check_in_date) ORDER BY MONTH(r.check_in_date)
");

// Get last 12 months for secondary chart
$last_12_months_revenue = get_multiple_results("
    SELECT DATE_FORMAT(r.check_in_date, '%b %y') as month_label, SUM(r.total_amount) as revenue
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = $host_id AND r.check_in_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND r.payment_status = 'completed'
    GROUP BY DATE_FORMAT(r.check_in_date, '%Y-%m') ORDER BY DATE_FORMAT(r.check_in_date, '%Y-%m')
");

// Get yearly revenue
$yearly_revenue = get_multiple_results("
    SELECT YEAR(r.check_in_date) as year, SUM(r.total_amount) as revenue
    FROM reservations r INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = $host_id AND r.payment_status = 'completed'
    GROUP BY YEAR(r.check_in_date) ORDER BY year DESC
");

// Get host units
$host_units = get_multiple_results("
    SELECT u.*, (SELECT ui.image_path FROM unit_images ui WHERE ui.unit_id = u.unit_id ORDER BY ui.created_at DESC LIMIT 1) AS unit_image
    FROM units u WHERE u.host_id = $host_id ORDER BY u.created_at DESC LIMIT 8
");

// Get recent bookings
$recent_bookings = get_multiple_results("
    SELECT r.*, u.unit_name, u.unit_number, rs.full_name as renter_name
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN users rs ON r.user_id = rs.user_id
    WHERE u.host_id = $host_id ORDER BY r.created_at DESC LIMIT 10
");

$page_title = 'Host Dashboard';

// Extra CSS for Host Dashboard
ob_start();
?>
<style>
    /* Specific overrides for this page if any */
    .btn-refresh { background: #95a5a6; color: white; }
    .btn-add { background: var(--success); color: white; }
    .price-tag { background: #000; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
    .unit-card .unit-image { height: 160px; object-fit: cover; }
</style>
<?php
$extra_css = ob_get_clean();

// Shared Header
include '../templates/host_layout_header.php';
?>

<!-- Page Header -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h1 class="page-title"><i class="fas fa-chart-line pe-2"></i>Host Dashboard</h1>
        <p class="text-muted small mt-1">Hello, <?php echo htmlspecialchars($host_data['full_name']); ?>! Here's what's happening with your properties today.</p>
    </div>
    <div class="col-md-5 text-md-end d-flex gap-2 justify-content-md-end mt-3 mt-md-0">
        <button type="button" class="btn glass-panel text-muted border rounded-pill px-3 py-2 shadow-sm" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="window.location.href='unit_management.php'">
            <i class="fas fa-plus me-2"></i>Add property
        </button>
    </div>
</div>

<!-- Quick Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card-modern shadow-sm border-0">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary p-3 rounded-3" style="background-color: rgba(15, 23, 42, 0.1);">
                    <i class="fas fa-building fs-5"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-bold uppercase mb-0">Properties</h6>
                    <h3 class="fw-bold mb-0"><?php echo $total_units; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-modern shadow-sm border-0">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success p-3 rounded-3" style="background-color: rgba(16, 185, 129, 0.1);">
                    <i class="fas fa-calendar-check fs-5"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-bold uppercase mb-0">Total Bookings</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $reservations_stats['total'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-modern shadow-sm border-0">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning p-3 rounded-3" style="background-color: rgba(245, 158, 11, 0.1);">
                    <i class="fas fa-chart-line fs-5"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-bold uppercase mb-0">Occupancy</h6>
                    <h3 class="fw-bold mb-0"><?php echo $occupancy_rate; ?>%</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-modern shadow-sm border-0 bg-primary bg-opacity-10" style="border: 1px solid rgba(15, 23, 42, 0.05) !important;">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary text-white p-3 rounded-3 shadow-sm">
                    <i class="fas fa-wallet fs-5"></i>
                </div>
                <div>
                    <h6 class="text-primary small fw-bold uppercase mb-0">Total Share</h6>
                    <h3 class="fw-bold mb-0 text-primary">₱<?php echo number_format($revenue_stats['total_revenue'] ?? 0, 0); ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="row g-4 mb-4">
    <!-- Charts Section -->
    <div class="col-lg-8">
        <div class="card-modern shadow-sm border-0 h-100">
            <h5 class="fw-bold mb-4"><i class="fas fa-chart-pie text-primary me-2"></i>Status & Revenue Performance</h5>
            <div class="row g-4">
                <div class="col-md-5">
                    <div style="height: 250px;"><canvas id="occupancyChart"></canvas></div>
                </div>
                <div class="col-md-7 border-start border-light border-2">
                    <div style="height: 250px;"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Reservations -->
    <div class="col-lg-4">
        <div class="card-modern shadow-sm border-0 h-100 p-0 overflow-hidden">
            <div class="p-4 border-bottom bg-light">
                <h5 class="fw-bold mb-0 text-primary"><i class="fas fa-calendar-alt me-2"></i>Upcoming Arrivals</h5>
            </div>
            <div class="p-4" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($upcoming_reservations)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times d-block mb-2 fs-3 opacity-25"></i>
                        <p class="small mb-0">No upcoming arrivals in the next 7 days</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_reservations as $r): ?>
                        <div class="d-flex align-items-center justify-content-between p-3 mb-2 rounded-3 border bg-light bg-opacity-50 hover-shadow transition">
                            <div class="overflow-hidden">
                                <div class="fw-bold text-dark text-truncate small"><?php echo htmlspecialchars($r['renter_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <span class="badge bg-white text-primary border me-1"><?php echo $r['unit_number']; ?></span>
                                    <?php echo date('M d', strtotime($r['check_in_date'])); ?>
                                </div>
                            </div>
                            <button class="btn btn-sm glass-panel border" onclick="window.location.href='reservations.php?view=<?php echo $r['reservation_id']; ?>'">
                                <i class="fas fa-chevron-right text-primary"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Access Units -->
<div class="row align-items-center mb-4 mt-2">
    <div class="col-6">
        <h5 class="fw-bold mb-0"><i class="fas fa-building text-primary me-2"></i>My Properties</h5>
    </div>
    <div class="col-6 text-end">
        <a href="unit_management.php" class="btn btn-link text-primary fw-bold text-decoration-none small p-0">View All Properties <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
</div>

<div class="row g-4 mb-5">
    <?php if (empty($host_units)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="fas fa-building d-block mb-3 fs-1 opacity-25"></i>
            <p>You haven't added any units yet.</p>
            <a href="unit_management.php" class="btn btn-primary rounded-pill px-4">Add Your First Unit</a>
        </div>
    <?php else: ?>
        <?php foreach ($host_units as $unit): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card-modern border-0 shadow-sm p-0 overflow-hidden h-100 hover-lift">
                    <div class="position-relative" style="height: 180px;">
                        <?php if ($unit['unit_image']): ?>
                            <img src="<?php echo htmlspecialchars($unit['unit_image']); ?>" class="w-100 h-100" style="object-fit:cover;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center h-100"><i class="fas fa-image text-muted fa-2x opacity-25"></i></div>
                        <?php endif; ?>
                        <div class="position-absolute top-0 end-0 m-2">
                            <span class="badge rounded-pill <?php echo $unit['is_available'] ? 'bg-success' : 'bg-danger'; ?> shadow-sm">
                                <?php echo $unit['is_available'] ? 'Available' : 'Occupied'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold mb-0 text-truncate me-2"><?php echo htmlspecialchars($unit['unit_name']); ?></h6>
                            <span class="text-primary fw-bold">₱<?php echo number_format($unit['price_per_night'] ?: $unit['price_per_month'], 0); ?></span>
                        </div>
                        <div class="text-muted small mb-3">
                            <i class="fas fa-hashtag me-1"></i><?php echo $unit['unit_number']; ?> • <?php echo $unit['pricing_type']; ?>
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-sm glass-panel border text-primary rounded-pill" onclick="window.location.href='unit_management.php?edit=<?php echo $unit['unit_id']; ?>'">
                                <i class="fas fa-edit me-1"></i>Manage Unit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Recent Bookings -->
<div class="card-modern border-0 shadow-sm p-0 overflow-hidden mb-4">
    <div class="p-4 border-bottom d-flex align-items-center justify-content-between bg-light bg-opacity-50">
        <h5 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Recent Activity</h5>
        <a href="reservations.php" class="btn btn-sm glass-panel text-primary border rounded-pill px-3">History</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light bg-opacity-50 text-muted small fw-bold">
                <tr>
                    <th class="ps-4">UNIT</th>
                    <th>GUEST</th>
                    <th>STAY PERIOD</th>
                    <th>PAYMENT</th>
                    <th class="pe-4">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_bookings as $booking): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold"><?php echo htmlspecialchars($booking['unit_number']); ?></div>
                            <div class="text-muted small text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($booking['unit_name']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($booking['renter_name']); ?></td>
                        <td><small><?php echo date('M d', strtotime($booking['check_in_date'])); ?> - <?php echo date('M d', strtotime($booking['check_out_date'])); ?></small></td>
                        <td><strong class="text-dark">₱<?php echo number_format($booking['total_amount'], 2); ?></strong></td>
                        <td class="pe-4">
                            <span class="badge rounded-pill <?php echo match($booking['status']) { 'confirmed'=>'bg-success', 'pending'=>'bg-warning text-dark', 'cancelled'=>'bg-danger', default=>'bg-info text-white' }; ?> shadow-sm px-3">
                                <?php echo strtoupper($booking['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; if (empty($recent_bookings)) echo '<tr><td colspan="5" class="text-center py-5 text-muted">No recent activity.</td></tr>'; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Charts JS for Footer
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Occupancy Chart
    new Chart(document.getElementById('occupancyChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Occupied'],
            datasets: [{
                data: [<?php echo $occupancy_data['available_units']; ?>, <?php echo $occupancy_data['occupied_units']; ?>],
                backgroundColor: ['#27ae60', '#e74c3c'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    // Revenue Chart
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(fn($m) => "'".$m['month']."'", $monthly_revenue)); ?>],
            datasets: [{
                label: 'Revenue ₱',
                data: [<?php echo implode(',', array_map(fn($m) => $m['revenue'], $monthly_revenue)); ?>],
                borderColor: '#3498db',
                fill: true,
                backgroundColor: 'rgba(52,152,219,0.1)',
                tension: 0.3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
});
</script>
<?php
$extra_js = ob_get_clean();

// Shared Footer
include '../templates/host_layout_footer.php';
?>
