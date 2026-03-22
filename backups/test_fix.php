<?php
// Test Database Connection Fix
echo "<h2>Testing Database Connection Fix</h2>";

// Test 1: Direct config include
echo "<h3>1. Testing Direct Config Include</h3>";
try {
    include 'config/db.php';
    if (isset($conn) && $conn->connect_error) {
        echo "<p style='color: red;'>✗ Database connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
        echo "<p>Database: " . $conn->database . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test 2: Test functions include
echo "<h3>2. Testing Functions Include</h3>";
try {
    include 'includes/functions.php';
    echo "<p style='color: green;'>✓ Functions file loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error loading functions: " . $e->getMessage() . "</p>";
}

// Test 3: Test session include
echo "<h3>3. Testing Session Include</h3>";
try {
    include 'includes/session.php';
    echo "<p style='color: green;'>✓ Session file loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error loading session: " . $e->getMessage() . "</p>";
}

// Test 4: Test admin dashboard
echo "<h3>4. Testing Admin Dashboard</h3>";
try {
    // Simulate the admin dashboard include
    if (isset($conn)) {
        $testQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
        if ($testQuery) {
            $result = mysqli_fetch_assoc($testQuery);
            echo "<p style='color: green;'>✓ Admin dashboard query works</p>";
            echo "<p>Total users: " . $result['total'] . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Admin dashboard query failed: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ No database connection available</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<p><a href='admin/admin_dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Admin Dashboard</a></p>";
echo "<p><a href='public/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Go to Login</a></p>";
?>


