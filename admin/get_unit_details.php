<?php
// get_unit_details.php
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']);

if (!isset($_GET['unit_id'])) {
    echo '<div class="alert alert-danger">Unit ID is required</div>';
    exit;
}

$unit_id = (int)$_GET['unit_id'];

// Fetch unit details
$unit = get_single_result("
    SELECT u.*, b.branch_name, b.city as location, b.address
    FROM units u 
    LEFT JOIN branches b ON u.branch_id = b.branch_id 
    WHERE u.unit_id = ?
", [$unit_id]);

if (!$unit) {
    echo '<div class="alert alert-danger">Unit not found</div>';
    exit;
}

// Fetch performance metrics
$metrics = get_single_result("
    SELECT 
        COUNT(*) as total_bookings,
        COUNT(CASE WHEN status IN ('confirmed', 'checked_in') THEN 1 END) as active_bookings,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
        SUM(total_amount) as total_revenue
    FROM reservations 
    WHERE unit_id = ?
", [$unit_id]);

// Fetch average rating separately
$rating_data = get_single_result("SELECT AVG(rating) as avg_rating FROM reviews WHERE unit_id = ?", [$unit_id]);
$metrics['avg_rating'] = $rating_data ? $rating_data['avg_rating'] : null;

// Fetch recent bookings
$recent_bookings = get_multiple_results("
    SELECT r.reservation_id, r.check_in_date, r.check_out_date, r.status, r.total_amount, u.full_name as user_name
    FROM reservations r 
    LEFT JOIN users u ON r.user_id = u.user_id 
    WHERE r.unit_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 5
", [$unit_id]);

// Calculate metrics
$occupancy_rate = $metrics['total_bookings'] > 0 ? 
    min(round(($metrics['completed_bookings'] / $metrics['total_bookings']) * 100, 1), 100) : 0;

$avg_booking_value = $metrics['total_bookings'] > 0 ? 
    round($metrics['total_revenue'] / $metrics['total_bookings'], 2) : 0;

// Calculate additional stats
$total_nights = get_single_result("
    SELECT SUM(DATEDIFF(check_out_date, check_in_date)) as total_nights 
    FROM reservations 
    WHERE unit_id = ? AND status = 'completed'
", [$unit_id]);

$total_nights = $total_nights['total_nights'] ?? 0;
?>

<!-- Unit Header with Gradient -->
<div class="unit-header-gradient rounded-top p-4 mb-0 text-white">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-2">
                <h3 class="mb-0 me-3"><?= htmlspecialchars($unit['unit_number']) ?></h3>
                <span class="badge status-badge-lg <?= $unit['is_available'] ? 'bg-success' : 'bg-warning' ?>">
                    <i class="fas fa-circle me-1" style="font-size: 8px;"></i>
                    <?= $unit['is_available'] ? 'AVAILABLE' : 'OCCUPIED' ?>
                </span>
            </div>
            <p class="mb-1">
                <i class="fas fa-home me-2"></i><?= htmlspecialchars($unit['unit_type']) ?>
            </p>
            <p class="mb-0 opacity-75">
                <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($unit['branch_name']) ?> • <?= htmlspecialchars($unit['location'] ?? $unit['address'] ?? '') ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <div class="price-display">
                <?php if (($unit['pricing_type'] ?? 'nightly') === 'monthly'): ?>
                    <div class="price-amount">₱<?= number_format($unit['price_per_month'], 2) ?></div>
                    <div class="price-period">per month</div>
                <?php else: ?>
                    <div class="price-amount">₱<?= number_format($unit['price_per_night'], 2) ?></div>
                    <div class="price-period">per night</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-body-content p-4">
    <div class="row">
        <!-- Left Column - Unit Information -->
        <div class="col-lg-6">
            <!-- Unit Specifications Card -->
            <div class="info-card mb-4">
                <div class="card-header-custom">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Unit Specifications
                    </h5>
                </div>
                <div class="card-body-custom">
                    <div class="specs-grid">
                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="spec-info">
                                <div class="spec-label">Floor</div>
                                <div class="spec-value"><?= $unit['floor_number'] ?? 'N/A' ?></div>
                            </div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="spec-info">
                                <div class="spec-label">Capacity</div>
                                <div class="spec-value"><?= $unit['max_occupancy'] ?> persons</div>
                            </div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="spec-info">
                                <div class="spec-label">Security Deposit</div>
                                <div class="spec-value">₱<?= number_format($unit['security_deposit'], 2) ?></div>
                            </div>
                        </div>
                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="spec-info">
                                <div class="spec-label">Created</div>
                                <div class="spec-value"><?= date('M j, Y', strtotime($unit['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($unit['description'])): ?>
                    <div class="description-section mt-4">
                        <h6 class="section-title">
                            <i class="fas fa-file-alt me-2"></i>Description
                        </h6>
                        <p class="description-text"><?= nl2br(htmlspecialchars($unit['description'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="info-card">
                <div class="card-header-custom">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Quick Stats
                    </h5>
                </div>
                <div class="card-body-custom">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number text-primary"><?= $total_nights ?></div>
                            <div class="stat-label">Total Nights</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number text-success"><?= $metrics['active_bookings'] ?></div>
                            <div class="stat-label">Active Bookings</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number text-info"><?= $metrics['completed_bookings'] ?></div>
                            <div class="stat-label">Completed Stays</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number text-warning"><?= $metrics['total_bookings'] ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Performance & Bookings -->
        <div class="col-lg-6">
            <!-- Performance Metrics -->
            <div class="info-card mb-4">
                <div class="card-header-custom">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Performance Overview
                    </h5>
                </div>
                <div class="card-body-custom">
                    <div class="performance-metrics-grid">
                        <div class="metric-card revenue">
                            <div class="metric-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value">₱<?= number_format($metrics['total_revenue'] ?? 0, 2) ?></div>
                                <div class="metric-label">Total Revenue</div>
                            </div>
                        </div>
                        <div class="metric-card occupancy">
                            <div class="metric-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?= $occupancy_rate ?>%</div>
                                <div class="metric-label">Occupancy Rate</div>
                            </div>
                        </div>
                        <div class="metric-card value">
                            <div class="metric-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value">₱<?= number_format($avg_booking_value, 2) ?></div>
                                <div class="metric-label">Avg Booking Value</div>
                            </div>
                        </div>
                        <?php if ($metrics['avg_rating']): ?>
                        <div class="metric-card rating">
                            <div class="metric-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-value"><?= number_format($metrics['avg_rating'], 1) ?></div>
                                <div class="metric-label">Average Rating</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="info-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Bookings
                    </h5>
                    <span class="badge bg-primary"><?= count($recent_bookings) ?></span>
                </div>
                <div class="card-body-custom">
                    <?php if ($recent_bookings): ?>
                        <div class="bookings-list">
                            <?php foreach ($recent_bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="booking-header">
                                    <div class="guest-name">
                                        <i class="fas fa-user me-2"></i>
                                        <?= htmlspecialchars($booking['user_name'] ?: 'Guest') ?>
                                    </div>
                                    <span class="booking-status badge bg-<?= 
                                        $booking['status'] === 'confirmed' ? 'success' : 
                                        ($booking['status'] === 'checked_in' ? 'primary' : 
                                        ($booking['status'] === 'completed' ? 'secondary' : 'warning'))
                                    ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </div>
                                <div class="booking-dates">
                                    <small>
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('M j, Y', strtotime($booking['check_in_date'])) ?> - 
                                        <?= date('M j, Y', strtotime($booking['check_out_date'])) ?>
                                    </small>
                                </div>
                                <div class="booking-footer">
                                    <div class="booking-amount">
                                        ₱<?= number_format($booking['total_amount'], 2) ?>
                                    </div>
                                    <div class="booking-id">
                                        #B<?= str_pad($booking['reservation_id'], 4, '0', STR_PAD_LEFT) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No bookings yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Styles for View Modal */
.unit-header-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-bottom: 1px solid #dee2e6;
}

.status-badge-lg {
    font-size: 12px;
    padding: 8px 16px;
    border-radius: 20px;
}

.price-display {
    text-align: center;
}

.price-amount {
    font-size: 28px;
    font-weight: 800;
    line-height: 1;
}

.price-period {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 4px;
}

.modal-body-content {
    background: #f8f9fa;
}

.info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
}

.card-header-custom {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header-custom h5 {
    color: #2c3e50;
    font-weight: 600;
}

.card-body-custom {
    padding: 20px;
}

/* Specifications Grid */
.specs-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.spec-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.spec-item:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.spec-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.spec-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

.spec-value {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}

/* Description Section */
.section-title {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 12px;
}

.description-text {
    color: #6c757d;
    line-height: 1.6;
    margin: 0;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.stat-item {
    text-align: center;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

/* Performance Metrics */
.performance-metrics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.metric-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-radius: 12px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.metric-card.revenue { border-left: 4px solid #27ae60; }
.metric-card.occupancy { border-left: 4px solid #3498db; }
.metric-card.value { border-left: 4px solid #f39c12; }
.metric-card.rating { border-left: 4px solid #e74c3c; }

.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.metric-card.revenue .metric-icon { background: #27ae60; }
.metric-card.occupancy .metric-icon { background: #3498db; }
.metric-card.value .metric-icon { background: #f39c12; }
.metric-card.rating .metric-icon { background: #e74c3c; }

.metric-value {
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1;
    margin-bottom: 4px;
}

.metric-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

/* Bookings List */
.bookings-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.booking-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    border-left: 4px solid #3498db;
    transition: all 0.3s ease;
}

.booking-item:hover {
    background: #e9ecef;
    transform: translateX(4px);
}

.booking-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 8px;
}

.guest-name {
    font-weight: 600;
    color: #2c3e50;
    flex: 1;
}

.booking-dates {
    margin-bottom: 8px;
}

.booking-footer {
    display: flex;
    justify-content: between;
    align-items: center;
}

.booking-amount {
    font-weight: 700;
    color: #27ae60;
}

.booking-id {
    font-size: 11px;
    color: #6c757d;
    font-weight: 500;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

/* Responsive Design */
@media (max-width: 768px) {
    .specs-grid,
    .stats-grid,
    .performance-metrics-grid {
        grid-template-columns: 1fr;
    }
    
    .booking-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .price-amount {
        font-size: 24px;
    }
}
</style>