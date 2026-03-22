<?php
/**
 * BookIT Test Data Population Script
 * Populates the database with realistic test data for complete project testing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config/constants.php';
include 'config/db.php';

echo "<h1>📊 BookIT Test Data Population</h1>";
echo "<hr>";

// Color coding for output
function success($msg) { echo "<span style='color: green;'>✓ $msg</span><br>"; }
function error_msg($msg) { echo "<span style='color: red;'>✗ $msg</span><br>"; }
function info($msg) { echo "<span style='color: blue;'>ℹ $msg</span><br>"; }
function section($title) { echo "<h3 style='margin-top: 20px; color: #333;'>$title</h3>"; }

// Hash password helper
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

section("1. CLEARING EXISTING DATA (Optional)");
echo "<p><small>Tables will be cleared to ensure clean data...</small></p>";

try {
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Clear tables in order
    $tables = ['amenity_bookings', 'amenities', 'reservations', 'notifications', 'units', 'branches', 'users'];
    
    foreach ($tables as $table) {
        if ($conn->query("TRUNCATE TABLE $table")) {
            success("Cleared $table");
        }
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
} catch (Exception $e) {
    error_msg("Error clearing tables: " . $e->getMessage());
}

section("2. CREATING ADMIN USERS");

// Admin user
$admin_data = [
    'full_name' => 'System Administrator',
    'email' => 'admin@bookit.com',
    'password' => hash_password('Admin@123456'),
    'phone' => '09171234567',
    'role' => 'admin',
    'branch_id' => null,
    'is_active' => 1
];

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role, branch_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssssii', $admin_data['full_name'], $admin_data['email'], $admin_data['password'], 
                   $admin_data['phone'], $admin_data['role'], $admin_data['branch_id'], $admin_data['is_active']);

if ($stmt->execute()) {
    $admin_id = $conn->insert_id;
    success("Admin user created (ID: $admin_id) - Email: admin@bookit.com, Password: Admin@123456");
} else {
    error_msg("Failed to create admin: " . $stmt->error);
}
$stmt->close();

section("3. CREATING HOST USERS (Property Managers)");

$hosts = [
    [
        'full_name' => 'Maria Garcia',
        'email' => 'maria.garcia@bookit.com',
        'password' => hash_password('Host@12345'),
        'phone' => '09175551234'
    ],
    [
        'full_name' => 'Juan Santos',
        'email' => 'juan.santos@bookit.com',
        'password' => hash_password('Host@12345'),
        'phone' => '09175555678'
    ],
    [
        'full_name' => 'Angela Reyes',
        'email' => 'angela.reyes@bookit.com',
        'password' => hash_password('Host@12345'),
        'phone' => '09175559999'
    ]
];

$host_ids = [];

foreach ($hosts as $host) {
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role, is_active) VALUES (?, ?, ?, ?, 'host', 1)");
    $stmt->bind_param('ssss', $host['full_name'], $host['email'], $host['password'], $host['phone']);
    
    if ($stmt->execute()) {
        $host_ids[] = $conn->insert_id;
        success("Host created: {$host['full_name']} (Email: {$host['email']}, Password: Host@12345)");
    } else {
        error_msg("Failed to create host {$host['full_name']}: " . $stmt->error);
    }
    $stmt->close();
}

section("4. CREATING RENTER USERS");

$renters = [
    [
        'full_name' => 'Michael Johnson',
        'email' => 'michael.johnson@email.com',
        'password' => hash_password('Renter@123'),
        'phone' => '09161234567'
    ],
    [
        'full_name' => 'Sarah Chen',
        'email' => 'sarah.chen@email.com',
        'password' => hash_password('Renter@123'),
        'phone' => '09161234568'
    ],
    [
        'full_name' => 'Robert Cruz',
        'email' => 'robert.cruz@email.com',
        'password' => hash_password('Renter@123'),
        'phone' => '09161234569'
    ],
    [
        'full_name' => 'Lisa Wong',
        'email' => 'lisa.wong@email.com',
        'password' => hash_password('Renter@123'),
        'phone' => '09161234570'
    ],
    [
        'full_name' => 'Carlos Mendoza',
        'email' => 'carlos.mendoza@email.com',
        'password' => hash_password('Renter@123'),
        'phone' => '09161234571'
    ]
];

$renter_ids = [];

foreach ($renters as $renter) {
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role, is_active) VALUES (?, ?, ?, ?, 'renter', 1)");
    $stmt->bind_param('ssss', $renter['full_name'], $renter['email'], $renter['password'], $renter['phone']);
    
    if ($stmt->execute()) {
        $renter_ids[] = $conn->insert_id;
        success("Renter created: {$renter['full_name']} (Email: {$renter['email']}, Password: Renter@123)");
    } else {
        error_msg("Failed to create renter {$renter['full_name']}: " . $stmt->error);
    }
    $stmt->close();
}

section("5. CREATING BRANCHES (Property Locations)");

$branches = [
    [
        'branch_name' => 'Downtown Residences',
        'address' => '123 Makati Avenue, Makati City',
        'city' => 'Makati',
        'contact_number' => '02-7234-5678',
        'email' => 'downtown@bookit.com',
        'host_id' => $host_ids[0]
    ],
    [
        'branch_name' => 'Bay View Condos',
        'address' => '456 Bay Drive, Pasay City',
        'city' => 'Pasay',
        'contact_number' => '02-8765-4321',
        'email' => 'bayview@bookit.com',
        'host_id' => $host_ids[1]
    ],
    [
        'branch_name' => 'BGC Plaza Apartments',
        'address' => '789 BGC Boulevard, Taguig City',
        'city' => 'Taguig',
        'contact_number' => '02-6234-9876',
        'email' => 'bgc@bookit.com',
        'host_id' => $host_ids[2]
    ]
];

$branch_ids = [];

foreach ($branches as $branch) {
    $stmt = $conn->prepare("INSERT INTO branches (branch_name, address, city, contact_number, email, host_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('ssssssi', $branch['branch_name'], $branch['address'], $branch['city'], 
                      $branch['contact_number'], $branch['email'], $branch['host_id']);
    
    if ($stmt->execute()) {
        $branch_ids[] = $conn->insert_id;
        success("Branch created: {$branch['branch_name']} (Host: {$host_ids[$branch['host_id'] - 1]})");
    } else {
        error_msg("Failed to create branch {$branch['branch_name']}: " . $stmt->error);
    }
    $stmt->close();
}

section("6. CREATING UNITS (Rental Properties)");

$units = [
    // Downtown Residences
    ['branch_id' => $branch_ids[0], 'unit_number' => '1201', 'type' => 'Studio', 'floor' => 12, 'rate' => 25000, 'deposit' => 50000, 'occupancy' => 1],
    ['branch_id' => $branch_ids[0], 'unit_number' => '1202', 'type' => '1-Bedroom', 'floor' => 12, 'rate' => 35000, 'deposit' => 70000, 'occupancy' => 2],
    ['branch_id' => $branch_ids[0], 'unit_number' => '1203', 'type' => '2-Bedroom', 'floor' => 12, 'rate' => 50000, 'deposit' => 100000, 'occupancy' => 4],
    ['branch_id' => $branch_ids[0], 'unit_number' => '1301', 'type' => '1-Bedroom', 'floor' => 13, 'rate' => 35000, 'deposit' => 70000, 'occupancy' => 2],
    
    // Bay View Condos
    ['branch_id' => $branch_ids[1], 'unit_number' => '501', 'type' => 'Studio', 'floor' => 5, 'rate' => 22000, 'deposit' => 44000, 'occupancy' => 1],
    ['branch_id' => $branch_ids[1], 'unit_number' => '502', 'type' => '1-Bedroom', 'floor' => 5, 'rate' => 32000, 'deposit' => 64000, 'occupancy' => 2],
    ['branch_id' => $branch_ids[1], 'unit_number' => '503', 'type' => '2-Bedroom', 'floor' => 5, 'rate' => 48000, 'deposit' => 96000, 'occupancy' => 4],
    ['branch_id' => $branch_ids[1], 'unit_number' => '601', 'type' => '1-Bedroom', 'floor' => 6, 'rate' => 32000, 'deposit' => 64000, 'occupancy' => 2],
    
    // BGC Plaza Apartments
    ['branch_id' => $branch_ids[2], 'unit_number' => '2001', 'type' => 'Studio', 'floor' => 20, 'rate' => 28000, 'deposit' => 56000, 'occupancy' => 1],
    ['branch_id' => $branch_ids[2], 'unit_number' => '2002', 'type' => '1-Bedroom', 'floor' => 20, 'rate' => 38000, 'deposit' => 76000, 'occupancy' => 2],
    ['branch_id' => $branch_ids[2], 'unit_number' => '2003', 'type' => '2-Bedroom', 'floor' => 20, 'rate' => 55000, 'deposit' => 110000, 'occupancy' => 4],
    ['branch_id' => $branch_ids[2], 'unit_number' => '2101', 'type' => '1-Bedroom', 'floor' => 21, 'rate' => 38000, 'deposit' => 76000, 'occupancy' => 2],
];

$unit_ids = [];

foreach ($units as $unit) {
    $desc = "Beautiful {$unit['type']} unit on floor {$unit['floor']}. Fully furnished with modern amenities.";
    $stmt = $conn->prepare("INSERT INTO units (branch_id, unit_number, unit_type, floor_number, monthly_rate, security_deposit, is_available, description, max_occupancy) 
                           VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
    $stmt->bind_param('isisidsi', $unit['branch_id'], $unit['unit_number'], $unit['type'], $unit['floor'], 
                      $unit['rate'], $unit['deposit'], $desc, $unit['occupancy']);
    
    if ($stmt->execute()) {
        $unit_ids[] = $conn->insert_id;
        success("Unit created: {$unit['unit_number']} ({$unit['type']}) - ₱{$unit['rate']}/month");
    } else {
        error_msg("Failed to create unit {$unit['unit_number']}: " . $stmt->error);
    }
    $stmt->close();
}

section("7. CREATING AMENITIES");

$amenities = [
    ['name' => 'Swimming Pool', 'description' => 'Olympic-size swimming pool', 'price_per_use' => 200],
    ['name' => 'Gym', 'description' => '24/7 fully equipped gym facility', 'price_per_use' => 150],
    ['name' => 'Parking Space', 'description' => 'Dedicated parking slot for vehicle', 'price_per_month' => 2000],
    ['name' => 'Security Deposit Locker', 'description' => 'Safe storage for valuables', 'price_per_month' => 500],
    ['name' => 'WiFi Internet', 'description' => 'High-speed WiFi connectivity', 'price_per_month' => 1500],
    ['name' => 'Housekeeping', 'description' => 'Professional housekeeping service', 'price_per_use' => 800],
];

$amenity_ids = [];

foreach ($amenities as $amenity) {
    $price = $amenity['price_per_use'] ?? $amenity['price_per_month'];
    $stmt = $conn->prepare("INSERT INTO amenities (amenity_name, description, price) VALUES (?, ?, ?)");
    $stmt->bind_param('ssd', $amenity['name'], $amenity['description'], $price);
    
    if ($stmt->execute()) {
        $amenity_ids[] = $conn->insert_id;
        success("Amenity created: {$amenity['name']} - ₱{$price}");
    } else {
        error_msg("Failed to create amenity: " . $stmt->error);
    }
    $stmt->close();
}

section("8. CREATING RESERVATIONS (Booking History)");

$reservations = [
    [
        'user_id' => $renter_ids[0],
        'unit_id' => $unit_ids[0],
        'check_in' => '2025-01-15',
        'check_out' => '2025-01-22',
        'total_amount' => 175000,
        'status' => 'confirmed',
        'special_requests' => 'High floor preferred, city view',
        'payment_method' => 'credit_card'
    ],
    [
        'user_id' => $renter_ids[1],
        'unit_id' => $unit_ids[1],
        'check_in' => '2025-02-01',
        'check_out' => '2025-02-28',
        'total_amount' => 1050000,
        'status' => 'confirmed',
        'special_requests' => 'Extra pillows and blankets needed',
        'payment_method' => 'gcash'
    ],
    [
        'user_id' => $renter_ids[2],
        'unit_id' => $unit_ids[4],
        'check_in' => '2025-01-20',
        'check_out' => '2025-01-25',
        'total_amount' => 110000,
        'status' => 'awaiting_approval',
        'special_requests' => 'Late check-in around 10 PM',
        'payment_method' => 'card'
    ],
    [
        'user_id' => $renter_ids[3],
        'unit_id' => $unit_ids[8],
        'check_in' => '2025-01-25',
        'check_out' => '2025-02-01',
        'total_amount' => 196000,
        'status' => 'confirmed',
        'special_requests' => '',
        'payment_method' => 'gcash'
    ],
    [
        'user_id' => $renter_ids[4],
        'unit_id' => $unit_ids[2],
        'check_in' => '2025-02-10',
        'check_out' => '2025-02-17',
        'total_amount' => 350000,
        'status' => 'awaiting_approval',
        'special_requests' => 'Family of 4, need extra beds',
        'payment_method' => 'card'
    ],
];

$reservation_ids = [];

foreach ($reservations as $res) {
    $stmt = $conn->prepare("INSERT INTO reservations (user_id, unit_id, check_in_date, check_out_date, total_amount, status, special_requests, payment_method, created_at, updated_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param('iissdsss', $res['user_id'], $res['unit_id'], $res['check_in'], $res['check_out'], 
                      $res['total_amount'], $res['status'], $res['special_requests'], $res['payment_method']);
    
    if ($stmt->execute()) {
        $reservation_ids[] = $conn->insert_id;
        $dates = "{$res['check_in']} to {$res['check_out']}";
        $amount = number_format($res['total_amount'], 2);
        success("Reservation created: Unit {$unit_ids[$res['unit_id']-1]} | Dates: $dates | Amount: ₱$amount | Status: {$res['status']}");
    } else {
        error_msg("Failed to create reservation: " . $stmt->error);
    }
    $stmt->close();
}

section("9. CREATING SAMPLE AMENITY BOOKINGS");

$amenity_bookings = [
    ['reservation_id' => $reservation_ids[0], 'amenity_id' => $amenity_ids[0], 'quantity' => 3, 'total_price' => 600],
    ['reservation_id' => $reservation_ids[0], 'amenity_id' => $amenity_ids[1], 'quantity' => 7, 'total_price' => 1050],
    ['reservation_id' => $reservation_ids[1], 'amenity_id' => $amenity_ids[2], 'quantity' => 1, 'total_price' => 2000],
];

foreach ($amenity_bookings as $booking) {
    $stmt = $conn->prepare("INSERT INTO amenity_bookings (reservation_id, amenity_id, quantity, total_price, booked_at) 
                           VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('iiid', $booking['reservation_id'], $booking['amenity_id'], $booking['quantity'], $booking['total_price']);
    
    if ($stmt->execute()) {
        success("Amenity booking created: Amenity {$booking['amenity_id']}, Qty: {$booking['quantity']}, Price: ₱{$booking['total_price']}");
    } else {
        error_msg("Failed to create amenity booking: " . $stmt->error);
    }
    $stmt->close();
}

section("10. CREATING SAMPLE NOTIFICATIONS");

$notifications = [
    [
        'user_id' => $renter_ids[0],
        'title' => 'Booking Confirmed',
        'message' => 'Your booking for Unit 1201 from Jan 15-22 has been confirmed by the host.',
        'type' => 'booking_approved',
        'is_read' => 0
    ],
    [
        'user_id' => $renter_ids[2],
        'title' => 'New Booking Request',
        'message' => 'Your booking request for Unit 501 is pending approval from the host.',
        'type' => 'new_booking',
        'is_read' => 0
    ],
    [
        'user_id' => $host_ids[0],
        'title' => 'New Booking Request',
        'message' => 'You have a new booking request for Unit 1201 from Michael Johnson.',
        'type' => 'new_booking',
        'is_read' => 0
    ],
];

foreach ($notifications as $notif) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('isssi', $notif['user_id'], $notif['title'], $notif['message'], $notif['type'], $notif['is_read']);
    
    if ($stmt->execute()) {
        success("Notification created: {$notif['title']}");
    } else {
        error_msg("Failed to create notification: " . $stmt->error);
    }
    $stmt->close();
}

section("✅ DATA POPULATION COMPLETE!");

echo "<hr>";
echo "<div style='background-color: #f0f0f0; padding: 20px; border-radius: 5px;'>";
echo "<h3>📋 Summary of Test Data Created:</h3>";
echo "<ul>";
echo "<li><strong>1 Admin User</strong> (admin@bookit.com / Admin@123456)</li>";
echo "<li><strong>" . count($host_ids) . " Host Users</strong> (Property Managers)</li>";
echo "<li><strong>" . count($renter_ids) . " Renter Users</strong> (Customers)</li>";
echo "<li><strong>" . count($branch_ids) . " Branches</strong> (Property Locations)</li>";
echo "<li><strong>" . count($unit_ids) . " Units</strong> (Rental Properties)</li>";
echo "<li><strong>" . count($amenity_ids) . " Amenities</strong> (Services)</li>";
echo "<li><strong>" . count($reservation_ids) . " Reservations</strong> (Bookings)</li>";
echo "<li><strong>Sample Amenity Bookings & Notifications</strong></li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<div style='background-color: #e8f5e9; padding: 20px; border-radius: 5px; margin-top: 20px;'>";
echo "<h3>🔐 TEST ACCOUNT CREDENTIALS:</h3>";
echo "<h4>Admin:</h4>";
echo "<ul>";
echo "<li>Email: <code>admin@bookit.com</code></li>";
echo "<li>Password: <code>Admin@123456</code></li>";
echo "</ul>";

echo "<h4>Host (Sample):</h4>";
echo "<ul>";
echo "<li>Email: <code>maria.garcia@bookit.com</code></li>";
echo "<li>Password: <code>Host@12345</code></li>";
echo "</ul>";

echo "<h4>Renter (Sample):</h4>";
echo "<ul>";
echo "<li>Email: <code>michael.johnson@email.com</code></li>";
echo "<li>Password: <code>Renter@123</code></li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p style='text-align: center; color: #666; margin-top: 30px;'>";
echo "✨ Your BookIT system is now ready with realistic test data!<br>";
echo "You can log in and test all features across different user roles.";
echo "</p>";

$conn->close();
?>
