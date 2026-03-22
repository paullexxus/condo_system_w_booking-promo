<?php
// BookIT Book Amenity
// Amenity booking for renters

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';
include_once '../includes/renter_functions.php';
checkRole(['renter']); // Tanging renters lang ang pwede

$message = '';
$error = '';
$availableAmenities = [];
$selectedBranch = '';
$bookingDate = '';

// Handle amenity booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_amenity'])) {
    $amenityId = $_POST['amenity_id'];
    $branchId = $_POST['branch_id'];
    $bookingDate = $_POST['booking_date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $specialRequests = sanitize_input($_POST['special_requests']);
    
    // Kumuha ng amenity details
    $amenity = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM amenities WHERE amenity_id = $amenityId"));
    
    if ($amenity) {
        // I-calculate ang total amount
        $startDateTime = new DateTime($bookingDate . ' ' . $startTime);
        $endDateTime = new DateTime($bookingDate . ' ' . $endTime);
        $hours = $startDateTime->diff($endDateTime)->h;
        $totalAmount = $amenity['hourly_rate'] * $hours;
        
        // I-book ang amenity
        $bookingId = bookAmenity(
            $_SESSION['user_id'], 
            $amenityId, 
            $branchId, 
            $bookingDate, 
            $startTime, 
            $endTime, 
            $totalAmount
        );
        
        if ($bookingId) {
            // Mag-send ng notification
            sendNotification(
                $_SESSION['user_id'],
                "Amenity Booking Confirmed",
                "Your booking for " . $amenity['amenity_name'] . " has been confirmed for " . formatDate($bookingDate) . " at " . $startTime,
                'booking',
                'system'
            );
            
            $message = "Amenity booking confirmed! Booking ID: " . $bookingId;
            $availableAmenities = []; // I-clear ang search results
        } else {
            $error = "Failed to book amenity. It may no longer be available.";
        }
    } else {
        $error = "Invalid amenity selected.";
    }
}

// Handle search for amenities
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_amenities'])) {
    $selectedBranch = $_POST['branch_id'];
    $bookingDate = $_POST['booking_date'];
    
        // Kumuha ng amenities sa selected branch
        $availableAmenities = mysqli_query($conn, "SELECT * FROM amenities WHERE branch_id = $selectedBranch AND is_available = 1 ORDER BY amenity_name");
    
    if (mysqli_num_rows($availableAmenities) == 0) {
        $message = "No amenities available for the selected branch.";
    }
}

// Kumuha ng lahat ng branches
$branches = mysqli_query($conn, "SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Amenity - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/renter/book_amenity.css">
    <style>    
    .search-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .amenity-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        .amenity-card:hover {
            transform: translateY(-5px);
        }
        .amenity-image {
            height: 200px;
            background: linear-gradient(45deg, #e8f5e8, #d4edda);
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #28a745;
        }
        .price-tag {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .capacity-badge {
            background: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
    </style>
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
                                        <li><a class="dropdown-item" href="reserve_unit.php"><i class="fas fa-home me-2"></i>Reserve Unit</a></li>
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
            <!-- Search Form -->
            <div class="search-card">
                <h3><i class="fas fa-swimming-pool"></i> Book Additional Amenities</h3>
                <p class="mb-4">Reserve condo facilities like swimming pool, gym, function rooms, and more</p>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Select Branch</label>
                            <select class="form-select" name="branch_id" required>
                                <option value="">Choose Branch</option>
                                <?php while ($branch = mysqli_fetch_assoc($branches)): ?>
                                    <option value="<?php echo $branch['branch_id']; ?>" 
                                            <?php echo $selectedBranch == $branch['branch_id'] ? 'selected' : ''; ?>>
                                        <?php echo $branch['branch_name']; ?> - <?php echo $branch['city']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Booking Date</label>
                            <input type="date" class="form-control" name="booking_date" 
                                   value="<?php echo $bookingDate; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" name="search_amenities" class="btn btn-light w-100">
                                <i class="fas fa-search"></i> Find Amenities
                            </button>
                        </div>
                    </div>
                </form>
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

            <!-- Available Amenities -->
            <?php if ($availableAmenities && mysqli_num_rows($availableAmenities) > 0): ?>
                <h4><i class="fas fa-swimming-pool"></i> Available Amenities</h4>
                <div class="row">
                    <?php while ($amenity = mysqli_fetch_assoc($availableAmenities)): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card amenity-card">
                                <div class="amenity-image">
                                    <i class="fas fa-swimming-pool fa-3x"></i>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php echo $amenity['amenity_name']; ?>
                                        <span class="price-tag float-end">
                                            <?php echo formatCurrency($amenity['hourly_rate']); ?>/hour
                                        </span>
                                    </h5>
                                    <p class="card-text">
                                        <span class="capacity-badge">
                                            <i class="fas fa-users"></i> Max <?php echo $amenity['max_capacity']; ?> persons
                                        </span>
                                    </p>
                                    <?php if ($amenity['description']): ?>
                                        <p class="card-text"><small class="text-muted"><?php echo $amenity['description']; ?></small></p>
                                    <?php endif; ?>
                                    
                                    <!-- Booking Form -->
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="amenity_id" value="<?php echo $amenity['amenity_id']; ?>">
                                        <input type="hidden" name="branch_id" value="<?php echo $amenity['branch_id']; ?>">
                                        <input type="hidden" name="booking_date" value="<?php echo $bookingDate; ?>">
                                        
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <label class="form-label">Start Time</label>
                                                <input type="time" class="form-control" name="start_time" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">End Time</label>
                                                <input type="time" class="form-control" name="end_time" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Special Requests (Optional)</label>
                                            <textarea class="form-control" name="special_requests" rows="2" 
                                                      placeholder="Any special requirements..."></textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted">Rate: <?php echo formatCurrency($amenity['hourly_rate']); ?>/hour</small>
                                            </div>
                                            <button type="submit" name="book_amenity" class="btn btn-success">
                                                <i class="fas fa-calendar-plus"></i> Book Now
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_amenities'])): ?>
                <div class="text-center py-5">
                    <i class="fas fa-swimming-pool fa-3x text-muted mb-3"></i>
                    <h5>No Amenities Available</h5>
                    <p class="text-muted">No amenities are available for the selected branch and date.</p>
                    <button class="btn btn-success" onclick="window.location.reload()">
                        <i class="fas fa-refresh"></i> Try Different Branch
                    </button>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-swimming-pool fa-3x text-muted mb-3"></i>
                    <h5>Book Additional Amenities</h5>
                    <p class="text-muted">Select a branch and date to see available amenities.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate end time when start time changes
        document.querySelectorAll('input[name="start_time"]').forEach(function(startTimeInput) {
            startTimeInput.addEventListener('change', function() {
                const startTime = this.value;
                const endTimeInput = this.closest('.row').querySelector('input[name="end_time"]');
                
                if (startTime) {
                    const [hours, minutes] = startTime.split(':');
                    const endTime = new Date();
                    endTime.setHours(parseInt(hours) + 1, parseInt(minutes));
                    
                    const endTimeString = endTime.getHours().toString().padStart(2, '0') + ':' + 
                                        endTime.getMinutes().toString().padStart(2, '0');
                    endTimeInput.value = endTimeString;
                }
            });
        });
    </script>
    <script src="../assets/js/renter/book_amenity.js"></script>
</body>
</html>
