<?php
// BookIT Admin Settings
// System Configuration & Management

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']); // Only admin can access

$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'general';

// Helper function to update setting in database
function updateSetting($key, $value) {
    $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
    if (!execute_query($sql, [$key, $value, $value])) {
        throw new RuntimeException("Database error: Could not update setting '$key'");
    }
    return true;
}

// Handle form submissions via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    try {
    // General Settings
    if (isset($_POST['save_general'])) {
        try {
            updateSetting('system_name', sanitize_input($_POST['system_name']));
            updateSetting('company_name', sanitize_input($_POST['company_name']));
            updateSetting('address', sanitize_input($_POST['address']));
            updateSetting('email', sanitize_input($_POST['email']));
            updateSetting('contact_number', sanitize_input($_POST['contact_number']));
            updateSetting('date_format', sanitize_input($_POST['date_format']));
            $response['success'] = true;
            $response['message'] = "General settings updated successfully!";
        } catch (Throwable $e) {
            $response['message'] = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // Email Settings
    if (isset($_POST['save_email'])) {
        try {
            updateSetting('sender_email', sanitize_input($_POST['sender_email']));
            updateSetting('smtp_host', sanitize_input($_POST['smtp_host']));
            updateSetting('smtp_port', sanitize_input($_POST['smtp_port']));
            updateSetting('smtp_username', sanitize_input($_POST['smtp_username']));
            updateSetting('smtp_password', sanitize_input($_POST['smtp_password']));
            updateSetting('smtp_encryption', sanitize_input($_POST['smtp_encryption'] ?? 'tls'));
            updateSetting('notif_reservation', isset($_POST['notif_reservation']) ? 'yes' : 'no');
            updateSetting('notif_payment', isset($_POST['notif_payment']) ? 'yes' : 'no');
            updateSetting('notif_review', isset($_POST['notif_review']) ? 'yes' : 'no');
            updateSetting('notif_system', isset($_POST['notif_system']) ? 'yes' : 'no');
            $response['success'] = true;
            $response['message'] = "Email settings updated successfully!";
        } catch (Throwable $e) {
            $response['message'] = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // Payment Settings
    if (isset($_POST['save_payment'])) {
        try {
            updateSetting('payment_gcash', isset($_POST['payment_gcash']) ? 'yes' : 'no');
            updateSetting('gcash_account', sanitize_input($_POST['gcash_account'] ?? ''));
            updateSetting('payment_bank', isset($_POST['payment_bank']) ? 'yes' : 'no');
            updateSetting('bank_details', sanitize_input($_POST['bank_details'] ?? ''));
            updateSetting('payment_paypal', isset($_POST['payment_paypal']) ? 'yes' : 'no');
            updateSetting('paypal_account', sanitize_input($_POST['paypal_account'] ?? ''));
            updateSetting('payment_dragonpay', isset($_POST['payment_dragonpay']) ? 'yes' : 'no');
            updateSetting('dragonpay_id', sanitize_input($_POST['dragonpay_id'] ?? ''));
            updateSetting('transaction_fee', sanitize_input($_POST['transaction_fee'] ?? '0'));
            updateSetting('admin_revenue_percent', sanitize_input($_POST['admin_revenue_percent'] ?? '10'));
            updateSetting('host_revenue_percent', sanitize_input($_POST['host_revenue_percent'] ?? '90'));
            updateSetting('min_downpayment_percent', sanitize_input($_POST['min_downpayment_percent'] ?? '50'));
            updateSetting('payment_instructions', sanitize_input($_POST['payment_instructions'] ?? ''));
            $response['success'] = true;
            $response['message'] = "Payment and Revenue settings updated successfully!";
        } catch (Throwable $e) {
            $response['message'] = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // Security Settings
    if (isset($_POST['save_security'])) {
        try {
            updateSetting('login_attempts', sanitize_input($_POST['login_attempts']));
            updateSetting('lockout_duration', sanitize_input($_POST['lockout_duration']));
            updateSetting('session_timeout', sanitize_input($_POST['session_timeout'] ?? '60'));
            updateSetting('password_min_length', sanitize_input($_POST['password_min_length']));
            updateSetting('password_expiration', sanitize_input($_POST['password_expiration'] ?? '90'));
            updateSetting('req_uppercase', isset($_POST['req_uppercase']) ? 'yes' : 'no');
            updateSetting('req_lowercase', isset($_POST['req_lowercase']) ? 'yes' : 'no');
            updateSetting('req_numbers', isset($_POST['req_numbers']) ? 'yes' : 'no');
            updateSetting('req_special', isset($_POST['req_special']) ? 'yes' : 'no');
            updateSetting('two_factor', isset($_POST['two_factor']) ? 'yes' : 'no');
            updateSetting('force_https', isset($_POST['force_https']) ? 'yes' : 'no');
            updateSetting('ip_whitelist', isset($_POST['ip_whitelist']) ? 'yes' : 'no');
            updateSetting('search_limit_per_min', sanitize_input($_POST['search_limit_per_min'] ?? '5'));
            updateSetting('search_lockout_duration', sanitize_input($_POST['search_lockout_duration'] ?? '10'));
            updateSetting('msg_limit_per_min', sanitize_input($_POST['msg_limit_per_min'] ?? '10'));
            updateSetting('msg_lockout_duration', sanitize_input($_POST['msg_lockout_duration'] ?? '5'));
            $response['success'] = true;
            $response['message'] = "Security and Abuse Protection settings updated successfully!";
        } catch (Throwable $e) {
            $response['message'] = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // Branch Defaults
    if (isset($_POST['save_branch_defaults'])) {
        try {
            updateSetting('default_amenities', sanitize_input($_POST['default_amenities']));
            $response['success'] = true;
            $response['message'] = "Branch defaults updated successfully!";
        } catch (Throwable $e) {
            $response['message'] = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // UI Customization
    if (isset($_POST['save_ui'])) {
        try {
            updateSetting('primary_color', sanitize_input($_POST['primary_color'] ?? '#3498db'));
            updateSetting('secondary_color', sanitize_input($_POST['secondary_color'] ?? '#2c3e50'));
            updateSetting('custom_message', sanitize_input($_POST['custom_message']));
            $response['success'] = true;
            $response['message'] = "UI settings updated successfully!";
        } catch (Throwable $e) {
            $response['message'] = "Error updating settings: " . $e->getMessage();
        }
    }
    } catch (Throwable $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// Get current settings from database
$settings = [];
try {
    // Fetch all system settings as key-value pairs
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // Handle error silently - defaults will be used
}

// Helper function to get setting with default value
function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Get all admins
$admins = get_multiple_results("SELECT user_id, full_name, email, is_active FROM users WHERE role = 'admin' ORDER BY created_at DESC");

// Get all branches
$all_branches = get_multiple_results("SELECT branch_id, branch_name, host_id, (SELECT COUNT(*) FROM units WHERE branch_id = branches.branch_id) as unit_count FROM branches WHERE is_active = 1 ORDER BY branch_name");

// Catalog amenities (global id + name; unit-level pricing is on units / legacy flows)
$all_amenities = get_multiple_results(
    "SELECT id AS amenity_id, name AS amenity_name, '' AS description, 0 AS hourly_rate FROM amenities ORDER BY name"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - BookIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <link href="../assets/css/admin/settings.css" rel="stylesheet">
    <link href="../assets/css/modals.css" rel="stylesheet">
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title mb-1">
                    <i class="fas fa-cog me-2"></i>System Settings
                </h1>
                <p class="text-muted">Configure and manage system-wide settings</p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn-refresh" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Settings Navigation Tabs -->
        <div class="settings-nav">
            <div class="nav-tabs">
                <a href="?tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
                    <i class="fas fa-sliders-h"></i> General
                </a>
                <a href="?tab=email" class="nav-tab <?php echo $active_tab == 'email' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Email & Notifications
                </a>
                <a href="?tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i> Payment
                </a>
                <a href="?tab=security" class="nav-tab <?php echo $active_tab == 'security' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i> Security
                </a>
                <a href="?tab=access" class="nav-tab <?php echo $active_tab == 'access' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> Access Control
                </a>
                <a href="?tab=data" class="nav-tab <?php echo $active_tab == 'data' ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i> Data Management
                </a>
                <a href="?tab=defaults" class="nav-tab <?php echo $active_tab == 'defaults' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Defaults
                </a>
                <a href="?tab=ui" class="nav-tab <?php echo $active_tab == 'ui' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> UI Customization
                </a>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="settings-content">

            <!-- 1. GENERAL SETTINGS -->
            <?php if ($active_tab == 'general'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-sliders-h"></i> General Settings</h2>
                    <p>Configure basic system information and preferences</p>
                </div>

                <form method="POST" class="settings-form" id="generalForm">
                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-info-circle"></i> System Information</h5>
                        
                        <div class="form-group">
                            <label class="form-label">System Name / Title</label>
                            <input type="text" class="form-control" name="system_name" value="<?php echo htmlspecialchars(getSetting('system_name', 'BookIT')); ?>" placeholder="e.g., BookIT Rental System">
                            <small class="form-text text-muted">Name displayed in browser tab and header</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars(getSetting('company_name', 'BookIT Management')); ?>" placeholder="e.g., BookIT Management Inc.">
                        </div>

                        <div class="form-group">
                            <label class="form-label">System Logo</label>
                            <div class="logo-upload">
                                <div class="logo-preview">
                                    <img src="../assets/images/logo.png" alt="Logo" id="logoPreview">
                                </div>
                                <div class="logo-controls">
                                    <input type="file" class="form-control" id="logoFile" accept="image/*">
                                    <small class="form-text text-muted">Recommended size: 200x200px</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Favicon</label>
                            <input type="file" class="form-control" accept="image/*">
                            <small class="form-text text-muted">Icon that appears in browser tab</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-map-marker-alt"></i> Contact Information</h5>
                        
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3" placeholder="Street, City, Province, Postal Code"><?php echo htmlspecialchars(getSetting('address', '')); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars(getSetting('email', '')); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" value="<?php echo htmlspecialchars(getSetting('contact_number', '')); ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-globe"></i> Regional Settings</h5>
                        
                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <div class="form-control" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px 12px; border-radius: 8px;">
                                <strong>Asia/Manila (Philippine Time - UTC+8)</strong>
                            </div>
                            <small class="form-text text-muted">Timezone is fixed to Philippine Time</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Default Currency</label>
                            <div class="currency-display">
                                <span class="currency-symbol">₱</span>
                                <input type="text" class="form-control" value="Philippine Peso (PHP)" disabled>
                            </div>
                            <small class="form-text text-muted">Default currency is fixed to PHP</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date Format</label>
                            <select class="form-select" name="date_format">
                                <option value="MM/DD/YYYY" <?php echo getSetting('date_format') == 'MM/DD/YYYY' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                <option value="DD/MM/YYYY" <?php echo getSetting('date_format') == 'DD/MM/YYYY' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="YYYY-MM-DD" <?php echo getSetting('date_format') == 'YYYY-MM-DD' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmModal" onclick="prepareSettingsForm('general', 'General Settings')">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 2. EMAIL & NOTIFICATIONS -->
            <?php if ($active_tab == 'email'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-envelope"></i> Email & Notification Settings</h2>
                    <p>Configure email delivery and notification preferences</p>
                </div>

                <form method="POST" class="settings-form" id="emailForm">
                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-envelope-open"></i> SMTP Configuration</h5>
                        
                        <div class="form-group">
                            <label class="form-label">Sender Email Address</label>
                            <input type="email" class="form-control" name="sender_email" value="<?php echo htmlspecialchars(getSetting('sender_email', '')); ?>" placeholder="noreply@bookit.com">
                            <small class="form-text text-muted">Email address used for automated system emails</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host', '')); ?>" placeholder="smtp.gmail.com">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars(getSetting('smtp_port', '587')); ?>" placeholder="587">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Encryption</label>
                                    <select class="form-select" name="smtp_encryption">
                                        <option value="tls" <?php echo getSetting('smtp_encryption', 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo getSetting('smtp_encryption', 'tls') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo getSetting('smtp_encryption', 'tls') == 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars(getSetting('smtp_username', '')); ?>" placeholder="your-email@gmail.com">
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars(getSetting('smtp_password', '')); ?>" placeholder="••••••••••">
                            <small class="form-text text-muted">For Gmail, use app-specific password</small>
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-paper-plane"></i> Test Email
                        </button>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-bell"></i> Notification Preferences</h5>
                        
                        <div class="notification-settings">
                            <div class="notification-item">
                                <div class="notification-check">
                                    <input type="checkbox" id="notif_reservation" name="notif_reservation" value="yes" <?php echo getSetting('notif_reservation') === 'yes' ? 'checked' : ''; ?>>
                                    <label for="notif_reservation">Reservation Confirmation Emails</label>
                                </div>
                                <p class="text-muted">Send email confirmations when reservations are made</p>
                            </div>

                            <div class="notification-item">
                                <div class="notification-check">
                                    <input type="checkbox" id="notif_payment" name="notif_payment" value="yes" <?php echo getSetting('notif_payment') === 'yes' ? 'checked' : ''; ?>>
                                    <label for="notif_payment">Payment Received Notifications</label>
                                </div>
                                <p class="text-muted">Notify hosts when payments are received</p>
                            </div>

                            <div class="notification-item">
                                <div class="notification-check">
                                    <input type="checkbox" id="notif_review" name="notif_review" value="yes" <?php echo getSetting('notif_review') === 'yes' ? 'checked' : ''; ?>>
                                    <label for="notif_review">Review & Rating Alerts</label>
                                </div>
                                <p class="text-muted">Alert hosts/renters about new reviews</p>
                            </div>

                            <div class="notification-item">
                                <div class="notification-check">
                                    <input type="checkbox" id="notif_system" name="notif_system" value="yes" <?php echo getSetting('notif_system') === 'yes' ? 'checked' : ''; ?>>
                                    <label for="notif_system">System Notifications</label>
                                </div>
                                <p class="text-muted">Important system alerts and updates</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-envelope"></i> Email Templates</h5>
                        <div class="template-buttons">
                            <button type="button" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope"></i> Reservation Confirmation
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-money-bill"></i> Payment Receipt
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-key"></i> Password Reset
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-check"></i> Account Verification
                            </button>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmModal" onclick="prepareSettingsForm('email', 'Email Settings')">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 3. PAYMENT CONFIGURATION -->
            <?php if ($active_tab == 'payment'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-credit-card"></i> Payment Configuration</h2>
                    <p>Manage payment methods and transaction settings</p>
                </div>

                <form method="POST" class="settings-form" id="paymentForm">
                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-credit-card"></i> Available Payment Methods</h5>
                        
                        <div class="payment-methods">
                            <div class="payment-method-item">
                                <input type="checkbox" id="gcash" name="payment_gcash" value="yes" <?php echo getSetting('payment_gcash') === 'yes' ? 'checked' : ''; ?>>
                                <label for="gcash">
                                    <span class="method-icon"><i class="fas fa-mobile-alt"></i></span>
                                    GCash
                                </label>
                                <input type="text" class="form-control form-control-sm" name="gcash_account" value="<?php echo htmlspecialchars(getSetting('gcash_account', '')); ?>" placeholder="GCash Number/Account">
                            </div>

                            <div class="payment-method-item">
                                <input type="checkbox" id="bank" name="payment_bank" value="yes" <?php echo getSetting('payment_bank') === 'yes' ? 'checked' : ''; ?>>
                                <label for="bank">
                                    <span class="method-icon"><i class="fas fa-building"></i></span>
                                    Bank Transfer
                                </label>
                                <input type="text" class="form-control form-control-sm" name="bank_details" value="<?php echo htmlspecialchars(getSetting('bank_details', '')); ?>" placeholder="Bank Details">
                            </div>

                            <div class="payment-method-item">
                                <input type="checkbox" id="paypal" name="payment_paypal" value="yes" <?php echo getSetting('payment_paypal') === 'yes' ? 'checked' : ''; ?>>
                                <label for="paypal">
                                    <span class="method-icon"><i class="fab fa-cc-paypal"></i></span>
                                    PayPal
                                </label>
                                <input type="text" class="form-control form-control-sm" name="paypal_account" value="<?php echo htmlspecialchars(getSetting('paypal_account', '')); ?>" placeholder="PayPal Account">
                            </div>

                            <div class="payment-method-item">
                                <input type="checkbox" id="dragonpay" name="payment_dragonpay" value="yes" <?php echo getSetting('payment_dragonpay') === 'yes' ? 'checked' : ''; ?>>
                                <label for="dragonpay">
                                    <span class="method-icon"><i class="fas fa-dragon"></i></span>
                                    Dragonpay
                                </label>
                                <input type="text" class="form-control form-control-sm" name="dragonpay_id" value="<?php echo htmlspecialchars(getSetting('dragonpay_id', '')); ?>" placeholder="Dragonpay ID">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-cog"></i> Transaction Settings</h5>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Transaction Fee (%)</label>
                                    <input type="number" class="form-control" name="transaction_fee" value="<?php echo htmlspecialchars(getSetting('transaction_fee', '2.5')); ?>" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group text-primary">
                                    <label class="form-label font-bold">Admin Revenue (%)</label>
                                    <input type="number" class="form-control border-primary" name="admin_revenue_percent" value="<?php echo htmlspecialchars(getSetting('admin_revenue_percent', '10')); ?>" step="0.5">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group text-success">
                                    <label class="form-label font-bold">Host Return (%)</label>
                                    <input type="number" class="form-control border-success" name="host_revenue_percent" value="<?php echo htmlspecialchars(getSetting('host_revenue_percent', '90')); ?>" step="0.5">
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Minimum Downpayment (%)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="min_downpayment_percent" value="<?php echo htmlspecialchars(getSetting('min_downpayment_percent', '50')); ?>" min="1" max="100">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="form-text text-muted">Required partial payment to confirm booking</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Instructions (for manual methods)</label>
                            <textarea class="form-control" name="payment_instructions" rows="5" placeholder="Add payment instructions here..."><?php echo htmlspecialchars(getSetting('payment_instructions', '')); ?></textarea>
                            <small class="form-text text-muted">Display these instructions to renters during payment</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmModal" onclick="prepareSettingsForm('payment', 'Payment Settings')">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 4. SECURITY SETTINGS -->
            <?php if ($active_tab == 'security'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-shield-alt"></i> Security & Privacy Settings</h2>
                    <p>Configure security policies and access controls</p>
                </div>

                <form method="POST" class="settings-form" id="securityForm">
                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-lock"></i> Login & Authentication</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Maximum Login Attempts</label>
                                    <input type="number" class="form-control" name="login_attempts" value="<?php echo htmlspecialchars(getSetting('login_attempts', '5')); ?>" placeholder="5">
                                    <small class="form-text text-muted">Failed attempts before lockout</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Lockout Duration (minutes)</label>
                                    <input type="number" class="form-control" name="lockout_duration" value="<?php echo htmlspecialchars(getSetting('lockout_duration', '30')); ?>" placeholder="30">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" name="session_timeout" value="<?php echo htmlspecialchars(getSetting('session_timeout', '60')); ?>" placeholder="60">
                            <small class="form-text text-muted">Auto logout after inactivity</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-key"></i> Password Policy</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Minimum Password Length</label>
                                    <input type="number" class="form-control" name="password_min_length" value="<?php echo htmlspecialchars(getSetting('password_min_length', '8')); ?>" placeholder="8">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Password Expiration (days)</label>
                                    <input type="number" class="form-control" name="password_expiration" value="<?php echo htmlspecialchars(getSetting('password_expiration', '90')); ?>" placeholder="90">
                                    <small class="form-text text-muted">0 = no expiration</small>
                                </div>
                            </div>
                        </div>

                        <div class="password-requirements">
                            <h6>Password Requirements</h6>
                            <div class="requirement-check">
                                <input type="checkbox" id="req_uppercase" name="req_uppercase" value="yes" <?php echo getSetting('req_uppercase') === 'yes' ? 'checked' : ''; ?>>
                                <label for="req_uppercase">Uppercase letters (A-Z)</label>
                            </div>
                            <div class="requirement-check">
                                <input type="checkbox" id="req_lowercase" name="req_lowercase" value="yes" <?php echo getSetting('req_lowercase') === 'yes' ? 'checked' : ''; ?>>
                                <label for="req_lowercase">Lowercase letters (a-z)</label>
                            </div>
                            <div class="requirement-check">
                                <input type="checkbox" id="req_numbers" name="req_numbers" value="yes" <?php echo getSetting('req_numbers') === 'yes' ? 'checked' : ''; ?>>
                                <label for="req_numbers">Numbers (0-9)</label>
                            </div>
                            <div class="requirement-check">
                                <input type="checkbox" id="req_special" name="req_special" value="yes" <?php echo getSetting('req_special') === 'yes' ? 'checked' : ''; ?>>
                                <label for="req_special">Special characters (!@#$%)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-shield-alt"></i> Additional Security</h5>
                        
                        <div class="security-option">
                            <input type="checkbox" id="two_factor" name="two_factor" value="yes" <?php echo getSetting('two_factor') === 'yes' ? 'checked' : ''; ?>>
                            <label for="two_factor">Enable Two-Factor Authentication (2FA)</label>
                            <p class="text-muted">Require additional verification for admin login</p>
                        </div>

                        <div class="security-option">
                            <input type="checkbox" id="force_https" name="force_https" value="yes" <?php echo getSetting('force_https') === 'yes' ? 'checked' : ''; ?>>
                            <label for="force_https">Force HTTPS Connection</label>
                            <p class="text-muted">Redirect all traffic to secure connection</p>
                        </div>

                        <div class="security-option">
                            <input type="checkbox" id="ip_whitelist" name="ip_whitelist" value="yes" <?php echo getSetting('ip_whitelist') === 'yes' ? 'checked' : ''; ?>>
                            <label for="ip_whitelist">IP Whitelist for Admin Panel</label>
                            <p class="text-muted">Restrict admin access to specific IP addresses</p>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title text-warning"><i class="fas fa-biohazard"></i> Abuse Protection</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Search Rate Limit (per min)</label>
                                    <input type="number" class="form-control border-warning" name="search_limit_per_min" value="<?php echo htmlspecialchars(getSetting('search_limit_per_min', '5')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Search Lockout (minutes)</label>
                                    <input type="number" class="form-control border-warning" name="search_lockout_duration" value="<?php echo htmlspecialchars(getSetting('search_lockout_duration', '10')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Messaging Rate Limit (per min)</label>
                                    <input type="number" class="form-control border-warning" name="msg_limit_per_min" value="<?php echo htmlspecialchars(getSetting('msg_limit_per_min', '10')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Messaging Lockout (minutes)</label>
                                    <input type="number" class="form-control border-warning" name="msg_lockout_duration" value="<?php echo htmlspecialchars(getSetting('msg_lockout_duration', '5')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-history"></i> Activity Logs</h5>
                        
                        <div class="activity-log-info">
                            <p>View and manage system activity logs for audit purposes</p>
                            <button type="button" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i> View Activity Logs
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-download"></i> Export Logs
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-trash"></i> Clear Old Logs
                            </button>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmModal" onclick="prepareSettingsForm('security', 'Security Settings')">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 5. ACCESS CONTROL -->
            <?php if ($active_tab == 'access'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-users-cog"></i> User Access Control</h2>
                    <p>Manage roles, permissions, and admin accounts</p>
                </div>

                <div class="access-control-container">
                    <div class="access-section">
                        <h5 class="section-title"><i class="fas fa-user-tie"></i> Admin Accounts</h5>
                        
                        <div class="admin-list">
                            <?php 
                            if (is_array($admins) && count($admins) > 0) {
                                foreach ($admins as $admin):
                            ?>
                            <div class="admin-item">
                                <div class="admin-info">
                                    <div class="admin-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="admin-details">
                                        <h6><?php echo htmlspecialchars($admin['full_name']); ?></h6>
                                        <p class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></p>
                                    </div>
                                </div>
                                <div class="admin-actions">
                                    <button class="btn btn-sm btn-outline-primary">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="disableAdminAccount(<?php echo $admin['user_id']; ?>)">
                                        <?php echo $admin['is_active'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </div>
                            </div>
                            <?php 
                                endforeach;
                            } else {
                                echo '<p class="text-muted">No admin accounts found</p>';
                            }
                            ?>
                        </div>

                        <button type="button" class="btn btn-primary btn-sm mt-3">
                            <i class="fas fa-user-plus"></i> Add Admin Account
                        </button>
                    </div>

                    <div class="access-section">
                        <h5 class="section-title"><i class="fas fa-lock"></i> Roles & Permissions</h5>
                        
                        <div class="roles-grid">
                            <div class="role-card">
                                <h6><i class="fas fa-crown"></i> Admin</h6>
                                <div class="permissions-list">
                                    <span class="badge bg-success">Create</span>
                                    <span class="badge bg-success">Read</span>
                                    <span class="badge bg-success">Update</span>
                                    <span class="badge bg-success">Delete</span>
                                </div>
                                <button class="btn btn-sm btn-outline-primary mt-2">Edit Permissions</button>
                            </div>

                            <div class="role-card">
                                <h6><i class="fas fa-user-tie"></i> Manager/Host</h6>
                                <div class="permissions-list">
                                    <span class="badge bg-success">Create</span>
                                    <span class="badge bg-success">Read</span>
                                    <span class="badge bg-success">Update</span>
                                    <span class="badge bg-warning">Delete (Own)</span>
                                </div>
                                <button class="btn btn-sm btn-outline-primary mt-2">Edit Permissions</button>
                            </div>

                            <div class="role-card">
                                <h6><i class="fas fa-user"></i> Renter</h6>
                                <div class="permissions-list">
                                    <span class="badge bg-success">Read</span>
                                    <span class="badge bg-success">Create (Book)</span>
                                    <span class="badge bg-warning">Update (Own)</span>
                                </div>
                                <button class="btn btn-sm btn-outline-primary mt-2">Edit Permissions</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 6. DATA MANAGEMENT -->
            <?php if ($active_tab == 'data'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-database"></i> System Data Management</h2>
                    <p>Backup, restore, and manage system data</p>
                </div>

                <div class="data-management-container">
                    <div class="data-section">
                        <h5 class="section-title"><i class="fas fa-shield-alt"></i> Database Backup & Restore</h5>
                        
                        <div class="backup-info-box">
                            <div class="backup-info">
                                <p><strong>Last Backup:</strong> November 13, 2025 at 3:45 PM</p>
                                <p><strong>Backup Size:</strong> 125 MB</p>
                            </div>
                        </div>

                        <div class="backup-actions">
                            <button type="button" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Backup Now
                            </button>
                            <button type="button" class="btn btn-outline-primary">
                                <i class="fas fa-history"></i> Backup History
                            </button>
                            <button type="button" class="btn btn-outline-warning">
                                <i class="fas fa-undo"></i> Restore From Backup
                            </button>
                        </div>
                    </div>

                    <div class="data-section">
                        <h5 class="section-title"><i class="fas fa-file-export"></i> Data Export</h5>
                        
                        <div class="export-options">
                            <div class="export-item">
                                <h6>Export All Data</h6>
                                <p class="text-muted">Download complete database in various formats</p>
                                <div class="export-buttons">
                                    <button type="button" class="btn btn-sm btn-outline-primary">CSV</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary">Excel</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary">PDF</button>
                                </div>
                            </div>

                            <div class="export-item">
                                <h6>Export By Category</h6>
                                <select class="form-select form-select-sm">
                                    <option>Select data type...</option>
                                    <option>Users</option>
                                    <option>Reservations</option>
                                    <option>Payments</option>
                                    <option>Units</option>
                                    <option>Branches</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="data-section">
                        <h5 class="section-title"><i class="fas fa-folder"></i> Storage Management</h5>
                        
                        <div class="storage-stats">
                            <div class="storage-item">
                                <span class="storage-label">Profile Pictures</span>
                                <span class="storage-size">45 MB</span>
                            </div>
                            <div class="storage-item">
                                <span class="storage-label">Unit Images</span>
                                <span class="storage-size">320 MB</span>
                            </div>
                            <div class="storage-item">
                                <span class="storage-label">Documents</span>
                                <span class="storage-size">85 MB</span>
                            </div>
                            <div class="storage-item">
                                <span class="storage-label">System Logs</span>
                                <span class="storage-size">12 MB</span>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-danger btn-sm mt-3">
                            <i class="fas fa-broom"></i> Clear Cache
                        </button>
                    </div>

                    <div class="data-section danger-zone">
                        <h5 class="section-title text-danger"><i class="fas fa-exclamation-triangle"></i> System Logs</h5>
                        
                        <button type="button" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-eye"></i> View System Logs
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-trash"></i> Clear All Logs
                        </button>
                        <small class="d-block mt-2 text-muted">Clearing logs cannot be undone</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 7. DEFAULTS -->
            <?php if ($active_tab == 'defaults'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-building"></i> Branches & Amenity Defaults</h2>
                    <p>Manage default settings for branches and amenities</p>
                </div>

                <div class="defaults-container">
                    <div class="defaults-section">
                        <h5 class="section-title"><i class="fas fa-building"></i> Branch Settings</h5>
                        
                        <div class="branch-list">
                            <?php 
                            if (is_array($all_branches) && count($all_branches) > 0) {
                                foreach ($all_branches as $branch):
                            ?>
                            <div class="branch-item">
                                <div class="branch-info">
                                    <h6><?php echo htmlspecialchars($branch['branch_name']); ?></h6>
                                    <p class="text-muted">Units: <?php echo $branch['unit_count']; ?></p>
                                </div>
                                <div class="branch-actions">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editBranch(<?php echo $branch['branch_id']; ?>)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteBranch(<?php echo $branch['branch_id']; ?>, '<?php echo htmlspecialchars($branch['branch_name']); ?>')">Delete</button>
                                </div>
                            </div>
                            <?php 
                                endforeach;
                            } else {
                                echo '<p class="text-muted">No branches found</p>';
                            }
                            ?>
                        </div>

                        <button type="button" class="btn btn-primary btn-sm mt-3" onclick="addBranch()">
                            <i class="fas fa-plus"></i> Add Branch
                        </button>
                    </div>

                    <div class="defaults-section">
                        <h5 class="section-title"><i class="fas fa-star"></i> Default Amenities</h5>
                        
                        <div class="amenities-list">
                            <?php 
                            if (is_array($all_amenities) && count($all_amenities) > 0) {
                                foreach ($all_amenities as $amenity):
                            ?>
                            <div class="amenity-item">
                                <div class="amenity-checkbox">
                                    <input type="checkbox" id="amenity_<?php echo $amenity['amenity_id'] ?? sanitize_input(str_replace(' ', '_', strtolower($amenity['amenity_name']))); ?>" checked>
                                    <label for="amenity_<?php echo $amenity['amenity_id'] ?? sanitize_input(str_replace(' ', '_', strtolower($amenity['amenity_name']))); ?>">
                                        <?php echo htmlspecialchars($amenity['amenity_name']); ?>
                                    </label>
                                </div>
                                <input type="number" class="amenity-price" placeholder="N/A" value="0" step="0.01" disabled title="Catalog amenities have no hourly rate in the current schema">
                            </div>
                            <?php 
                                endforeach;
                            } else {
                                echo '<p class="text-muted">No amenities found in database</p>';
                            }
                            ?>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm mt-3" onclick="addCustomAmenity()">
                            <i class="fas fa-plus"></i> Add Custom Amenity
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 8. UI CUSTOMIZATION -->
            <?php if ($active_tab == 'ui'): ?>
            <div class="settings-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-palette"></i> UI Customization</h2>
                    <p>Customize the appearance and branding of the system</p>
                </div>

                <form method="POST" class="settings-form" id="uiForm">
                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-paint-brush"></i> Theme Colors</h5>
                        
                        <div class="color-settings">
                            <div class="color-item">
                                <label class="form-label">Primary Color</label>
                                <div class="color-picker-group">
                                    <input type="color" class="color-picker" name="primary_color" value="<?php echo htmlspecialchars(getSetting('primary_color', '#3498db')); ?>">
                                    <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars(getSetting('primary_color', '#3498db')); ?>" readonly>
                                </div>
                            </div>

                            <div class="color-item">
                                <label class="form-label">Secondary Color</label>
                                <div class="color-picker-group">
                                    <input type="color" class="color-picker" name="secondary_color" value="<?php echo htmlspecialchars(getSetting('secondary_color', '#2c3e50')); ?>">
                                    <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars(getSetting('secondary_color', '#2c3e50')); ?>" readonly>
                                </div>
                            </div>

                            <div class="color-item">
                                <label class="form-label">Success Color</label>
                                <div class="color-picker-group">
                                    <input type="color" class="color-picker" name="success_color" value="#27ae60">
                                    <input type="text" class="form-control form-control-sm" value="#27ae60" readonly>
                                </div>
                            </div>

                            <div class="color-item">
                                <label class="form-label">Danger Color</label>
                                <div class="color-picker-group">
                                    <input type="color" class="color-picker" name="danger_color" value="#e74c3c">
                                    <input type="text" class="form-control form-control-sm" value="#e74c3c" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-image"></i> Branding</h5>
                        
                        <div class="form-group">
                            <label class="form-label">Homepage Banner</label>
                            <div class="banner-upload">
                                <div class="banner-preview">
                                    <img src="../assets/images/banner.jpg" alt="Banner" id="bannerPreview">
                                </div>
                                <input type="file" class="form-control" id="bannerFile" accept="image/*">
                                <small class="form-text text-muted">Recommended size: 1200x400px</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Background Image</label>
                            <input type="file" class="form-control" accept="image/*">
                            <small class="form-text text-muted">Will be used as page background</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-heading"></i> Custom Messages</h5>
                        
                        <div class="form-group">
                            <label class="form-label">Homepage Welcome Title</label>
                            <input type="text" class="form-control" name="welcome_title" value="<?php echo htmlspecialchars(getSetting('welcome_title', 'Welcome to BookIT Rentals')); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Homepage Welcome Description</label>
                            <textarea class="form-control" name="custom_message" rows="4" placeholder="Add welcome message..."><?php echo htmlspecialchars(getSetting('custom_message', '')); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Footer Copyright Text</label>
                            <input type="text" class="form-control" placeholder="© 2025 BookIT. All rights reserved.">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmModal" onclick="prepareSettingsForm('ui', 'UI Settings')">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i> Reset to Default
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>

    </main>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmTitle">Save Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Are you sure you want to save these settings? This will update the system for all users.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmBtn">
                    <i class="fas fa-check"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentForm = null;
let currentFormType = null;

function prepareSettingsForm(formType, formTitle) {
    currentFormType = formType;
    currentForm = document.querySelector(`#${formType}Form`) || document.querySelector('form.settings-form');
    
    document.getElementById('confirmTitle').textContent = `Save ${formTitle}`;
    document.getElementById('confirmMessage').textContent = `Are you sure you want to save these ${formTitle.toLowerCase()}? This will update the system for all users.`;
}

document.getElementById('confirmBtn').addEventListener('click', async function() {
    if (!currentForm) {
        alert('Error: Form not found');
        return;
    }

    const formData = new FormData(currentForm);
    
    // Add the appropriate save flag
    if (currentFormType === 'general') {
        formData.append('save_general', '1');
    } else if (currentFormType === 'email') {
        formData.append('save_email', '1');
    } else if (currentFormType === 'payment') {
        formData.append('save_payment', '1');
    } else if (currentFormType === 'security') {
        formData.append('save_security', '1');
    } else if (currentFormType === 'branch_defaults') {
        formData.append('save_branch_defaults', '1');
    } else if (currentFormType === 'ui') {
        formData.append('save_ui', '1');
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Show success message
            showAlert(result.message, 'success');
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            // Reload page after 1.5 seconds
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert(result.message, 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('An error occurred while saving settings', 'danger');
    }
});

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto remove alert after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('[role="alert"]');
        if (alert) alert.remove();
    }, 5000);
}
</script>
<script src="../assets/js/admin/settings.js"></script>

</body>
</html>
