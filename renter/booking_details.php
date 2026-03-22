<?php
// BookIT Renter Booking Details
// Shows booking details and approval status

include_once '../includes/session.php';
include_once '../includes/functions.php';
checkRole(['renter']);

// File paths helper for uploads
include_once '../config/file_paths.php';

$error = '';
$booking = null;
$bookingId = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    header('Location: my_bookings.php');
    exit;
}

// Get booking details
$booking = get_single_result(
    "SELECT r.*, u.unit_number, u.unit_type, u.monthly_rate, u.description as unit_description,
            b.branch_name, b.branch_address,
            renter.full_name, renter.email, renter.phone
     FROM reservations r 
     JOIN units u ON r.unit_id = u.unit_id 
     JOIN branches b ON r.branch_id = b.branch_id 
     JOIN users renter ON r.user_id = renter.user_id 
     WHERE r.reservation_id = ? AND r.user_id = ?",
    [$bookingId, $_SESSION['user_id']]
);

if (!$booking) {
    $error = "Booking not found.";
}

// Get payment info if exists
$payment = null;
if ($booking && $booking['status'] == 'approved') {
    $payment = get_single_result(
        "SELECT * FROM payments WHERE reservation_id = ? ORDER BY created_at DESC LIMIT 1",
        [$bookingId]
    );
}

