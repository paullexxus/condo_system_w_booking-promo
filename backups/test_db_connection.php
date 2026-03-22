<?php
// Simple Database Connection Test
include 'config/db.php';

echo "<h2>Database Connection Test</h2>";

// Test 1: Basic connection
echo "<h3>1. Basic Connection Test</h3>";
if ($conn->connect_error) {
    echo "<p style='color: red;'>✗ Connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✓ Connection successful</p>";
    echo "<p>Database: " . $conn->database . "</p>";
    echo "<p>Server info: " . $conn->server_info . "</p>";
}

// Test 2: Check if database exists
echo "<h3>2. Database Exists Test</h3>";
$result = mysqli_query($conn, "SELECT DATABASE() as db_name");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<p style='color: green;'>✓ Current database: " . $row['db_name'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ Could not get database name</p>";
}

// Test 3: Check if users table exists
echo "<h3>3. Users Table Test</h3>";
$result = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($result) > 0) {
    echo "<p style='color: green;'>✓ Users table exists</p>";
    
    // Count users
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"));
    echo "<p>Total users: " . $count['total'] . "</p>";
    
    // Show admin users
    $admin = mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin'");
    if (mysqli_num_rows($admin) > 0) {
        echo "<p style='color: green;'>✓ Admin users found:</p>";
        while ($user = mysqli_fetch_assoc($admin)) {
            echo "<p>Email: " . $user['email'] . " | Active: " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ No admin users found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Users table does not exist</p>";
}

// Test 4: Test a simple query
echo "<h3>4. Simple Query Test</h3>";
$result = mysqli_query($conn, "SELECT 1 as test");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<p style='color: green;'>✓ Simple query works: " . $row['test'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ Simple query failed: " . mysqli_error($conn) . "</p>";
}

// Test 5: Test prepared statement
echo "<h3>5. Prepared Statement Test</h3>";
$stmt = $conn->prepare("SELECT ? as test");
$testValue = "Hello World";
$stmt->bind_param("s", $testValue);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<p style='color: green;'>✓ Prepared statement works: " . $row['test'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ Prepared statement failed</p>";
}
$stmt->close();

echo "<hr>";
echo "<p><a href='debug_login_detailed.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Detailed Login Debug</a></p>";
echo "<p><a href='public/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Go to Login</a></p>";
?>


