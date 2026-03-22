<?php
// Verify BookIT System is Working
echo "<h2>BookIT System Verification</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .test-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style>";

// Test 1: Database Connection
echo "<div class='test-section'>";
echo "<h3>1. Database Connection</h3>";
try {
    include 'config/db.php';
    if ($conn->connect_error) {
        echo "<p class='error'>✗ Database connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p class='success'>✓ Database connection successful</p>";
        echo "<p class='info'>Database: " . $conn->database . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Functions Include
echo "<div class='test-section'>";
echo "<h3>2. Functions File</h3>";
try {
    include 'includes/functions.php';
    echo "<p class='success'>✓ Functions file loaded successfully</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading functions: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 3: Session Include
echo "<div class='test-section'>";
echo "<h3>3. Session File</h3>";
try {
    include 'includes/session.php';
    echo "<p class='success'>✓ Session file loaded successfully</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading session: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: Database Tables
echo "<div class='test-section'>";
echo "<h3>4. Database Tables</h3>";
$tables = ['users', 'branches', 'units', 'amenities', 'reservations', 'amenity_bookings', 'payments', 'notifications', 'reviews'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "<p class='success'>✓ Table '$table' exists</p>";
    } else {
        echo "<p class='error'>✗ Table '$table' does not exist</p>";
    }
}
echo "</div>";

// Test 5: Admin User
echo "<div class='test-section'>";
echo "<h3>5. Admin User</h3>";
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin'"));
if ($admin) {
    echo "<p class='success'>✓ Admin user exists</p>";
    echo "<p class='info'>Email: " . $admin['email'] . "</p>";
    echo "<p class='info'>Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "</p>";
} else {
    echo "<p class='error'>✗ No admin user found</p>";
}
echo "</div>";

// Test 6: Test Admin Dashboard Query
echo "<div class='test-section'>";
echo "<h3>6. Admin Dashboard Query Test</h3>";
try {
    $totalUsers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE is_active = 1"));
    $totalBranches = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM branches WHERE is_active = 1"));
    $totalReservations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM reservations"));
    $totalUnits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM units WHERE is_available = 1"));
    
    echo "<p class='success'>✓ Admin dashboard queries work</p>";
    echo "<p class='info'>Total Users: " . $totalUsers['total'] . "</p>";
    echo "<p class='info'>Total Branches: " . $totalBranches['total'] . "</p>";
    echo "<p class='info'>Total Reservations: " . $totalReservations['total'] . "</p>";
    echo "<p class='info'>Total Units: " . $totalUnits['total'] . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Admin dashboard query failed: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<hr>";
echo "<h3>System Status:</h3>";
if (isset($conn) && !$conn->connect_error && isset($admin)) {
    echo "<p class='success'><strong>🎉 BookIT System is Ready!</strong></p>";
    echo "<p><a href='admin/admin_dashboard.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🚀 Go to Admin Dashboard</a></p>";
    echo "<p><a href='public/login.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; margin-left: 10px;'>🔐 Go to Login</a></p>";
} else {
    echo "<p class='error'><strong>⚠️ System needs setup</strong></p>";
    echo "<p><a href='setup_database.php' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🛠️ Setup Database</a></p>";
}
?>


