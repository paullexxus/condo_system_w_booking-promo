<?php
// BookIT Payment Success Handler
// Confirms payment and updates booking status

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/renter_functions.php';
checkRole(['renter']);

$message = '';
$error = '';
$paymentResult = null;

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['transaction_ref'])) {
    if (!isset($_SESSION['pending_payment'])) {
        $error = "No pending payment found.";
    } else {
        $pending = $_SESSION['pending_payment'];
        $transactionRef = sanitize_input($_POST['transaction_ref']);
        
        // Process payment in database
        $bookingId = $pending['booking_id'];
        $type = $pending['type'];
        $paymentId = bin2hex(random_bytes(16));  // Generate unique payment ID
        
        try {
            // Create payment record
            $paymentSql = "INSERT INTO payments 
                          (payment_id, user_id, reservation_id, amenity_booking_id, amount, payment_method, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
            
            $reservationId = ($type == 'reservation') ? $bookingId : null;
            $amenityId = ($type == 'amenity') ? $bookingId : null;
            
            execute_query($paymentSql, [
                $paymentId,
                $_SESSION['user_id'],
                $reservationId,
                $amenityId,
                $pending['total'],
                $pending['method']
            ]);
            
            // Update booking status
            if ($type == 'reservation') {
                execute_query("UPDATE reservations SET status = 'confirmed', payment_status = 'paid' WHERE reservation_id = ?", [$bookingId]);
                
                // Get reservation details for confirmation
                $booking = get_single_result("SELECT r.*, u.host_id FROM reservations r JOIN units u ON r.unit_id = u.unit_id WHERE r.reservation_id = ?", [$bookingId]);
                
                // Send confirmation email to renter
                sendNotification(
                    $_SESSION['user_id'],
                    "Payment Confirmed - Reservation #$bookingId",
                    "Your payment has been successfully processed. Your reservation is now confirmed.",
                    'payment',
                    'system'
                );
                
                // Notify host about payment
                if ($booking && $booking['host_id']) {
                    sendNotification(
                        $booking['host_id'],
                        "Payment Received - Reservation #$bookingId",
                        "Payment received for reservation #$bookingId. Amount: ₱" . $pending['total'],
                        'payment',
                        'system'
                    );
                }
            } else {
                execute_query("UPDATE amenity_bookings SET status = 'confirmed', payment_status = 'paid' WHERE booking_id = ?", [$bookingId]);
                
                // Get amenity details for confirmation
                $booking = get_single_result("SELECT ab.*, a.name AS amenity_name, b.host_id FROM amenity_bookings ab JOIN amenities a ON ab.amenity_id = a.id JOIN branches b ON ab.branch_id = b.branch_id WHERE ab.booking_id = ?", [$bookingId]);
                
                // Send confirmation email to renter
                sendNotification(
                    $_SESSION['user_id'],
                    "Payment Confirmed - Amenity Booking #$bookingId",
                    "Your payment has been successfully processed. Your amenity booking is now confirmed.",
                    'payment',
                    'system'
                );
                
                // Notify host about payment
                if ($booking && $booking['host_id']) {
                    sendNotification(
                        $booking['host_id'],
                        "Payment Received - Amenity Booking #$bookingId",
                        "Payment received for amenity booking #$bookingId (" . $booking['amenity_name'] . "). Amount: ₱" . $pending['total'],
                        'payment',
                        'system'
                    );
                }
            }
            
            // Clear pending payment from session
            unset($_SESSION['pending_payment']);
            
            $paymentResult = [
                'success' => true,
                'paymentId' => $paymentId,
                'amount' => $pending['total'],
                'method' => $pending['method'],
                'bookingId' => $bookingId,
                'type' => $type
            ];
        } catch (Exception $e) {
            $error = "Failed to process payment. Please contact support.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/renter/payment_success.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/renter/renter-modern.css">
    <link href="../assets/css/renter/payment_success.css" rel="stylesheet">
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .detail-label {
            color: #666;
            font-size: 14px;
        }
        
        .detail-value {
            font-weight: bold;
            color: #333;
            text-align: right;
        }
        
        .detail-value.highlight {
            color: #28a745;
            font-size: 16px;
        }
        
        .next-steps {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
        }
        
        .next-steps h5 {
            color: #0c5460;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .next-steps li {
            color: #0c5460;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-primary-action {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            flex: 1;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }
        
        .btn-primary-action:hover {
            color: white;
            transform: scale(1.02);
        }
        
        .btn-secondary-action {
            border: 2px solid #ddd;
            background: white;
            color: #666;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-secondary-action:hover {
            border-color: #999;
            background: #f9f9f9;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .receipt-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .receipt-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .receipt-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <?php if ($error): ?>
                <div style="background: #f8d7da; padding: 40px; text-align: center; color: #721c24;">
                    <div style="font-size: 48px; margin-bottom: 20px;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h1 style="font-size: 28px; margin-bottom: 10px;">Payment Failed</h1>
                </div>
                
                <div style="padding: 40px;">
                    <div class="error-box">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="my_bookings.php" class="btn-secondary-action">
                            <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                        </a>
                    </div>
                </div>
            <?php elseif ($paymentResult): ?>
                <div class="confirmation-header">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1>Payment Successful!</h1>
                </div>
                
                <div class="confirmation-body">
                    <p style="text-align: center; color: #666; margin-bottom: 25px;">
                        Your payment has been successfully processed. Your 
                        <?php echo $paymentResult['type'] == 'reservation' ? 'reservation' : 'amenity booking'; ?>
                        is now confirmed.
                    </p>
                    
                    <!-- Payment Details -->
                    <div class="confirmation-details">
                        <div class="detail-row">
                            <span class="detail-label">Payment ID</span>
                            <span class="detail-value" style="font-family: monospace; font-size: 12px;">
                                <?php echo substr($paymentResult['paymentId'], 0, 8); ?>...
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Amount Paid</span>
                            <span class="detail-value highlight">
                                ₱<?php echo number_format($paymentResult['amount'], 2); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Payment Method</span>
                            <span class="detail-value">
                                <?php echo ucfirst(str_replace('_', ' ', $paymentResult['method'])); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Booking ID</span>
                            <span class="detail-value">#<?php echo $paymentResult['bookingId']; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Timestamp</span>
                            <span class="detail-value"><?php echo date('M d, Y h:i A'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="next-steps">
                        <h5><i class="fas fa-info-circle me-2"></i>What Happens Next?</h5>
                        <ul>
                            <li>A confirmation email will be sent to your registered email address</li>
                            <li>Check your email for booking details and important information</li>
                            <li>You can view your booking in "My Bookings" section</li>
                            <li>Contact us if you have any questions or issues</li>
                        </ul>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="my_bookings.php" class="btn-primary-action">
                            <i class="fas fa-calendar-check me-2"></i>View My Bookings
                        </a>
                    </div>
                    
                    <!-- Receipt Link -->
                    <div class="receipt-link">
                        <a href="#" onclick="window.print(); return false;">
                            <i class="fas fa-download me-1"></i>Download Receipt
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <p><i class="fas fa-info-circle me-2"></i>Processing your payment confirmation...</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
