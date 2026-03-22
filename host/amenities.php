<?php
// Host Amenities Management
// View renter amenity bookings and manage requests

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle amenity requests via form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['booking_id'])) {
        $booking_id = (int)sanitize_input($_POST['booking_id']);
        $action = sanitize_input($_POST['action']);
        
        // Verify the amenity booking belongs to this host's unit
        $booking = get_single_result(
            "SELECT ab.* FROM amenity_bookings ab 
             INNER JOIN units u ON ab.branch_id = u.branch_id 
             WHERE ab.booking_id = ? AND u.host_id = ?",
            [$booking_id, $host_id]
        );
        
        if ($booking) {
            if ($action === 'approve') {
                $conn->query("UPDATE amenity_bookings SET status = 'approved' WHERE booking_id = $booking_id");
                $action_message = "Amenity request approved!";
                $action_success = true;
            } else if ($action === 'reject') {
                $reason = sanitize_input($_POST['reason'] ?? 'Request rejected');
                $conn->query("UPDATE amenity_bookings SET status = 'rejected' WHERE booking_id = $booking_id");
                $action_message = "Amenity request rejected!";
                $action_success = true;
            } else if ($action === 'complete') {
                $conn->query("UPDATE amenity_bookings SET status = 'completed' WHERE booking_id = $booking_id");
                $action_message = "Amenity booking marked as completed!";
                $action_success = true;
            }
        } else {
            $action_message = "Amenity booking not found or access denied";
        }
    }
}

// Get all amenity bookings for this host's units
$amenity_bookings = get_multiple_results("
    SELECT 
        ab.*,
        a.amenity_name,
        a.hourly_rate,
        u.unit_name,
        u.unit_number,
        us.full_name as renter_name,
        us.email as renter_email
    FROM amenity_bookings ab
    INNER JOIN amenities a ON ab.amenity_id = a.amenity_id
    INNER JOIN units u ON ab.branch_id = u.branch_id
    INNER JOIN users us ON ab.user_id = us.user_id
    WHERE u.host_id = $host_id
    ORDER BY ab.booking_date DESC
");

// Get status counts
$status_counts = [];
$statuses = ['pending', 'approved', 'completed', 'rejected'];
foreach ($statuses as $status) {
    $count = $conn->query(
        "SELECT COUNT(*) as cnt FROM amenity_bookings ab 
         INNER JOIN units u ON ab.branch_id = u.branch_id 
         WHERE u.host_id = $host_id AND ab.status = '$status'"
    )->fetch_assoc()['cnt'];
    $status_counts[$status] = $count;
}

// Get pending approvals
$pending_approvals = get_multiple_results("
    SELECT 
        ab.*,
        a.amenity_name,
        a.hourly_rate,
        u.unit_name,
        us.full_name as renter_name
    FROM amenity_bookings ab
    INNER JOIN amenities a ON ab.amenity_id = a.amenity_id
    INNER JOIN units u ON ab.branch_id = u.branch_id
    INNER JOIN users us ON ab.user_id = us.user_id
    WHERE u.host_id = $host_id
    AND ab.status = 'pending'
    ORDER BY ab.booking_date ASC
");

$page_title = 'Amenities';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | BookIT Host</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content { padding: 30px; }
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-box i {
            color: #856404;
            font-size: 20px;
        }
        
        .status-filter {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .status-filter .btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-filter .btn:hover {
            border-color: #3498db;
            background: #ecf0f1;
        }
        
        .status-filter .btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .booking-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .amenity-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .booking-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #cfe2ff; color: #084298; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #842029; }
        
        .unit-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .unit-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .renter-name {
            color: #666;
            font-size: 13px;
        }
        
        .booking-details {
            margin-bottom: 12px;
            padding: 12px;
            background: #ecf0f1;
            border-radius: 6px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 6px;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-buttons .btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .btn-complete {
            background: #6f42c1;
            color: white;
        }
        
        .btn-complete:hover {
            background: #5a32a3;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1><i class="fas fa-star"></i> Amenities & Requests</h1>
            </div>
            
            <!-- Success/Error Message -->
            <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>" style="margin-bottom: 20px;">
                <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Pending Alert -->
            <?php if ($status_counts['pending'] > 0): ?>
            <div class="alert-box">
                <i class="fas fa-exclamation-circle"></i>
                <span><strong><?php echo $status_counts['pending']; ?></strong> pending amenity request(s) awaiting your approval</span>
            </div>
            <?php endif; ?>
            
            <!-- Status Filter -->
            <div class="status-filter">
                <button class="btn active" onclick="filterByStatus('all')">
                    All (<?php echo array_sum($status_counts); ?>)
                </button>
                <?php foreach ($statuses as $status): ?>
                <button class="btn" onclick="filterByStatus('<?php echo $status; ?>')">
                    <?php echo ucfirst($status); ?> (<?php echo $status_counts[$status]; ?>)
                </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Bookings Grid -->
            <div class="bookings-grid" id="bookings-container">
                <?php if (empty($amenity_bookings)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-inbox"></i>
                        <p>No amenity bookings yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($amenity_bookings as $booking): ?>
                    <div class="booking-card" data-status="<?php echo $booking['status']; ?>">
                        <div class="booking-header">
                            <div class="amenity-name">
                                <i class="fas fa-star"></i> <?php echo htmlspecialchars($booking['amenity_name']); ?>
                            </div>
                            <span class="booking-status status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                        
                        <div class="unit-info">
                            <div class="unit-name">
                                <?php echo htmlspecialchars($booking['unit_name']); ?> (#<?php echo $booking['unit_number']; ?>)
                            </div>
                            <div class="renter-name">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['renter_name']); ?>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-row">
                                <span class="detail-label">Duration:</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($booking['start_time'])); ?> to <?php echo date('M d, Y', strtotime($booking['end_time'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Hours:</span>
                                <span class="detail-value">
                                    <?php 
                                    $hours = (strtotime($booking['end_time']) - strtotime($booking['start_time'])) / 3600;
                                    echo round($hours, 1);
                                    ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Rate:</span>
                                <span class="detail-value">₱<?php echo number_format($booking['hourly_rate'], 2); ?>/hr</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total:</span>
                                <span class="detail-value">₱<?php echo number_format($booking['total_amount'] ?? 0, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <?php if ($booking['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this amenity request?')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="reason" value="Request rejected">
                                    <button type="submit" class="btn btn-reject" onclick="return confirm('Reject this amenity request?')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            <?php elseif ($booking['status'] === 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-complete" onclick="return confirm('Mark this amenity booking as completed?')">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByStatus(status) {
            const cards = document.querySelectorAll('.booking-card');
            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
            
            document.querySelectorAll('.status-filter .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
