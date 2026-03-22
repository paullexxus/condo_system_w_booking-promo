<?php
// BookIT Checkout System
// Complete payment flow for renters

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/renter_functions.php';
checkRole(['renter']); // Only renters can checkout

$message = '';
$error = '';
$bookingDetails = null;
$paymentMethods = [];

// Get booking details (reservation or amenity)
if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = sanitize_input($_GET['type']);
    $id = (int)$_GET['id'];
    
    // Validate input
    if ($id <= 0 || !in_array($type, ['reservation', 'amenity'])) {
        $error = "Invalid booking request.";
    } else {
        if ($type == 'reservation') {
            $bookingDetails = get_single_result(
                "SELECT r.*, u.unit_number, u.unit_type, u.monthly_rate, u.security_deposit, 
                        b.branch_name, b.address, b.city
                FROM reservations r 
                JOIN units u ON r.unit_id = u.unit_id 
                JOIN branches b ON r.branch_id = b.branch_id 
                WHERE r.reservation_id = ? AND r.user_id = ? AND r.status IN ('pending', 'approved')",
                [$id, $_SESSION['user_id']]
            );
            
            if (!$bookingDetails) {
                $error = "Reservation not found or already paid.";
            } elseif ($bookingDetails['status'] != 'approved') {
                $error = "Your booking is still awaiting host approval. Payment will be available after approval.";
            }
        } elseif ($type == 'amenity') {
            $bookingDetails = get_single_result(
                "SELECT ab.*, a.amenity_name, a.description, a.hourly_rate, b.branch_name 
                FROM amenity_bookings ab 
                JOIN amenities a ON ab.amenity_id = a.amenity_id 
                JOIN branches b ON ab.branch_id = b.branch_id 
                WHERE ab.booking_id = ? AND ab.user_id = ? AND ab.status = 'pending'",
                [$id, $_SESSION['user_id']]
            );
            
            if (!$bookingDetails) {
                $error = "Amenity booking not found or already paid.";
            }
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reservation_id'])) {
    // Handle POST from my_bookings.php
    $reservationId = (int)$_POST['reservation_id'];
    
    $bookingDetails = get_single_result(
        "SELECT r.*, u.unit_number, u.unit_type, u.monthly_rate, u.security_deposit, 
                b.branch_name, b.address, b.city
        FROM reservations r 
        JOIN units u ON r.unit_id = u.unit_id 
        JOIN branches b ON r.branch_id = b.branch_id 
        WHERE r.reservation_id = ? AND r.user_id = ?",
        [$reservationId, $_SESSION['user_id']]
    );
    
    if (!$bookingDetails) {
        $error = "Reservation not found.";
    } elseif ($bookingDetails['status'] != 'approved') {
        $error = "Your booking is still awaiting host approval. Payment will be available after approval.";
    }
} else {
    $error = "No booking specified.";
}

// Available payment methods with descriptions
$paymentMethods = [
    'gcash' => [
        'name' => 'GCash',
        'icon' => 'fab fa-mobile',
        'description' => 'Send money via GCash app instantly',
        'processing_time' => '0-5 minutes',
        'fee' => 0  // No additional fee
    ],
    'paymaya' => [
        'name' => 'PayMaya',
        'icon' => 'fas fa-credit-card',
        'description' => 'Digital wallet and payment solution',
        'processing_time' => '0-10 minutes',
        'fee' => 0  // PayMaya covers fees
    ],
    'bank_transfer' => [
        'name' => 'Bank Transfer',
        'icon' => 'fas fa-university',
        'description' => 'Direct transfer to our business account',
        'processing_time' => '1-2 hours',
        'fee' => 0
    ],
    'credit_card' => [
        'name' => 'Credit/Debit Card',
        'icon' => 'fas fa-credit-card',
        'description' => 'Visa, Mastercard, or any major card',
        'processing_time' => 'Instant',
        'fee' => 2.5  // 2.5% processing fee
    ]
];

// Handle payment initiation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['initiate_payment'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    } else {
        $paymentMethod = sanitize_input($_POST['payment_method']);
        
        // Validate payment method
        if (!in_array($paymentMethod, array_keys($paymentMethods))) {
            $error = "Invalid payment method selected.";
        } else if (!$bookingDetails) {
            $error = "Booking details not found.";
        } else {
            // Calculate total with fees
            $subtotal = (float)$bookingDetails['total_amount'];
            $fee = ($subtotal * $paymentMethods[$paymentMethod]['fee']) / 100;
            $total = $subtotal + $fee;
            
            // Store payment session data
            $_SESSION['pending_payment'] = [
                'type' => $type,
                'id' => $id,
                'booking_id' => $type == 'reservation' ? $bookingDetails['reservation_id'] : $bookingDetails['booking_id'],
                'method' => $paymentMethod,
                'subtotal' => $subtotal,
                'fee' => $fee,
                'total' => $total,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Redirect to payment gateway
            header("Location: payment_gateway.php?ref=" . bin2hex(random_bytes(16)));
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/renter/renter-modern.css">
    <link href="../assets/css/renter/checkout.css" rel="stylesheet">
        
        .payment-method-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .payment-method-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .payment-method-icon {
            font-size: 32px;
            color: #667eea;
            width: 50px;
            text-align: center;
        }
        
        .payment-method-info {
            flex: 1;
        }
        
        .payment-method-info h5 {
            margin: 0;
            font-weight: bold;
            color: #333;
        }
        
        .payment-method-info p {
            margin: 5px 0 0;
            color: #666;
            font-size: 13px;
        }
        
        .payment-method-meta {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 12px;
            color: #999;
        }
        
        .payment-method-fee {
            text-align: right;
            color: #667eea;
            font-weight: bold;
        }
        
        .radio-input {
            display: none;
        }
        
        .radio-input:checked + .payment-method-card {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .checkout-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            border-top: 2px solid #f0f0f0;
            padding-top: 30px;
        }
        
        .btn-proceed {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 15px 40px;
            font-weight: bold;
            border-radius: 8px;
            flex: 1;
            transition: transform 0.2s;
        }
        
        .btn-proceed:hover {
            color: white;
            transform: scale(1.02);
        }
        
        .btn-cancel {
            border: 2px solid #ddd;
            background: white;
            color: #666;
            padding: 15px 40px;
            font-weight: bold;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-cancel:hover {
            border-color: #999;
            background: #f9f9f9;
        }
        
        .security-badge {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 13px;
        }
        
        .security-badge i {
            color: #28a745;
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .checkout-body {
                padding: 20px;
            }
            
            .checkout-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <!-- Header -->
        <div class="checkout-card">
            <div class="checkout-header">
                <h1><i class="fas fa-shopping-cart me-2"></i>Secure Checkout</h1>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!$error && $bookingDetails): ?>
            <div class="checkout-card">
                <div class="checkout-body">
                    <!-- Booking Summary -->
                    <div class="booking-summary">
                        <h5><?php echo $type == 'reservation' ? 'Unit Reservation' : 'Amenity Booking'; ?></h5>
                        
                        <?php if ($type == 'reservation'): ?>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-building me-2"></i>Unit</span>
                                <span class="booking-item-value"><?php echo $bookingDetails['unit_number']; ?> - <?php echo $bookingDetails['unit_type']; ?></span>
                            </div>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-map-marker-alt me-2"></i>Location</span>
                                <span class="booking-item-value"><?php echo $bookingDetails['branch_name']; ?>, <?php echo $bookingDetails['city']; ?></span>
                            </div>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-calendar-check me-2"></i>Check-in</span>
                                <span class="booking-item-value"><?php echo date('M d, Y', strtotime($bookingDetails['check_in_date'])); ?></span>
                            </div>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-calendar-times me-2"></i>Check-out</span>
                                <span class="booking-item-value"><?php echo date('M d, Y', strtotime($bookingDetails['check_out_date'])); ?></span>
                            </div>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-moon me-2"></i>Duration</span>
                                <span class="booking-item-value"><?php echo calculateDays($bookingDetails['check_in_date'], $bookingDetails['check_out_date']); ?> nights</span>
                            </div>
                        <?php else: ?>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-swimming-pool me-2"></i>Amenity</span>
                                <span class="booking-item-value"><?php echo $bookingDetails['amenity_name']; ?></span>
                            </div>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-map-marker-alt me-2"></i>Location</span>
                                <span class="booking-item-value"><?php echo $bookingDetails['branch_name']; ?></span>
                            </div>
                            <div class="booking-item">
                                <span class="booking-item-label"><i class="fas fa-calendar-check me-2"></i>Date</span>
                                <span class="booking-item-value"><?php echo date('M d, Y', strtotime($bookingDetails['booking_date'])); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Pricing Breakdown -->
                            <div class="pricing-breakdown">
                                <div class="pricing-row">
                                    <span>Subtotal</span>
                                    <span>₱<?php echo number_format($bookingDetails['total_amount'], 2); ?></span>
                                </div>
                                <?php if (!empty($bookingDetails['security_deposit'])): ?>
                                <div class="pricing-row">
                                    <span>Security Deposit</span>
                                    <span>₱<?php echo number_format($bookingDetails['security_deposit'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($bookingDetails['cleaning_fee'])): ?>
                                <div class="pricing-row">
                                    <span>Cleaning Fee</span>
                                    <span>₱<?php echo number_format($bookingDetails['cleaning_fee'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($bookingDetails['service_fee'])): ?>
                                <div class="pricing-row">
                                    <span>Service Fee</span>
                                    <span>₱<?php echo number_format($bookingDetails['service_fee'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="pricing-row total">
                                    <span>Total Amount</span>
                                    <span>₱<?php echo number_format($bookingDetails['total_amount'] + ($bookingDetails['security_deposit'] ?? 0) + ($bookingDetails['cleaning_fee'] ?? 0) + ($bookingDetails['service_fee'] ?? 0), 2); ?></span>
                                </div>
                            </div>
                    </div>
                    
                    <!-- Payment Methods Selection -->
                    <form method="POST" id="checkoutForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="payment-methods-section">
                            <h3>Select Payment Method</h3>
                            
                            <?php foreach ($paymentMethods as $methodKey => $method): ?>
                                <label class="payment-method-label">
                                    <input type="radio" name="payment_method" value="<?php echo $methodKey; ?>" class="radio-input" required>
                                    <div class="payment-method-card">
                                        <div class="payment-method-content">
                                            <div class="payment-method-icon">
                                                <i class="<?php echo $method['icon']; ?>"></i>
                                            </div>
                                            <div class="payment-method-info" style="flex: 1;">
                                                <h5><?php echo $method['name']; ?></h5>
                                                <p><?php echo $method['description']; ?></p>
                                                <div class="payment-method-meta">
                                                    <span><i class="fas fa-clock me-1"></i><?php echo $method['processing_time']; ?></span>
                                                    <span><i class="fas fa-check-circle me-1"></i>Secure</span>
                                                </div>
                                            </div>
                                            <?php if ($method['fee'] > 0): ?>
                                                <div class="payment-method-fee">
                                                    +<?php echo $method['fee']; ?>% fee
                                                </div>
                                            <?php else: ?>
                                                <div class="payment-method-fee text-success">
                                                    No fees
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="checkout-actions">
                            <a href="my_bookings.php" class="btn-cancel">
                                <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                            </a>
                            <button type="submit" name="initiate_payment" class="btn-proceed">
                                <i class="fas fa-lock me-2"></i>Proceed to Payment
                            </button>
                        </div>
                        
                        <!-- Security Badge -->
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            Your payment information is encrypted and secure
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No booking found. Please select a valid booking to proceed.
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/renter/checkout.js"></script>
    <script src="../assets/js/renter/ui.js"></script>
</body>
</html>
