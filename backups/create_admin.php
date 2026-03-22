<?php
// Create Fresh Admin User
include 'config/db.php';

echo "<h2>Create Fresh Admin User</h2>";

// Delete existing admin user
$deleteSql = "DELETE FROM users WHERE email = 'admin@bookit.com'";
if (mysqli_query($conn, $deleteSql)) {
    echo "<p style='color: blue;'>✓ Removed existing admin user</p>";
} else {
    echo "<p style='color: orange;'>No existing admin user to remove</p>";
}

// Create new admin user
$email = 'admin@bookit.com';
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$fullName = 'System Administrator';

$insertSql = "INSERT INTO users (full_name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)";
$stmt = $conn->prepare($insertSql);
$stmt->bind_param("sss", $fullName, $email, $hashedPassword);

if ($stmt->execute()) {
    echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<p>Email: $email</p>";
    echo "<p>Password: $password</p>";
    echo "<p>Password Hash: " . substr($hashedPassword, 0, 30) . "...</p>";
    
    // Test the password
    echo "<hr>";
    echo "<h3>Password Verification Test:</h3>";
    if (password_verify($password, $hashedPassword)) {
        echo "<p style='color: green;'>✓ Password verification works!</p>";
    } else {
        echo "<p style='color: red;'>✗ Password verification failed</p>";
    }
    
    echo "<hr>";
    echo "<p><a href='public/login.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🚀 Go to Login Page</a></p>";
    
} else {
    echo "<p style='color: red;'>✗ Failed to create admin user: " . mysqli_error($conn) . "</p>";
}

$stmt->close();

echo "<hr>";
echo "<h3>Debug Info:</h3>";
echo "<p>Database: " . $conn->database . "</p>";
echo "<p>Server: " . $conn->server_info . "</p>";
?>


