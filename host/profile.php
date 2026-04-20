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

// Fetch bank account data
$bank_account = get_single_result("SELECT * FROM user_bank_accounts WHERE user_id = ?", [$host_id]) ?: [];

// Fetch GCash account data
$gcash_account = get_single_result("SELECT * FROM user_payment_methods WHERE user_id = ? AND method = 'gcash'", [$host_id]) ?: [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        if ($action === 'update_profile') {
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            execute_query("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?", [$full_name, $email, $phone, $host_id]);
            $_SESSION['fullname'] = $full_name;
            $action_message = "Profile updated successfully!";
            $action_success = true;
        }
        else if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $user = get_single_result("SELECT password FROM users WHERE user_id = ?", [$host_id]);
            
            if (!password_verify($current_password, $user['password'])) { $action_message = "Current password is incorrect"; }
            else if ($new_password !== $confirm_password) { $action_message = "New passwords do not match"; }
            else if (strlen($new_password) < 8) { $action_message = "Password must be at least 8 characters"; }
            else {
                execute_query("UPDATE users SET password = ? WHERE user_id = ?", [password_hash($new_password, PASSWORD_DEFAULT), $host_id]);
                $action_message = "Password changed successfully!";
                $action_success = true;
            }
        }
        else if ($action === 'update_bank') {
            $bank_name = sanitize_input($_POST['bank_name'] ?? '');
            $account_number = sanitize_input($_POST['account_number'] ?? '');
            $account_name = sanitize_input($_POST['account_name'] ?? '');
            
            $existing = get_single_result("SELECT * FROM user_bank_accounts WHERE user_id = ?", [$host_id]);
            if ($existing) {
                execute_query("UPDATE user_bank_accounts SET bank_name = ?, account_number = ?, account_name = ? WHERE user_id = ?", [$bank_name, $account_number, $account_name, $host_id]);
            } else {
                execute_query("INSERT INTO user_bank_accounts (user_id, bank_name, account_number, account_name) VALUES (?, ?, ?, ?)", [$host_id, $bank_name, $account_number, $account_name]);
            }
            $action_message = "Bank account updated successfully!";
            $action_success = true;
        }
        else if ($action === 'update_gcash') {
            $gcash_number = sanitize_input($_POST['gcash_number'] ?? '');
            $existing = get_single_result("SELECT * FROM user_payment_methods WHERE user_id = ? AND method = 'gcash'", [$host_id]);
            if ($existing) {
                execute_query("UPDATE user_payment_methods SET account_details = ? WHERE user_id = ? AND method = 'gcash'", [$gcash_number, $host_id]);
            } else {
                execute_query("INSERT INTO user_payment_methods (user_id, method, account_details) VALUES (?, 'gcash', ?)", [$host_id, $gcash_number]);
            }
            $action_message = "GCash account updated successfully!";
            $action_success = true;
        }
        else if ($action === 'update_profile_picture' && isset($_FILES['profile_picture'])) {
            $uploadDir = '../uploads/profile_pictures/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $file = $_FILES['profile_picture'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($ext, $allowed) && $file['size'] < 5000000) {
                $newName = "host_" . $host_id . "_" . uniqid() . "." . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                    // Delete old
                    if (!empty($host_data['profile_picture']) && file_exists($uploadDir . $host_data['profile_picture'])) {
                        unlink($uploadDir . $host_data['profile_picture']);
                    }
                    execute_query("UPDATE users SET profile_picture = ? WHERE user_id = ?", [$newName, $host_id]);
                    $_SESSION['profile_picture'] = $newName;
                    $action_message = "Profile picture updated!";
                    $action_success = true;
                }
            } else {
                $action_message = "Invalid file type or size (>5MB)";
            }
        }
        else if ($action === 'remove_profile_picture') {
            $uploadDir = '../uploads/profile_pictures/';
            if (!empty($host_data['profile_picture']) && file_exists($uploadDir . $host_data['profile_picture'])) {
                unlink($uploadDir . $host_data['profile_picture']);
            }
            execute_query("UPDATE users SET profile_picture = NULL WHERE user_id = ?", [$host_id]);
            unset($_SESSION['profile_picture']);
            $action_message = "Profile picture removed.";
            $action_success = true;
        }
    }
}

// Refresh host data
$host_data = get_single_result("SELECT * FROM users WHERE user_id = ?", [$host_id]);
$page_title = 'Profile & Settings';

