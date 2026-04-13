<?php
// BookIT Payment System
// Multi-branch Condo Rental Reservation System

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';
include_once '../includes/renter_functions.php';
checkRole(['renter']); // Tanging renters lang ang pwede

$message = '';
$error = '';
$reservation = null;
$amenityBooking = null;

// Kumuha ng reservation o amenity booking details - FIXED: Use prepared statements
if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = sanitize_input($_GET['type']);
    $id = (int)$_GET['id'];
    
    // Validate input
    if ($id <= 0 || !in_array($type, ['reservation', 'amenity'])) {
        $error = "Invalid payment request";
    } else {
        if ($type == 'reservation') {
            $reservation = get_single_result(
                "SELECT r.*, u.unit_number, u.unit_type, b.branch_name, b.address 
                FROM reservations r 
                JOIN units u ON r.unit_id = u.unit_id 
                JOIN branches b ON r.branch_id = b.branch_id 
                WHERE r.reservation_id = ? AND r.user_id = ?",
                [$id, $_SESSION['user_id']]
            );
        } elseif ($type == 'amenity') {
            $amenityBooking = get_single_result(
                "SELECT ab.*, a.name AS amenity_name, '' AS description, b.branch_name 
                FROM amenity_bookings ab 
                JOIN amenities a ON ab.amenity_id = a.id 
                JOIN branches b ON ab.branch_id = b.branch_id 
                WHERE ab.booking_id = ? AND ab.user_id = ?",
                [$id, $_SESSION['user_id']]
            );
        }
    }
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $paymentMethod = $_POST['payment_method'];
    $amount = $_POST['amount'];
    $transactionReference = sanitize_input($_POST['transaction_reference']);
    
    $reservationId = null;
    $amenityBookingId = null;
    
    if ($reservation) {
        $reservationId = $reservation['reservation_id'];
    } elseif ($amenityBooking) {
        $amenityBookingId = $amenityBooking['booking_id'];
    }
    
    // I-process ang payment
    $paymentId = processPayment($reservationId, $amenityBookingId, $_SESSION['user_id'], $amount, $paymentMethod, $transactionReference);
    
    if ($paymentId) {
        // I-update ang status ng reservation o amenity booking
        if ($reservation) {
            $sql = "UPDATE reservations SET payment_status = 'paid' WHERE reservation_id = ?";
            execute_query($sql, [$reservationId]);
        } elseif ($amenityBooking) {
            $sql = "UPDATE amenity_bookings SET status = 'confirmed' WHERE booking_id = ?";
            execute_query($sql, [$amenityBookingId]);
        }
        
        // Mag-send ng notification
        $title = $reservation ? "Payment Confirmed - Unit Reservation" : "Payment Confirmed - Amenity Booking";
        $message = $reservation ? 
            "Your payment for Unit " . $reservation['unit_number'] . " has been confirmed." :
            "Your payment for " . $amenityBooking['amenity_name'] . " has been confirmed.";
            
        sendNotification($_SESSION['user_id'], $title, $message, 'payment', 'system');
        
        $message = "Payment processed successfully! Payment ID: " . $paymentId;
    } else {
        $error = "Failed to process payment. Please try again.";
    }
}

