<?php
// Payment Management Module
// SECURITY FIX: Proper session and role validation

include_once dirname(__FILE__) . '/../includes/session.php';
include_once dirname(__FILE__) . '/../includes/functions.php';
include_once dirname(__FILE__) . '/../config/db.php';

// CRITICAL: Only admins can access payment management
checkRole(['admin']);

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get user info
$user_role = $_SESSION['role'] ?? 'guest';
$is_admin = ($user_role === 'admin');
$is_host = ($user_role === 'host');
$user_id = $_SESSION['user_id'] ?? 0;

// Get branches for filter
function getBranches($conn) {
    $sql = "SELECT branch_id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name";
    return get_multiple_results($sql);
}

// Get hosts for filter - using your users table structure
function getHosts($conn) {
    $sql = "SELECT user_id, full_name as username FROM users WHERE role = 'host' AND is_active = 1 ORDER BY full_name";
    return get_multiple_results($sql);
}

// Get all payments with filters - UPDATED for your table structure
function getAllPayments($conn, $filters = []) {
    $query = "SELECT p.*";
    
    // Add joined fields using your actual table structure
    $query .= ", r.reservation_id, r.check_in_date as check_in, r.check_out_date as check_out, r.total_amount";
    $query .= ", u.full_name as renter_name, u.email as renter_email, u.phone as renter_phone";
    $query .= ", unit.unit_name as property_name, unit.branch_id";
    $query .= ", host.full_name as host_name, host.email as host_email, host.phone as host_phone";
    $query .= ", b.branch_name, b.address as branch_address, b.contact_number as branch_phone";
    
    $query .= " FROM payments p";
    $query .= " LEFT JOIN reservations r ON p.reservation_id = r.reservation_id";
    $query .= " LEFT JOIN users u ON p.user_id = u.user_id";
    $query .= " LEFT JOIN units unit ON r.unit_id = unit.unit_id";
    $query .= " LEFT JOIN users host ON unit.host_id = host.user_id";
    $query .= " LEFT JOIN branches b ON unit.branch_id = b.branch_id";
    $query .= " WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['branch_id'])) {
        $query .= " AND unit.branch_id = ?";
        $params[] = $filters['branch_id'];
    }
    
    if (!empty($filters['host_id'])) {
        $query .= " AND unit.host_id = ?";
        $params[] = $filters['host_id'];
    }
    
    if (!empty($filters['renter_name'])) {
        $query .= " AND u.full_name LIKE ?";
        $params[] = "%" . $filters['renter_name'] . "%";
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND p.payment_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(p.payment_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(p.payment_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $query .= " ORDER BY p.payment_date DESC";
    
    return get_multiple_results($query, $params);
}

// Get host payments - UPDATED for your table structure
function getHostPayments($conn, $host_id, $filters = []) {
    $query = "SELECT p.*";
    $query .= ", r.reservation_id, r.check_in_date as check_in, r.check_out_date as check_out";
    $query .= ", u.full_name as renter_name, u.email as renter_email";
    $query .= ", unit.unit_name as property_name";
    $query .= ", b.branch_name";
    
    $query .= " FROM payments p";
    $query .= " LEFT JOIN reservations r ON p.reservation_id = r.reservation_id";
    $query .= " LEFT JOIN users u ON p.user_id = u.user_id";
    $query .= " LEFT JOIN units unit ON r.unit_id = unit.unit_id";
    $query .= " LEFT JOIN branches b ON unit.branch_id = b.branch_id";
    $query .= " WHERE unit.host_id = ?";
    
    $params = [$host_id];
    
    if (!empty($filters['renter_name'])) {
        $query .= " AND u.full_name LIKE ?";
        $params[] = "%" . $filters['renter_name'] . "%";
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND p.payment_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(p.payment_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(p.payment_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $query .= " ORDER BY p.payment_date DESC";
    
    return get_multiple_results($query, $params);
}

// Get revenue analytics - UPDATED for your table structure
function getRevenueAnalytics($conn, $filters = []) {
    $query = "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN payment_status = 'refunded' THEN amount ELSE 0 END) as refunded_amount,
                AVG(CASE WHEN payment_status = 'paid' THEN amount ELSE NULL END) as avg_transaction,
                payment_method,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 ELSE NULL END) as successful_payments
              FROM payments p
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(payment_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(payment_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $query .= " GROUP BY payment_method";
    
    return get_multiple_results($query, $params);
}

// Get host revenue summary - UPDATED for your table structure
function getHostRevenueSummary($conn, $host_id, $filters = []) {
    $query = "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN p.payment_status = 'pending' THEN p.amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN p.payment_status = 'refunded' THEN p.amount ELSE 0 END) as refunded_amount,
                AVG(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE NULL END) as avg_transaction
              FROM payments p
              LEFT JOIN reservations r ON p.reservation_id = r.reservation_id
              LEFT JOIN units unit ON r.unit_id = unit.unit_id
              WHERE unit.host_id = ?";
    
    $params = [$host_id];
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(p.payment_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(p.payment_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $result = get_single_result($query, $params);
    return $result ?: [];
}

// Get payment details - UPDATED for your table structure
function getPaymentDetails($conn, $payment_id) {
    $query = "SELECT p.*";
    $query .= ", r.reservation_id, r.check_in_date as check_in, r.check_out_date as check_out, r.total_amount";
    $query .= ", u.full_name as renter_name, u.email as renter_email, u.phone as renter_phone, u.address as renter_address";
    $query .= ", unit.unit_name as property_name, unit.unit_type as property_type, unit.address as property_address";
    $query .= ", host.full_name as host_name, host.email as host_email, host.phone as host_phone";
    $query .= ", b.branch_name, b.address as branch_address, b.contact_number as branch_phone";
    
    $query .= " FROM payments p";
    $query .= " LEFT JOIN reservations r ON p.reservation_id = r.reservation_id";
    $query .= " LEFT JOIN users u ON p.user_id = u.user_id";
    $query .= " LEFT JOIN units unit ON r.unit_id = unit.unit_id";
    $query .= " LEFT JOIN users host ON unit.host_id = host.user_id";
    $query .= " LEFT JOIN branches b ON unit.branch_id = b.branch_id";
    $query .= " WHERE p.payment_id = ?";
    
    return get_single_result($query, [$payment_id]);
}

// Check if required tables exist
function checkRequiredTables($conn) {
    $required_tables = ['payments', 'reservations', 'users', 'units', 'branches'];
    $existing_tables = [];
    
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $existing_tables[] = $row[0];
    }
    
    foreach ($required_tables as $table) {
        if (!in_array($table, $existing_tables)) {
            return false;
        }
    }
    
    return true;
}

// Get filter data
$branches = getBranches($conn);
$hosts = getHosts($conn);

// Process filters
$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $filters = array_filter([
        'branch_id' => $_POST['branch_id'] ?? '',
        'host_id' => $_POST['host_id'] ?? '',
        'renter_name' => $_POST['renter_name'] ?? '',
        'status' => $_POST['status'] ?? '',
        'start_date' => $_POST['start_date'] ?? '',
        'end_date' => $_POST['end_date'] ?? ''
    ]);
}

// Get payments based on user role - ACTUAL DATA
if ($is_admin) {
    $payments = getAllPayments($conn, $filters);
    $analytics = getRevenueAnalytics($conn, $filters);
} else {
    $payments = getHostPayments($conn, $user_id, $filters);
    $host_revenue = getHostRevenueSummary($conn, $user_id, $filters);
}

// Handle status updates
if (isset($_POST['update_status'])) {
    $payment_id = $_POST['payment_id'];
    $new_status = $_POST['new_status'];
    $refund_reason = $_POST['refund_reason'] ?? null;
    
    if ($refund_reason) {
        $sql = "UPDATE payments SET payment_status = ?, refund_reason = ? WHERE payment_id = ?";
        $params = [$new_status, $refund_reason, $payment_id];
    } else {
        $sql = "UPDATE payments SET payment_status = ? WHERE payment_id = ?";
        $params = [$new_status, $payment_id];
    }
    
    if (execute_query($sql, $params)) {
        $_SESSION['success_message'] = "Payment status updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error_message = "Failed to update payment status.";
    }
}

// Handle success message
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css">
    <link rel="stylesheet" href="../assets/css/admin/admin-common.css">
    <link rel="stylesheet" href="../assets/css/modules/payment_management.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class="fas fa-money-bill-wave me-2"></i>Payment Management</h1>
            <p>Monitor and manage all payment transactions</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Database Check -->
        <?php if (!checkRequiredTables($conn)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Database Setup Required:</strong> Some required tables are missing. Please make sure all tables (payments, reservations, users, units, branches) exist in your database.
        </div>
        <?php endif; ?>

        <!-- Analytics Dashboard -->
        <?php if ($is_admin && checkRequiredTables($conn)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="analytics-card p-4">
                    <h4 class="mb-4"><i class="fas fa-chart-line me-2"></i>Revenue Overview</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0">₱<?php echo number_format(array_sum(array_column($analytics, 'total_revenue')) ?: 0, 2); ?></h3>
                                <small>Total Revenue</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0">₱<?php echo number_format(array_sum(array_column($analytics, 'pending_amount')) ?: 0, 2); ?></h3>
                                <small>Pending Payments</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0"><?php echo array_sum(array_column($analytics, 'total_transactions')) ?: 0; ?></h3>
                                <small>Total Transactions</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0">₱<?php 
                                    $avg_transactions = array_filter(array_column($analytics, 'avg_transaction'));
                                    echo !empty($avg_transactions) ? number_format(array_sum($avg_transactions) / count($avg_transactions), 2) : '0.00'; 
                                ?></h3>
                                <small>Average Transaction</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($is_host && checkRequiredTables($conn)): ?>
        <!-- Host Revenue Summary -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="analytics-card p-4">
                    <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>My Revenue Summary</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0">₱<?php echo number_format($host_revenue['total_revenue'] ?? 0, 2); ?></h3>
                                <small>Total Revenue</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0">₱<?php echo number_format($host_revenue['pending_amount'] ?? 0, 2); ?></h3>
                                <small>Pending Payments</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0"><?php echo $host_revenue['total_transactions'] ?? 0; ?></h3>
                                <small>Total Transactions</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="mb-0">₱<?php echo number_format($host_revenue['avg_transaction'] ?? 0, 2); ?></h3>
                                <small>Average Transaction</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filter-section">
            <form method="POST" class="row g-3">
                <?php if ($is_admin && !empty($branches)): ?>
                <div class="col-md-3">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-select">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>" 
                                <?php echo isset($filters['branch_id']) && $filters['branch_id'] == $branch['branch_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if ($is_admin && !empty($hosts)): ?>
                <div class="col-md-3">
                    <label class="form-label">Host</label>
                    <select name="host_id" class="form-select">
                        <option value="">All Hosts</option>
                        <?php foreach ($hosts as $host): ?>
                            <option value="<?php echo $host['user_id']; ?>" 
                                <?php echo isset($filters['host_id']) && $filters['host_id'] == $host['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($host['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-2">
                    <label class="form-label">Renter Name</label>
                    <input type="text" name="renter_name" class="form-control" 
                           placeholder="Search renter..." 
                           value="<?php echo $filters['renter_name'] ?? ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo isset($filters['status']) && $filters['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo isset($filters['status']) && $filters['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="refunded" <?php echo isset($filters['status']) && $filters['status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        <option value="failed" <?php echo isset($filters['status']) && $filters['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo $filters['start_date'] ?? ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo $filters['end_date'] ?? ''; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" name="apply_filters" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <a href="payment_management.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Payments List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?php echo $is_admin ? 'All Payments' : 'My Payments'; ?>
                    <span class="badge bg-primary ms-2"><?php echo count($payments); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No payments found in the database.</p>
                        <p class="text-muted small">Payments will appear here once transactions are recorded in your payments table.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Reservation ID</th>
                                    <th>Renter</th>
                                    <th>Unit</th>
                                    <?php if ($is_admin): ?>
                                    <th>Host</th>
                                    <th>Branch</th>
                                    <?php endif; ?>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['payment_id']; ?></td>
                                    <td>#<?php echo $payment['reservation_id'] ?? 'N/A'; ?></td>
                                    <td>
                                        <?php if (!empty($payment['renter_name'])): ?>
                                            <div><?php echo htmlspecialchars($payment['renter_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['renter_email'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <div>User #<?php echo $payment['user_id']; ?></div>
                                            <small class="text-muted">User information not available</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['property_name'] ?? 'N/A'); ?></td>
                                    <?php if ($is_admin): ?>
                                    <td>
                                        <?php if (!empty($payment['host_name'])): ?>
                                            <div><?php echo htmlspecialchars($payment['host_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['host_email'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <div>Host not found</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['branch_name'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <i class="fas fa-<?php 
                                                switch($payment['payment_method']) {
                                                    case 'gcash': echo 'mobile-alt'; break;
                                                    case 'paypal': echo 'paypal'; break;
                                                    case 'credit_card': echo 'credit-card'; break;
                                                    case 'bank_transfer': echo 'university'; break;
                                                    default: echo 'money-bill';
                                                }
                                            ?> me-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <span class="badge payment-status 
                                            <?php echo 'status-' . $payment['payment_status']; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-payment" 
                                                data-payment-id="<?php echo $payment['payment_id']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#paymentDetailsModal">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($is_admin): ?>
                                        <button class="btn btn-sm btn-outline-warning update-status" 
                                                data-payment-id="<?php echo $payment['payment_id']; ?>"
                                                data-current-status="<?php echo $payment['payment_status']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentDetailsContent">
                    <div class="text-center py-4">
                        <div class="loading"></div>
                        <p class="mt-2">Loading payment details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Payment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="payment_id" id="update_payment_id">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="new_status" class="form-select" id="new_status_select" required>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="refunded">Refunded</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="mb-3" id="refundReasonSection" style="display: none;">
                            <label class="form-label">Refund Reason</label>
                            <textarea name="refund_reason" class="form-control" rows="3" placeholder="Enter refund reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <?php if ($is_admin): ?>
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="export_payments.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Date Range</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Export CSV</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/modules/payment_management.js"></script>
</body>
</html>
<?php
// Connection will be closed automatically by register_shutdown_function in db.php
?>