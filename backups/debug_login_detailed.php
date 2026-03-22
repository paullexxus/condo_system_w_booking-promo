<?php
// BookIT Detailed Login Debug
// Comprehensive debugging for login issues

include 'config/db.php';

echo "<h2>BookIT Detailed Login Debug</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .test-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style>";

// 1. Test Database Connection
echo "<div class='test-section'>";
echo "<h3>1. Database Connection Test</h3>";
if ($conn->connect_error) {
    echo "<p class='error'>✗ Database connection failed: " . $conn->connect_error . "</p>";
    exit();
} else {
    echo "<p class='success'>✓ Database connection successful</p>";
    echo "<p class='info'>Host: $host | User: $user | Database: $dbname</p>";
}
echo "</div>";

// 2. Check if users table exists
echo "<div class='test-section'>";
echo "<h3>2. Users Table Check</h3>";
$result = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($result) > 0) {
    echo "<p class='success'>✓ Users table exists</p>";
    
    // Check table structure
    $structure = mysqli_query($conn, "DESCRIBE users");
    echo "<h4>Table Structure:</h4>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($structure)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ Users table does not exist</p>";
    exit();
}
echo "</div>";

// 3. Check all users in database
echo "<div class='test-section'>";
echo "<h3>3. All Users in Database</h3>";
$users = mysqli_query($conn, "SELECT user_id, full_name, email, role, is_active, created_at FROM users ORDER BY user_id");
if (mysqli_num_rows($users) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Created</th></tr>";
    while ($user = mysqli_fetch_assoc($users)) {
        $activeStatus = $user['is_active'] ? 'Yes' : 'No';
        $rowClass = $user['is_active'] ? 'success' : 'warning';
        echo "<tr>";
        echo "<td>" . $user['user_id'] . "</td>";
        echo "<td>" . $user['full_name'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td class='$rowClass'>" . $activeStatus . "</td>";
        echo "<td>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No users found in database</p>";
}
echo "</div>";

// 4. Test specific login credentials
echo "<div class='test-section'>";
echo "<h3>4. Login Credentials Test</h3>";

$testCredentials = [
    ['admin@bookit.com', 'admin123'],
    ['admin@bookit.com', 'password'],
    ['admin@bookit.com', 'admin'],
    ['admin@bookit.com', '123456']
];

foreach ($testCredentials as $cred) {
    $email = $cred[0];
    $password = $cred[1];
    
    echo "<h4>Testing: $email / $password</h4>";
    
    // Check if user exists
    $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        echo "<p class='success'>✓ User found</p>";
        echo "<p class='info'>Name: " . $user['full_name'] . "</p>";
        echo "<p class='info'>Role: " . $user['role'] . "</p>";
        echo "<p class='info'>Active: " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
        echo "<p class='info'>Password Hash: " . substr($user['password'], 0, 30) . "...</p>";
        
        // Test password verification
        if (password_verify($password, $user['password'])) {
            echo "<p class='success'>✓ Password verification SUCCESSFUL!</p>";
            echo "<p class='success'><strong>This combination should work for login!</strong></p>";
        } else {
            echo "<p class='error'>✗ Password verification failed</p>";
        }
    } else {
        echo "<p class='error'>✗ User not found</p>";
    }
    $stmt->close();
    echo "<hr>";
}
echo "</div>";

// 5. Test login form processing
echo "<div class='test-section'>";
echo "<h3>5. Login Form Test</h3>";

if (isset($_POST['test_login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    echo "<h4>Testing Login with: $email</h4>";
    
    // Simulate the exact login process from login.php
    if (empty($email) || empty($password)) {
        echo "<p class='error'>✗ Empty fields</p>";
    } else {
        $query = "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            echo "<p class='success'>✓ User found and active</p>";
            
            if (password_verify($password, $user['password'])) {
                echo "<p class='success'>✓ Password verification successful!</p>";
                echo "<p class='success'><strong>LOGIN SHOULD WORK!</strong></p>";
                echo "<p class='info'>User ID: " . $user['user_id'] . "</p>";
                echo "<p class='info'>Full Name: " . $user['full_name'] . "</p>";
                echo "<p class='info'>Role: " . $user['role'] . "</p>";
            } else {
                echo "<p class='error'>✗ Password verification failed</p>";
            }
        } else {
            echo "<p class='error'>✗ User not found or inactive</p>";
        }
        $stmt->close();
    }
}

echo "<form method='POST' style='background: #e9ecef; padding: 15px; border-radius: 5px;'>";
echo "<h4>Test Login Form:</h4>";
echo "<p>Email: <input type='email' name='email' value='admin@bookit.com' style='padding: 5px; width: 200px;'></p>";
echo "<p>Password: <input type='password' name='password' value='admin123' style='padding: 5px; width: 200px;'></p>";
echo "<button type='submit' name='test_login' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Test Login</button>";
echo "</form>";
echo "</div>";

// 6. Create admin user if none exists
echo "<div class='test-section'>";
echo "<h3>6. Create Admin User (if needed)</h3>";

$adminCheck = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"));
if ($adminCheck['count'] == 0) {
    echo "<p class='warning'>No admin user found. Creating one...</p>";
    
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $insertSql = "INSERT INTO users (full_name, email, password, role, is_active) VALUES ('System Administrator', 'admin@bookit.com', '$adminPassword', 'admin', 1)";
    
    if (mysqli_query($conn, $insertSql)) {
        echo "<p class='success'>✓ Admin user created successfully!</p>";
        echo "<p class='info'>Email: admin@bookit.com</p>";
        echo "<p class='info'>Password: admin123</p>";
    } else {
        echo "<p class='error'>✗ Failed to create admin user: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p class='success'>✓ Admin user already exists</p>";
}
echo "</div>";

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<p><a href='setup_database.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Setup Database</a></p>";
echo "<p><a href='public/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Go to Login</a></p>";
?>


