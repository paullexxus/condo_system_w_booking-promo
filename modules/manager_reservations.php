<?php
session_start();

// Define site URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/BookIT');
}

// Try to include database files
try {
    require_once '../config/db.php';
    $db_connected = true;
} catch (Exception $e) {
    $db_connected = false;
    $error_message = $e->getMessage();
}

// GET ACTUAL USER DATA FROM DATABASE
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? '';
$user_data = [];

if ($db_connected && $user_id) {
    try {
        // Get actual user data from database
        $user_query = "SELECT user_id, username, full_name, email, role, phone FROM users WHERE user_id = ?";
        $user_data = get_single_result($user_query, [$user_id]);
        
        if ($user_data) {
            // Update session with actual data
            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['user_role'] = $user_data['role'];
            $_SESSION['user_name'] = $user_data['username'];
            $_SESSION['fullname'] = $user_data['full_name'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['email'] = $user_data['email'];
            $_SESSION['phone'] = $user_data['phone'] ?? '';
        } else {
            // User not found in database
            $db_connected = false;
            $error_message = "User not found in database";
        }
    } catch (Exception $e) {
        $db_connected = false;
        $error_message = $e->getMessage();
    }
}

// If no session or database error, redirect to login
if (!$db_connected || !$user_id) {
    header('Location: ' . SITE_URL . '/public/login.php');
    exit();
}

// Now use the actual data from database
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$fullname = $_SESSION['fullname'];
$username = $_SESSION['user_name'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_term = $_GET['search'] ?? '';
$unit_filter = $_GET['unit'] ?? '';

// Get manager's units for filter
$manager_units = [];
if ($db_connected && $user_role === 'manager') {
    try {
        $units_query = "SELECT unit_id, unit_name FROM units WHERE host_id = ? ORDER BY unit_name";
        $manager_units = get_multiple_results($units_query, [$user_id]);
    } catch (Exception $e) {
        $units_error = $e->getMessage();
    }
}

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

if ($db_connected) {
    try {
        // Build query based on user role
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
            r.renter_rating,
            r.renter_feedback,
            u.unit_name,
            b.branch_name,
            usr.full_name as renter_name,
            usr.email as renter_email,
            usr.phone as renter_phone
        FROM reservations r
        LEFT JOIN units u ON r.unit_id = u.unit_id
        LEFT JOIN branches b ON r.branch_id = b.branch_id
        LEFT JOIN users usr ON r.user_id = usr.user_id
        WHERE 1=1";
        
        $params = [];
        
        // Role-based filtering
        if ($user_role === 'manager') {
            $query .= " AND u.host_id = ?";
            $params[] = $user_id;
        }
        
        // Apply filters
        if (!empty($status_filter)) {
            $query .= " AND r.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($unit_filter) && $user_role === 'manager') {
            $query .= " AND r.unit_id = ?";
            $params[] = $unit_filter;
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
        
        $query .= " ORDER BY r.check_in_date DESC LIMIT 50";
        
        $reservations = get_multiple_results($query, $params);

        // Get stats
        $stats_query = "SELECT 
            r.status, 
            COUNT(*) as count 
        FROM reservations r
        LEFT JOIN units u ON r.unit_id = u.unit_id
        WHERE 1=1";
        
        $stats_params = [];
        
        if ($user_role === 'manager') {
            $stats_query .= " AND u.host_id = ?";
            $stats_params[] = $user_id;
        }
        
        $stats_query .= " GROUP BY r.status";
        
        $status_counts = get_multiple_results($stats_query, $stats_params);

        foreach ($status_counts as $row) {
            $status_key = strtolower($row['status']);
            $stats[$status_key] = $row['count'];
            $total_count += $row['count'];
        }

    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }
}
?>
<!-- REST OF YOUR HTML CODE -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reservations - Host Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<link href="../assets/css/modules/reservations.css" rel="stylesheet">
</head>
<body>
<!-- =================== SIDEBAR =================== -->
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-building"></i>
        <span>BookIT Host</span>
    </div>
    <nav class="sidebar-menu">
        <ul>
            <li><a href="<?php echo SITE_URL; ?>/host/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/host/my_units.php"><i class="fas fa-home"></i> <span>My Units</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/modules/host_reservations.php" class="active"><i class="fas fa-calendar-check"></i> <span>Reservations</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/host/payments.php"><i class="fas fa-credit-card"></i> <span>Payments</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/host/calendar.php"><i class="fas fa-calendar-alt"></i> <span>Calendar</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/host/reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
        </ul>
    </nav>

    <!-- =================== PROFILE SECTION =================== -->
    <div class="sidebar-profile">
        <div class="profile-info">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-details">
                <span class="profile-name"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                <span class="profile-role">Property Host</span>
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
                    My Unit Reservations
                    <small class="text-muted">(Host Management)</small>
                </h1>
                <p class="text-muted mb-0">Manage bookings for your properties</p>
            </div>
            <div class="text-end">
                <span class="badge bg-success fs-6">Host</span>
                <div class="text-muted mt-1">Welcome, <?php echo $_SESSION['user_name']; ?></div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? $total_count : '0'; ?></div>
                <div class="stat-label">Total Bookings</div>
                <i class="fas fa-calendar-alt stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? $stats['pending'] : '0'; ?></div>
                <div class="stat-label">Pending Approval</div>
                <i class="fas fa-clock stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? ($stats['confirmed'] ?? 0) : '0'; ?></div>
                <div class="stat-label">Confirmed</div>
                <i class="fas fa-check-circle stat-icon"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $db_connected ? ($stats['checked-in'] ?? 0) : '0'; ?></div>
                <div class="stat-label">Active Stays</div>
                <i class="fas fa-sign-in-alt stat-icon"></i>
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
                        <!-- HOST-SPECIFIC FILTERS -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Bookings</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" name="search" class="form-control" placeholder="Search by renter, unit..." value="<?php echo htmlspecialchars($search_term); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Unit</label>
                                        <select name="unit" class="form-control">
                                            <option value="">All Units</option>
                                            <?php foreach ($host_units as $unit): ?>
                                            <option value="<?php echo $unit['unit_id']; ?>" <?php echo $unit_filter == $unit['unit_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($unit['unit_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
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
                                        <label class="form-label">Check-in From</label>
                                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Check-out To</label>
                                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Reservations Table -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list-alt me-2"></i>
                                    My Unit Reservations
                                    <small class="text-muted">(Showing <?php echo count($reservations); ?> bookings)</small>
                                </h5>
                                <button class="btn btn-outline-primary" onclick="hostReservationManager.refreshReservations()">
                                    <i class="fas fa-sync-alt me-2"></i>Refresh
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Reservation ID</th>
                                                <th>Renter</th>
                                                <th>Unit</th>
                                                <th>Check-in</th>
                                                <th>Check-out</th>
                                                <th>Total Amount</th>
                                                <th>Payment</th>
                                                <th>Status</th>
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
                                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($res['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($res['renter_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($res['renter_email'] ?? 'No email'); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($res['unit_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($res['branch_name'] ?? 'No branch'); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($res['check_in_date'])); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('D', strtotime($res['check_in_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($res['check_out_date'])); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('D', strtotime($res['check_out_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong>₱<?php echo number_format($res['total_amount'], 2); ?></strong>
                                                </td>
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
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- View Details -->
                                                        <button class="btn btn-outline-primary view-details" title="View Details" data-id="<?php echo $res['reservation_id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <!-- Approve/Reject for pending -->
                                                        <?php if ($res['reservation_status'] === 'pending'): ?>
                                                            <button class="btn btn-success approve-btn" title="Approve" data-id="<?php echo $res['reservation_id']; ?>">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-danger reject-btn" title="Reject" data-id="<?php echo $res['reservation_id']; ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Status Updates -->
                                                        <?php if (in_array($res['reservation_status'], ['confirmed'])): ?>
                                                            <button class="btn btn-outline-success update-status" title="Check-in" data-id="<?php echo $res['reservation_id']; ?>" data-status="checked-in">
                                                                <i class="fas fa-sign-in-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($res['reservation_status'] === 'checked-in'): ?>
                                                            <button class="btn btn-outline-info update-status" title="Check-out" data-id="<?php echo $res['reservation_id']; ?>" data-status="checked-out">
                                                                <i class="fas fa-sign-out-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Notes -->
                                                        <button class="btn btn-outline-info notes-btn" title="View/Add Notes" data-id="<?php echo $res['reservation_id']; ?>">
                                                            <i class="fas fa-sticky-note"></i>
                                                        </button>
                                                        
                                                        <!-- Special Requests -->
                                                        <?php if (!empty($res['special_requests'])): ?>
                                                            <button class="btn btn-outline-warning view-requests" title="View Special Requests" data-requests="<?php echo htmlspecialchars($res['special_requests']); ?>">
                                                                <i class="fas fa-comment-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Feedback -->
                                                        <?php if (!empty($res['renter_feedback'])): ?>
                                                            <button class="btn btn-outline-success view-feedback" title="View Feedback" 
                                                                data-rating="<?php echo $res['renter_rating'] ?? 0; ?>"
                                                                data-feedback="<?php echo htmlspecialchars($res['renter_feedback']); ?>">
                                                                <i class="fas fa-star"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php elseif (!$db_connected): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="fas fa-database fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">Database not connected</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">No reservations found for your units</p>
                                                    <a href="<?php echo SITE_URL; ?>/host/my_units.php" class="btn btn-primary">
                                                        <i class="fas fa-plus me-2"></i>Add Units
                                                    </a>
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
                    <div class="tab-pane fade" id="calendar-view">
                        <div class="calendar-container">
                            <div class="card calendar-card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>My Booking Calendar
                                        <small class="opacity-75">- Unit Availability</small>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="host-booking-calendar"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals Container -->
<div id="modals-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="js/host_reservation_scripts.js"></script>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    hostReservationManager.init({
        userRole: 'host',
        baseUrl: '<?php echo SITE_URL; ?>/modules'
    });
    
    // Initialize calendar if on calendar tab
    const calendarTab = document.getElementById('calendar-tab');
    if (calendarTab) {
        calendarTab.addEventListener('click', function() {
            setTimeout(() => {
                hostReservationManager.initializeHostCalendar();
            }, 100);
        });
    }
});
</script>
</body>
</html>