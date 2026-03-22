<?php
// BookIT Database Connection Test
// Run this to test if database connection is working

include 'config/db.php';

echo "<h2>BookIT Database Connection Test</h2>";

// Test basic connection
if ($conn->connect_error) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $conn->connect_error . "</p>";
    exit();
} else {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
}

// Test if tables exist
$tables = ['users', 'branches', 'units', 'amenities', 'reservations', 'amenity_bookings', 'payments', 'notifications', 'reviews'];

foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
    }
}

// Test if admin user exists
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin'"));
if ($admin) {
    echo "<p style='color: green;'>✓ Admin user exists</p>";
    echo "<p>Email: " . $admin['email'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ Admin user does not exist</p>";
}

// Test if branches exist
$branches = mysqli_query($conn, "SELECT COUNT(*) as count FROM branches");
$branchCount = mysqli_fetch_assoc($branches);
echo "<p>Branches: " . $branchCount['count'] . "</p>";

// Test if units exist
$units = mysqli_query($conn, "SELECT COUNT(*) as count FROM units");
$unitCount = mysqli_fetch_assoc($units);
echo "<p>Units: " . $unitCount['count'] . "</p>";

// Test if amenities exist
$amenities = mysqli_query($conn, "SELECT COUNT(*) as count FROM amenities");
$amenityCount = mysqli_fetch_assoc($amenities);
echo "<p>Amenities: " . $amenityCount['count'] . "</p>";

echo "<hr>";
echo "<h3>Test Complete!</h3>";
echo "<p><a href='setup_database.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Setup Database</a></p>";
echo "<p><a href='public/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Go to Login</a></p>";
?>


