<?php
// Admin Reservation Management Module
// SECURITY FIX: Proper role checking and session validation

// Include session first to validate user
include_once dirname(__FILE__) . '/../includes/session.php';
include_once dirname(__FILE__) . '/../includes/functions.php';
include_once dirname(__FILE__) . '/../config/db.php';

// CRITICAL: Check that only admins can access this page
checkRole(['admin']);

// Get user info from session
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$db_connected = true;
$error_message = '';

// Get filter parameters
$branch_filter = $_GET['branch'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_term = $_GET['search'] ?? '';

// Try to get real data if database is connected
$reservations = [];
$total_count = 0;
$stats = [
    'pending' => 0,
    'confirmed' => 0,
    'checked-in' => 0,
    'checked-out' => 0,
    'cancelled' => 0,
    'completed' => 0
];

// Get branches for filter (Admin only)
$branches = [];
if ($db_connected && $user_role === 'admin') {
    try {
        $branches_query = "SELECT branch_id, branch_name FROM branches ORDER BY branch_name";
        $branches = get_multiple_results($branches_query);
    } catch (Exception $e) {
        $branches_error = $e->getMessage();
    }
}

if ($db_connected) {
    try {
        // Build query with filters
        $query = "SELECT 
            r.reservation_id,
            r.check_in_date,
            r.check_out_date,
            r.total_amount,
            r.payment_status,
            r.status as reservation_status,
            r.created_at,
            r.user_id,
            r.unit_id,
            r.branch_id,
            r.special_requests,
            r.host_notes,
            r.admin_notes,
            r.cancellation_reason,
            r.approved_by,
            r.approved_at,
            u.unit_name,
            b.branch_name,
            usr.full_name as renter_name,
            host.full_name as host_name
        FROM reservations r
        LEFT JOIN units u ON r.unit_id = u.unit_id
        LEFT JOIN branches b ON r.branch_id = b.branch_id
        LEFT JOIN users usr ON r.user_id = usr.user_id
        LEFT JOIN users host ON u.host_id = host.user_id
        WHERE 1=1";
        
        $params = [];
        
        // Apply filters
        if ($user_role === 'host') {
            // Host can only see their own units' reservations
            $query .= " AND u.host_id = ?";
            $params[] = $user_id;
        }
        
        if (!empty($branch_filter)) {
            $query .= " AND r.branch_id = ?";
            $params[] = $branch_filter;
        }
        
        if (!empty($status_filter)) {
            $query .= " AND r.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($date_from)) {
            $query .= " AND r.check_in_date >= ?";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $query .= " AND r.check_out_date <= ?";
            $params[] = $date_to;
        }
        
        if (!empty($search_term)) {
            $query .= " AND (r.reservation_id LIKE ? OR usr.full_name LIKE ? OR u.unit_name LIKE ?)";
            $search_like = "%$search_term%";
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }
        
        $query .= " ORDER BY r.created_at DESC LIMIT 50";
        
        $reservations = get_multiple_results($query, $params);

        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM reservations r 
                       LEFT JOIN units u ON r.unit_id = u.unit_id WHERE 1=1";
        $count_params = [];
        
        if ($user_role === 'host') {
            $count_query .= " AND u.host_id = ?";
            $count_params[] = $user_id;
        }
        
        $count_result = get_single_result($count_query, $count_params);
        $total_count = $count_result['total'] ?? 0;

        // Get status counts
        $stats_query = "SELECT status, COUNT(*) as count FROM reservations r 
                       LEFT JOIN units u ON r.unit_id = u.unit_id WHERE 1=1";
        $stats_params = [];
        
        if ($user_role === 'host') {
            $stats_query .= " AND u.host_id = ?";
            $stats_params[] = $user_id;
        }
        
        $stats_query .= " GROUP BY status";
        $status_counts = get_multiple_results($stats_query, $stats_params);

        foreach ($status_counts as $row) {
            $status_key = strtolower($row['status']);
            $stats[$status_key] = $row['count'];
        }

    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }
}

// Get analytics data for admin
$analytics = [];
if ($db_connected && $user_role === 'admin') {
    try {
        // Monthly reservations
        $monthly_query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM reservations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month 
        ORDER BY month DESC 
        LIMIT 6";
        $analytics['monthly'] = get_multiple_results($monthly_query);
        
        // Top branches
        $branches_query = "SELECT 
            b.branch_name,
            COUNT(*) as reservation_count
        FROM reservations r
        JOIN branches b ON r.branch_id = b.branch_id
        WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY b.branch_id, b.branch_name
        ORDER BY reservation_count DESC 
        LIMIT 5";
        $analytics['top_branches'] = get_multiple_results($branches_query);
        
    } catch (Exception $e) {
        $analytics_error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation Management - <?php echo ucfirst($user_role); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<link href="../assets/css/modules/reservations.css" rel="stylesheet">
</head>
<body>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin:10px;">
            <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin:10px;">
            <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<!-- =================== SIDEBAR =================== -->
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-building"></i>
        <span>BookIT <?php echo ucfirst($user_role); ?></span>
    </div>
    <nav class="sidebar-menu">
        <ul>
            <li><a href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <?php if ($user_role === 'admin'): ?>
            <li><a href="<?php echo SITE_URL; ?>/admin/manage_branch.php"><i class="fas fa-code-branch"></i><span>Branch Management</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/admin/user_management.php"><i class="fas fa-users"></i> <span>User Management</span></a></li>
            <?php endif; ?>
            <li><a href="<?php echo SITE_URL; ?>/admin/unit_management.php"><i class="fas fa-home"></i> <span>Unit Management</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/modules/reservations.php" class="active"><i class="fas fa-calendar-check"></i> <span>Reservation Management</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/modules/payment_management.php"><i class="fas fa-credit-card"></i> <span>Payment Management</span></a></li>
            <?php if ($user_role === 'admin'): ?>
            <li><a href="<?php echo SITE_URL; ?>/admin/amenity_management.php"><i class="fas fa-swimming-pool"></i> <span>Amenity Management</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/admin/reports.php"><i class="fas fa-chart-line"></i> <span>Reports</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/admin/settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- =================== PROFILE SECTION =================== -->
    <div class="sidebar-profile">
        <div class="profile-info">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-details">
                <span class="profile-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?></span>
                <span class="profile-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'User')); ?></span>
            </div>
        </div>
        <a href="../public/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>

<!-- Main Content -->
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="reservation-header">
            <div>
                <h1>
                    <i class="fas fa-calendar-check me-2"></i>
                    Reservation Management
                    <small class="text-muted">(<?php echo ucfirst($user_role); ?> View)</small>
                </h1>
                <p class="text-muted mb-0">Manage all bookings and reservations</p>
            </div>
            <div class="text-end">
                <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($user_role); ?></span>
                <div class="text-muted mt-1">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['fullname'] ?? 'User'); ?></div>
            </div>
        </div>

        <!-- Database Status -->
        <?php if (!$db_connected): ?>
        <div class="alert alert-warning">
            <strong>Database Not Connected:</strong> Showing demo data.
            <?php if (isset($error_message)) echo "Error: " . $error_message; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <strong>Database Connected!</strong> Showing real data from database.
            <?php if (isset($db_error)): ?>
            <div class="mt-2 alert alert-danger">
                <strong>Query Error:</strong> <?php echo $db_error; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ADMIN ANALYTICS DASHBOARD -->
        <?php if ($user_role === 'admin' && $db_connected): ?>
        <div class="analytics-section mb-4">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Reservation Trends (Last 6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="reservationChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-star me-2"></i>Top Branches (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['top_branches'])): ?>
                                <?php foreach ($analytics['top_branches'] as $branch): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                    <span><?php echo htmlspecialchars($branch['branch_name']); ?></span>
                                    <span class="badge bg-primary"><?php echo $branch['reservation_count']; ?> bookings</span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? $total_count : '0'; ?></div>
                <div class="stat-label">Total Reservations</div>
                <i class="fas fa-calendar-alt stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? $stats['pending'] : '0'; ?></div>
                <div class="stat-label">Pending</div>
                <i class="fas fa-clock stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? ($stats['confirmed'] ?? 0) : '0'; ?></div>
                <div class="stat-label">Confirmed</div>
                <i class="fas fa-check-circle stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? ($stats['checked-in'] ?? 0) : '0'; ?></div>
                <div class="stat-label">Checked-in</div>
                <i class="fas fa-sign-in-alt stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? ($stats['checked-out'] ?? 0) : '0'; ?></div>
                <div class="stat-label">Checked-out</div>
                <i class="fas fa-sign-out-alt stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? ($stats['cancelled'] ?? 0) : '0'; ?></div>
                <div class="stat-label">Cancelled</div>
                <i class="fas fa-times-circle stat-icon"></i>
            </div>
        </div>

        <!-- VIEW TOGGLE: List View / Calendar View -->
        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-view">
                            <i class="fas fa-list me-2"></i>List View
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar-view">
                            <i class="fas fa-calendar-alt me-2"></i>Calendar View
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content">
                    <!-- LIST VIEW TAB -->
                    <div class="tab-pane fade show active" id="list-view">
                        <!-- ADVANCED FILTERS -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Advanced Filters</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" name="search" class="form-control" placeholder="Search reservations..." value="<?php echo htmlspecialchars($search_term); ?>">
                                    </div>
                                    <?php if ($user_role === 'admin'): ?>
                                    <div class="col-md-2">
                                        <label class="form-label">Branch</label>
                                        <select name="branch" class="form-control">
                                            <option value="">All Branches</option>
                                            <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo $branch['branch_id']; ?>" <?php echo $branch_filter == $branch['branch_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="checked-in" <?php echo $status_filter == 'checked-in' ? 'selected' : ''; ?>>Checked-in</option>
                                            <option value="checked-out" <?php echo $status_filter == 'checked-out' ? 'selected' : ''; ?>>Checked-out</option>
                                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Date From</label>
                                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Date To</label>
                                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <a href="reservations.php" class="btn btn-secondary w-100">Clear</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Reservations Table -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list-alt me-2"></i>
                                    <?php echo $user_role === 'admin' ? 'All Reservations' : 'My Unit Reservations'; ?>
                                    <small style="color: white;">(Showing <?php echo count($reservations); ?> records)</small>
                                </h5>
                                <div class="btn-group">
                                    <button class="btn btn-outline-primary" onclick="reservationManager.refreshReservations()">
                                        <i class="fas fa-sync-alt me-2"></i>Refresh
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Reservation ID</th>
                                                <th>Renter Name</th>
                                                <th>Unit Name</th>
                                                <th>Branch</th>
                                                <?php if ($user_role === 'admin'): ?>
                                                <th>Host Name</th>
                                                <?php endif; ?>
                                                <th>Check-in</th>
                                                <th>Check-out</th>
                                                <th>Total Amount</th>
                                                <th>Payment Status</th>
                                                <th>Reservation Status</th>
                                                <th>Created Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($db_connected && !empty($reservations)): ?>
                                            <?php foreach ($reservations as $res): ?>
                                            <tr>
                                                <td>
                                                    <strong>RES-<?php echo $res['reservation_id']; ?></strong>
                                                    <br>
                                                    <small class="text-muted">ID: <?php echo $res['reservation_id']; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($res['renter_name'] ?? 'User ID: ' . $res['user_id']); ?></td>
                                                <td><?php echo htmlspecialchars($res['unit_name'] ?? 'Unit ID: ' . $res['unit_id']); ?></td>
                                                <td><?php echo htmlspecialchars($res['branch_name'] ?? 'Branch ID: ' . $res['branch_id']); ?></td>
                                                <?php if ($user_role === 'admin'): ?>
                                                <td><?php echo htmlspecialchars($res['host_name'] ?? 'N/A'); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo date('M j, Y', strtotime($res['check_in_date'])); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($res['check_out_date'])); ?></td>
                                                <td>₱<?php echo number_format($res['total_amount'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $payment_badge_class = [
                                                        'paid' => 'badge-paid',
                                                        'pending' => 'badge-pending-payment',
                                                        'completed' => 'badge-paid',
                                                        'refunded' => 'badge-refunded'
                                                    ][$res['payment_status']] ?? 'badge-secondary';
                                                    ?>
                                                    <span class="status-badge <?php echo $payment_badge_class; ?>">
                                                        <?php echo ucfirst($res['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_mapping = [
                                                        'pending' => 'badge-pending',
                                                        'confirmed' => 'badge-confirmed',
                                                        'checked-in' => 'badge-checked-in',
                                                        'checked-out' => 'badge-checked-out',
                                                        'cancelled' => 'badge-cancelled',
                                                        'completed' => 'badge-completed'
                                                    ];

                                                    $status_value = strtolower($res['reservation_status']);
                                                    $status_badge_class = $status_mapping[$status_value] ?? 'badge-secondary';
                                                    ?>
                                                    <span class="status-badge <?php echo $status_badge_class; ?>">
                                                        <?php echo ucfirst(str_replace('-', ' ', $status_value)); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($res['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- Server-side action forms (no AJAX) -->
                                                        <form method="POST" action="process_reservation.php" style="display:inline-block;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                            <button type="submit" name="action" value="view" class="btn btn-outline-primary" title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>

                                                        <form method="GET" action="edit_reservation.php" style="display:inline-block;">
                                                            <input type="hidden" name="id" value="<?php echo $res['reservation_id']; ?>">
                                                            <button type="submit" class="btn btn-outline-warning" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>

                                                        <!-- Approve/Reject (server-side) -->
                                                        <?php if (in_array($res['reservation_status'], ['awaiting_approval','pending'])): ?>
                                                            <form method="POST" action="process_reservation.php" style="display:inline-block;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                                <button type="submit" name="action" value="approve" class="btn btn-success" title="Approve">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="process_reservation.php" style="display:inline-block;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                                <button type="submit" name="action" value="reject" class="btn btn-danger" title="Reject">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <form method="POST" action="process_reservation.php" style="display:inline-block;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                            <button type="submit" name="action" value="notes" class="btn btn-outline-info" title="View/Add Notes">
                                                                <i class="fas fa-sticky-note"></i>
                                                            </button>
                                                        </form>

                                                        <?php if (!empty($res['special_requests'])): ?>
                                                            <button class="btn btn-outline-warning view-requests" title="View Special Requests" data-requests="<?php echo htmlspecialchars($res['special_requests']); ?>">
                                                                <i class="fas fa-comment-alt"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if (in_array($res['reservation_status'], ['pending', 'confirmed'])): ?>
                                                            <form method="POST" action="process_reservation.php" style="display:inline-block;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                                <input type="hidden" name="new_status" value="checked-in">
                                                                <button type="submit" name="action" value="update_status" class="btn btn-outline-success" title="Check-in">
                                                                    <i class="fas fa-sign-in-alt"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if ($res['reservation_status'] === 'checked-in'): ?>
                                                            <form method="POST" action="process_reservation.php" style="display:inline-block;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                                <input type="hidden" name="new_status" value="checked-out">
                                                                <button type="submit" name="action" value="update_status" class="btn btn-outline-info" title="Check-out">
                                                                    <i class="fas fa-sign-out-alt"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if (in_array($res['reservation_status'], ['pending', 'confirmed', 'checked-in'])): ?>
                                                            <form method="POST" action="process_reservation.php" style="display:inline-block;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                                <input type="hidden" name="new_status" value="cancelled">
                                                                <button type="submit" name="action" value="update_status" class="btn btn-outline-danger" title="Cancel">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php elseif (!$db_connected): ?>
                                            <tr>
                                                <td colspan="<?php echo $user_role === 'admin' ? '12' : '11'; ?>" class="text-center py-4">
                                                    <i class="fas fa-database fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">Database not connected</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo $user_role === 'admin' ? '12' : '11'; ?>" class="text-center py-4">
                                                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">No reservations found</p>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CALENDAR VIEW TAB -->
                    <!-- Calendar View Tab -->
<div class="tab-pane fade" id="calendar-view">
    <div class="calendar-container">
        <div class="card calendar-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Booking Calendar
                    <small class="opacity-75">- All Reservations</small>
                </h5>
            </div>
            <div class="card-body">
                <div id="booking-calendar"></div>
            </div>
        </div>
    </div>
</div>

        <!-- Reports Section for Admin -->
        <?php if ($user_role === 'admin'): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                                <h5>Export to Excel</h5>
                                <p class="text-muted">Download reservation data in Excel format</p>
                                <button class="btn btn-success" onclick="reservationManager.exportToExcel()">
                                    <i class="fas fa-download me-2"></i>Download Excel
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                <h5>Export to PDF</h5>
                                <p class="text-muted">Generate PDF report of reservations</p>
                                <button class="btn btn-danger" onclick="reservationManager.generatePDFReport()">
                                    <i class="fas fa-file-pdf me-2"></i>Generate PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-envelope fa-3x text-primary mb-3"></i>
                                <h5>Send Notifications</h5>
                                <p class="text-muted">Send status updates to renters</p>
                                <button class="btn btn-primary" onclick="reservationManager.showNotificationModal()">
                                    <i class="fas fa-paper-plane me-2"></i>Send Notifications
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modals Container -->
<div id="modals-container"></div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border: 2px solid #dc3545; border-radius: 10px; box-shadow: 0 5px 25px rgba(220, 53, 69, 0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; border-radius: 8px 8px 0 0;">
                <h5 class="modal-title" id="rejectModalLabel">
                    <i class="fas fa-ban"></i> Reject Reservation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <input type="hidden" id="rejectReservationId">
                
                <div class="alert alert-warning" style="border-left: 4px solid #ffc107;">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone. The renter will be notified of the rejection.
                </div>
                
                <div class="mb-3">
                    <label for="rejectionReason" class="form-label fw-bold">Rejection Reason <span style="color: #dc3545;">*</span></label>
                    <textarea class="form-control" id="rejectionReason" placeholder="Please explain why you're rejecting this reservation..." required style="border: 1px solid #dee2e6; border-radius: 5px; min-height: 100px;"></textarea>
                    <small class="form-text text-muted d-block mt-2">The renter will see this reason in their notification.</small>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmRejectBtn" style="background: #dc3545; border-color: #dc3545; padding: 8px 20px;">
                    <i class="fas fa-ban"></i> Confirm Rejection
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="../assets/js/modules/reservations.js"></script>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Pass PHP data to JavaScript
    reservationManager.init({
        userRole: '<?php echo $user_role; ?>',
        monthlyData: <?php echo json_encode($analytics['monthly'] ?? []); ?>,
        branches: <?php echo json_encode($branches); ?>
    });
    
    // Initialize calendar if on calendar tab
    const calendarTab = document.getElementById('calendar-tab');
    if (calendarTab) {
        calendarTab.addEventListener('click', function() {
            setTimeout(() => {
                reservationManager.initializeBookingCalendar();
            }, 100);
        });
    }
});
</script>
</body>
</html>