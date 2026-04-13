<?php
// BookIT Host Booking Approval System
// Managers/Hosts approve or reject bookings for their branch

include_once '../includes/session.php';
include_once '../includes/functions.php';
checkRole(['host']); // Only hosts can approve
// File paths helper for uploads
include_once '../config/file_paths.php';

$message = '';
$error = '';
$pendingBookings = [];
$approvedBookings = [];
$rejectedBookings = [];

// Get host's branch
$hostBranch = get_single_result(
    "SELECT b.* FROM branches b JOIN users u ON b.branch_id = u.branch_id WHERE u.user_id = ?",
    [$_SESSION['user_id']]
);

if (!$hostBranch) {
    $error = "You are not assigned to any branch.";
} else {
    // Handle approval
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_booking'])) {
        $reservationId = (int)$_POST['reservation_id'];
        
        if (approveReservation($reservationId, $_SESSION['user_id'])) {
            $message = "Booking approved successfully! Renter notified.";
        } else {
            $error = "Failed to approve booking.";
        }
    }
    
    // Handle rejection
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_booking'])) {
        $reservationId = (int)$_POST['reservation_id'];
        $reason = sanitize_input($_POST['rejection_reason'] ?? '');
        
        if (empty($reason)) {
            $error = "Please provide a rejection reason.";
        } else if (rejectReservation($reservationId, $_SESSION['user_id'], $reason)) {
            $message = "Booking rejected. Renter notified of rejection.";
        } else {
            $error = "Failed to reject booking.";
        }
    }
    
    // Handle payment approval by host (manual verification)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_payment'])) {
        $paymentId = (int)$_POST['payment_id'];

        // Get payment and reservation
        $payment = get_single_result("SELECT * FROM payments WHERE payment_id = ?", [$paymentId]);
        if ($payment) {
            $reservation = get_single_result("SELECT * FROM reservations WHERE reservation_id = ?", [$payment['reservation_id']]);
            if ($reservation && $reservation['branch_id'] == $hostBranch['branch_id']) {
                // Mark payment completed
                execute_query("UPDATE payments SET payment_status = 'completed' WHERE payment_id = ?", [$paymentId]);

                // Update reservation: set payment_status and confirm booking
                execute_query("UPDATE reservations SET payment_status = 'paid', status = 'confirmed', confirmed_at = NOW() WHERE reservation_id = ?", [$payment['reservation_id']]);

                // Reserve unit (mark unavailable)
                execute_query("UPDATE units SET is_available = 0 WHERE unit_id = ?", [$reservation['unit_id']]);

                // Notify renter
                sendNotification($reservation['user_id'], 'Payment Approved', 'Your payment for reservation #' . $reservation['reservation_id'] . ' has been approved. Booking confirmed.', 'payment', 'system');

                $message = 'Payment approved and reservation confirmed.';
            } else {
                $error = 'Reservation not found or not in your branch.';
            }
        } else {
            $error = 'Payment record not found.';
        }
    }

    // Handle payment rejection by host
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_payment'])) {
        $paymentId = (int)$_POST['payment_id'];
        $reason = sanitize_input($_POST['payment_reject_reason'] ?? '');

        if (empty($reason)) {
            $error = 'Please provide a rejection reason.';
        } else {
            $payment = get_single_result("SELECT * FROM payments WHERE payment_id = ?", [$paymentId]);
            if ($payment) {
                $reservation = get_single_result("SELECT * FROM reservations WHERE reservation_id = ?", [$payment['reservation_id']]);
                if ($reservation && $reservation['branch_id'] == $hostBranch['branch_id']) {
                    // Mark payment failed
                    execute_query("UPDATE payments SET payment_status = 'failed' WHERE payment_id = ?", [$paymentId]);

                    // Update reservation: mark rejected with reason so renter can re-upload or cancel
                    execute_query("UPDATE reservations SET status = 'rejected', rejection_reason = ?, rejected_at = NOW() WHERE reservation_id = ?", [$reason, $payment['reservation_id']]);

                    // Notify renter
                    sendNotification($reservation['user_id'], 'Payment Rejected', 'Your payment for reservation #' . $reservation['reservation_id'] . ' was rejected. Reason: ' . $reason, 'payment', 'system');

                    $message = 'Payment rejected and renter notified.';
                } else {
                    $error = 'Reservation not found or not in your branch.';
                }
            } else {
                $error = 'Payment record not found.';
            }
        }
    }
    
    // Get pending bookings for this branch
    $pendingBookings = get_multiple_results(
        "SELECT r.*, u.unit_number, u.unit_type, u.price_per_night, u.price_per_month, u.pricing_type, 
                renter.full_name as renter_name, renter.email as renter_email, renter.phone as renter_phone
         FROM reservations r 
         JOIN units u ON r.unit_id = u.unit_id 
         JOIN users renter ON r.user_id = renter.user_id 
         WHERE r.branch_id = ? AND r.status = 'awaiting_approval'
         ORDER BY r.created_at DESC",
        [$hostBranch['branch_id']]
    );
    
    // Get approved bookings
    $approvedBookings = get_multiple_results(
        "SELECT r.*, u.unit_number, u.unit_type, 
                renter.full_name as renter_name
         FROM reservations r 
         JOIN units u ON r.unit_id = u.unit_id 
         JOIN users renter ON r.user_id = renter.user_id 
         WHERE r.branch_id = ? AND r.status IN ('approved','confirmed')
         ORDER BY r.approved_at DESC",
        [$hostBranch['branch_id']]
    );
    
    // Get rejected bookings
    $rejectedBookings = get_multiple_results(
        "SELECT r.*, u.unit_number, u.unit_type, 
                renter.full_name as renter_name
         FROM reservations r 
         JOIN units u ON r.unit_id = u.unit_id 
         JOIN users renter ON r.user_id = renter.user_id 
         WHERE r.branch_id = ? AND r.status = 'rejected'
         ORDER BY r.rejected_at DESC LIMIT 10",
        [$hostBranch['branch_id']]
    );

    // Get payments pending verification for this branch
    $pendingPayments = get_multiple_results(
        "SELECT p.*, r.user_id, r.unit_id, r.branch_id, u.unit_number, renter.full_name as renter_name
         FROM payments p
         JOIN reservations r ON p.reservation_id = r.reservation_id
         JOIN units u ON r.unit_id = u.unit_id
         JOIN users renter ON r.user_id = renter.user_id
         WHERE r.branch_id = ? AND p.payment_status = 'pending'
         ORDER BY p.payment_date DESC",
        [$hostBranch['branch_id']]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Approvals - BookIT Host</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .approval-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .approval-header h1 {
            margin: 0;
            font-weight: bold;
        }
        
        .approval-header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        
        .booking-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .booking-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .booking-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .booking-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .booking-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .booking-status.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .booking-status.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .booking-body {
            padding: 20px;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
        }
        
        .detail-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .pricing-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .pricing-row.total {
            border-bottom: 2px solid #667eea;
            padding-top: 10px;
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
        }
        
        .renter-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .renter-info h6 {
            margin: 0 0 10px 0;
            color: #0c5460;
            font-weight: bold;
        }
        
        .renter-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #0c5460;
            font-size: 14px;
        }
        
        .renter-item:last-child {
            margin-bottom: 0;
        }
        
        .renter-item i {
            color: #0c5460;
            width: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn-approve {
            background: #28a745;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-reject {
            background: #dc3545;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .btn-view-details {
            background: #667eea;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        
        .btn-view-details:hover {
            background: #5568d3;
        }
        
        .tabs-section {
            margin-top: 30px;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .modal-body textarea {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            font-family: Arial;
            min-height: 100px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .tab-nav button {
            background: none;
            border: none;
            padding: 15px 20px;
            font-weight: bold;
            color: #666;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            bottom: -2px;
        }
        
        .tab-nav button:hover {
            color: #667eea;
        }
        
        .tab-nav button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .booking-count {
            background: #667eea;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 5px;
        }
    </style>
</head>
<body style="background: #f5f7fa; padding: 30px 20px;">
    <div class="container">
        <!-- Header -->
        <div class="approval-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1><i class="fas fa-check-circle me-2"></i>Booking Approvals</h1>
                    <p>Manage and approve bookings for <?php echo htmlspecialchars($hostBranch['branch_name'] ?? ''); ?></p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 14px; opacity: 0.8;">Branch Manager</div>
                    <div style="font-size: 18px; font-weight: bold;"><?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($hostBranch): ?>
            <!-- Tabs Navigation -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="showTab('pending')">
                    <i class="fas fa-clock me-2"></i>
                    Pending Approval
                    <span class="booking-count"><?php echo count($pendingBookings); ?></span>
                </button>
                <button class="tab-btn" onclick="showTab('payments')">
                    <i class="fas fa-receipt me-2"></i>
                    Payments
                    <span class="booking-count"><?php echo count($pendingPayments ?? []); ?></span>
                </button>
                <button class="tab-btn" onclick="showTab('approved')">
                    <i class="fas fa-check me-2"></i>
                    Approved
                    <span class="booking-count"><?php echo count($approvedBookings); ?></span>
                </button>
                <button class="tab-btn" onclick="showTab('rejected')">
                    <i class="fas fa-times me-2"></i>
                    Rejected
                    <span class="booking-count"><?php echo count($rejectedBookings); ?></span>
                </button>
            </div>
            
            <!-- Pending Bookings Tab -->
            <div id="pending" class="tab-content active">
                <div class="tabs-section">
                    <?php if (empty($pendingBookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No Pending Bookings</h4>
                            <p>All bookings have been reviewed!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingBookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div>
                                        <h5 style="margin: 0;">Booking #<?php echo $booking['reservation_id']; ?></h5>
                                        <small class="text-muted">Submitted <?php echo date('M d, Y h:i A', strtotime($booking['created_at'])); ?></small>
                                    </div>
                                    <span class="booking-status pending">⏳ Awaiting Approval</span>
                                </div>
                                
                                <div class="booking-body">
                                    <!-- Unit Details -->
                                    <div class="booking-details">
                                        <div class="detail-item">
                                            <div class="detail-label"><i class="fas fa-building me-1"></i>Unit</div>
                                            <div class="detail-value"><?php echo $booking['unit_number']; ?> (<?php echo $booking['unit_type']; ?>)</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label"><i class="fas fa-calendar-check me-1"></i>Check-in</div>
                                            <div class="detail-value"><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label"><i class="fas fa-calendar-times me-1"></i>Check-out</div>
                                            <div class="detail-value"><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label"><i class="fas fa-moon me-1"></i>Duration</div>
                                            <div class="detail-value"><?php echo calculateDays($booking['check_in_date'], $booking['check_out_date']); ?> nights</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Renter Information -->
                                    <div class="renter-info">
                                        <h6>Renter Information</h6>
                                        <div class="renter-item">
                                            <i class="fas fa-user"></i>
                                            <span><?php echo htmlspecialchars($booking['renter_name']); ?></span>
                                        </div>
                                        <div class="renter-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo htmlspecialchars($booking['renter_email']); ?></span>
                                        </div>
                                        <div class="renter-item">
                                            <i class="fas fa-phone"></i>
                                            <span><?php echo htmlspecialchars($booking['renter_phone']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Special Requests -->
                                    <?php if (!empty($booking['special_requests'])): ?>
                                        <div style="background: #f0f0f0; padding: 12px; border-radius: 5px; margin-bottom: 15px;">
                                            <strong>Special Requests:</strong>
                                            <p style="margin: 5px 0 0;"><?php echo htmlspecialchars($booking['special_requests']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Pricing Details -->
                                    <div style="margin-bottom: 15px;">
                                        <div class="pricing-row">
                                            <span>Unit Rate (<?php echo calculateDays($booking['check_in_date'], $booking['check_out_date']); ?> nights)</span>
                                            <span>₱<?php echo number_format($booking['total_amount'] * 0.7, 2); ?></span>
                                        </div>
                                        <div class="pricing-row">
                                            <span>Security Deposit</span>
                                            <span>₱<?php echo number_format($booking['security_deposit'], 2); ?></span>
                                        </div>
                                        <div class="pricing-row total">
                                            <span>Total Amount</span>
                                            <span>₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="action-buttons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="reservation_id" value="<?php echo $booking['reservation_id']; ?>">
                                            <button type="submit" name="approve_booking" class="btn-approve" onclick="return confirm('Approve this booking? The renter will be notified.');">
                                                <i class="fas fa-check me-1"></i>Approve Booking
                                            </button>
                                        </form>
                                        
                                        <button class="btn-reject" onclick="showRejectModal(<?php echo $booking['reservation_id']; ?>)">
                                            <i class="fas fa-times me-1"></i>Reject Booking
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payments Verification Tab -->
            <div id="payments" class="tab-content">
                <div class="tabs-section">
                    <?php if (empty($pendingPayments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No Payments Pending Verification</h4>
                            <p>Upload receipts will appear here for verification.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingPayments as $p): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div>
                                        <h5 style="margin:0;">Payment #<?php echo $p['payment_id']; ?> for Booking #<?php echo $p['reservation_id']; ?></h5>
                                        <small class="text-muted">Uploaded <?php echo date('M d, Y h:i A', strtotime($p['payment_date'] ?? $p['created_at'] ?? 'now')); ?></small>
                                    </div>
                                    <span class="booking-status pending">⏳ Pending Verification</span>
                                </div>
                                <div class="booking-body">
                                    <div class="booking-details">
                                        <div class="detail-item">
                                            <div class="detail-label">Unit</div>
                                            <div class="detail-value"><?php echo $p['unit_number']; ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Renter</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($p['renter_name']); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Amount</div>
                                            <div class="detail-value">₱<?php echo number_format($p['amount'], 2); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Method</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($p['payment_method']); ?></div>
                                        </div>
                                    </div>

                                    <!-- Receipt Preview -->
                                    <div style="margin: 15px 0;">
                                        <strong>Receipt:</strong>
                                        <div style="margin-top:10px;">
                                            <?php
                                            $url = FileUploadHelper::getPaymentProofURL($p['payment_method'] ?? 'other') . '/' . ($p['transaction_reference'] ?? '');
                                            ?>
                                            <a href="<?php echo $url; ?>" target="_blank">View Receipt</a>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>">
                                            <button type="submit" name="approve_payment" class="btn-approve" onclick="return confirm('Approve this payment and confirm the booking?');">
                                                <i class="fas fa-check me-1"></i>Approve Payment
                                            </button>
                                        </form>

                                        <button class="btn-reject" onclick="showRejectPaymentModal(<?php echo $p['payment_id']; ?>)">
                                            <i class="fas fa-times me-1"></i>Reject Payment
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Approved Bookings Tab -->
            <div id="approved" class="tab-content">
                <div class="tabs-section">
                    <?php if (empty($approvedBookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h4>No Approved Bookings</h4>
                            <p>Approve bookings to see them here</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($approvedBookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div>
                                        <h5 style="margin: 0;">Booking #<?php echo $booking['reservation_id']; ?> - <?php echo htmlspecialchars($booking['renter_name']); ?></h5>
                                        <small class="text-muted">Unit <?php echo $booking['unit_number']; ?> | Approved <?php echo date('M d, Y', strtotime($booking['approved_at'])); ?></small>
                                    </div>
                                    <span class="booking-status approved">✓ Approved</span>
                                </div>
                                
                                <div class="booking-body" style="padding: 15px;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                                        <div>
                                            <small class="text-muted">Check-in</small>
                                            <div style="font-weight: bold;"><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Check-out</small>
                                            <div style="font-weight: bold;"><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Total Amount</small>
                                            <div style="font-weight: bold; color: #667eea;">₱<?php echo number_format($booking['total_amount'], 2); ?></div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Payment Status</small>
                                            <div style="font-weight: bold; color: #ff9800;">
                                                <?php echo $booking['payment_status'] == 'paid' ? '✓ Paid' : '⏳ Awaiting Payment'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Rejected Bookings Tab -->
            <div id="rejected" class="tab-content">
                <div class="tabs-section">
                    <?php if (empty($rejectedBookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-ban"></i>
                            <h4>No Rejected Bookings</h4>
                            <p>You haven't rejected any bookings yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rejectedBookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div>
                                        <h5 style="margin: 0;">Booking #<?php echo $booking['reservation_id']; ?> - <?php echo htmlspecialchars($booking['renter_name']); ?></h5>
                                        <small class="text-muted">Rejected <?php echo date('M d, Y', strtotime($booking['rejected_at'])); ?></small>
                                    </div>
                                    <span class="booking-status rejected">✗ Rejected</span>
                                </div>
                                
                                <div class="booking-body" style="padding: 15px;">
                                    <div style="margin-bottom: 10px;">
                                        <strong>Reason:</strong>
                                        <p style="margin: 5px 0; color: #666;"><?php echo htmlspecialchars($booking['rejection_reason'] ?? 'No reason provided'); ?></p>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                                        <div>
                                            <small class="text-muted">Unit</small>
                                            <div style="font-weight: bold;"><?php echo $booking['unit_number']; ?></div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Check-in</small>
                                            <div style="font-weight: bold;"><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Check-out</small>
                                            <div style="font-weight: bold;"><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border: 2px solid #dc3545; border-radius: 10px; box-shadow: 0 5px 25px rgba(220, 53, 69, 0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; border-radius: 8px 8px 0 0;">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="fas fa-ban"></i> Reject Booking
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body" style="padding: 25px;">
                        <input type="hidden" name="reservation_id" id="rejectReservationId">
                        
    
                    <!-- Payment Rejection Modal -->
                    <div class="modal fade" id="rejectPaymentModal" tabindex="-1" aria-labelledby="rejectPaymentModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                            <div class="modal-content" style="border: 2px solid #dc3545; border-radius: 10px;">
                                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white;">
                                    <h5 class="modal-title" id="rejectPaymentModalLabel"><i class="fas fa-ban"></i> Reject Payment</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body" style="padding: 20px;">
                                        <input type="hidden" name="payment_id" id="rejectPaymentId">
                                        <div class="mb-3">
                                            <label for="paymentRejectReason" class="form-label fw-bold">Rejection Reason <span style="color: #dc3545;">*</span></label>
                                            <textarea class="form-control" id="paymentRejectReason" name="payment_reject_reason" required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="reject_payment" class="btn btn-danger">Confirm Reject</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label fw-bold">Rejection Reason <span style="color: #dc3545;">*</span></label>
                            <textarea class="form-control" id="rejectionReason" name="rejection_reason" placeholder="Please explain why you're rejecting this booking..." required style="border: 1px solid #dee2e6; border-radius: 5px;"></textarea>
                            <small class="form-text text-muted d-block mt-2">The renter will see this reason in their notification.</small>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="reject_booking" class="btn btn-danger" style="background: #dc3545; border-color: #dc3545; padding: 8px 20px;">
                            <i class="fas fa-ban"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.closest('.tab-btn').classList.add('active');
        }
        
        // Show rejection modal
        function showRejectModal(reservationId) {
            document.getElementById('rejectReservationId').value = reservationId;
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }

        // Show payment rejection modal
        function showRejectPaymentModal(paymentId) {
            document.getElementById('rejectPaymentId').value = paymentId;
            document.getElementById('paymentRejectReason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectPaymentModal'));
            modal.show();
        }
    </script>
</body>
</html>