ob_start();
?>
<style>
#profileContext {
    max-width: 1100px;
    margin: 0 auto;
}
#profileContext .settings-nav { 
    border-bottom: 2px solid #e2e8f0; 
    margin-bottom: 30px; 
    display: flex; 
    gap: 30px; 
}
#profileContext .nav-btn { 
    background: none; 
    border: none; 
    padding: 15px 0; 
    font-size: 0.95rem;
    font-weight: 600; 
    color: #64748b; 
    border-bottom: 3px solid transparent; 
    transition: var(--transition); 
    display: flex;
    align-items: center;
    gap: 10px;
}
#profileContext .nav-btn:hover { color: var(--accent); }
#profileContext .nav-btn.active { color: var(--accent); border-bottom-color: var(--accent); }

#profileContext .tab-content { display: none; }
#profileContext .tab-content.active { display: block; animation: profileFadeIn 0.4s ease-out; }

@keyframes profileFadeIn { 
    from { opacity: 0; transform: translateY(10px); } 
    to { opacity: 1; transform: translateY(0); } 
}

#profileContext .identity-card {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 20px;
    padding: 30px;
}
#profileContext .avatar-circle {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.1);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 20px;
}
#profileContext .identity-meta {
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: 20px;
    padding-top: 20px;
}
#profileContext .form-label-premium {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 8px;
    display: block;
}
#profileContext .input-premium {
    background-color: #f8fafc;
    border: 2px solid #f1f5f9;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.95rem;
    transition: var(--transition);
}
#profileContext .input-premium:focus {
    background-color: white;
    border-color: var(--accent);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    outline: none;
}
@media (max-width: 991px) {
    #profileContext .sidebar-sticky { position: static !important; margin-bottom: 30px; }
}
</style>
<?php
$extra_css = ob_get_clean();

include '../templates/host_layout_header.php';
?>

