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
                "SELECT r.*, u.unit_number, u.unit_type, b.branch_name, b.address, b.is_active as branch_active 
                FROM reservations r 
                JOIN units u ON r.unit_id = u.unit_id 
                JOIN branches b ON r.branch_id = b.branch_id 
                WHERE r.reservation_id = ? AND r.user_id = ?",
                [$id, $_SESSION['user_id']]
            );
            
            // Strict Payment Validations
            if ($reservation) {
                // Check if already paid or cancelled
                if ($reservation['status'] !== 'pending') {
                    $_SESSION['flash_error'] = "This reservation is already " . htmlspecialchars($reservation['status']) . ". Payment is no longer required or possible.";
                    header('Location: my_bookings.php');
                    exit;
                }
                
                // Check for hold expiration
                if (!empty($reservation['hold_expiry']) && strtotime($reservation['hold_expiry']) < time()) {
                    execute_query("UPDATE reservations SET status = 'expired' WHERE reservation_id = ?", [$id]);
                    $_SESSION['flash_error'] = "Your reservation hold has expired. The unit is no longer reserved for you. Please book again.";
                    header('Location: my_bookings.php');
                    exit;
                }
            } else {
                $_SESSION['flash_error'] = "Invalid or unauthorized reservation access.";
                header('Location: my_bookings.php');
                exit;
            }
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
    $amountSubmitted = (float)$_POST['amount'];
    $transactionReference = sanitize_input($_POST['transaction_reference']);
    
    $reservationId = null;
    $amenityBookingId = null;
    $totalRequired = 0;

    if ($reservation) {
        $reservationId = $reservation['reservation_id'];
        $base = (float)$reservation['total_amount'];
        $sec_deposit = (float)($reservation['security_deposit'] ?? 0);
        $service_fee = $base * 0.05;
        $vat = ($base + $service_fee) * 0.12;
        $totalRequired = $base + $sec_deposit + $service_fee + $vat;
    } elseif ($amenityBooking) {
        $amenityBookingId = $amenityBooking['booking_id'];
        $totalRequired = (float)$amenityBooking['total_amount'];
    }

    if (abs($amountSubmitted - $totalRequired) > 0.01) {
        $error = "Payment amount discrepancy detected. Please try again.";
    }
    
    $paymentProofPath = '';
    if (empty($error) && isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['payment_proof']['tmp_name'];
        $fileSize = $_FILES['payment_proof']['size'];
        $fileName = $_FILES['payment_proof']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmpPath);
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = "Invalid file extension. Only JPG, PNG, and PDF allowed.";
        } elseif (!in_array($mimeType, $allowedMimeTypes)) {
            $error = "Invalid file content. Please upload a real image or PDF.";
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $error = "File size exceeds 5MB limit.";
        } else {
            $uploadDir = dirname(__FILE__, 2) . '/uploads/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $newFileName = 'RCPT_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $paymentProofPath = 'uploads/receipts/' . $newFileName;
            } else {
                $error = "Failed to save the uploaded file.";
            }
        }
    } else if (empty($error)) {
        $error = "Payment proof is required. Please upload your receipt.";
    }
    
    if (empty($error)) {
        $paymentId = processPayment($reservationId, $amenityBookingId, $_SESSION['user_id'], $totalRequired, $paymentMethod, $transactionReference, 'pending', $paymentProofPath);
        
        if ($paymentId) {
            header('Location: my_bookings.php?payment_success=true&reservation_id=' . ($reservationId ?? $amenityBookingId));
            exit;
        } else {
            $error = "Failed to process payment. Possibly already under review.";
        }
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
                    <?php if ($reservation && $reservation['status'] === 'pending' && !empty($reservation['hold_expiry'])): ?>
                        <div class="mt-2 text-warning fw-bold">
                            <i class="fas fa-clock"></i> Hold expires in: <span id="payment-timer" data-expires="<?php echo $reservation['hold_expiry']; ?>">--:--</span>
                        </div>
                    <?php endif; ?>
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
                                <form method="POST" id="paymentForm" enctype="multipart/form-data">
                                    <?php 
                                        if ($reservation) {
                                            $base_ui = (float)$reservation['total_amount'];
                                            $sec_deposit = (float)($reservation['security_deposit'] ?? 0);
                                            $service_fee = $base_ui * 0.05;
                                            $vat = ($base_ui + $service_fee) * 0.12;
                                            $totalToPay = $base_ui + $sec_deposit + $service_fee + $vat;
                                        } else {
                                            $totalToPay = $amenityBooking['total_amount'];
                                        }
                                        $partialAmount = $reservation ? round($totalToPay * 0.5, 2) : 0;
                                    ?>
                                    
                                    <!-- Payment Choice Removed, Full Payment Default enforced -->
                                    <input type="hidden" name="payment_plan" value="full">
                                    <input type="hidden" name="amount" id="paymentAmountInput" value="<?php echo $totalToPay; ?>">
                                    <input type="hidden" name="is_partial" id="isPartialInput" value="0">
                                    
                                    <div class="row">
                                        <!-- GCash -->
                                        <div class="col-md-4 mb-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('gcash')">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-mobile-alt fa-2x text-success mb-3"></i>
                                                    <h6>GCash</h6>
                                                    <small class="text-muted">E-Wallet</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- PayMaya -->
                                        <div class="col-md-4 mb-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('paymaya')">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-credit-card fa-2x text-primary mb-3"></i>
                                                    <h6>PayMaya</h6>
                                                    <small class="text-muted">E-Wallet</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- PayPal -->
                                        <div class="col-md-4 mb-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('paypal')">
                                                <div class="card-body text-center">
                                                    <i class="fab fa-paypal fa-2x text-info mb-3"></i>
                                                    <h6>PayPal</h6>
                                                    <small class="text-muted">International</small>
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
                                    
                                    <div class="mb-4" id="paymentProofDiv" style="display: none;">
                                        <label class="form-label fw-bold">Payment Proof / Receipt <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" name="payment_proof" id="paymentProofInput" accept="image/*,.pdf">
                                        <small class="form-text text-muted">
                                            Please upload a screenshot or PDF of your transaction receipt. Required for verification.
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
                                    <span>Base Rental Amount:</span>
                                    <span><?php echo format_currency($base_ui); ?></span>
                                    </div>
                                    <?php if ($sec_deposit > 0): ?>
                                        <div class="d-flex justify-content-between mb-2 text-muted">
                                            <span style="font-size: 0.9em;">Security Deposit (Refundable):</span>
                                            <span style="font-size: 0.9em;"><?php echo format_currency($sec_deposit); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between mb-2 text-muted">
                                        <span style="font-size: 0.9em;">Service Fee (5%):</span>
                                        <span style="font-size: 0.9em;"><?php echo format_currency($service_fee); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 text-muted">
                                        <span style="font-size: 0.9em;">VAT (12%):</span>
                                        <span style="font-size: 0.9em;"><?php echo format_currency($vat); ?></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total Required:</strong>
                                        <strong id="summaryTotalDisplay" class="text-primary fs-5"><?php echo format_currency($totalToPay); ?></strong>
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
