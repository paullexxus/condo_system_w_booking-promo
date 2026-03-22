<?php
// superadmin/index.php - MAIN DASHBOARD
// Pull summary data for charts from the database
include_once '../config/db.php';
include '../includes/session.php';
include '../includes/functions.php';
checkRole(['admin']);

// Additional statistics using your existing functions - UPDATED
$totalUsers = 0;
$totalBranches = 0;
$totalUnits = 0;
$totalReservations = 0;
$totalRevenue = 0;

// Total Users (Host & Renters) - Only active
$result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE is_active = 1");
if ($result && $row = $result->fetch_assoc()) $totalUsers = (int)$row['cnt'];

// Total Branches - Only active
$result = $conn->query("SELECT COUNT(*) as cnt FROM branches WHERE is_active = 1");
if ($result && $row = $result->fetch_assoc()) $totalBranches = (int)$row['cnt'];

// Total Units (Listings) - Only from active branches
$result = $conn->query("SELECT COUNT(*) as cnt FROM units u 
                       JOIN branches b ON u.branch_id = b.branch_id 
                       WHERE b.is_active = 1");
if ($result && $row = $result->fetch_assoc()) $totalUnits = (int)$row['cnt'];

// Total Reservations - Only from active branches
$result = $conn->query("SELECT COUNT(*) as cnt FROM reservations r 
                       JOIN branches b ON r.branch_id = b.branch_id 
                       WHERE b.is_active = 1");
if ($result && $row = $result->fetch_assoc()) $totalReservations = (int)$row['cnt'];

// Total Revenue (from reservations) - Only from active branches
$result = $conn->query("SELECT SUM(r.total_amount) as total FROM reservations r 
                       JOIN branches b ON r.branch_id = b.branch_id 
                       WHERE r.status IN ('confirmed', 'checked_in', 'completed') 
                       AND b.is_active = 1");
if ($result && $row = $result->fetch_assoc()) $totalRevenue = (float)$row['total'];

// CORRECTED: Booking Rate Calculation - Number of unique booked units from ACTIVE branches
$result = $conn->query("SELECT COUNT(DISTINCT r.unit_id) as booked_units 
                       FROM reservations r 
                       JOIN branches b ON r.branch_id = b.branch_id 
                       WHERE r.status IN ('confirmed', 'checked_in') 
                       AND b.is_active = 1");
$bookedUnits = 0;
if ($result && $row = $result->fetch_assoc()) $bookedUnits = (int)$row['booked_units'];

// Calculate booking rate (max 100%) - Only from active branches
$bookingRate = $totalUnits > 0 ? min(round(($bookedUnits / $totalUnits) * 100, 1), 100) : 0;

// Reservation Status Breakdown
$reservationStats = [
    'pending' => 0,
    'confirmed' => 0,
    'checked_in' => 0,
    'cancelled' => 0,
    'completed' => 0
];

$result = $conn->query("SELECT status, COUNT(*) as cnt FROM reservations GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'] ?? 'unknown';
        if (isset($reservationStats[$status])) {
            $reservationStats[$status] = (int)$row['cnt'];
        }
    }
}

// User Role Breakdown
$userStats = [
    'admin' => 0,
    'host' => 0,
    'renter' => 0,
    'manager' => 0  // For compatibility
];

$result = $conn->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $userStats[$row['role']] = (int)$row['cnt'];
    }
}

// Get recent reservations for the activity feed
$recentReservations = get_multiple_results(
    "SELECT r.*, u.full_name, un.unit_number, b.branch_name 
     FROM reservations r 
     JOIN users u ON r.user_id = u.user_id 
     JOIN units un ON r.unit_id = un.unit_id 
     JOIN branches b ON r.branch_id = b.branch_id 
     ORDER BY r.created_at DESC LIMIT 5"
);

// Monthly Revenue Data for Chart
$monthlyRevenue = fetch_pairs($conn, 
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
            SUM(total_amount) as revenue 
     FROM reservations 
     WHERE status IN ('confirmed', 'checked_in', 'completed')
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month DESC LIMIT 6", 
    'month', 'revenue'
);

// Monthly Revenue Data for current year
$monthly_revenue = get_multiple_results("
    SELECT 
        DATE_FORMAT(created_at, '%b') as month,
        MONTH(created_at) as month_num,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM reservations
    WHERE YEAR(created_at) = YEAR(CURDATE())
    AND status IN ('confirmed', 'checked_in', 'completed')
    AND payment_status = 'completed'
    GROUP BY MONTH(created_at), DATE_FORMAT(created_at, '%b')
    ORDER BY MONTH(created_at)
");

// Yearly Revenue Data for history
$yearly_revenue = get_multiple_results("
    SELECT 
        YEAR(created_at) as year,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM reservations
    WHERE status IN ('confirmed', 'checked_in', 'completed')
    AND payment_status = 'completed'
    GROUP BY YEAR(created_at)
    ORDER BY YEAR(created_at) DESC
");

// Last 12 months revenue data for detailed chart
$last_12_months_revenue = get_multiple_results("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month_year,
        DATE_FORMAT(created_at, '%b %y') as month_label,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM reservations
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND status IN ('confirmed', 'checked_in', 'completed')
    AND payment_status = 'completed'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %y')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m')
");

// Reservation Status for Pie Chart
$reservationsStatus = fetch_pairs($conn, 
    "SELECT COALESCE(status,'Unknown') AS status, COUNT(*) AS cnt 
     FROM reservations 
     GROUP BY status", 
    'status', 'cnt'
);

// Units by Type for Chart
$unitsByType = fetch_pairs($conn, 
    "SELECT COALESCE(unit_type,'Unknown') AS type, COUNT(*) AS cnt 
     FROM units 
     GROUP BY unit_type 
     ORDER BY cnt DESC", 
    'type', 'cnt'
);

// Users by Role for Chart
$usersByRole = fetch_pairs($conn, 
    "SELECT COALESCE(role,'Unknown') AS role, COUNT(*) AS cnt 
     FROM users 
     GROUP BY role 
     ORDER BY cnt DESC", 
    'role', 'cnt'
);

// JSON encode for front-end
$monthlyRevenueJson = json_encode($monthlyRevenue);
$reservationsStatusJson = json_encode($reservationsStatus);
$unitsByTypeJson = json_encode($unitsByType);
$usersByRoleJson = json_encode($usersByRole);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container-fluid">
        <div class="row">
  <?php include '../includes/sidebar.php'; ?>

  <!-- =================== DASHBOARD CONTENT =================== -->
  <main class="content">
    <div class="dashboard-header">
      <h1>Admin Dashboard Overview</h1>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-container">
      <div class="stat-card">
        <div class="stat-number"><?php echo $totalBranches; ?></div>
        <div class="stat-label">Total Branches</div>
        <i class="fas fa-code-branch fa-2x stat-icon"></i>
        <button class="stat-action-btn" onclick="openModal('branchesModal')">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $totalUnits; ?></div>
        <div class="stat-label">Total Units</div>
        <i class="fas fa-home fa-2x stat-icon"></i>
        <button class="stat-action-btn" onclick="openModal('unitsModal')">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $totalReservations; ?></div>
        <div class="stat-label">Total Reservations</div>
        <i class="fas fa-calendar-check fa-2x stat-icon"></i>
        <button class="stat-action-btn" onclick="openModal('reservationsModal')">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
      <div class="stat-card">
        <div class="stat-number" id="totalRevenue">₱<?php echo number_format($totalRevenue, 2); ?></div>
        <div class="stat-label">Total Revenue</div>
        <i class="fas fa-money-bill-wave fa-2x stat-icon"></i>
        <button class="stat-action-btn" onclick="openModal('revenueModal')">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $totalUsers; ?></div>
        <div class="stat-label">Total Users</div>
        <i class="fas fa-users fa-2x stat-icon"></i>
        <button class="stat-action-btn" onclick="openModal('usersModal')">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
      <div class="stat-card">
        <div class="stat-number" id="bookRate"><?php echo $bookingRate; ?>%</div>
        <div class="stat-label">Occupancy Rate</div>
        <i class="fas fa-chart-line fa-2x stat-icon"></i>
        <button class="stat-action-btn" onclick="openModal('occupancyModal')">
          <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="stats-container">
      <div class="stat-card">
        <div class="stat-number"><?php echo $bookedUnits; ?></div>
        <div class="stat-label">Currently Booked Units</div>
        <i class="fas fa-bed fa-2x stat-icon"></i>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $totalUnits - $bookedUnits; ?></div>
        <div class="stat-label">Available Units</div>
        <i class="fas fa-door-open fa-2x stat-icon"></i>
      </div>
    </div>

    <!-- Reservation Status Breakdown -->
    <div class="stats-breakdown">
      <div class="breakdown-card">
        <h3>Reservation Status</h3>
        <div class="breakdown-grid">
          <div class="breakdown-item pending">
            <span class="breakdown-number"><?php echo $reservationStats['pending'] ?? 0; ?></span>
            <span class="breakdown-label">Pending</span>
          </div>
          <div class="breakdown-item confirmed">
            <span class="breakdown-number"><?php echo $reservationStats['confirmed'] ?? 0; ?></span>
            <span class="breakdown-label">Confirmed</span>
          </div>
          <div class="breakdown-item cancelled">
            <span class="breakdown-number"><?php echo $reservationStats['cancelled'] ?? 0; ?></span>
            <span class="breakdown-label">Cancelled</span>
          </div>
          <div class="breakdown-item completed">
            <span class="breakdown-number"><?php echo $reservationStats['completed'] ?? 0; ?></span>
            <span class="breakdown-label">Completed</span>
          </div>
        </div>
      </div>
      
      <div class="breakdown-card">
        <h3>User Roles</h3>
        <div class="breakdown-grid">
          <div class="breakdown-item admin">
            <span class="breakdown-number"><?php echo $userStats['admin']; ?></span>
            <span class="breakdown-label">Admins</span>
          </div>
          <div class="breakdown-item manager">
            <span class="breakdown-number"><?php echo $userStats['host']; ?></span>
            <span class="breakdown-label">Hosts/Managers</span>
          </div>
          <div class="breakdown-item renter">
            <span class="breakdown-number"><?php echo $userStats['renter']; ?></span>
            <span class="breakdown-label">Renters</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Quick Analysis Charts -->
    <div class="charts-container">
      <!-- Revenue Trend -->
      <div class="chart-card full-width">
        <div class="chart-header">
          <h3 class="chart-title">Monthly Revenue Trend</h3>
        </div>
        <canvas id="revenueChart"></canvas>
      </div>

      <div class="charts-grid">
        <!-- Reservation Status -->
        <div class="chart-card">
          <div class="chart-header">
            <h3 class="chart-title">Reservations by Status</h3>
          </div>
          <canvas id="reservationsChart"></canvas>
        </div>

        <!-- Units by Type -->
        <div class="chart-card">
          <div class="chart-header">
            <h3 class="chart-title">Units by Type</h3>
          </div>
          <canvas id="unitsChart"></canvas>
        </div>

        <!-- Users by Role -->
        <div class="chart-card">
          <div class="chart-header">
            <h3 class="chart-title">Users by Role</h3>
          </div>
          <canvas id="usersChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Recent Activity Feed -->
    <div class="activity-feed">
      <div class="activity-header">
        <h3 class="activity-title">Recent Reservations</h3>
        <a href="/BookIT/admin/reservations.php" class="view-all">View All</a>
      </div>
      <ul class="activity-list">
        <?php if (!empty($recentReservations)): ?>
          <?php foreach ($recentReservations as $reservation): ?>
            <li class="activity-item">
              <div class="activity-info">
                <h4><?php echo htmlspecialchars($reservation['full_name']); ?></h4>
                <p>Unit <?php echo htmlspecialchars($reservation['unit_number']); ?> • <?php echo htmlspecialchars($reservation['branch_name']); ?></p>
                <small><?php echo formatDate($reservation['check_in_date']); ?> to <?php echo formatDate($reservation['check_out_date']); ?></small>
              </div>
              <div style="display: flex; align-items: center; gap: 10px;">
                <span class="activity-status status-<?php echo $reservation['status']; ?>">
                  <?php echo ucfirst($reservation['status']); ?>
                </span>
                <button class="btn-icon" onclick="openReservationDetails(<?php echo $reservation['reservation_id']; ?>, '<?php echo htmlspecialchars($reservation['full_name']); ?>', '<?php echo htmlspecialchars($reservation['unit_number']); ?>', '<?php echo $reservation['status']; ?>')">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="activity-item">
            <div class="activity-info">
              <p>No recent reservations</p>
            </div>
          </li>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Revenue History Section -->
    <div class="revenue-history-section">
      <h3 class="section-title"><i class="fas fa-history"></i> Revenue History</h3>
      
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
          <div class="table-responsive revenue-table">
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
                <?php if ($total_monthly > 0): ?>
                <tr class="total-row">
                  <td><strong>TOTAL</strong></td>
                  <td class="text-end"><strong>₱<?php echo number_format($total_monthly, 0); ?></strong></td>
                  <td class="text-end">100%</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Last 12 Months Revenue Chart -->
        <div class="tab-pane fade" id="last-12-revenue" role="tabpanel">
          <div class="chart-card" style="margin-top: 20px;">
            <div class="chart-container">
              <canvas id="last12MonthsChartAdmin"></canvas>
            </div>
          </div>
          <div class="table-responsive revenue-table">
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
          <div class="table-responsive revenue-table">
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
                <?php if ($total_all_years > 0): ?>
                <tr class="total-row">
                  <td><strong>TOTAL (All Years)</strong></td>
                  <td class="text-end"><strong>₱<?php echo number_format($total_all_years, 0); ?></strong></td>
                  <td class="text-end">100%</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>
</div>

  <script>
    // Pass PHP data to JavaScript
    const monthlyRevenueData = <?php echo $monthlyRevenueJson; ?>;
    const reservationsStatusData = <?php echo $reservationsStatusJson; ?>;
    const unitsByTypeData = <?php echo $unitsByTypeJson; ?>;
    const usersByRoleData = <?php echo $usersByRoleJson; ?>;

    // Revenue History Data for Charts
    const adminLast12MonthsRevenue = {
        labels: [<?php 
            $labels_12 = [];
            foreach ($last_12_months_revenue as $item) {
                $labels_12[] = "'" . $item['month_label'] . "'";
            }
            echo implode(',', $labels_12);
        ?>],
        data: [<?php 
            $revenues_12 = [];
            foreach ($last_12_months_revenue as $item) {
                $revenues_12[] = $item['revenue'];
            }
            echo implode(',', $revenues_12);
        ?>]
    };
  </script>
  <script src="../assets/js/admin/admin_dashboard.js"></script>
  <script>
    // Last 12 Months Revenue Chart for Admin
    const last12MonthsCtxAdmin = document.getElementById('last12MonthsChartAdmin');
    if (last12MonthsCtxAdmin) {
        new Chart(last12MonthsCtxAdmin.getContext('2d'), {
            type: 'bar',
            data: {
                labels: adminLast12MonthsRevenue.labels,
                datasets: [{
                    label: 'Monthly Revenue (₱)',
                    data: adminLast12MonthsRevenue.data,
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
  </script>

  <!-- =================== MODALS =================== -->
  
  <!-- Branches Modal -->
  <div id="branchesModal" class="modal-overlay" onclick="closeModal('branchesModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2><i class="fas fa-code-branch"></i> Branches Management</h2>
        <button class="modal-close" onclick="closeModal('branchesModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p>Total Active Branches: <strong><?php echo $totalBranches; ?></strong></p>
        <p>Manage all property branches and their details.</p>
        <div class="modal-actions">
          <a href="/BookIT/admin/manage_branch.php" class="btn btn-primary">
            <i class="fas fa-edit"></i> Manage Branches
          </a>
          <a href="/BookIT/admin/manage_branch.php?action=add" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Branch
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Units Modal -->
  <div id="unitsModal" class="modal-overlay" onclick="closeModal('unitsModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2><i class="fas fa-home"></i> Units Management</h2>
        <button class="modal-close" onclick="closeModal('unitsModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p>Total Units: <strong><?php echo $totalUnits; ?></strong></p>
        <p>Booked Units: <strong><?php echo $bookedUnits; ?></strong></p>
        <p>Available Units: <strong><?php echo $totalUnits - $bookedUnits; ?></strong></p>
        <div class="modal-actions">
          <a href="/BookIT/admin/unit_management.php" class="btn btn-primary">
            <i class="fas fa-edit"></i> Manage Units
          </a>
          <a href="/BookIT/admin/unit_management.php?action=add" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Unit
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Reservations Modal -->
  <div id="reservationsModal" class="modal-overlay" onclick="closeModal('reservationsModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2><i class="fas fa-calendar-check"></i> Reservations</h2>
        <button class="modal-close" onclick="closeModal('reservationsModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p>Total Reservations: <strong><?php echo $totalReservations; ?></strong></p>
        <div class="reservation-stats">
          <div class="stat-item"><span>Pending:</span> <strong><?php echo $reservationStats['pending'] ?? 0; ?></strong></div>
          <div class="stat-item"><span>Confirmed:</span> <strong><?php echo $reservationStats['confirmed'] ?? 0; ?></strong></div>
          <div class="stat-item"><span>Cancelled:</span> <strong><?php echo $reservationStats['cancelled'] ?? 0; ?></strong></div>
          <div class="stat-item"><span>Completed:</span> <strong><?php echo $reservationStats['completed'] ?? 0; ?></strong></div>
        </div>
        <div class="modal-actions">
          <a href="/BookIT/modules/reservations.php" class="btn btn-primary">
            <i class="fas fa-list"></i> View All Reservations
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Revenue Modal -->
  <div id="revenueModal" class="modal-overlay" onclick="closeModal('revenueModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2><i class="fas fa-money-bill-wave"></i> Revenue Overview</h2>
        <button class="modal-close" onclick="closeModal('revenueModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p>Total Revenue: <strong>₱<?php echo number_format($totalRevenue, 2); ?></strong></p>
        <p>Generated from confirmed, checked-in, and completed reservations.</p>
        <div class="modal-actions">
          <a href="/BookIT/modules/payment_management.php" class="btn btn-primary">
            <i class="fas fa-credit-card"></i> Payment Management
          </a>
          <a href="/BookIT/admin/reports.php" class="btn btn-info">
            <i class="fas fa-chart-line"></i> View Reports
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Users Modal -->
  <div id="usersModal" class="modal-overlay" onclick="closeModal('usersModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2><i class="fas fa-users"></i> Users Management</h2>
        <button class="modal-close" onclick="closeModal('usersModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p>Total Users: <strong><?php echo $totalUsers; ?></strong></p>
        <div class="user-stats">
          <div class="stat-item"><span>Admins:</span> <strong><?php echo $userStats['admin']; ?></strong></div>
          <div class="stat-item"><span>Hosts/Managers:</span> <strong><?php echo $userStats['host']; ?></strong></div>
          <div class="stat-item"><span>Renters:</span> <strong><?php echo $userStats['renter']; ?></strong></div>
        </div>
        <div class="modal-actions">
          <a href="/BookIT/admin/user_management.php" class="btn btn-primary">
            <i class="fas fa-edit"></i> Manage Users
          </a>
          <a href="/BookIT/admin/user_management.php?action=add" class="btn btn-success">
            <i class="fas fa-user-plus"></i> Add New User
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Occupancy Modal -->
  <div id="occupancyModal" class="modal-overlay" onclick="closeModal('occupancyModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2><i class="fas fa-chart-line"></i> Occupancy Rate</h2>
        <button class="modal-close" onclick="closeModal('occupancyModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p>Current Occupancy Rate: <strong><?php echo $bookingRate; ?>%</strong></p>
        <p>Based on confirmed and checked-in reservations.</p>
        <div class="occupancy-details">
          <div class="detail-row">
            <span>Total Units:</span> <strong><?php echo $totalUnits; ?></strong>
          </div>
          <div class="detail-row">
            <span>Currently Booked:</span> <strong><?php echo $bookedUnits; ?></strong>
          </div>
          <div class="detail-row">
            <span>Available:</span> <strong><?php echo $totalUnits - $bookedUnits; ?></strong>
          </div>
        </div>
        <div class="modal-actions">
          <a href="/BookIT/admin/unit_management.php" class="btn btn-primary">
            <i class="fas fa-home"></i> Unit Management
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Reservation Details Modal -->
  <div id="reservationDetailsModal" class="modal-overlay" onclick="closeModal('reservationDetailsModal')">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="modal-header">
        <h2><i class="fas fa-eye"></i> Reservation Details</h2>
        <button class="modal-close" onclick="closeModal('reservationDetailsModal')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="details-grid">
          <div class="detail-item">
            <span class="label">Guest Name:</span>
            <span class="value" id="detailsGuestName">-</span>
          </div>
          <div class="detail-item">
            <span class="label">Unit Number:</span>
            <span class="value" id="detailsUnitNumber">-</span>
          </div>
          <div class="detail-item">
            <span class="label">Reservation ID:</span>
            <span class="value" id="detailsReservationId">-</span>
          </div>
          <div class="detail-item">
            <span class="label">Status:</span>
            <span class="value" id="detailsStatus">-</span>
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" onclick="closeModal('reservationDetailsModal')">Close</button>
          <a href="#" id="detailsViewBtn" class="btn btn-primary">
            <i class="fas fa-arrow-right"></i> View Full Details
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Styles and JavaScript -->
  <script>
    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    }

    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
      }
    }

    function openReservationDetails(id, name, unit, status) {
      document.getElementById('detailsReservationId').textContent = id;
      document.getElementById('detailsGuestName').textContent = name;
      document.getElementById('detailsUnitNumber').textContent = unit;
      document.getElementById('detailsStatus').textContent = status.charAt(0).toUpperCase() + status.slice(1);
      document.getElementById('detailsViewBtn').href = '/BookIT/modules/reservations.php?id=' + id;
      openModal('reservationDetailsModal');
    }

    // Close modal when pressing ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
          if (modal.style.display === 'flex') {
            closeModal(modal.id);
          }
        });
      }
    });
  </script>

</body>
</html>