// simulatePaymentGateway() implementation moved to includes/renter_functions.php
// to avoid duplicate declarations across pages. The centralized function
// in `includes/renter_functions.php` will be used by the payment flow.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/renter/payment.css">
</head>
<body>
    <div class="container-fluid">
        <!-- Renter Navbar: only Be a Host + Profile dropdown -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="../public/index.php">
                    <i class="fas fa-building"></i> BookIT
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav align-items-center">
                        <li class="nav-item me-2">
                            <?php if (isLoggedIn() && in_array($_SESSION['role'], ['host','manager','admin'])): ?>
                                <a class="nav-link btn btn-outline-light btn-sm px-3" href="../public/manager_register.php">
                                    <i class="fas fa-handshake"></i> Be a Host
                                </a>
                            <?php else: ?>
                                <a class="nav-link btn btn-outline-light btn-sm px-3" href="../public/be_host.php">
                                    <i class="fas fa-handshake"></i> Be a Host
                                </a>
                            <?php endif; ?>
                        </li>
                        <li class="nav-item">
                            <?php if (isLoggedIn()): ?>
                                <div class="nav-link dropdown">
                                    <a class="dropdown-toggle d-flex align-items-center text-white text-decoration-none" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-user-circle fa-lg me-2"></i>
                                        <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                                        <li><a class="dropdown-item" href="../modules/notifications.php"><i class="fas fa-bell me-2"></i>Notifications</a></li>
                                        <li><a class="dropdown-item" href="my_bookings.php"><i class="fas fa-calendar-check me-2"></i>My Bookings</a></li>
                                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="../public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container mt-4">
            <?php if (!$reservation && !$amenityBooking): ?>
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h5>Invalid Payment Request</h5>
                    <p class="text-muted">The payment request is invalid or you don't have permission to access it.</p>
                    <a href="my_bookings.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to My Bookings
                    </a>
                </div>
            <?php else: ?>
                <!-- Payment Header -->
                <div class="payment-card">
                    <h3><i class="fas fa-credit-card"></i> Secure Payment</h3>
                    <p class="mb-0">Complete your payment to confirm your booking</p>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Payment Methods -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-credit-card"></i> Select Payment Method</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="paymentForm">
                                    <input type="hidden" name="amount" value="<?php echo $reservation ? $reservation['total_amount'] : $amenityBooking['total_amount']; ?>">
                                    
                                    <div class="row">
                                        <!-- GCash -->
                                        <div class="col-md-6 mb-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('gcash')">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-mobile-alt fa-2x text-success mb-3"></i>
                                                    <h6>GCash</h6>
                                                    <small class="text-muted">Pay using GCash wallet</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- PayMaya -->
                                        <div class="col-md-6 mb-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('paymaya')">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-credit-card fa-2x text-primary mb-3"></i>
                                                    <h6>PayMaya</h6>
                                                    <small class="text-muted">Pay using PayMaya wallet</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Bank Transfer -->
                                        <div class="col-md-6 mb-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('bank_transfer')">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-university fa-2x text-info mb-3"></i>
                                                    <h6>Bank Transfer</h6>
                                                    <small class="text-muted">Direct bank transfer</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Credit Card -->
                                        <div class="col-md-6 mb-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('credit_card')">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-credit-card fa-2x text-warning mb-3"></i>
                                                    <h6>Credit Card</h6>
                                                    <small class="text-muted">Visa, Mastercard, etc.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="payment_method" id="selectedPaymentMethod" required>
                                    
                                    <div class="mb-3" id="transactionReferenceDiv" style="display: none;">
                                        <label class="form-label">Transaction Reference Number</label>
                                        <input type="text" class="form-control" name="transaction_reference" 
                                               placeholder="Enter your transaction reference number">
                                        <small class="form-text text-muted">
                                            Please provide the reference number from your payment confirmation.
                                        </small>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="process_payment" class="btn btn-primary btn-lg" disabled id="payButton">
                                            <i class="fas fa-lock"></i> Process Payment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h5><i class="fas fa-receipt"></i> Payment Summary</h5>
                            
                            <?php if ($reservation): ?>
                                <div class="mb-3">
                                    <strong>Unit Reservation</strong><br>
                                    <small class="text-muted">
                                        Unit <?php echo $reservation['unit_number']; ?> - <?php echo $reservation['unit_type']; ?><br>
                                        <?php echo $reservation['branch_name']; ?><br>
                                        <?php echo formatDate($reservation['check_in_date']); ?> - <?php echo formatDate($reservation['check_out_date']); ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Rental Amount:</span>
                                    <span><?php echo format_currency($reservation['total_amount']); ?></span>
                                </div>
                                
                                <?php if ($reservation['security_deposit'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Security Deposit:</span>
                                        <span><?php echo format_currency($reservation['security_deposit']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total Amount:</strong>
                                    <strong><?php echo format_currency($reservation['total_amount'] + $reservation['security_deposit']); ?></strong>
                                </div>
                                
                            <?php elseif ($amenityBooking): ?>
                                <div class="mb-3">
                                    <strong>Amenity Booking</strong><br>
                                    <small class="text-muted">
                                        <?php echo $amenityBooking['amenity_name']; ?><br>
                                        <?php echo $amenityBooking['branch_name']; ?><br>
                                        <?php echo formatDate($amenityBooking['booking_date']); ?> at <?php echo $amenityBooking['start_time']; ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <strong>Total Amount:</strong>
                                    <strong><?php echo format_currency($amenityBooking['total_amount']); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt"></i> Your payment is secured with SSL encryption
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/renter/payment.js"></script>
</body>
</html>
