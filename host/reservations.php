<?php
// Host Reservations Management
// View, approve, cancel, and track reservations

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle reservation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['reservation_id'])) {
        $reservation_id = (int)sanitize_input($_POST['reservation_id']);
        $action = sanitize_input($_POST['action']);
        
        // Verify the reservation belongs to this host
        $res = get_single_result(
            "SELECT r.* FROM reservations r 
             INNER JOIN units u ON r.unit_id = u.unit_id 
             INNER JOIN branches b ON r.branch_id = b.branch_id
             WHERE r.reservation_id = ? AND (u.host_id = ? OR b.host_id = ?)",
            [$reservation_id, $host_id, $host_id]
        );
        
        if ($res) {
            if ($action === 'approve') {
                // FIXED: Use prepared statement
                $updateSql = "UPDATE reservations SET status = 'confirmed' WHERE reservation_id = ?";
                execute_query($updateSql, [$reservation_id]);
                $action_message = "Reservation approved successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Reservation Approved",
                    "Your reservation #" . $reservation_id . " has been approved by the host! You can now proceed to payment.",
                    'booking',
                    'system'
                );
            } else if ($action === 'cancel') {
                $reason = sanitize_input($_POST['reason'] ?? 'Host cancelled');
                // FIXED: Use prepared statement
                $updateSql = "UPDATE reservations SET status = 'cancelled', cancellation_reason = ? WHERE reservation_id = ?";
                execute_query($updateSql, [$reason, $reservation_id]);
                $action_message = "Reservation cancelled successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Reservation Cancelled",
                    "Your reservation #" . $reservation_id . " has been cancelled. Reason: " . $reason,
                    'booking',
                    'system'
                );
            } else if ($action === 'checkin') {
                // FIXED: Use prepared statement
                $updateSql = "UPDATE reservations SET status = 'checked_in', checked_in_by = ? WHERE reservation_id = ?";
                execute_query($updateSql, [$host_id, $reservation_id]);
                $action_message = "Guest checked in successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Checked In",
                    "You have been checked in to reservation #" . $reservation_id . ".",
                    'booking',
                    'system'
                );
            } else if ($action === 'checkout') {
                // FIXED: Use prepared statement
                $updateSql = "UPDATE reservations SET status = 'completed', checked_out_by = ? WHERE reservation_id = ?";
                execute_query($updateSql, [$host_id, $reservation_id]);
                $action_message = "Guest checked out successfully!";
                $action_success = true;
                
                // Send notification to renter
                sendNotification(
                    $res['user_id'],
                    "Checked Out",
                    "Your checkout for reservation #" . $reservation_id . " has been completed.",
                    'booking',
                    'system'
                );
            }
        } else {
            $action_message = "Reservation not found or access denied";
        }
    }
}

