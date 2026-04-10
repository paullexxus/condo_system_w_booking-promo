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
<body class="bg-gray-50 text-gray-800">
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

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 pt-32 pb-16">
        
        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-8 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    <span class="font-medium"><?php echo $message; ?></span>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" class="text-green-500 hover:text-green-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    <span class="font-medium"><?php echo $error; ?></span>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Card 1 — Profile Header -->
        <div class="bg-white rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-10 mb-8 flex flex-col items-center text-center relative overflow-hidden border border-gray-100">
            <!-- Decorative Background Element -->
            <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-r from-blue-50 to-indigo-50"></div>
            
            <!-- Avatar -->
            <div class="relative z-10 mb-4 group cursor-pointer" onclick="<?php echo $hasProfilePictureColumn ? 'document.getElementById(\'profilePictureInput\').click()' : 'alert(\'Profile picture feature not available\')'; ?>">
                <div class="w-32 h-32 rounded-full border-4 border-white shadow-md overflow-hidden bg-white">
                    <img src="<?php echo $profilePicture; ?>" alt="Profile Picture" id="profileImage" class="w-full h-full object-cover">
                </div>
                <?php if ($hasProfilePictureColumn): ?>
                <div class="absolute inset-0 bg-black bg-opacity-40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <i class="fas fa-camera text-white text-2xl"></i>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Hidden file input -->
            <?php if ($hasProfilePictureColumn): ?>
            <form method="POST" enctype="multipart/form-data" class="hidden" id="profilePicForm">
                <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" onchange="document.getElementById('profilePicForm').submit()">
            </form>
            <?php endif; ?>
            
            <h2 class="text-3xl font-bold text-gray-900 mb-1 z-10"><?php echo htmlspecialchars($_SESSION['fullname']); ?></h2>
            <p class="text-gray-500 font-medium mb-8 z-10 text-lg"><?php echo ucfirst($_SESSION['role']); ?></p>
            
            <!-- Action Buttons -->
            <div class="flex flex-wrap justify-center gap-4 z-10 w-full">
                <a href="#account-info" class="flex-1 min-w-[200px] max-w-[220px] py-3 px-6 rounded-xl bg-blue-50 text-blue-600 font-semibold hover:bg-blue-100 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </a>
                <button type="button" data-bs-toggle="modal" data-bs-target="#passwordModal" class="flex-1 min-w-[200px] max-w-[220px] py-3 px-6 rounded-xl bg-gray-50 text-gray-700 font-semibold hover:bg-gray-100 transition-colors flex items-center justify-center gap-2 border border-gray-200">
                    <i class="fas fa-lock"></i> Change Password
                </button>
                <button type="button" onclick="<?php echo $hasProfilePictureColumn ? 'document.getElementById(\'profilePictureInput\').click()' : 'alert(\'Profile picture feature not available\')'; ?>" class="flex-1 min-w-[200px] max-w-[220px] py-3 px-6 rounded-xl bg-gray-50 text-gray-700 font-semibold hover:bg-gray-100 transition-colors flex items-center justify-center gap-2 border border-gray-200">
                    <i class="fas fa-camera"></i> Change Picture
                </button>
            </div>
        </div>

        <!-- Card 2 — Account Information -->
        <div id="account-info" class="bg-white rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-10 border border-gray-100 scroll-mt-24">
            <div class="mb-8 border-b border-gray-100 pb-6 flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Account Information</h3>
                    <p class="text-gray-500">Manage your personal details and contact information.</p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-400 font-medium uppercase tracking-wider mb-1">User ID</div>
                    <div class="text-sm text-gray-700 font-semibold bg-gray-50 px-3 py-1 rounded-lg border border-gray-100">#<?php echo $userData['user_id']; ?></div>
                </div>
            </div>
            
            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                    <!-- Left Column -->
                    <div class="space-y-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($userData['full_name']); ?>" required
                                   class="w-full px-5 py-4 rounded-xl bg-gray-50 border-none focus:ring-2 focus:ring-blue-500 text-gray-900 font-medium transition-shadow outline-none">
                        </div>
                        
                        <?php if ($hasPhoneColumn): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" placeholder="+63 9xx xxx xxxx"
                                   class="w-full px-5 py-4 rounded-xl bg-gray-50 border-none focus:ring-2 focus:ring-blue-500 text-gray-900 font-medium transition-shadow outline-none">
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required
                                   class="w-full px-5 py-4 rounded-xl bg-gray-50 border-none focus:ring-2 focus:ring-blue-500 text-gray-900 font-medium transition-shadow outline-none">
                        </div>
                        
                        <?php if ($hasAddressColumn): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>" placeholder="Enter your full address"
                                   class="w-full px-5 py-4 rounded-xl bg-gray-50 border-none focus:ring-2 focus:ring-blue-500 text-gray-900 font-medium transition-shadow outline-none">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-12 flex justify-end">
                    <button type="submit" name="update_profile" class="py-4 px-10 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-[0_8px_20px_rgb(37,99,235,0.25)] transition-all transform hover:-translate-y-0.5 outline-none">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Back Link -->
        <div class="mt-10 text-center">
            <a href="../public/browse_units.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-blue-600 font-medium transition-colors">
                <i class="fas fa-arrow-left"></i> Back to Browse Units
            </a>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-2xl border-0 shadow-2xl overflow-hidden">
                <div class="modal-header border-b border-gray-100 p-6 bg-white">
                    <h5 class="modal-title font-bold text-xl text-gray-900">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-6 space-y-6 bg-white">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                            <input type="password" name="current_password" required class="w-full px-5 py-4 rounded-xl bg-gray-50 border-none focus:ring-2 focus:ring-blue-500 text-gray-900 transition-shadow outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters" class="w-full px-5 py-4 rounded-xl bg-gray-50 border-none focus:ring-2 focus:ring-blue-500 text-gray-900 transition-shadow outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6" class="w-full px-5 py-4 rounded-xl bg-gray-50 border-none focus:ring-2 focus:ring-blue-500 text-gray-900 transition-shadow outline-none">
                        </div>
                    </div>
                    <div class="modal-footer border-t border-gray-100 p-6 bg-gray-50 flex justify-end gap-3">
                        <button type="button" class="py-3 px-6 rounded-xl bg-white border border-gray-200 text-gray-700 font-semibold hover:bg-gray-50 transition-colors outline-none shadow-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_password" class="py-3 px-6 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-md shadow-blue-200 transition-all outline-none">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($hasProfilePictureColumn): ?>
        <script src="../assets/js/profile.js"></script>
    <?php endif; ?>
</body>
</html>