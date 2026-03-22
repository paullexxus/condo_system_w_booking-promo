<?php
// Direct setup - Create Juan Santos branch and units with sample booking
include 'config/db.php';

echo "<h2 style='color: #667eea;'>Setting Up Juan Santos Booking System</h2>";

// Step 1: Check if Juan Santos exists and has a branch
$juan_result = $conn->query("SELECT * FROM users WHERE full_name = 'Juan Santos' OR email LIKE '%juan.santos%'");
$juan = $juan_result->fetch_assoc();

if (!$juan) {
    echo "<p style='color: red;'>ERROR: Juan Santos user not found in database!</p>";
    exit;
}

$juan_id = $juan['user_id'];
echo "<p style='color: green;'>✓ Found Juan Santos (ID: $juan_id)</p>";

// Step 2: Check if branch exists
$branch_result = $conn->query("SELECT * FROM branches WHERE host_id = $juan_id");

if ($branch_result->num_rows > 0) {
    $branch = $branch_result->fetch_assoc();
    $branch_id = $branch['branch_id'];
    echo "<p style='color: orange;'>⚠ Branch already exists (ID: $branch_id)</p>";
} else {
    // Create branch
    $sql = "INSERT INTO branches (branch_name, address, city, contact_number, email, host_id, is_active, status) 
            VALUES ('BookIT Manila', '123 Makati Avenue, Makati City', 'Makati', '09175555678', 'manila@bookit.com', $juan_id, 1, 'active')";
    
    if ($conn->query($sql)) {
        $branch_id = $conn->insert_id;
        echo "<p style='color: green;'>✓ Branch created (ID: $branch_id)</p>";
    } else {
        echo "<p style='color: red;'>ERROR creating branch: " . $conn->error . "</p>";
        exit;
    }
}

// Step 3: Check if units exist
$units_result = $conn->query("SELECT COUNT(*) as cnt FROM units WHERE branch_id = $branch_id");
$units_count = $units_result->fetch_assoc()['cnt'];

if ($units_count > 0) {
    echo "<p style='color: orange;'>⚠ Units already exist ($units_count units)</p>";
} else {
    // Create sample units
    $units = [
        ['Luxury Studio Unit', 'A101', 'Studio', 2500.00, 500.00],
        ['1-Bedroom Deluxe', 'A102', '1BR', 3500.00, 750.00],
        ['2-Bedroom Family Suite', 'A103', '2BR', 5000.00, 1000.00]
    ];
    
    foreach ($units as $unit) {
        $sql = "INSERT INTO units (unit_name, host_id, branch_id, unit_number, unit_type, price, floor_number, monthly_rate, security_deposit, is_available, description, max_occupancy, is_active)
                VALUES ('{$unit[0]}', $juan_id, $branch_id, '{$unit[1]}', '{$unit[2]}', {$unit[3]}, 1, {$unit[3]}, {$unit[4]}, 1, 'Sample unit', 2, 1)";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ Unit created: {$unit[0]}</p>";
        } else {
            echo "<p style='color: red;'>ERROR creating unit: " . $conn->error . "</p>";
        }
    }
}

// Step 4: Create sample booking from first renter
$renter_result = $conn->query("SELECT * FROM users WHERE role = 'renter' AND is_active = 1 LIMIT 1");

if ($renter_result->num_rows > 0) {
    $renter = $renter_result->fetch_assoc();
    $renter_id = $renter['user_id'];
    $renter_name = $renter['full_name'];
    
    // Check if booking already exists
    $booking_check = $conn->query("SELECT * FROM reservations WHERE branch_id = $branch_id AND user_id = $renter_id LIMIT 1");
    
    if ($booking_check->num_rows > 0) {
        echo "<p style='color: orange;'>⚠ Sample booking already exists</p>";
    } else {
        // Get first unit
        $unit_result = $conn->query("SELECT * FROM units WHERE branch_id = $branch_id ORDER BY unit_id LIMIT 1");
        $unit = $unit_result->fetch_assoc();
        
        if ($unit) {
            $check_in = date('Y-m-d', strtotime('+1 day'));
            $check_out = date('Y-m-d', strtotime('+6 days'));
            $total_amount = ($unit['monthly_rate'] / 30) * 5;
            $security_deposit = $unit['security_deposit'];
            
            $sql = "INSERT INTO reservations (user_id, unit_id, branch_id, check_in_date, check_out_date, total_amount, security_deposit, status, payment_status, special_requests, created_at)
                    VALUES ($renter_id, {$unit['unit_id']}, $branch_id, '$check_in', '$check_out', $total_amount, $security_deposit, 'awaiting_approval', 'not_paid', 'Please prepare WiFi and fresh towels', NOW())";
            
            if ($conn->query($sql)) {
                echo "<p style='color: green;'>✓ Sample booking created from $renter_name</p>";
                echo "<p style='color: #555; margin-left: 20px;'>Unit: {$unit['unit_number']} | Check-in: $check_in | Check-out: $check_out</p>";
            } else {
                echo "<p style='color: red;'>ERROR creating booking: " . $conn->error . "</p>";
            }
        }
    }
} else {
    echo "<p style='color: orange;'>⚠ No renter accounts found in system</p>";
}

echo "<hr>";
echo "<h3 style='color: green;'>Setup Complete!</h3>";
echo "<p><a href='host/booking_approvals.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Booking Approvals</a></p>";
echo "<p><a href='host/reservations.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View All Reservations</a></p>";
?>
