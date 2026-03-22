<?php
// Host Profile & Settings
// Update personal info, change password, manage bank info

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Fetch host data
$host_data = get_single_result("SELECT * FROM users WHERE user_id = ?", [$host_id]);

// Fetch bank account data
$bank_account = get_single_result("SELECT * FROM user_bank_accounts WHERE user_id = ?", [$host_id]);
if (!$bank_account) {
    $bank_account = [];
}

// Fetch GCash account data
$gcash_account = get_single_result("SELECT * FROM user_payment_methods WHERE user_id = ? AND method = 'gcash'", [$host_id]);
if (!$gcash_account) {
    $gcash_account = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        // Update Personal Information
        if ($action === 'update_profile') {
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
            execute_query($sql, [$full_name, $email, $phone, $host_id]);
            
            $_SESSION['fullname'] = $full_name;
            $action_message = "Profile updated successfully!";
            $action_success = true;
        }
        
        // Change Password
        else if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Get current password hash
            $user = get_single_result("SELECT password FROM users WHERE user_id = ?", [$host_id]);
            
            if (!password_verify($current_password, $user['password'])) {
                $action_message = "Current password is incorrect";
            } else if ($new_password !== $confirm_password) {
                $action_message = "New passwords do not match";
            } else if (strlen($new_password) < 8) {
                $action_message = "Password must be at least 8 characters";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE user_id = ?";
                execute_query($sql, [$hashed, $host_id]);
                $action_message = "Password changed successfully!";
                $action_success = true;
            }
        }
        
        // Update Bank Account
        else if ($action === 'update_bank') {
            $bank_name = sanitize_input($_POST['bank_name'] ?? '');
            $account_number = sanitize_input($_POST['account_number'] ?? '');
            $account_name = sanitize_input($_POST['account_name'] ?? '');
            
            if (empty($bank_name) || empty($account_number) || empty($account_name)) {
                $action_message = "All bank fields are required";
            } else {
                // Check if record exists
                $existing = get_single_result("SELECT * FROM user_bank_accounts WHERE user_id = ?", [$host_id]);
                
                if ($existing) {
                    $sql = "UPDATE user_bank_accounts SET bank_name = ?, account_number = ?, account_name = ? WHERE user_id = ?";
                    execute_query($sql, [$bank_name, $account_number, $account_name, $host_id]);
                } else {
                    $sql = "INSERT INTO user_bank_accounts (user_id, bank_name, account_number, account_name) VALUES (?, ?, ?, ?)";
                    execute_query($sql, [$host_id, $bank_name, $account_number, $account_name]);
                }
                
                $action_message = "Bank account updated successfully!";
                $action_success = true;
            }
        }
        
        // Update GCash Account
        else if ($action === 'update_gcash') {
            $gcash_number = sanitize_input($_POST['gcash_number'] ?? '');
            
            if (empty($gcash_number)) {
                $action_message = "GCash number is required";
            } else {
                $existing = get_single_result("SELECT * FROM user_payment_methods WHERE user_id = ? AND method = 'gcash'", [$host_id]);
                
                if ($existing) {
                    $sql = "UPDATE user_payment_methods SET account_details = ? WHERE user_id = ? AND method = 'gcash'";
                    execute_query($sql, [$gcash_number, $host_id]);
                } else {
                    $sql = "INSERT INTO user_payment_methods (user_id, method, account_details) VALUES (?, 'gcash', ?)";
                    execute_query($sql, [$host_id, $gcash_number]);
                }
                
                $action_message = "GCash account updated successfully!";
                $action_success = true;
            }
        }
    }
}

// Get host data
$host_data = get_single_result("SELECT * FROM users WHERE user_id = ?", [$host_id]);
$bank_account = get_single_result("SELECT * FROM user_bank_accounts WHERE user_id = ?", [$host_id]);
$gcash_account = get_single_result("SELECT * FROM user_payment_methods WHERE user_id = ? AND method = 'gcash'", [$host_id]);

$page_title = 'Profile & Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | BookIT Host</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content { padding: 30px; }
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }
        
        .settings-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .settings-tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 30px;
        }
        
        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            color: #3498db;
        }
        
        .tab-btn.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #3498db;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group:last-child {
            margin-bottom: 0;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-text {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }
        
        .btn-secondary:hover {
            background: #d5dbdb;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: flex;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: flex;
        }
        
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-role {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1><i class="fas fa-user-cog"></i> Profile & Settings</h1>
            </div>
            
            <div class="settings-wrapper">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="profile-name"><?php echo htmlspecialchars($host_data['full_name']); ?></div>
                    <div class="profile-role"><?php echo ucfirst($host_data['role']); ?></div>
                </div>
                
                <!-- Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="switchTab('personal')">
                        <i class="fas fa-user"></i> Personal Information
                    </button>
                    <button class="tab-btn" onclick="switchTab('password')">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                    <button class="tab-btn" onclick="switchTab('payment')">
                        <i class="fas fa-wallet"></i> Payment Methods
                    </button>
                </div>
                
                <!-- Success/Error Alert -->
                <?php if ($action_message): ?>
                <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>">
                    <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($action_message); ?>
                </div>
                <?php endif; ?>
                
                <!-- Personal Information Tab -->
                <div id="personal" class="tab-content active">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user"></i> Personal Information
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($host_data['full_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($host_data['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($host_data['phone'] ?? ''); ?>">
                                <div class="form-text">Include country code for international numbers</div>
                            </div>
                            
                            <div class="button-group">
                                <button type="button" class="btn btn-secondary" onclick="location.reload()">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Tab -->
                <div id="password" class="tab-content">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-lock"></i> Change Password
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" required>
                                    <div class="form-text">Must be at least 8 characters</div>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="button-group">
                                <button type="button" class="btn btn-secondary" onclick="location.reload()">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Payment Methods Tab -->
                <div id="payment" class="tab-content">
                    <!-- Bank Account Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-university"></i> Bank Account Information
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_bank">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Bank Name</label>
                                    <input type="text" name="bank_name" value="<?php echo htmlspecialchars($bank_account['bank_name'] ?? ''); ?>" placeholder="e.g., BDO, BPI, Metrobank">
                                </div>
                                <div class="form-group">
                                    <label>Account Name</label>
                                    <input type="text" name="account_name" value="<?php echo htmlspecialchars($bank_account['account_name'] ?? ''); ?>" placeholder="Name on account">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Account Number</label>
                                <input type="text" name="account_number" value="<?php echo htmlspecialchars($bank_account['account_number'] ?? ''); ?>" placeholder="Bank account number">
                                <div class="form-text">Your account information is kept secure and only used for payouts</div>
                            </div>
                            
                            <div class="button-group">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('payment').querySelector('form').reset()">Clear</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Bank Info
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- GCash Account Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-mobile-alt"></i> GCash Account
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_gcash">
                            <div class="form-group">
                                <label>GCash Number</label>
                                <input type="tel" name="gcash_number" value="<?php echo htmlspecialchars($gcash_account['account_details'] ?? ''); ?>" placeholder="+63 9XX XXX XXXX">
                                <div class="form-text">GCash mobile number for receiving payments</div>
                            </div>
                            
                            <div class="button-group">
                                <button type="button" class="btn btn-secondary" onclick="location.reload()">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save GCash Info
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.closest('.tab-btn').classList.add('active');
        }
    </script>
    <script src="../assets/js/host/profile.js"></script>
</body>
</html>
