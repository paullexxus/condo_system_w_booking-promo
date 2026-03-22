<?php
/**
 * BookIT Data Verification & Analytics Dashboard
 * Displays real-time statistics of the populated database
 */

include 'config/constants.php';
include 'config/db.php';
include 'includes/functions.php';

// Get database statistics
$stats = [];

// Count users by role
$user_counts = get_single_result("SELECT 
    SUM(role='admin') as admins,
    SUM(role='host') as hosts,
    SUM(role='renter') as renters,
    COUNT(*) as total
FROM users");

// Reservation statistics
$reservation_stats = get_single_result("SELECT 
    SUM(status='awaiting_approval') as pending,
    SUM(status='confirmed') as confirmed,
    SUM(status='completed') as completed,
    SUM(status='cancelled') as cancelled,
    COUNT(*) as total,
    ROUND(AVG(total_amount), 2) as avg_booking_value,
    ROUND(SUM(total_amount), 2) as total_revenue
FROM reservations");

// Unit statistics
$unit_stats = get_single_result("SELECT 
    SUM(is_available=1) as available,
    SUM(is_available=0) as occupied,
    COUNT(*) as total,
    ROUND(AVG(monthly_rate), 2) as avg_rate
FROM units");

// Branch statistics
$branch_count = get_single_result("SELECT COUNT(*) as total FROM branches");

// Amenity statistics
$amenity_count = get_single_result("SELECT COUNT(*) as total FROM amenities");

// Pending notifications
$pending_notif = get_single_result("SELECT COUNT(*) as total FROM notifications WHERE is_read=0");

// Upcoming check-ins (next 7 days)
$upcoming = get_multiple_results("SELECT r.reservation_id, r.check_in_date, u.unit_number, us.full_name, us.email
FROM reservations r
JOIN units u ON r.unit_id = u.unit_id
JOIN users us ON r.user_id = us.user_id
WHERE r.status = 'confirmed' AND r.check_in_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY r.check_in_date ASC");

// Recent bookings
$recent_bookings = get_multiple_results("SELECT r.reservation_id, r.check_in_date, r.check_out_date, r.total_amount, r.status, u.unit_number, us.full_name
FROM reservations r
JOIN units u ON r.unit_id = u.unit_id
JOIN users us ON r.user_id = us.user_id
ORDER BY r.created_at DESC
LIMIT 10");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookIT - Data Verification Dashboard</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card h5 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table-card h3 {
            color: #667eea;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #cfe2ff; color: #084298; }
        .status-cancelled { background: #f8d7da; color: #842029; }
        .table thead {
            background: #f8f9fa;
            border-top: 2px solid #667eea;
        }
        .table th {
            color: #667eea;
            font-weight: 600;
            border: none;
        }
        .footer {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .currency {
            font-weight: 600;
            color: #28a745;
        }
        @media (max-width: 768px) {
            .stat-card { margin-bottom: 15px; }
            .stat-value { font-size: 2em; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 BookIT Database Verification Dashboard</h1>
            <p class="text-muted">Real-time statistics and data verification for your BookIT system</p>
        </div>

        <!-- Key Metrics Row 1 -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card success">
                    <h5>Total Users</h5>
                    <div class="stat-value"><?php echo $user_counts['total']; ?></div>
                    <div class="stat-label">Admin: <?php echo $user_counts['admins']; ?> | Host: <?php echo $user_counts['hosts']; ?> | Renter: <?php echo $user_counts['renters']; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card info">
                    <h5>Total Bookings</h5>
                    <div class="stat-value"><?php echo $reservation_stats['total']; ?></div>
                    <div class="stat-label">Pending: <?php echo $reservation_stats['pending']; ?> | Confirmed: <?php echo $reservation_stats['confirmed']; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card warning">
                    <h5>Total Revenue</h5>
                    <div class="stat-value currency">₱<?php echo number_format($reservation_stats['total_revenue'], 0); ?></div>
                    <div class="stat-label">Avg Booking: ₱<?php echo number_format($reservation_stats['avg_booking_value'], 0); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card success">
                    <h5>Properties</h5>
                    <div class="stat-value"><?php echo $unit_stats['total']; ?></div>
                    <div class="stat-label">Available: <?php echo $unit_stats['available']; ?> | Occupied: <?php echo $unit_stats['occupied']; ?></div>
                </div>
            </div>
        </div>

        <!-- Secondary Metrics Row 2 -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <h5>📍 Branches</h5>
                    <div class="stat-value"><?php echo $branch_count['total']; ?></div>
                    <div class="stat-label">Active locations</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <h5>🎯 Amenities</h5>
                    <div class="stat-value"><?php echo $amenity_count['total']; ?></div>
                    <div class="stat-label">Services available</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card warning">
                    <h5>🔔 Notifications</h5>
                    <div class="stat-value"><?php echo $pending_notif['total']; ?></div>
                    <div class="stat-label">Unread messages</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <h5>📈 Avg Unit Rate</h5>
                    <div class="stat-value currency">₱<?php echo number_format($unit_stats['avg_rate'], 0); ?></div>
                    <div class="stat-label">Per month</div>
                </div>
            </div>
        </div>

        <!-- Booking Status Distribution -->
        <div class="table-card">
            <h3>📋 Booking Status Distribution</h3>
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center p-3" style="border-radius: 10px; background: #e8f5e9;">
                        <h4 class="text-success"><?php echo $reservation_stats['confirmed']; ?></h4>
                        <p class="text-muted">Confirmed Bookings</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3" style="border-radius: 10px; background: #fff3cd;">
                        <h4 class="text-warning"><?php echo $reservation_stats['pending']; ?></h4>
                        <p class="text-muted">Pending Approval</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3" style="border-radius: 10px; background: #cfe2ff;">
                        <h4 class="text-info"><?php echo $reservation_stats['completed']; ?></h4>
                        <p class="text-muted">Completed</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3" style="border-radius: 10px; background: #f8d7da;">
                        <h4 class="text-danger"><?php echo $reservation_stats['cancelled']; ?></h4>
                        <p class="text-muted">Cancelled</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Check-ins -->
        <?php if (!empty($upcoming)): ?>
        <div class="table-card">
            <h3>📅 Upcoming Check-ins (Next 7 Days)</h3>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Unit</th>
                            <th>Guest Name</th>
                            <th>Check-in Date</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $booking): ?>
                        <tr>
                            <td><strong>#<?php echo $booking['reservation_id']; ?></strong></td>
                            <td><?php echo $booking['unit_number']; ?></td>
                            <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                            <td><?php echo htmlspecialchars($booking['email']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Bookings -->
        <div class="table-card">
            <h3>🆕 Recent Bookings</h3>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Guest</th>
                            <th>Unit</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $booking): ?>
                        <tr>
                            <td><strong>#<?php echo $booking['reservation_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                            <td><?php echo $booking['unit_number']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                            <td class="currency">₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0; color: #666;">
                ✨ BookIT System - Data Verification Complete<br>
                <small>Last updated: <?php echo date('Y-m-d H:i:s'); ?></small>
            </p>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