<div id="profileContext">
    <!-- Header -->
    <div class="row align-items-center mb-5">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="host_dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Account Settings</li>
                </ol>
            </nav>
            <h1 class="page-title"><i class="fas fa-user-shield"></i> Profile & Settings</h1>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Identity -->
        <div class="col-lg-4">
            <div class="sidebar-sticky" style="position: sticky; top: 100px;">
                <div class="identity-card shadow-lg mb-4 text-center">
                    <!-- Interactive Avatar -->
                    <div class="avatar-wrapper position-relative mx-auto mb-4" style="width: 120px; height: 120px;">
                        <div class="avatar-circle w-100 h-100 m-0 overflow-hidden shadow-sm" style="border: 4px solid rgba(255,255,255,0.2);">
                            <?php if (!empty($host_data['profile_picture'])): ?>
                                <img src="../uploads/profile_pictures/<?php echo $host_data['profile_picture']; ?>" class="w-100 h-100 object-fit-cover">
                            <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($host_data['full_name']); ?>&background=random&color=fff&size=200" class="w-100 h-100">
                            <?php endif; ?>
                        </div>
                        <div class="avatar-overlay position-absolute bottom-0 end-0">
                            <button type="button" class="btn btn-primary btn-sm rounded-circle shadow" onclick="document.getElementById('profilePicInput').click()">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Hidden Form -->
                    <form id="profilePicForm" method="POST" enctype="multipart/form-data" class="d-none">
                        <input type="hidden" name="action" value="update_profile_picture">
                        <input type="file" id="profilePicInput" name="profile_picture" accept="image/*" onchange="document.getElementById('profilePicForm').submit()">
                    </form>
                    
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($host_data['full_name']); ?></h4>
                    <p class="opacity-75 small mb-3"><?php echo htmlspecialchars($host_data['email']); ?></p>
                    
                    <?php if (!empty($host_data['profile_picture'])): ?>
                        <form method="POST" class="mb-3">
                            <input type="hidden" name="action" value="remove_profile_picture">
                            <button type="submit" class="btn btn-link text-white text-decoration-none opacity-50 smaller p-0 hover-opacity-100 transition">
                                <i class="fas fa-trash-alt me-1"></i> Remove Photo
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="identity-meta text-start">
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="opacity-75">Account Type</span>
                            <span class="fw-bold"><?php echo strtoupper($host_data['role']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="opacity-75">Status</span>
                            <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Verified</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="opacity-75">Member Since</span>
                            <span class="fw-bold"><?php echo date('M Y', strtotime($host_data['created_at'] ?? '2024-01-01')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="card-modern bg-white p-4">
                    <h6 class="fw-bold text-uppercase small text-muted mb-3 tracking-widest">Active Sessions</h6>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success-subtle p-2 rounded text-success"><i class="fas fa-desktop"></i></div>
                        <div class="small">
                            <div class="fw-bold">Current Session</div>
                            <div class="text-muted">Active now • This device</div>
                        </div>
                    </div>
                    <p class="smaller text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Security tip: Always log out on shared devices.</p>
                </div>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="col-lg-8">
            <!-- Navigation -->
            <div class="settings-nav">
                <button class="nav-btn active" data-tab="personal"><i class="fas fa-id-card"></i> Personal</button>
                <button class="nav-btn" data-tab="payment"><i class="fas fa-wallet"></i> Payouts</button>
                <button class="nav-btn" data-tab="password"><i class="fas fa-shield-alt"></i> Security</button>
            </div>

            <?php if ($action_message): ?>
                <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?> glass-panel border-0 mb-4 py-3 d-flex align-items-center">
                    <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?> me-3 fs-4"></i>
                    <div><?php echo $action_message; ?></div>
                </div>
            <?php endif; ?>

            <!-- Personal Info -->
            <div id="personal" class="tab-content active">
                <div class="card-modern bg-white">
                    <h5 class="fw-bold mb-4">Account Information</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label-premium">Full Legal Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><i class="fas fa-user text-muted"></i></span>
                                    <input type="text" name="full_name" class="form-control input-premium border-start-0 ps-0" value="<?php echo htmlspecialchars($host_data['full_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-premium">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control input-premium border-start-0 ps-0" value="<?php echo htmlspecialchars($host_data['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label-premium">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><i class="fas fa-phone text-muted"></i></span>
                                    <input type="tel" name="phone" class="form-control input-premium border-start-0 ps-0" value="<?php echo htmlspecialchars($host_data['phone']); ?>" placeholder="0912 345 6789">
                                </div>
                                <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> This number is used for booking notifications and guest inquiries.</div>
                            </div>
                            <div class="col-12 pt-3">
                                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm py-2 fw-bold">Update Profile</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payout Methods -->
            <div id="payment" class="tab-content">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card-modern bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0">Bank Transfer Settings</h5>
                                <div class="text-primary fs-3"><i class="fas fa-university"></i></div>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_bank">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label-premium">Bank Name</label>
                                        <input type="text" name="bank_name" class="form-control input-premium" value="<?php echo htmlspecialchars($bank_account['bank_name'] ?? ''); ?>" placeholder="BPI, BDO, Metrobank...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-premium">Account Holder Name</label>
                                        <input type="text" name="account_name" class="form-control input-premium" value="<?php echo htmlspecialchars($bank_account['account_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-premium">Account Number</label>
                                        <input type="text" name="account_number" class="form-control input-premium" value="<?php echo htmlspecialchars($bank_account['account_number'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12 pt-1">
                                        <button type="submit" class="btn btn-primary px-4 rounded-pill">Save Bank Info</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card-modern bg-white border-start border-4 border-primary">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0">GCash E-Wallet</h5>
                                <div class="text-primary font-bold fs-4">GCash</div>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_gcash">
                                <label class="form-label-premium">Registered GCash Number</label>
                                <div class="input-group mb-4" style="max-width: 400px;">
                                    <span class="input-group-text border-2 bg-light px-3">+63</span>
                                    <input type="text" name="gcash_number" class="form-control input-premium" value="<?php echo htmlspecialchars($gcash_account['account_details'] ?? ''); ?>">
                                </div>
                                <button type="submit" class="btn btn-outline-primary px-4 rounded-pill fw-bold">Save GCash</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security -->
            <div id="password" class="tab-content">
                <div class="card-modern bg-white">
                    <h5 class="fw-bold mb-4">Security & Authentication</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-4">
                            <label class="form-label-premium">Current Password</label>
                            <input type="password" name="current_password" class="form-control input-premium" required placeholder="Verify identity">
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label-premium">New Password</label>
                                <input type="password" name="new_password" class="form-control input-premium" required placeholder="At least 8 chars">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-premium">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control input-premium" required placeholder="Repeat new password">
                            </div>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm py-2 fw-bold">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * Hardened Tab Swiper with URL Hash Persistence
     */
    function switchTab(targetId) {
        // Update Visibility
        document.querySelectorAll('.tab-content').forEach(t => {
            t.classList.toggle('active', t.id === targetId);
        });

        // Update Nav Buttons
        document.querySelectorAll('.nav-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.tab === targetId);
        });

        // Update URL Hash
        window.location.hash = targetId;
    }

    // Initialize on Load
    document.addEventListener('DOMContentLoaded', function() {
        // Attach Listeners
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                switchTab(this.dataset.tab);
            });
        });

        // Load from Hash
        const hash = window.location.hash.replace('#', '');
        const validTabs = ['personal', 'payment', 'password'];
        if (hash && validTabs.includes(hash)) {
            switchTab(hash);
        }
    });

    // Handle External Hash Changes
    window.addEventListener('hashchange', function() {
        const hash = window.location.hash.replace('#', '');
        if (hash) switchTab(hash);
    });
</script>

<?php include '../templates/host_layout_footer.php'; ?>
