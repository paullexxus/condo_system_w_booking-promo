<?php
// modules/view_unit.php
include '../../includes/session.php';
include '../../includes/functions.php';
include_once '../../config/db.php';
checkRole(['admin']);

$unit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$unit_id) {
    header('Location: ../admin/unit_management.php');
    exit;
}

// Fetch unit details
$unit = get_single_result("
    SELECT u.*, b.branch_name, b.city as location 
    FROM units u 
    LEFT JOIN branches b ON u.branch_id = b.branch_id 
    WHERE u.unit_id = ?
", [$unit_id]);

if (!$unit) {
    header('Location: ../admin/unit_management.php?error=Unit not found');
    exit;
}

// Fetch unit performance metrics
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
    SELECT r.*, u.full_name as user_name, u.email 
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Details - BookIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
            --dark-color: #2c3e50;
        }
        
        .unit-header {
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .unit-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .metric-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        .badge-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .status-available { background: #d4edda; color: #155724; }
        .status-occupied { background: #fff3cd; color: #856404; }
        
        .amenity-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Unit Header -->
    <div class="unit-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <a href="<?php echo SITE_URL; ?>/admin/unit_management.php" class="back-btn mb-3 d-inline-block">
                        <i class="fas fa-arrow-left"></i> Back to Units
                    </a>
                    <h1 class="display-5 fw-bold"><?= htmlspecialchars($unit['unit_number']) ?></h1>
                    <p class="lead mb-0"><?= htmlspecialchars($unit['unit_type']) ?> • <?= htmlspecialchars($unit['branch_name']) ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge-status status-<?= $unit['is_available'] ? 'available' : 'occupied' ?>">
                        <i class="fas fa-circle me-1" style="font-size: 8px;"></i>
                        <?= $unit['is_available'] ? 'AVAILABLE' : 'OCCUPIED' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Left Column - Unit Details -->
            <div class="col-lg-8">
                <!-- Basic Information Card -->
                <div class="card unit-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Unit Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Unit Number:</strong></td>
                                        <td><?= htmlspecialchars($unit['unit_number']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Unit Type:</strong></td>
                                        <td><?= htmlspecialchars($unit['unit_type']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Branch:</strong></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?= htmlspecialchars($unit['branch_name']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Location:</strong></td>
                                        <td><?= htmlspecialchars($unit['location'] ?? 'N/A') ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Floor:</strong></td>
                                        <td><?= $unit['floor_number'] ?? 'N/A' ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Max Occupancy:</strong></td>
                                        <td><?= $unit['max_occupancy'] ?> persons</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Price per Night:</strong></td>
                                        <td class="text-success fw-bold">₱<?= number_format(($unit['monthly_rate'] ?? 0) / 30, 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Security Deposit:</strong></td>
                                        <td>₱<?= number_format($unit['security_deposit'], 2) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($unit['description'])): ?>
                        <div class="mt-3">
                            <strong>Description:</strong>
                            <p class="text-muted mt-2"><?= nl2br(htmlspecialchars($unit['description'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance Metrics Card -->
                <div class="card unit-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line text-success me-2"></i>
                            Performance Metrics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value text-primary"><?= $metrics['total_bookings'] ?></div>
                                    <div class="metric-label">Total Bookings</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value text-success"><?= $occupancy_rate ?>%</div>
                                    <div class="metric-label">Occupancy Rate</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value text-warning">₱<?= number_format($avg_booking_value, 2) ?></div>
                                    <div class="metric-label">Avg Booking Value</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value text-info">₱<?= number_format($metrics['total_revenue'] ?? 0, 2) ?></div>
                                    <div class="metric-label">Total Revenue</div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($metrics['avg_rating']): ?>
                        <div class="row text-center mt-3">
                            <div class="col-12">
                                <div class="metric-card">
                                    <div class="metric-value text-warning">
                                        <i class="fas fa-star"></i> <?= number_format($metrics['avg_rating'], 1) ?>/5.0
                                    </div>
                                    <div class="metric-label">Average Rating</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Bookings Card -->
                <div class="card unit-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history text-info me-2"></i>
                            Recent Bookings
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_bookings): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Guest</th>
                                            <th>Check-in</th>
                                            <th>Check-out</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td>#B<?= str_pad($booking['reservation_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($booking['check_in_date'])) ?></td>
                                            <td><?= date('M j, Y', strtotime($booking['check_out_date'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $booking['status'] === 'confirmed' ? 'success' : 
                                                    ($booking['status'] === 'checked_in' ? 'primary' : 
                                                    ($booking['status'] === 'completed' ? 'secondary' : 'warning'))
                                                ?>">
                                                    <?= ucfirst($booking['status']) ?>
                                                </span>
                                            </td>
                                            <td>₱<?= number_format($booking['total_amount'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No bookings yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Actions & Quick Info -->
            <div class="col-lg-4">
                <!-- Quick Actions Card -->
                <div class="card unit-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?php echo SITE_URL; ?>/admin/unit_management.php?edit=<?= $unit_id ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Edit Unit
                            </a>
                            <button class="btn btn-info" onclick="changeStatus(<?= $unit_id ?>)">
                                <i class="fas fa-sync-alt me-2"></i>Change Status
                            </button>
                            <a href="<?php echo SITE_URL; ?>/admin/reservations.php?unit=<?= $unit_id ?>" class="btn btn-primary">
                                <i class="fas fa-calendar-plus me-2"></i>Create Booking
                            </a>
                            <button class="btn btn-danger" onclick="confirmDelete(<?= $unit_id ?>)">
                                <i class="fas fa-trash me-2"></i>Delete Unit
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Unit Specifications Card -->
                <div class="card unit-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-ruler-combined text-secondary me-2"></i>
                            Specifications
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="amenity-item">
                            <i class="fas fa-users text-primary"></i>
                            <span>Capacity: <strong><?= $unit['max_occupancy'] ?> persons</strong></span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-layer-group text-success"></i>
                            <span>Floor: <strong><?= $unit['floor_number'] ?? 'N/A' ?></strong></span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-home text-info"></i>
                            <span>Type: <strong><?= htmlspecialchars($unit['unit_type']) ?></strong></span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-calendar-alt text-warning"></i>
                            <span>Created: <strong><?= date('M j, Y', strtotime($unit['created_at'])) ?></strong></span>
                        </div>
                    </div>
                </div>

                <!-- Revenue Summary Card -->
                <div class="card unit-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-money-bill-wave text-success me-2"></i>
                            Revenue Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <h3 class="text-success">₱<?= number_format($metrics['total_revenue'] ?? 0, 2) ?></h3>
                            <p class="text-muted">Total Revenue Generated</p>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">From <?= $metrics['total_bookings'] ?> total bookings</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function changeStatus(unitId) {
        if (confirm('Change unit status?')) {
            window.location.href = `../admin/unit_management.php?change_status=${unitId}`;
        }
    }
    
    function confirmDelete(unitId) {
        if (confirm('Are you sure you want to delete this unit?')) {
            window.location.href = `../admin/unit_management.php?delete=${unitId}`;
        }
    }
    </script>
</body>
</html>