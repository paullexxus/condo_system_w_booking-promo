<?php
// Enhanced Profile Page with CRUD and Profile Picture
include_once '../includes/public_session.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Database connection
include_once '../config/db.php';

$message = '';
$error = '';

// Check if columns exist with error handling
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
    // If there's an error checking columns, assume they don't exist
    $hasPhoneColumn = $hasAddressColumn = $hasProfilePictureColumn = false;
}

// Get notification count from database
$notificationCount = 0;
try {
    // Check if notifications table exists and get count
    $notificationQuery = "SELECT COUNT(*) as count FROM notifications 
                          WHERE user_id = {$_SESSION['user_id']} 
                          AND (is_read = 0 OR is_read IS NULL)";
    $notificationResult = mysqli_query($conn, $notificationQuery);
    
    if ($notificationResult && mysqli_num_rows($notificationResult) > 0) {
        $notificationData = mysqli_fetch_assoc($notificationResult);
        $notificationCount = $notificationData['count'];
    }
} catch (Exception $e) {
    // If notifications table doesn't exist, count will remain 0
    $notificationCount = 0;
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture']) && $hasProfilePictureColumn) {
    $uploadDir = '../uploads/profile_pictures/';
    
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
                
                // Delete old profile picture if exists - FIXED: Use prepared statement
                $oldPicture = get_single_result("SELECT profile_picture FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
                if ($oldPicture && $oldPicture['profile_picture'] && file_exists($uploadDir . $oldPicture['profile_picture'])) {
                    unlink($uploadDir . $oldPicture['profile_picture']);
                }
                
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    // Update database - FIXED: Use prepared statement
                    $updateQuery = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
                    if (execute_query($updateQuery, [$newFileName, $_SESSION['user_id']])) {
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
    $uploadDir = '../uploads/profile_pictures/';
    
    // Get current picture - FIXED: Use prepared statement
    $oldPicture = get_single_result("SELECT profile_picture FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    if ($oldPicture && $oldPicture['profile_picture'] && file_exists($uploadDir . $oldPicture['profile_picture'])) {
        unlink($uploadDir . $oldPicture['profile_picture']);
    }
    
    // Update database - FIXED: Use prepared statement
    $updateQuery = "UPDATE users SET profile_picture = NULL WHERE user_id = ?";
    if (execute_query($updateQuery, [$_SESSION['user_id']])) {
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
        // Check if email already exists (excluding current user) - FIXED: Use prepared statement
        $checkEmail = get_single_result("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $_SESSION['user_id']]);
        if ($checkEmail) {
            $error = "Email already exists!";
        } else {
            // Build update query based on available columns - FIXED: Use prepared statement
            $updateFields = ["full_name = ?", "email = ?"];
            $updateValues = [$fullname, $email];
            
            if ($hasPhoneColumn) {
                $updateFields[] = "phone = ?";
                $updateValues[] = $phone;
            }
            
            if ($hasAddressColumn) {
                $updateFields[] = "address = ?";
                $updateValues[] = $address;
            }
            
            $updateValues[] = $_SESSION['user_id'];
            
            $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
            
            if (execute_query($updateQuery, $updateValues)) {
                // Update session variables
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $email;
                
                $message = "Profile updated successfully!";
            } else {
                $error = "Error updating profile.";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current user data - FIXED: Use prepared statement
    $user = get_single_result("SELECT password FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    
    if (!$user) {
        $error = "User not found!";
    } elseif (!password_verify($current_password, $user['password'])) {
        // Verify current password
        $error = "Current password is incorrect!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Update password - FIXED: Use prepared statement
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $updateQuery = "UPDATE users SET password = ? WHERE user_id = ?";
        if (execute_query($updateQuery, [$hashed_password, $_SESSION['user_id']])) {
            $message = "Password changed successfully!";
        } else {
            $error = "Error changing password.";
        }
    }
}

// Get current user data - FIXED: Use prepared statement
$userData = get_single_result("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);

// Set default profile picture if none exists
if ($hasProfilePictureColumn && !empty($userData['profile_picture'])) {
    $profilePicture = '../uploads/profile_pictures/' . $userData['profile_picture'];
} else {
    $profilePicture = 'https://ui-avatars.com/api/?name=' . urlencode($userData['full_name']) . '&size=200&background=667eea&color=fff';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .shadow-soft {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card-modern {
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: white;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-luxury-primary {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 10px 20px rgba(243, 156, 18, 0.2);
        }

        .btn-luxury-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(243, 156, 18, 0.35);
            color: white;
        }
    </style>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Renter Navbar: only Be a Host + Profile dropdown -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../public/index.php">
                <i class="fas fa-building"></i> BookIT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item me-2">
                        <?php if (isLoggedIn() && in_array($_SESSION['role'], ['host','manager','admin'])): ?>
                            <a class="nav-link btn btn-outline-light btn-sm px-3" href="../public/manager_register.php">
                                <i class="fas fa-handshake"></i> Be a Host
                            </a>
                        <?php else: ?>
                            <a class="nav-link btn btn-outline-light btn-sm px-3" href="../public/be_host.php">
                                <i class="fas fa-handshake"></i> Be a Host
                            </a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <?php if (isLoggedIn()): ?>
                            <div class="nav-link dropdown">
                                <a class="dropdown-toggle d-flex align-items-center text-white text-decoration-none" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle fa-lg me-2"></i>
                                    <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                                    <li><a class="dropdown-item" href="../modules/notifications.php"><i class="fas fa-bell me-2"></i>Notifications</a></li>
                                    <li><a class="dropdown-item" href="my_bookings.php"><i class="fas fa-calendar-check me-2"></i>My Bookings</a></li>
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="../public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar" onclick="<?php echo $hasProfilePictureColumn ? 'document.getElementById(\'profilePictureInput\').click()' : 'alert(\'Profile picture feature not available\')'; ?>">
                    <img src="<?php echo $profilePicture; ?>" alt="Profile Picture" id="profileImage">
                    <?php if ($hasProfilePictureColumn): ?>
                    <div class="upload-overlay">
                        <i class="fas fa-camera"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <h2><?php echo $_SESSION['fullname']; ?></h2>
                <p class="mb-0"><?php echo ucfirst($_SESSION['role']); ?></p>
                
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
                            <?php if (!$hasProfilePictureColumn): ?>
                                <span class="badge bg-warning ms-1">!</span>
                            <?php endif; ?>
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
                                           value="<?php echo $userData['full_name']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo $userData['email']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <?php if ($hasPhoneColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo $userData['phone'] ?? ''; ?>" 
                                           placeholder="Enter phone number">
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($hasAddressColumn): ?>
                                <div class="col-md-<?php echo $hasPhoneColumn ? '6' : '12'; ?>">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" 
                                           value="<?php echo $userData['address'] ?? ''; ?>" 
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
                            <div class="current-picture">
                                <h6>Current Profile Picture</h6>
                                <?php if (!empty($userData['profile_picture'])): ?>
                                    <img src="<?php echo $profilePicture; ?>" alt="Current Profile Picture">
                                <?php else: ?>
                                    <img src="<?php echo $profilePicture; ?>" alt="Default Profile Picture">
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
                            
                            <?php if (!empty($userData['profile_picture'])): ?>
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
                                Profile picture feature is currently disabled. Please run the SQL command above to enable this feature.
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
                                    <div class="info-value">#<?php echo $userData['user_id']; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-label">Account Created</div>
                                    <div class="info-value"><?php echo date('F j, Y', strtotime($userData['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="row mt-4">
                    <div class="col-12">
                        <a href="../public/browse_units.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Browse Units
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($hasProfilePictureColumn): ?>
        <script src="../assets/js/profile.js"></script>
    <?php endif; ?>
</body>
</html>