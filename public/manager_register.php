<?php
include_once "../includes/auth.php";

if (isset($_POST['register'])) {
    // Basic information
    $manager_name = mysqli_real_escape_string($conn, $_POST['manager_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    
    // Condo information
    $condo_name = mysqli_real_escape_string($conn, $_POST['condo_name']);
    $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name']);
    $condo_address = mysqli_real_escape_string($conn, $_POST['condo_address']);
    $social_media = mysqli_real_escape_string($conn, $_POST['social_media']);

    // CHECK IF EMAIL EXISTS.
    $check_email = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if (mysqli_num_rows($check_email) > 0) {
        $error = "Email already registered!";
    } 
    elseif ($password != $confirm_password) {
        $error = "Passwords do not match!";
    } 
    // PASSWORD STRENGTH VALIDATION
    elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Password must be at least 8 characters long and include an uppercase letter, lowercase letter, number, and special character.";
    }
    else {
        // Handle file uploads for valid IDs
        $valid_id1_path = '';
        $valid_id2_path = '';
        
        // Upload first valid ID
        if (isset($_FILES['valid_id1']) && $_FILES['valid_id1']['error'] === UPLOAD_ERR_OK) {
            $valid_id1_name = time() . '_1_' . basename($_FILES['valid_id1']['name']);
            $target_dir = "../uploads/valid_ids/";
            $valid_id1_path = $target_dir . $valid_id1_name;
            
            // Create directory if it doesn't exist
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            // Check file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_extension = strtolower(pathinfo($valid_id1_path, PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                if (!move_uploaded_file($_FILES['valid_id1']['tmp_name'], $valid_id1_path)) {
                    $error = "Failed to upload first valid ID.";
                }
            } else {
                $error = "Invalid file type for first valid ID. Only JPG, JPEG, PNG, and PDF files are allowed.";
            }
        } else {
            $error = "Please upload first valid ID.";
        }
        
        // Upload second valid ID if no error from first upload
        if (!isset($error) && isset($_FILES['valid_id2']) && $_FILES['valid_id2']['error'] === UPLOAD_ERR_OK) {
            $valid_id2_name = time() . '_2_' . basename($_FILES['valid_id2']['name']);
            $target_dir = "../uploads/valid_ids/";
            $valid_id2_path = $target_dir . $valid_id2_name;
            
            // Check file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_extension = strtolower(pathinfo($valid_id2_path, PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_types)) {
                if (!move_uploaded_file($_FILES['valid_id2']['tmp_name'], $valid_id2_path)) {
                    $error = "Failed to upload second valid ID.";
                }
            } else {
                $error = "Invalid file type for second valid ID. Only JPG, JPEG, PNG, and PDF files are allowed.";
            }
        } else if (!isset($error)) {
            $error = "Please upload second valid ID.";
        }
        
        // If no errors with file uploads, proceed with registration
        if (!isset($error)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table with manager role and additional fields
            $query = "INSERT INTO users (full_name, email, password, phone, role, condo_name, branch_name, condo_address, social_media, valid_id1, valid_id2, status) 
                     VALUES ('$manager_name', '$email', '$hashed_password', '$phone', 'manager', '$condo_name', '$branch_name', '$condo_address', '$social_media', '$valid_id1_path', '$valid_id2_path', 'pending')";
            
            if (mysqli_query($conn, $query)) {
                $success = "Account created successfully! Your application is under review. You will be notified once approved.";
            } else {
                $error = "Something went wrong. Please try again. Error: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Registration - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public/manager_register.css">
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="row g-0">
                <!-- Left Side - Personal Information -->
                <div class="col-lg-6">
                    <div class="register-body">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary">
                                <i class="fas fa-user-tie"></i> Manager Registration
                            </h2>
                            <p class="text-muted">Register your property on BookIT and start hosting guests.</p>
                        </div>

                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(isset($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST" id="registerForm" enctype="multipart/form-data">
                            <h5 class="mb-3 text-primary"><i class="fas fa-user"></i> Personal Information</h5>
                            
                            <div class="form-floating">
                                <input type="text" class="form-control" id="manager_name" name="manager_name" 
                                       placeholder="Manager Name" required value="<?php echo isset($_POST['manager_name']) ? $_POST['manager_name'] : ''; ?>">
                                <label for="manager_name">
                                    <i class="fas fa-user"></i> Manager Name
                                </label>
                            </div>

                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Email Address" required value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                                <label for="email">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                            </div>

                            <div class="form-floating">
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="Phone Number" required pattern="[0-9]{11}" 
                                       title="Enter a valid 11 digit phone number" 
                                       value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>">
                                <label for="phone">
                                    <i class="fas fa-phone"></i> Phone Number
                                </label>
                            </div>

                            <div class="form-floating password-container">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Password" required>
                                <label for="password">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <span class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>

                            <!-- Password Requirements -->
                            <div class="password-requirements">
                                <h6 class="mb-3">Password Requirements:</h6>
                                <div class="requirement" id="req-length">
                                    <i class="fas fa-times"></i>
                                    <span>At least 8 characters long</span>
                                </div>
                                <div class="requirement" id="req-uppercase">
                                    <i class="fas fa-times"></i>
                                    <span>One uppercase letter</span>
                                </div>
                                <div class="requirement" id="req-lowercase">
                                    <i class="fas fa-times"></i>
                                    <span>One lowercase letter</span>
                                </div>
                                <div class="requirement" id="req-number">
                                    <i class="fas fa-times"></i>
                                    <span>One number</span>
                                </div>
                                <div class="requirement" id="req-special">
                                    <i class="fas fa-times"></i>
                                    <span>One special character</span>
                                </div>
                            </div>

                            <div class="form-floating password-container">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm Password" required>
                                <label for="confirm_password">
                                    <i class="fas fa-lock"></i> Confirm Password
                                </label>
                                <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                    </div>
                </div>

                <!-- Right Side - Property Information -->
                <div class="col-lg-6">
                    <div class="register-body">
                        <h5 class="mb-3 text-primary"><i class="fas fa-building"></i> Property Information</h5>
                        
                        <div class="form-floating">
                            <input type="text" class="form-control" id="condo_name" name="condo_name" 
                                   placeholder="Condo Name" required value="<?php echo isset($_POST['condo_name']) ? $_POST['condo_name'] : ''; ?>">
                            <label for="condo_name">
                                <i class="fas fa-building"></i> Condo Name
                            </label>
                        </div>

                        <div class="form-floating">
                            <input type="text" class="form-control" id="branch_name" name="branch_name" 
                                   placeholder="Branch Name" required value="<?php echo isset($_POST['branch_name']) ? $_POST['branch_name'] : ''; ?>">
                            <label for="branch_name">
                                <i class="fas fa-code-branch"></i> Branch Name
                            </label>
                        </div>

                        <div class="form-floating">
                            <textarea class="form-control" id="condo_address" name="condo_address" 
                                      placeholder="Condo Address" style="height: 100px" required><?php echo isset($_POST['condo_address']) ? $_POST['condo_address'] : ''; ?></textarea>
                            <label for="condo_address">
                                <i class="fas fa-map-marker-alt"></i> Condo Address
                            </label>
                        </div>

                        <div class="form-floating">
                            <input type="url" class="form-control" id="social_media" name="social_media" 
                                   placeholder="Social Media Link" value="<?php echo isset($_POST['social_media']) ? $_POST['social_media'] : ''; ?>">
                            <label for="social_media">
                                <i class="fas fa-link"></i> Social Media Link (Optional)
                            </label>
                        </div>

                        <div class="mb-3">
                            <label for="valid_id1" class="form-label">
                                <i class="fas fa-id-card"></i> Valid ID 1 (Government Issued)
                            </label>
                            <input type="file" class="form-control" id="valid_id1" name="valid_id1" 
                                   accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="form-text">Accepted formats: JPG, JPEG, PNG, PDF</div>
                        </div>

                        <div class="mb-4">
                            <label for="valid_id2" class="form-label">
                                <i class="fas fa-id-card"></i> Valid ID 2 (Secondary ID)
                            </label>
                            <input type="file" class="form-control" id="valid_id2" name="valid_id2" 
                                   accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="form-text">Accepted formats: JPG, JPEG, PNG, PDF</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="register" class="btn btn-primary btn-register btn-lg">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? 
                                <a href="login.php" class="login-link">Login Here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/public/manager_register.js"></script>
</body>
</html>