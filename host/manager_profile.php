<?php
// Manager Profile Page
include_once '../includes/session.php';
include_once '../includes/functions.php';
checkRole(['admin','manager']);

// Database connection
include_once '../config/db.php';

$message = '';
$error = '';

// Get manager's branch information
$managerBranch = null;
if ($_SESSION['role'] == 'host') {
    $managerBranch = get_single_result("SELECT b.* FROM branches b JOIN users u ON b.branch_id = u.branch_id WHERE u.user_id = ?", [$_SESSION['user_id']]);
}

// Get current manager data
$managerData = get_single_result("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);

// Check if columns exist
$hasPhoneColumn = false;
$hasAddressColumn = false;
$hasProfilePictureColumn = false;

try {
    $checkColumnsQuery = "SHOW COLUMNS FROM users";
    $columnsResult = mysqli_query($conn, $checkColumnsQuery);
    $existingColumns = [];
    while ($column = mysqli_fetch_assoc($columnsResult)) {
        $existingColumns[] = $column['Field'];
    }

    $hasPhoneColumn = in_array('phone', $existingColumns);
    $hasAddressColumn = in_array('address', $existingColumns);
    $hasProfilePictureColumn = in_array('profile_picture', $existingColumns);
    
} catch (Exception $e) {
    $hasPhoneColumn = $hasAddressColumn = $hasProfilePictureColumn = false;
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture']) && $hasProfilePictureColumn) {
    $uploadDir = '../assets/images/uploads/pfp/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = $_FILES['profile_picture']['name'];
    $fileTmpName = $_FILES['profile_picture']['tmp_name'];
    $fileSize = $_FILES['profile_picture']['size'];
    $fileError = $_FILES['profile_picture']['error'];
    $fileType = $_FILES['profile_picture']['type'];
    
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($fileExt, $allowedExtensions)) {
        if ($fileError === 0) {
            if ($fileSize < 5000000) { // 5MB limit
                // Generate unique filename
                $newFileName = "profile_" . $_SESSION['user_id'] . "_" . uniqid() . "." . $fileExt;
                $fileDestination = $uploadDir . $newFileName;
                
                // Delete old profile picture if exists
                $oldPicture = get_single_result("SELECT profile_picture FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
                if ($oldPicture && $oldPicture['profile_picture'] && file_exists($uploadDir . $oldPicture['profile_picture'])) {
                    unlink($uploadDir . $oldPicture['profile_picture']);
                }
                
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    // Update database
                    if (execute_query("UPDATE users SET profile_picture = ? WHERE user_id = ?", [$newFileName, $_SESSION['user_id']])) {
                        $_SESSION['profile_picture'] = $newFileName;
                        $message = "Profile picture updated successfully!";
                    } else {
                        $error = "Error updating profile picture in database.";
                    }
                } else {
                    $error = "There was an error uploading your file.";
                }
            } else {
                $error = "File is too large. Maximum size is 5MB.";
            }
        } else {
            $error = "There was an error uploading your file.";
        }
    } else {
        $error = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture']) && !$hasProfilePictureColumn) {
    $error = "Profile picture feature is not available. Please contact administrator.";
}

// Handle remove profile picture
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_picture']) && $hasProfilePictureColumn) {
    $uploadDir = '../assets/images/uploads/pfp/';
    
    // Get current picture
    $oldPicture = get_single_result("SELECT profile_picture FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    if ($oldPicture && $oldPicture['profile_picture'] && file_exists($uploadDir . $oldPicture['profile_picture'])) {
        unlink($uploadDir . $oldPicture['profile_picture']);
    }
    
    // Update database
    if (execute_query("UPDATE users SET profile_picture = NULL WHERE user_id = ?", [$_SESSION['user_id']])) {
        unset($_SESSION['profile_picture']);
        $message = "Profile picture removed successfully!";
    } else {
        $error = "Error removing profile picture.";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = sanitize_input($_POST['fullname']);
    $email = sanitize_input($_POST['email']);
    $phone = $hasPhoneColumn ? sanitize_input($_POST['phone']) : '';
    $address = $hasAddressColumn ? sanitize_input($_POST['address']) : '';
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        // Check if email already exists (excluding current user)
        $checkEmail = get_single_result("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $_SESSION['user_id']]);
        if ($checkEmail) {
            $error = "Email already exists!";
        } else {
            // Build update with proper parameter binding
            if ($hasPhoneColumn && $hasAddressColumn) {
                if (execute_query("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?", 
                    [$fullname, $email, $phone, $address, $_SESSION['user_id']])) {
                    // Update session variables
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['email'] = $email;
                    $message = "Profile updated successfully!";
                } else {
                    $error = "Error updating profile.";
                }
            } elseif ($hasPhoneColumn) {
                if (execute_query("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?", 
                    [$fullname, $email, $phone, $_SESSION['user_id']])) {
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['email'] = $email;
                    $message = "Profile updated successfully!";
                } else {
                    $error = "Error updating profile.";
                }
            } elseif ($hasAddressColumn) {
                if (execute_query("UPDATE users SET full_name = ?, email = ?, address = ? WHERE user_id = ?", 
                    [$fullname, $email, $address, $_SESSION['user_id']])) {
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['email'] = $email;
                    $message = "Profile updated successfully!";
                } else {
                    $error = "Error updating profile.";
                }
            } else {
                if (execute_query("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?", 
                    [$fullname, $email, $_SESSION['user_id']])) {
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['email'] = $email;
                    $message = "Profile updated successfully!";
                } else {
                    $error = "Error updating profile.";
                }
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current user data
    $user = get_single_result("SELECT password FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        if (execute_query("UPDATE users SET password = ? WHERE user_id = ?", [$hashed_password, $_SESSION['user_id']])) {
            $message = "Password changed successfully!";
        } else {
            $error = "Error changing password.";
        }
    }
}

// Set default profile picture if none exists
if ($hasProfilePictureColumn && !empty($managerData['profile_picture'])) {
    $profilePicture = '../assets/images/uploads/pfp/' . $managerData['profile_picture'];
} else {
    $profilePicture = 'https://ui-avatars.com/api/?name=' . urlencode($managerData['full_name']) . '&size=200&background=667eea&color=fff';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manager/manager_profile.css">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 230px; padding: 30px; transition: margin 0.3s; }
        @media (max-width: 1200px) { .main-content { margin-left: 210px; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .profile-container { margin-top: 0 !important; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content flex-grow-1">
            <div class="container-fluid">


        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar" onclick="<?php echo $hasProfilePictureColumn ? 'document.getElementById(\'profilePictureInput\').click()' : 'alert(\'Profile picture feature not available\')'; ?>">
                    <img src="<?php echo $profilePicture; ?>" alt="Profile Picture" id="profileImage">
                    <?php if ($hasProfilePictureColumn): ?>
                    <div class="upload-overlay">
                        <i class="fas fa-camera text-white"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <h2><?php echo $_SESSION['fullname']; ?></h2>
                <p class="mb-1">
                    <span class="manager-badge">
                        <i class="fas fa-user-tie me-1"></i><?php echo ucfirst($_SESSION['role']); ?>
                    </span>
                </p>
                
                <!-- Hidden file input -->
                <?php if ($hasProfilePictureColumn): ?>
                <form method="POST" enctype="multipart/form-data" class="file-upload-form">
                    <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                </form>
                <?php endif; ?>
            </div>

            <!-- Profile Content -->
            <div class="profile-content">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Branch Information (for managers) -->
                <?php if ($_SESSION['role'] == 'manager' && $managerBranch): ?>
                    <div class="branch-card">
                        <h5><i class="fas fa-building me-2"></i>Branch Information</h5>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="info-label">Branch Name</div>
                                <div class="info-value text-white"><?php echo $managerBranch['branch_name']; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Location</div>
                                <div class="info-value text-white">
                                    <?php 
                                    $location = $managerBranch['city'] ?? 'Taguig';
                                    if (isset($managerBranch['province']) && !empty($managerBranch['province'])) {
                                        $location .= ', ' . $managerBranch['province'];
                                    }
                                    echo $location;
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value text-white"><?php echo $managerBranch['contact_number'] ?? 'Not set'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Email</div>
                                <div class="info-value text-white"><?php echo $managerBranch['email'] ?? 'Not set'; ?></div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <div class="info-label">Address</div>
                                <div class="info-value text-white"><?php echo $managerBranch['address'] ?? 'Address not specified'; ?></div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($_SESSION['role'] == 'manager' && !$managerBranch): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No branch assigned. Please contact administrator to assign a branch.
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                            <i class="fas fa-user-edit me-2"></i>Edit Profile
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                            <i class="fas fa-lock me-2"></i>Change Password
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo !$hasProfilePictureColumn ? 'feature-disabled' : ''; ?>" id="picture-tab" data-bs-toggle="tab" data-bs-target="#picture" type="button" role="tab">
                            <i class="fas fa-camera me-2"></i>Profile Picture
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="profileTabsContent">
                    <!-- Edit Profile Tab -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="fullname" 
                                           value="<?php echo $managerData['full_name']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo $managerData['email']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <?php if ($hasPhoneColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo $managerData['phone'] ?? ''; ?>" 
                                           placeholder="Enter phone number">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasAddressColumn): ?>
                                <div class="col-md-<?php echo $hasPhoneColumn ? '6' : '12'; ?>">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" 
                                           value="<?php echo $managerData['address'] ?? ''; ?>" 
                                           placeholder="Enter your address">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="password" role="tabpanel">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required 
                                           minlength="6" placeholder="At least 6 characters">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required 
                                           minlength="6">
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Profile Picture Tab -->
                    <div class="tab-pane fade" id="picture" role="tabpanel">
                        <?php if ($hasProfilePictureColumn): ?>
                            <div class="current-picture text-center mb-4">
                                <h6>Current Profile Picture</h6>
                                <?php if (!empty($managerData['profile_picture'])): ?>
                                    <img src="<?php echo $profilePicture; ?>" alt="Current Profile Picture" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <img src="<?php echo $profilePicture; ?>" alt="Default Profile Picture" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                    <p class="text-muted mt-2">No profile picture set</p>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Upload New Profile Picture</label>
                                    <input type="file" class="form-control" name="profile_picture" accept="image/*" required>
                                    <div class="form-text">
                                        Supported formats: JPG, JPEG, PNG, GIF. Maximum size: 5MB.
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload Picture
                                </button>
                            </form>
                            
                            <?php if (!empty($managerData['profile_picture'])): ?>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="remove_picture" value="1">
                                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to remove your profile picture?')">
                                        <i class="fas fa-trash me-2"></i>Remove Picture
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Profile picture feature is currently disabled. Please contact administrator to enable this feature.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="row mt-5">
                    <div class="col-12">
                        <h5 class="mb-4"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-label">User ID</div>
                                    <div class="info-value">#<?php echo $managerData['user_id']; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-label">Account Created</div>
                                    <div class="info-value"><?php echo date('F j, Y', strtotime($managerData['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="row mt-4">
                    <div class="col-12">
                        <a href="manager_dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/host/profile.js"></script>
    <script>
        // Profile picture preview
        <?php if ($hasProfilePictureColumn): ?>
        document.getElementById('profilePictureInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImage').src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>