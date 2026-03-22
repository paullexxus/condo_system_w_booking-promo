<?php
// BookIT Payment Gateway Processor
// Handles payment processing with multiple gateways

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/renter_functions.php';
include_once '../config/paymongo.php';
checkRole(['renter']);

$message = '';
$error = '';
$paymentStatus = '';

// Check if there's a pending payment in session
if (!isset($_SESSION['pending_payment'])) {
    $error = "No pending payment found. Please complete checkout first.";
    $paymentStatus = 'error';
} else {
    $pending = $_SESSION['pending_payment'];
    $transactionRef = sanitize_input($_GET['ref'] ?? '');
    
    // Verify transaction reference matches
    if (empty($transactionRef)) {
        $error = "Invalid transaction reference.";
        $paymentStatus = 'error';
    } else {
        // Display payment processing based on method
        $method = $pending['method'];
        $total = $pending['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/renter/payment_gateway.css" rel="stylesheet">
        }
        
        .btn-success-payment:hover {
            color: white;
            transform: scale(1.02);
        }
        
        .btn-cancel-payment {
            border: 2px solid #ddd;
            background: white;
            color: #666;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-cancel-payment:hover {
            border-color: #999;
            background: #f9f9f9;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .security-info {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }
        
        .security-info i {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <div class="payment-header">
                <h1><i class="fas fa-credit-card me-2"></i>Payment Processing</h1>
            </div>
            
            <div class="payment-body">
                <?php if ($error): ?>
                    <div class="error-box">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                    <div class="action-buttons">
                        <a href="my_bookings.php" class="btn btn-secondary">Back to Bookings</a>
                    </div>
                <?php elseif (isset($pending)): ?>
                    <!-- Payment Amount -->
                    <div class="payment-amount">
                        <p class="text-muted mb-0">Total Amount</p>
                        <div>
                            <span class="currency">₱</span>
                            <span class="amount"><?php echo number_format($pending['total'], 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="payment-info">
                        <div class="payment-info-row">
                            <span class="payment-info-label">Subtotal</span>
                            <span class="payment-info-value">₱<?php echo number_format($pending['subtotal'], 2); ?></span>
                        </div>
                        <?php if ($pending['fee'] > 0): ?>
                            <div class="payment-info-row">
                                <span class="payment-info-label">Payment Fee</span>
                                <span class="payment-info-value text-warning">+₱<?php echo number_format($pending['fee'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="payment-info-row">
                            <span class="payment-info-label"><strong>Total</strong></span>
                            <span class="payment-info-value"><strong>₱<?php echo number_format($pending['total'], 2); ?></strong></span>
                        </div>
                    </div>
                    
                    <!-- Payment Method Display -->
                    <div class="payment-method-display">
                        <i class="fab fa-mobile-alt"></i>
                        <div class="info">
                            <h5><?php echo ucfirst(str_replace('_', ' ', $pending['method'])); ?></h5>
                            <p>Processing your payment securely...</p>
                        </div>
                    </div>
                    
                    <!-- Processing Steps -->
                    <div class="warning-box">
                        <strong>Important:</strong> Do NOT close this window or go back during payment processing.
                        Your payment will be processed in a few moments.
                    </div>
                    
                    <ul class="processing-steps">
                        <li class="done">Payment details verified</li>
                        <li class="active">Connecting to payment gateway...</li>
                        <li>Processing transaction</li>
                        <li>Confirming payment</li>
                        <li>Updating your booking</li>
                    </ul>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <form method="POST" action="payment_success.php" style="flex: 1; display: flex;">
                            <input type="hidden" name="transaction_ref" value="<?php echo $transactionRef; ?>">
                            <button type="submit" class="btn-success-payment" style="width: 100%;">
                                <i class="fas fa-check-circle me-2"></i>Confirm Payment
                            </button>
                        </form>
                    </div>
                    
                    <!-- Security Information -->
                    <div class="security-info">
                        <i class="fas fa-lock me-1"></i>
                        Your payment is processed securely by our payment partners
                    </div>
                    
                    <!-- Alternative Actions -->
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="checkout.php?type=<?php echo $pending['type']; ?>&id=<?php echo $pending['id']; ?>" 
                           class="btn btn-link btn-sm">
                            ← Back to Checkout
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/renter/payment_gateway.js"></script>