// Handle receipt upload (manual payment proof)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_receipt'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed.';
    } else {
        $payment_method = strtolower(sanitize_input($_POST['payment_method'] ?? 'gcash'));
        $allowed_methods = ['gcash', 'bank_transfer', 'paypal', 'other'];
        if (!in_array($payment_method, $allowed_methods)) $payment_method = 'other';

        // Validate file
        $validation = FileUploadHelper::validateFile($_FILES['payment_receipt'] ?? null, ALLOWED_IMAGE_EXTENSIONS, MAX_IMAGE_SIZE);
        if (!$validation['success']) {
            $error = $validation['error'] ?? 'Invalid file upload.';
        } else {
            // Save file
            $target_dir = FileUploadHelper::getPaymentProofDirectory($payment_method);
            $result = FileUploadHelper::saveFile($_FILES['payment_receipt'], $target_dir, 'payment_' . $bookingId);
            if (!$result['success']) {
                $error = $result['error'] ?? 'Failed to save uploaded file.';
            } else {
                $filename = $result['filename'];
                $amount = (float)($booking['total_amount'] ?? 0);

                // Insert payment record (pending verification)
                $insert = execute_query(
                    "INSERT INTO payments (reservation_id, user_id, amount, payment_method, transaction_reference, payment_status) VALUES (?, ?, ?, ?, ?, 'pending')",
                    [$bookingId, $_SESSION['user_id'], $amount, $payment_method, $filename]
                );

                if ($insert) {
                    // Update reservation payment_status to pending
                    execute_query("UPDATE reservations SET payment_status = 'pending' WHERE reservation_id = ?", [$bookingId]);

                    // Notify host to verify payment
                    $branch = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$booking['branch_id']]);
                    if ($branch && $branch['host_id']) {
                        sendNotification($branch['host_id'], 'Payment Receipt Uploaded', 'A renter uploaded a payment receipt for reservation #' . $bookingId . '. Please verify.', 'payment', 'system');
                    }

                    $message = 'Receipt uploaded successfully and sent for verification.';
                    // Reload payment details
                    $payment = get_single_result(
                        "SELECT * FROM payments WHERE reservation_id = ? ORDER BY payment_date DESC LIMIT 1",
                        [$bookingId]
                    );
                } else {
                    $error = 'Failed to record payment. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/renter/booking_details.css" rel="stylesheet">
    <link href="../assets/css/renter/booking_details.css" rel="stylesheet">
            border-bottom: 1px solid #eee;
        }
        
        .pricing-row.total {
            border-bottom: none;
            border-top: 2px solid #667eea;
            padding-top: 15px;
            margin-top: 10px;
            font-weight: bold;
            font-size: 18px;
            color: #667eea;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .btn-pay {
            background: #28a745;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-pay:hover {
            background: #218838;
            text-decoration: none;
            color: white;
        }
        
        .btn-back {
            background: #6c757d;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: #5a6268;
            text-decoration: none;
            color: white;
        }
        
        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            color: #0c5460;
        }
        
        .rejection-box {
            background: #f8d7da;
            border-left: 4px solid #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            color: #721c24;
        }
        
        .rejection-box strong {
            display: block;
            margin-bottom: 8px;
        }
        
        .status-description {
            margin-top: 15px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 5px;
            font-size: 14px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <a href="my_bookings.php" class="btn-back mb-4">
            <i class="fas fa-arrow-left me-2"></i>Back to My Bookings
        </a>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($booking): ?>
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-file-alt me-2"></i>Booking Details</h1>
                <p>Booking #<?php echo $booking['reservation_id']; ?> - Created <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></p>
            </div>
            
            <!-- Status Card -->
            <div class="status-card">
                <h4 style="margin-bottom: 15px;">Booking Status</h4>
                
                <?php if ($booking['status'] == 'awaiting_approval'): ?>
                    <span class="status-badge status-pending">
                        <i class="fas fa-clock me-2"></i>Awaiting Host Approval
                    </span>
                    <div class="status-description">
                        <i class="fas fa-info-circle me-2"></i>
                        Your booking has been submitted and is awaiting approval from the branch host. You'll receive a notification once they review your booking. Payment will be available after approval.
                    </div>
                
                <?php elseif ($booking['status'] == 'approved'): ?>
                    <span class="status-badge status-approved">
                        <i class="fas fa-check-circle me-2"></i>Approved - Ready to Pay
                    </span>
                    <div class="status-description">
                        <i class="fas fa-info-circle me-2"></i>
                        Great! The host has approved your booking. You can now proceed to payment.
                    </div>
                
                <?php elseif ($booking['status'] == 'rejected'): ?>
                    <span class="status-badge status-rejected">
                        <i class="fas fa-times-circle me-2"></i>Rejected
                    </span>
                    <?php if (!empty($booking['rejection_reason'])): ?>
                        <div class="rejection-box">
                            <strong>Reason for Rejection:</strong>
                            <?php echo htmlspecialchars($booking['rejection_reason']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="info-box">
                        <i class="fas fa-info-circle me-2"></i>
                        This booking has been rejected by the host. You can browse other units or contact support.
                    </div>
                
                <?php elseif ($booking['status'] == 'confirmed'): ?>
                    <span class="status-badge status-confirmed">
                        <i class="fas fa-check-double me-2"></i>Confirmed
                    </span>
                    <div class="status-description">
                        <i class="fas fa-check-circle me-2"></i>
                        Your booking is confirmed! Check your email for reservation details and instructions.
                    </div>
                <?php endif; ?>
                
                <!-- Timeline -->
                <div class="timeline" style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #f0f0f0;">
                    <div class="timeline-item <?php echo $booking['status'] != 'awaiting_approval' ? 'active' : 'current'; ?>">
                        <div class="timeline-icon"><i class="fas fa-pencil"></i></div>
                        <div class="timeline-content">
                            <h6>Booking Submitted</h6>
                            <p><?php echo date('M d, Y h:i A', strtotime($booking['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?php echo in_array($booking['status'], ['approved', 'confirmed']) ? 'active' : ($booking['status'] == 'awaiting_approval' ? 'current' : 'pending'); ?>">
                        <div class="timeline-icon"><i class="fas fa-check"></i></div>
                        <div class="timeline-content">
                            <h6>Host Approval</h6>
                            <p>
                                <?php if (in_array($booking['status'], ['approved', 'confirmed'])): ?>
                                    Approved on <?php echo date('M d, Y h:i A', strtotime($booking['approved_at'])); ?>
                                <?php else: ?>
                                    Pending
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?php echo $booking['payment_status'] == 'paid' ? 'active' : ($booking['status'] == 'approved' ? 'current' : 'pending'); ?>">
                        <div class="timeline-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="timeline-content">
                            <h6>Payment</h6>
                            <p>
                                <?php if ($booking['payment_status'] == 'paid'): ?>
                                    Paid on <?php echo date('M d, Y h:i A', strtotime($payment['created_at'] ?? '')); ?>
                                <?php else: ?>
                                    Pending
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?php echo $booking['status'] == 'confirmed' ? 'active' : 'pending'; ?>">
                        <div class="timeline-icon"><i class="fas fa-check-double"></i></div>
                        <div class="timeline-content">
                            <h6>Confirmed</h6>
                            <p>
                                <?php if ($booking['status'] == 'confirmed'): ?>
                                    Confirmed on <?php echo date('M d, Y h:i A', strtotime($booking['confirmed_at'] ?? '')); ?>
                                <?php else: ?>
                                    Pending payment
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Unit Information -->
            <div class="detail-section">
                <h4><i class="fas fa-building me-2"></i>Unit Information</h4>
                <div class="unit-info">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Unit Number</div>
                            <div class="detail-value"><?php echo $booking['unit_number']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Unit Type</div>
                            <div class="detail-value"><?php echo ucwords(str_replace('_', ' ', $booking['unit_type'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Monthly Rate</div>
                            <div class="detail-value">₱<?php echo number_format($booking['monthly_rate'], 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($booking['unit_description'])): ?>
                    <h6 style="margin-top: 20px; margin-bottom: 10px;">Description</h6>
                    <p style="color: #555; line-height: 1.6;">
                        <?php echo htmlspecialchars($booking['unit_description']); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Location Information -->
            <div class="detail-section">
                <h4><i class="fas fa-map-marker-alt me-2"></i>Location</h4>
                <div class="location-info">
                    <h6><?php echo htmlspecialchars($booking['branch_name']); ?></h6>
                    <p style="margin: 0; color: #0c5460;">
                        <i class="fas fa-location-dot me-2"></i><?php echo htmlspecialchars($booking['branch_address']); ?>
                    </p>
                </div>
            </div>
            
            <!-- Booking Details -->
            <div class="detail-section">
                <h4><i class="fas fa-calendar-alt me-2"></i>Reservation Details</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Check-in Date</div>
                        <div class="detail-value"><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Check-out Date</div>
                        <div class="detail-value"><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value"><?php echo calculateDays($booking['check_in_date'], $booking['check_out_date']); ?> nights</div>
                    </div>
                </div>
                
                <?php if (!empty($booking['special_requests'])): ?>
                    <div style="margin-top: 20px; background: #f9f9f9; padding: 15px; border-radius: 5px;">
                        <h6>Special Requests</h6>
                        <p style="margin: 5px 0 0; color: #555;">
                            <?php echo htmlspecialchars($booking['special_requests']); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pricing Information -->
            <div class="detail-section">
                <h4><i class="fas fa-receipt me-2"></i>Pricing Breakdown</h4>
                <div class="pricing-box">
                    <div class="pricing-row">
                        <span>Unit Rate (<?php echo calculateDays($booking['check_in_date'], $booking['check_out_date']); ?> nights × ₱<?php echo number_format($booking['monthly_rate'], 2); ?>/month)</span>
                        <span>₱<?php echo number_format(($booking['total_amount'] - $booking['security_deposit']) * 0.85, 2); ?></span>
                    </div>
                    <div class="pricing-row">
                        <span>Service Fee</span>
                        <span>₱<?php echo number_format(($booking['total_amount'] - $booking['security_deposit']) * 0.15, 2); ?></span>
                    </div>
                    <div class="pricing-row">
                        <span>Security Deposit (Refundable)</span>
                        <span>₱<?php echo number_format($booking['security_deposit'], 2); ?></span>
                    </div>
                    <div class="pricing-row total">
                        <span>Total Amount Due</span>
                        <span>₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if (in_array($booking['status'], ['approved','pending']) && $booking['payment_status'] != 'paid'): ?>
                    <div style="width:100%; max-width:600px;">
                        <div class="info-box" style="margin-bottom:12px;">
                            <strong>Manual Payment Instructions</strong>
                            <p style="margin:6px 0 0;">Please pay via GCash or Bank Transfer using the details provided by the branch. After payment, upload a clear screenshot of your payment receipt below for host verification.</p>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="upload_receipt" value="1">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="gcash">GCash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="paypal">PayPal</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Upload Payment Receipt (image)</label>
                                <input type="file" name="payment_receipt" accept="image/*" class="form-control" required>
                                <small class="text-muted">Allowed: jpg, png, gif. Max 5MB.</small>
                            </div>
                            <div class="mb-3">
                                <button type="submit" class="btn-pay">
                                    <i class="fas fa-upload me-2"></i>Upload Receipt
                                </button>
                            </div>
                        </form>
                    </div>
                <?php elseif ($booking['status'] == 'confirmed' && $booking['payment_status'] == 'paid'): ?>
                    <div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Payment Completed</strong> on <?php echo date('M d, Y', strtotime($payment['created_at'] ?? '')); ?>
                    </div>
                <?php endif; ?>
                
                <a href="my_bookings.php" class="btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Back to My Bookings
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
