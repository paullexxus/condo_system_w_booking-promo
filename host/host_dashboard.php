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

// Get monthly revenue data for chart (current year)
$monthly_revenue = get_multiple_results("
    SELECT 
        DATE_FORMAT(r.check_in_date, '%b') as month,
        MONTH(r.check_in_date) as month_num,
        COALESCE(SUM(r.total_amount), 0) as revenue
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = $host_id
    AND YEAR(r.check_in_date) = YEAR(CURDATE())
    AND r.payment_status = 'completed'
    GROUP BY MONTH(r.check_in_date), DATE_FORMAT(r.check_in_date, '%b')
    ORDER BY MONTH(r.check_in_date)
");

// Get yearly revenue data for history
$yearly_revenue = get_multiple_results("
    SELECT 
        YEAR(r.check_in_date) as year,
        COALESCE(SUM(r.total_amount), 0) as revenue
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = $host_id
    AND r.payment_status = 'completed'
    GROUP BY YEAR(r.check_in_date)
    ORDER BY YEAR(r.check_in_date) DESC
");

// Get last 12 months revenue data for detailed chart
$last_12_months_revenue = get_multiple_results("
    SELECT 
        DATE_FORMAT(r.check_in_date, '%Y-%m') as month_year,
        DATE_FORMAT(r.check_in_date, '%b %y') as month_label,
        COALESCE(SUM(r.total_amount), 0) as revenue
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = $host_id
    AND r.check_in_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND r.payment_status = 'completed'
    GROUP BY DATE_FORMAT(r.check_in_date, '%Y-%m'), DATE_FORMAT(r.check_in_date, '%b %y')
    ORDER BY DATE_FORMAT(r.check_in_date, '%Y-%m')
");

// Get host units for quick access (with latest unit image)
$host_units = get_multiple_results("
    SELECT 
        u.*,
        (
            SELECT ui.image_path
            FROM unit_images ui
            WHERE ui.unit_id = u.unit_id
            ORDER BY ui.created_at DESC
            LIMIT 1
        ) AS unit_image
    FROM units u
    WHERE u.host_id = $host_id
    ORDER BY u.created_at DESC
    LIMIT 8
");

// Get recent bookings
$recent_bookings = get_multiple_results("
    SELECT r.*, u.unit_name, u.unit_number, u.unit_type, rs.full_name as renter_name
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN users rs ON r.user_id = rs.user_id
    WHERE u.host_id = $host_id
    ORDER BY r.created_at DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <link href="../assets/css/host/host_dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title mb-1">
                    <i class="fas fa-home me-2"></i>Host Dashboard
                </h1>
                <p class="text-muted">Welcome, <?php echo htmlspecialchars($host_data['full_name']); ?></p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn-refresh" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn-add" onclick="window.location.href='unit_management.php'">
                    <i class="fas fa-plus"></i> Add Unit
                </button>
            </div>
        </div>

        <!-- Quick Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div class="stat-content">
                    <h6>Total Units</h6>
                    <p class="stat-value"><?php echo $total_units; ?></p>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h6>Total Bookings</h6>
                    <p class="stat-value"><?php echo $reservations_stats['total'] ?? 0; ?></p>
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h6>Total Revenue</h6>
                    <p class="stat-value">₱<?php echo number_format($revenue_stats['total_revenue'] ?? 0, 0); ?></p>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-content">
                    <h6>Occupancy Rate</h6>
                    <p class="stat-value"><?php echo $occupancy_rate; ?>%</p>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="dashboard-grid">
            <!-- Charts Section -->
            <div class="chart-section">
                <!-- Occupancy Chart -->
                <div class="chart-card">
                    <h5 class="card-title"><i class="fas fa-chart-pie"></i> Unit Status</h5>
                    <div class="chart-container">
                        <canvas id="occupancyChart"></canvas>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="chart-card">
                    <h5 class="card-title"><i class="fas fa-chart-line"></i> Monthly Revenue</h5>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Upcoming Reservations -->
            <div class="upcoming-section">
                <h5 class="section-title"><i class="fas fa-calendar-alt"></i> Upcoming Reservations (7 days)</h5>
                <div class="reservations-list">
                    <?php 
                    if (is_array($upcoming_reservations) && count($upcoming_reservations) > 0) {
                        foreach ($upcoming_reservations as $reservation):
                    ?>
                    <div class="reservation-item">
                        <div class="reservation-info">
                            <h6><?php echo htmlspecialchars($reservation['unit_name'] ?? ''); ?> #<?php echo $reservation['unit_number']; ?></h6>
                            <p class="text-muted"><?php echo htmlspecialchars($reservation['renter_name']); ?></p>
                            <small class="date-range">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('M d, Y', strtotime($reservation['check_in_date'])); ?> - 
                                <?php echo date('M d, Y', strtotime($reservation['check_out_date'])); ?>
                            </small>
                        </div>
                        <div class="reservation-actions">
                            <span class="badge bg-success">Confirmed</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewReservationDetails(<?php echo $reservation['reservation_id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    } else {
                        echo '<p class="text-muted text-center py-4">No upcoming reservations</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Reservation Stats -->
        <div class="reservation-stats">
            <div class="stat-row">
                <div class="stat-box">
                    <h6>Pending</h6>
                    <p class="value pending"><?php echo $reservations_stats['pending'] ?? 0; ?></p>
                </div>
                <div class="stat-box">
                    <h6>Confirmed</h6>
                    <p class="value confirmed"><?php echo $reservations_stats['confirmed'] ?? 0; ?></p>
                </div>
                <div class="stat-box">
                    <h6>Completed</h6>
                    <p class="value completed"><?php echo $reservations_stats['completed'] ?? 0; ?></p>
                </div>
                <div class="stat-box">
                    <h6>Cancelled</h6>
                    <p class="value cancelled"><?php echo $reservations_stats['cancelled'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Access Units -->
        <div class="units-section">
            <div class="section-header">
                <h5 class="section-title"><i class="fas fa-home"></i> Your Units</h5>
                <a href="unit_management.php" class="view-all">View All →</a>
            </div>
            <div class="units-grid">
                <?php 
                if (is_array($host_units) && count($host_units) > 0) {
                    foreach ($host_units as $unit):
                ?>
                <div class="unit-card">
                    <div class="unit-image">
                        <?php if (!empty($unit['unit_image'])): ?>
                            <img src="<?php echo htmlspecialchars($unit['unit_image']); ?>" alt="<?php echo htmlspecialchars($unit['unit_name'] ?: ('Unit ' . $unit['unit_number'])); ?>">
                        <?php else: ?>
                            <i class="fas fa-home"></i>
                        <?php endif; ?>
                        <span class="unit-status <?php echo $unit['is_available'] ? 'available' : 'occupied'; ?>">
                            <?php echo $unit['is_available'] ? 'Available' : 'Occupied'; ?>
                        </span>
                    </div>
                    <div class="unit-details">
                        <h6><?php echo htmlspecialchars($unit['unit_name'] ?? ''); ?></h6>
                        <p class="unit-number">Unit #<?php echo htmlspecialchars($unit['unit_number']); ?></p>
                        <p class="unit-type"><?php echo htmlspecialchars($unit['unit_type']); ?></p>
                        <div class="unit-rate">
                            <strong>₱<?php echo number_format($unit['monthly_rate'] ?? 0, 0); ?></strong>
                            <span class="text-muted">/night</span>
                        </div>
                    </div>
                    <div class="unit-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="editUnit(<?php echo $unit['unit_id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUnit(<?php echo $unit['unit_id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php 
                    endforeach;
                } else {
                    echo '<p class="text-muted text-center py-4">No units added yet</p>';
                }
                ?>
            </div>
        </div>

        <!-- Recent Bookings Table -->
        <div class="bookings-section">
            <div class="section-header">
                <h5 class="section-title"><i class="fas fa-list"></i> Recent Bookings</h5>
                <a href="reservations.php" class="view-all">View All →</a>
            </div>
            <div class="table-card">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Renter</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (is_array($recent_bookings) && count($recent_bookings) > 0) {
                            foreach ($recent_bookings as $booking):
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($booking['unit_name'] ?? ''); ?></strong>
                                <br><small class="text-muted">#<?php echo $booking['unit_number']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($booking['renter_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                            <td><strong>₱<?php echo number_format($booking['total_amount'], 0); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($booking['status']) {
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'checked_in' => 'info',
                                        'completed' => 'secondary',
                                        'cancelled' => 'danger',
                                        default => 'light'
                                    };
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewReservationDetails(<?php echo $booking['reservation_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        } else {
                            echo '<tr><td colspan="7" class="text-center text-muted py-4">No bookings yet</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Revenue History Section -->
        <div class="revenue-history-section">
            <h5 class="section-title"><i class="fas fa-history"></i> Revenue History</h5>
            
            <!-- Tabs for Monthly/Yearly -->
            <ul class="nav nav-tabs revenue-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly-revenue" type="button" role="tab">
                        <i class="fas fa-calendar-alt"></i> Monthly (This Year)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="last-12-tab" data-bs-toggle="tab" data-bs-target="#last-12-revenue" type="button" role="tab">
                        <i class="fas fa-chart-line"></i> Last 12 Months
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="yearly-tab" data-bs-toggle="tab" data-bs-target="#yearly-revenue" type="button" role="tab">
                        <i class="fas fa-calendar"></i> Yearly Summary
                    </button>
                </li>
            </ul>

            <div class="tab-content revenue-content">
                <!-- Monthly Revenue (This Year) -->
                <div class="tab-pane fade show active" id="monthly-revenue" role="tabpanel">
                    <div class="table-card">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_monthly = 0;
                                if (is_array($monthly_revenue) && count($monthly_revenue) > 0) {
                                    foreach ($monthly_revenue as $m) {
                                        $total_monthly += $m['revenue'];
                                    }
                                    foreach ($monthly_revenue as $m):
                                    $percentage = $total_monthly > 0 ? ($m['revenue'] / $total_monthly) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo $m['month']; ?></strong></td>
                                    <td class="text-end"><strong>₱<?php echo number_format($m['revenue'], 0); ?></strong></td>
                                    <td class="text-end">
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                } else {
                                    echo '<tr><td colspan="3" class="text-center text-muted py-4">No revenue data for this year</td></tr>';
                                }
                                ?>
                                <tr class="total-row">
                                    <td><strong>TOTAL</strong></td>
                                    <td class="text-end"><strong>₱<?php echo number_format($total_monthly, 0); ?></strong></td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Last 12 Months Revenue Chart -->
                <div class="tab-pane fade" id="last-12-revenue" role="tabpanel">
                    <div class="chart-card" style="margin-top: 20px;">
                        <div class="chart-container">
                            <canvas id="last12MonthsChart"></canvas>
                        </div>
                    </div>
                    <div class="table-card">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_12_months = 0;
                                if (is_array($last_12_months_revenue) && count($last_12_months_revenue) > 0) {
                                    foreach ($last_12_months_revenue as $m) {
                                        $total_12_months += $m['revenue'];
                                    }
                                    foreach ($last_12_months_revenue as $m):
                                ?>
                                <tr>
                                    <td><?php echo $m['month_label']; ?></td>
                                    <td class="text-end"><strong>₱<?php echo number_format($m['revenue'], 0); ?></strong></td>
                                </tr>
                                <?php 
                                    endforeach;
                                } else {
                                    echo '<tr><td colspan="2" class="text-center text-muted py-4">No revenue data available</td></tr>';
                                }
                                ?>
                                <tr class="total-row">
                                    <td><strong>TOTAL (12 Months)</strong></td>
                                    <td class="text-end"><strong>₱<?php echo number_format($total_12_months, 0); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Yearly Summary -->
                <div class="tab-pane fade" id="yearly-revenue" role="tabpanel">
                    <div class="table-card">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th class="text-end">Total Revenue</th>
                                    <th class="text-end">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_all_years = 0;
                                if (is_array($yearly_revenue) && count($yearly_revenue) > 0) {
                                    foreach ($yearly_revenue as $y) {
                                        $total_all_years += $y['revenue'];
                                    }
                                    foreach ($yearly_revenue as $y):
                                    $year_percentage = $total_all_years > 0 ? ($y['revenue'] / $total_all_years) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo $y['year']; ?></strong></td>
                                    <td class="text-end"><strong>₱<?php echo number_format($y['revenue'], 0); ?></strong></td>
                                    <td class="text-end">
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $year_percentage; ?>%">
                                                <?php echo number_format($year_percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                } else {
                                    echo '<tr><td colspan="3" class="text-center text-muted py-4">No revenue data available</td></tr>';
                                }
                                ?>
                                <tr class="total-row">
                                    <td><strong>TOTAL (All Years)</strong></td>
                                    <td class="text-end"><strong>₱<?php echo number_format($total_all_years, 0); ?></strong></td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Occupancy Chart
    const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
    new Chart(occupancyCtx, {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Occupied'],
            datasets: [{
                data: [<?php echo $occupancy_data['available_units'] ?? 0; ?>, <?php echo $occupancy_data['occupied_units'] ?? 0; ?>],
                backgroundColor: ['#2ecc71', '#e74c3c'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: [<?php 
                $months = [];
                foreach ($monthly_revenue as $item) {
                    $months[] = "'" . $item['month'] . "'";
                }
                echo implode(',', $months);
            ?>],
            datasets: [{
                label: 'Revenue (₱)',
                data: [<?php 
                    $revenues = [];
                    foreach ($monthly_revenue as $item) {
                        $revenues[] = $item['revenue'];
                    }
                    echo implode(',', $revenues);
                ?>],
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#3498db'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Last 12 Months Revenue Chart
    const last12MonthsCtx = document.getElementById('last12MonthsChart');
    if (last12MonthsCtx) {
        new Chart(last12MonthsCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: [<?php 
                    $labels_12 = [];
                    foreach ($last_12_months_revenue as $item) {
                        $labels_12[] = "'" . $item['month_label'] . "'";
                    }
                    echo implode(',', $labels_12);
                ?>],
                datasets: [{
                    label: 'Monthly Revenue (₱)',
                    data: [<?php 
                        $revenues_12 = [];
                        foreach ($last_12_months_revenue as $item) {
                            $revenues_12[] = $item['revenue'];
                        }
                        echo implode(',', $revenues_12);
                    ?>],
                    backgroundColor: '#3498db',
                    borderColor: '#2980b9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function viewReservationDetails(reservationId) {
        window.location.href = `reservations.php?view=${reservationId}`;
    }

    function editUnit(unitId) {
        window.location.href = `unit_management.php?edit=${unitId}`;
    }

    function deleteUnit(unitId) {
        if (confirm('Are you sure you want to delete this unit?')) {
            window.location.href = `unit_management.php?delete=${unitId}`;
        }
    }
</script>
<script src="../assets/js/host/host_dashboard.js"></script>

</body>
</html>