// Get all reservations for this host - FIXED: Use prepared statement
$all_reservations = get_multiple_results("
    SELECT 
        r.*,
        u.unit_number,
        u.unit_type,
        us.full_name as renter_name,
        us.email as renter_email,
        us.phone as renter_phone
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN branches b ON r.branch_id = b.branch_id
    INNER JOIN users us ON r.user_id = us.user_id
    WHERE (u.host_id = ? OR b.host_id = ?)
    ORDER BY r.check_in_date DESC
", [$host_id, $host_id]);

// Get counts by status - FIXED: Use prepared statement
$status_counts = [];
$statuses = ['awaiting_approval', 'confirmed', 'checked_in', 'completed', 'cancelled'];
foreach ($statuses as $status) {
    $count_result = get_single_result(
        "SELECT COUNT(*) as cnt FROM reservations r 
         INNER JOIN units u ON r.unit_id = u.unit_id 
         INNER JOIN branches b ON r.branch_id = b.branch_id
         WHERE (u.host_id = ? OR b.host_id = ?) AND r.status = ?",
        [$host_id, $host_id, $status]
    );
    $status_counts[$status] = $count_result ? $count_result['cnt'] : 0;
}

// Get upcoming check-ins - FIXED: Use prepared statement
$upcoming_checkins = get_multiple_results("
    SELECT 
        r.*,
        u.unit_number,
        u.unit_type,
        us.full_name as renter_name
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN branches b ON r.branch_id = b.branch_id
    INNER JOIN users us ON r.user_id = us.user_id
    WHERE (u.host_id = ? OR b.host_id = ?)
    AND r.status IN ('confirmed')
    AND DATE(r.check_in_date) = CURDATE()
    ORDER BY r.check_in_date ASC
", [$host_id, $host_id]);

$page_title = 'Reservations';
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
        
        .reservations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .reservation-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .reservation-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .reservation-unit {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .reservation-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cfe2ff; color: #084298; }
        .status-checked_in { background: #d1e7dd; color: #0f5132; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-cancelled { background: #f8d7da; color: #842029; }
        
        .reservation-info {
            margin-bottom: 15px;
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .renter-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .renter-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .renter-contact {
            font-size: 12px;
            color: #666;
        }
        
        .dates-section {
            background: #ecf0f1;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .date-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .date-row:last-child {
            margin-bottom: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 15px;
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
        
        .btn-cancel {
            background: #dc3545;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #c82333;
        }
        
        .btn-checkin {
            background: #17a2b8;
            color: white;
        }
        
        .btn-checkin:hover {
            background: #138496;
        }
        
        .btn-checkout {
            background: #6f42c1;
            color: white;
        }
        
        .btn-checkout:hover {
            background: #5a32a3;
        }
        
        .btn-disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .upcoming-section {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        
        .upcoming-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        
        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .upcoming-item {
            background: white;
            padding: 12px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
                <h1><i class="fas fa-calendar-check"></i> Reservations</h1>
            </div>
            
            <!-- Success/Error Message -->
            <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>" style="margin-bottom: 20px;">
                <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Check-ins Alert -->
            <?php if (!empty($upcoming_checkins)): ?>
            <div class="upcoming-section">
                <h3><i class="fas fa-clock"></i> Today's Check-ins</h3>
                <div class="upcoming-list">
                    <?php foreach ($upcoming_checkins as $checkin): ?>
                    <div class="upcoming-item">
                        <div>
                            <strong><?php echo htmlspecialchars($checkin['renter_name']); ?></strong>
                            <small style="display: block; color: #666;">Unit: <?php echo htmlspecialchars($checkin['unit_type']); ?> (#<?php echo $checkin['unit_number']; ?>)</small>
                        </div>
                        <button onclick="quickCheckin(<?php echo $checkin['reservation_id']; ?>)" class="btn btn-checkin" style="margin: 0;">
                            <i class="fas fa-sign-in-alt"></i> Check In Now
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
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
            
            <!-- Reservations Grid -->
            <div class="reservations-grid" id="reservations-container">
                <?php if (empty($all_reservations)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-inbox"></i>
                        <p>No reservations yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_reservations as $res): ?>
                    <div class="reservation-card" data-status="<?php echo $res['status']; ?>">
                        <div class="reservation-header">
                            <div class="reservation-unit">
                                <?php echo htmlspecialchars($res['unit_type']); ?>
                                <small style="display: block; color: #999; font-weight: normal;">Unit #<?php echo $res['unit_number']; ?></small>
                            </div>
                            <span class="reservation-status status-<?php echo $res['status']; ?>">
                                <?php echo ucfirst($res['status']); ?>
                            </span>
                        </div>
                        
                        <div class="renter-info">
                            <div class="renter-name"><?php echo htmlspecialchars($res['renter_name']); ?></div>
                            <div class="renter-contact">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($res['renter_email']); ?><br>
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($res['renter_phone'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        
                        <div class="dates-section">
                            <div class="date-row">
                                <span><i class="fas fa-sign-in-alt"></i> Check-in:</span>
                                <strong><?php echo date('M d, Y', strtotime($res['check_in_date'])); ?></strong>
                            </div>
                            <div class="date-row">
                                <span><i class="fas fa-sign-out-alt"></i> Check-out:</span>
                                <strong><?php echo date('M d, Y', strtotime($res['check_out_date'])); ?></strong>
                            </div>
                        </div>
                        
                        <div class="reservation-info">
                            <div class="info-row">
                                <span class="info-label">Duration:</span>
                                <span class="info-value"><?php echo (int)((strtotime($res['check_out_date']) - strtotime($res['check_in_date'])) / 86400); ?> nights</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Amount:</span>
                                <span class="info-value">₱<?php echo number_format($res['total_amount'], 2); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Payment:</span>
                                <span class="info-value"><?php echo ucfirst($res['payment_status']); ?></span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <?php if ($res['status'] === 'awaiting_approval'): ?>
                                <form method="POST" style="display:inline; flex: 1;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Are you sure you want to APPROVE this reservation? The renter will be notified.')" style="width: 100%;">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-cancel" onclick="showRejectModal(<?php echo $res['reservation_id']; ?>)" style="flex: 1; margin-left: 8px;">
                                    <i class="fas fa-ban"></i> Reject
                                </button>
                            <?php elseif ($res['status'] === 'confirmed'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="checkin">
                                    <button type="submit" class="btn btn-checkin" onclick="return confirm('Check in guest now?')">
                                        <i class="fas fa-sign-in-alt"></i> Check In
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="reason" value="Host cancelled">
                                    <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this reservation?')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </form>
                            <?php elseif ($res['status'] === 'checked_in'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                    <input type="hidden" name="action" value="checkout">
                                    <button type="submit" class="btn btn-checkout" onclick="return confirm('Check out guest now?')">
                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-disabled" disabled>
                                    <?php echo ucfirst($res['status']); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Rejection Modal -->
    <style>
        #rejectModal.modal {
            display: none !important;
        }
        #rejectModal.show {
            display: block !important;
        }
    </style>
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border: 2px solid #dc3545; border-radius: 10px; box-shadow: 0 5px 25px rgba(220, 53, 69, 0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; border-radius: 8px 8px 0 0;">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="fas fa-ban"></i> Reject Reservation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body" style="padding: 25px;">
                        <input type="hidden" id="rejectReservationId" name="reservation_id">
                        <input type="hidden" name="action" value="cancel">
                        
                        <div class="alert alert-warning" style="border-left: 4px solid #ffc107;">
                            <i class="fas fa-exclamation-triangle"></i> This action cannot be undone. The renter will be notified of the rejection.
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label fw-bold">Reason for Rejection <span style="color: #dc3545;">*</span></label>
                            <textarea class="form-control" id="rejectionReason" name="reason" placeholder="Provide a reason for rejecting this booking..." rows="4" required style="border: 1px solid #dee2e6; border-radius: 5px;"></textarea>
                            <small class="form-text text-muted d-block mt-2">The renter will see this reason in their notification.</small>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-danger" style="background: #dc3545; border-color: #dc3545; padding: 8px 20px;">
                            <i class="fas fa-ban"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByStatus(status) {
            const cards = document.querySelectorAll('.reservation-card');
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
        
        function showRejectModal(reservationId) {
            document.getElementById('rejectReservationId').value = reservationId;
            document.getElementById('rejectionReason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }
        
        function quickCheckin(reservationId) {
            if (confirm('Check in guest now?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="reservation_id" value="${reservationId}">
                    <input type="hidden" name="action" value="checkin">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
