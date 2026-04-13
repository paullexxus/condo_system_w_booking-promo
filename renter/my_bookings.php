<?php
// BookIT My Bookings
// Multi-branch Condo Rental Reservation System

include '../includes/session.php';
include '../includes/functions.php';
include_once '../includes/auth.php';
include_once '../includes/renter_functions.php';
checkRole(['renter']); // Tanging renters lang ang pwede

$message = '';
$error = '';
$csrf_token = generateCSRFToken();

// Handle booking removal (for pending bookings)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_booking'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    } else {
        $reservationId = (int)$_POST['reservation_id'];
        
        // I-check kung pwede pa i-remove (awaiting_approval status only)
        $reservation = get_single_result(
            "SELECT * FROM reservations WHERE reservation_id = ? AND user_id = ?",
            [$reservationId, $_SESSION['user_id']]
        );
        
        if ($reservation && in_array($reservation['status'], ['awaiting_approval', 'pending'], true)) {
            // I-delete ang reservation completely
            $sql = "DELETE FROM reservations WHERE reservation_id = ?";
            if (execute_query($sql, [$reservationId])) {
                $message = "Booking removed successfully!";
            } else {
                $error = "Failed to remove booking.";
            }
        } else {
            $error = "Cannot remove booking. Only pending bookings can be removed.";
        }
    }
}

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    } else {
        $reservationId = (int)$_POST['reservation_id'];
        
        // I-check kung pwede pa i-cancel (at least 24 hours before check-in)
        $reservation = get_single_result(
            "SELECT * FROM reservations WHERE reservation_id = ? AND user_id = ?",
            [$reservationId, $_SESSION['user_id']]
        );
        
        if ($reservation) {
            $checkInDate = new DateTime($reservation['check_in_date']);
            $today = new DateTime();
            $hoursUntilCheckIn = $today->diff($checkInDate)->h + ($today->diff($checkInDate)->days * 24);
            
            if ($hoursUntilCheckIn >= 24 && in_array($reservation['status'], ['confirmed', 'approved'], true)) {
                // I-cancel ang reservation
                $sql = "UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?";
                if (execute_query($sql, [$reservationId])) {
                    // Mag-send ng notification
                    sendNotification(
                        $_SESSION['user_id'],
                        "Reservation Cancelled",
                        "Your reservation #" . $reservationId . " has been cancelled successfully.",
                        'booking',
                        'system'
                    );
                    
                    // Notify host
                    $unit = get_single_result("SELECT u.host_id FROM units u WHERE unit_id = ?", [$reservation['unit_id']]);
                    if ($unit && $unit['host_id']) {
                        sendNotification(
                            $unit['host_id'],
                            "Reservation Cancelled",
                            "Renter has cancelled reservation #" . $reservationId . ".",
                            'booking',
                            'system'
                        );
                    }
                    
                    $message = "Reservation cancelled successfully!";
                } else {
                    $error = "Failed to cancel reservation.";
                }
            } else {
                $error = "Cannot cancel reservation. Must be cancelled at least 24 hours before check-in.";
            }
        } else {
            $error = "Reservation not found.";
        }
    }
}

