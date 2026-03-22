<?php
// Admin Profile Management
// Allow admins to view and edit their own profile

include_once '../config/db.php';
include_once '../includes/session.php';
include_once '../includes/functions.php';
checkRole(['admin']);

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch admin data
$admin = [];
$query = "SELECT * FROM users WHERE user_id = ? AND role = 'admin'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    $error = "Admin profile not found";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone'] ?? '');
    
    // Validate email uniqueness (excluding current admin)
    $email_check = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $stmt = $conn->prepare($email_check);
    $stmt->bind_param("si", $email, $admin_id);
    $stmt->execute();
    $email_result = $stmt->get_result();
    $stmt->close();
    
    if ($email_result->num_rows > 0) {
        $error = "Email address already in use";
    } else {
        $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssi", $full_name, $email, $phone, $admin_id);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            // Refresh admin data
            $admin['full_name'] = $full_name;
            $admin['email'] = $email;
            $admin['phone'] = $phone;
        } else {
            $error = "Failed to update profile: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $admin['password_hash'])) {
        $error = "Current password is incorrect";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $pwd_query = "UPDATE users SET password_hash = ? WHERE user_id = ?";
        $stmt = $conn->prepare($pwd_query);
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
        } else {
            $error = "Failed to change password: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            flex-shrink: 0;
        }
        
        .profile-info h2 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .profile-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1.5px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #2c3e50;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="profile-container">
                <!-- Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?></h2>
                        <p><?php echo htmlspecialchars($admin['email'] ?? 'admin@system.com'); ?></p>
                        <p><small>Role: <strong>Administrator</strong></small></p>
                    </div>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Card -->
                <div class="profile-card">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Personal Information</h3>
                            
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" 
                                    value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" 
                                    value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                    value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" 
                                    placeholder="Optional">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Password Card -->
                <div class="profile-card">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h3><i class="fas fa-lock"></i> Change Password</h3>
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                    placeholder="Minimum 8 characters" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Account Info Card -->
                <div class="profile-card">
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                        
                        <div class="form-group">
                            <label>User ID:</label>
                            <input type="text" value="<?php echo htmlspecialchars($admin['user_id'] ?? ''); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Account Created:</label>
                            <input type="text" value="<?php echo isset($admin['created_at']) ? date('F d, Y H:i', strtotime($admin['created_at'])) : 'N/A'; ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Account Status:</label>
                            <input type="text" value="<?php echo ($admin['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>" disabled>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
