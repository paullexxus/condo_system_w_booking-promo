<?php
// BookIT Admin Password Update
// Run this script to update admin password

include_once '../config/db.php';

// New admin credentials
$admin_email = 'admin@bookit.com';
$new_password = 'admin123'; // Change this to your desired password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update admin password
$sql = "UPDATE users SET password = ? WHERE email = ? AND role = 'admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $admin_email);

if ($stmt->execute()) {
    echo "Admin password updated successfully!<br>";
    echo "Email: " . $admin_email . "<br>";
    echo "Password: " . $new_password . "<br>";
    echo "<a href='../public/login.php'>Go to Login</a>";
} else {
    echo "Failed to update admin password.";
}

$stmt->close();
$conn->close();
?>