// Kumuha ng user reservations - LEFT JOIN so rows still show if unit/branch metadata is missing
$reservations = get_multiple_results(
    "SELECT r.*, u.unit_number, u.unit_type, b.branch_name, b.address
    FROM reservations r 
    LEFT JOIN units u ON r.unit_id = u.unit_id 
    LEFT JOIN branches b ON r.branch_id = b.branch_id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC",
    [$_SESSION['user_id']]
);
if (!is_array($reservations)) {
    $reservations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        a {
            text-decoration: none !important;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .shadow-soft {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card-modern {
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: white;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-luxury-primary {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 10px 20px rgba(243, 156, 18, 0.2);
        }

        .btn-luxury-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(243, 156, 18, 0.35);
            color: white;
        }

        .booking-status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Premium Navigation -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-soft">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="../public/index.php" class="flex items-center gap-3 text-2xl font-bold text-gray-900 hover:opacity-80 smooth-transition">
                    <img src="../assets/images/logo/bookit.png" alt="BookIT Logo" class="w-14 h-14 rounded-xl object-cover">
                    BookIT
                </a>

                <div class="hidden md:flex items-center gap-10">
                    <a href="../public/index.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Home</a>
                    <a href="../public/browse_units.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Browse</a>
                    <a href="my_bookings.php" class="text-blue-600 font-600 border-b-2 border-blue-600 pb-2">My Bookings</a>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 smooth-transition">
                            <i class="fas fa-user-circle text-2xl"></i>
                            <span class="hidden sm:inline text-sm font-500"><?php echo htmlspecialchars(substr($_SESSION['fullname'], 0, 15)); ?></span>
                        </button>
                        <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                            <a href="../modules/notifications.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 first:rounded-t-lg smooth-transition border-b">
                                <i class="fas fa-bell mr-2 text-blue-500"></i> Notifications
                            </a>
                            <a href="reserve_unit.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 smooth-transition border-b">
                                <i class="fas fa-plus mr-2 text-orange-500"></i> New Reservation
                            </a>
                            <a href="profile.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 smooth-transition border-b">
                                <i class="fas fa-cog mr-2 text-gray-500"></i> Settings
                            </a>
                            <?php if (isLoggedIn() && in_array($_SESSION['role'], ['host','manager','admin'])): ?>
                                <a href="../host/host_dashboard.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 smooth-transition border-b">
                                    <i class="fas fa-tachometer-alt mr-2 text-purple-500"></i> Host Dashboard
                                </a>
                            <?php endif; ?>
                            <hr class="my-2">
                            <a href="../public/logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 last:rounded-b-lg smooth-transition font-500">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-5xl font-bold text-gray-900 mb-2"><i class="fas fa-calendar-check text-orange-500"></i> My Bookings</h1>
                <p class="text-gray-600 text-lg">Manage all your condo reservations in one place</p>
            </div>
            <a href="reserve_unit.php" class="btn-modern btn-luxury-primary text-lg">
                <i class="fas fa-plus"></i> New Reservation
            </a>
        </div>

            <!-- Messages -->
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
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

            <!-- Bookings List -->
            <?php if (!empty($reservations) && count($reservations) > 0): ?>
                <div class="row">
                    <?php foreach ($reservations as $booking): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card booking-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-home"></i> 
                                        <?php echo !empty($booking['unit_name']) ? htmlspecialchars($booking['unit_name']) : 'Unit ' . htmlspecialchars($booking['unit_number']); ?>
                                    </h6>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php 
                                            if (in_array($booking['status'], ['awaiting_approval', 'pending'], true)) {
                                                echo '<i class="fas fa-clock me-1"></i>Pending Approval';
                                            } elseif ($booking['status'] == 'approved') {
                                                echo '<i class="fas fa-check me-1"></i>Approved';
                                            } elseif ($booking['status'] == 'confirmed') {
                                                echo '<i class="fas fa-check-double me-1"></i>Confirmed';
                                            } else {
                                                echo ucfirst(str_replace('_', ' ', $booking['status']));
                                            }
                                        ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">
                                        <strong><i class="fas fa-building"></i> Branch:</strong> <?php echo $booking['branch_name']; ?><br>
                                        <strong><i class="fas fa-map-marker-alt"></i> Address:</strong> <?php echo $booking['address']; ?><br>
                                        <strong><i class="fas fa-bed"></i> Type:</strong> <?php echo !empty($booking['unit_name']) ? htmlspecialchars($booking['unit_name']) : htmlspecialchars($booking['unit_type']); ?><br>
                                        <strong><i class="fas fa-calendar"></i> Check-in:</strong> <?php echo formatDate($booking['check_in_date']); ?><br>
                                        <strong><i class="fas fa-calendar"></i> Check-out:</strong> <?php echo formatDate($booking['check_out_date']); ?><br>
                                        <strong><i class="fas fa-clock"></i> Duration:</strong> <?php echo calculateDays($booking['check_in_date'], $booking['check_out_date']); ?> days<br>
                                        <strong><i class="fas fa-money-bill-wave"></i> Total Amount:</strong> <?php echo format_currency($booking['total_amount']); ?><br>
                                        <strong><i class="fas fa-shield-alt"></i> Security Deposit:</strong> <?php echo format_currency($booking['security_deposit']); ?>
                                    </p>
                                    
                                    <?php if ($booking['special_requests']): ?>
                                        <div class="alert alert-info">
                                            <strong><i class="fas fa-comment"></i> Special Requests:</strong><br>
                                            <?php echo $booking['special_requests']; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Approval Status Alert -->
                                    <?php if (in_array($booking['status'], ['awaiting_approval', 'pending'], true)): ?>
                                        <div class="alert alert-warning alert-sm" style="padding: 10px; margin-bottom: 15px;">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <small>Your booking is awaiting approval from the branch host. You'll be notified once they review it.</small>
                                        </div>
                                    <?php elseif ($booking['status'] == 'rejected'): ?>
                                        <div class="alert alert-danger alert-sm" style="padding: 10px; margin-bottom: 15px;">
                                            <i class="fas fa-times-circle me-1"></i>
                                            <small><?php echo !empty($booking['rejection_reason']) ? 'Reason: ' . htmlspecialchars($booking['rejection_reason']) : 'This booking has been rejected.'; ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="payment-badge payment-<?php echo $booking['payment_status']; ?>">
                                            Payment: <?php echo ucfirst(str_replace('_', ' ', $booking['payment_status'])); ?>
                                        </span>
                                        <small class="text-muted">
                                            Booked: <?php echo formatDate($booking['created_at']); ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <button class="btn btn-sm btn-info" 
                                                onclick="showBookingDetailsModal(<?php echo htmlspecialchars(json_encode($booking), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        
                                        <?php if ($booking['status'] == 'confirmed'): ?>
                                            <?php
                                            $checkInDate = new DateTime($booking['check_in_date']);
                                            $today = new DateTime();
                                            $hoursUntilCheckIn = $today->diff($checkInDate)->h + ($today->diff($checkInDate)->days * 24);
                                            ?>
                                            <?php if ($hoursUntilCheckIn >= 24): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmCancel(<?php echo $booking['reservation_id']; ?>)">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                            <!-- If checked out, allow leaving a review (within 14 days) -->
                                            <?php
                                            $canReview = false;
                                            try {
                                                $co = new DateTime($booking['check_out_date']);
                                                $nowDT = new DateTime();
                                                $diff = $nowDT->diff($co);
                                                if ($co <= $nowDT && $diff->days <= 14) {
                                                    $existingReview = get_single_result("SELECT review_id FROM reviews WHERE unit_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1", [$booking['unit_id'], $_SESSION['user_id']]);
                                                    if (!$existingReview) $canReview = true;
                                                }
                                            } catch (Exception $e) {}
                                            if ($canReview): ?>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal" data-reservation='<?php echo json_encode(['reservation_id'=>$booking['reservation_id'],'unit_number'=>$booking['unit_number']]); ?>'>
                                                    <i class="fas fa-star"></i> Leave Review
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif (in_array($booking['status'], ['approved', 'confirmed'], true) && !in_array(strtolower((string)($booking['payment_status'] ?? '')), ['paid'], true)): ?>
                                            <form action="checkout.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="reservation_id" value="<?php echo $booking['reservation_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-credit-card"></i> Pay Now
                                                </button>
                                            </form>
                                        <?php elseif (in_array($booking['status'], ['awaiting_approval', 'pending'], true)): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmRemove(<?php echo $booking['reservation_id']; ?>)">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        <?php elseif ($booking['status'] == 'rejected'): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmRemove(<?php echo $booking['reservation_id']; ?>)">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5>No Bookings Found</h5>
                    <p class="text-muted">You haven't made any reservations yet.</p>
                    <a href="reserve_unit.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Make Your First Reservation
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Remove Booking Modal -->
    <div class="modal fade" id="removeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="reservation_id" id="remove_reservation_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Remove Booking</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to remove this booking?</p>
                        <p class="text-muted">This will permanently delete your booking request. You can make a new booking if needed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Booking</button>
                        <button type="submit" name="remove_booking" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Remove Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="reservation_id" id="cancel_reservation_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Confirm Cancellation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to cancel this reservation?</p>
                        <p class="text-muted">This action cannot be undone. Any payments made may be subject to refund policies.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Reservation</button>
                        <button type="submit" name="cancel_booking" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancel Reservation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailsModal" tabindex="-1" size="lg">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalTitle">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="bookingDetailsContent">
                        <!-- Details will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="submit_review.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="reservation_id" id="review_reservation_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-star"></i> Leave a Review</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select" required>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Very Good</option>
                                <option value="3">3 - Good</option>
                                <option value="2">2 - Fair</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comments</label>
                            <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/renter/my_bookings.js"></script>
    <script>
    // Populate review modal with reservation id when opened
    var reviewModal = document.getElementById('reviewModal');
    reviewModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var data = button.getAttribute('data-reservation');
        try {
            var obj = JSON.parse(data);
            document.getElementById('review_reservation_id').value = obj.reservation_id;
        } catch (e) {}
    });
    </script>
</body>
</html>
