<?php
include_once "../includes/auth.php";
include_once "../config/constants.php";

if (isset($_POST['register'])) {
    // Basic information (use host_name from the form; guard missing POST keys)
    $host_name = mysqli_real_escape_string($conn, $_POST['host_name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $password = mysqli_real_escape_string($conn, $_POST['password'] ?? '');
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password'] ?? '');
    
    // Condo information
    $condo_name = mysqli_real_escape_string($conn, $_POST['condo_name'] ?? '');
    $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name'] ?? '');
    $condo_address = mysqli_real_escape_string($conn, $_POST['condo_address'] ?? '');
    $social_media = mysqli_real_escape_string($conn, $_POST['social_media'] ?? '');

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
        $valid_id2_path = '';        // Upload first valid ID
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
            // Insert core user record (keep schema-safe columns only)
            $query = "INSERT INTO users (full_name, email, password, phone, role, is_active) 
                     VALUES ('" . $host_name . "', '" . $email . "', '" . $hashed_password . "', '" . $phone . "', 'host', 0)";

            if (mysqli_query($conn, $query)) {
                $new_user_id = $conn->insert_id;

                // Save application details to a JSON file to avoid schema changes
                $appDir = __DIR__ . '/../uploads/host_applications/';
                if (!is_dir($appDir)) mkdir($appDir, 0755, true);
                $appData = [
                    'user_id' => $new_user_id,
                    'host_name' => $host_name,
                    'condo_name' => $condo_name,
                    'branch_name' => $branch_name,
                    'condo_address' => $condo_address,
                    'social_media' => $social_media,
                    'valid_id1' => $valid_id1_path,
                    'valid_id2' => $valid_id2_path,
                    'submitted_at' => date('c')
                ];
                file_put_contents($appDir . $new_user_id . '.json', json_encode($appData));

                // Redirect after successful registration
                header("Location: login.php?registered=host");
                exit();
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Host Registration - BookIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui']
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-orange-50 via-red-50 to-orange-50 font-sans">
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="w-full max-w-5xl bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Header with brand gradient -->
            <div class="bg-gradient-to-r from-orange-500 via-red-500 to-orange-600 px-8 pt-10 pb-8 text-center">
                <div class="flex items-center justify-center w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl mb-4 mx-auto">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold text-white">Become a Host</h1>
                <p class="text-orange-100 text-base mt-2">Register your property and start earning with BookIT</p>
            </div>

            <!-- Form content -->
            <div class="p-8 sm:p-10">

                <!-- Alerts Container -->
            <div id="alertsContainer" class="mb-6 min-h-0">
                <?php if(isset($error)): ?>
                    <div class="rounded-xl bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700 flex items-start animate-in fade-in slide-in-from-top shadow-sm">
                        <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if(isset($success)): ?>
                    <div class="rounded-xl bg-green-50 border-l-4 border-green-500 p-4 text-sm text-green-700 flex items-start animate-in fade-in slide-in-from-top shadow-sm">
                        <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Registration Form -->
            <form action="" method="POST" enctype="multipart/form-data" id="registerForm" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Column 1: Personal Information -->
                <div class="space-y-5">
                    <div class="pb-4 border-b-2 border-orange-200">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Personal Information
                        </h3>
                    </div>

                    <!-- Host Name -->
                    <div>
                        <label for="host_name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input type="text" id="host_name" name="host_name" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="Your Full Name" value="<?php echo isset($_POST['host_name']) ? htmlspecialchars($_POST['host_name']) : ''; ?>">
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="email" name="email" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="your@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" id="phone" name="phone" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="+63 9xxxxxxxxx" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition pr-10"
                                placeholder="Create a strong password">
                            <button type="button" onclick="togglePasswordVisibility('password', 'togglePasswordIcon')" 
                                class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600 transition">
                                <svg id="togglePasswordIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" required
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition pr-10"
                                placeholder="Confirm your password">
                            <button type="button" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPasswordIcon')" 
                                class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600 transition">
                                <svg id="toggleConfirmPasswordIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Password Requirements -->
                    <div id="passwordRequirements" class="bg-gradient-to-br from-orange-50 to-red-50 rounded-lg p-4 border border-orange-200 transition-all duration-300">
                        <p class="text-sm font-semibold text-orange-900 mb-3">Password Requirements:</p>
                        <div class="space-y-2">
                            <div id="req-length" class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" id="icon-length" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                At least 8 characters
                            </div>
                            <div id="req-uppercase" class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" id="icon-uppercase" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One uppercase letter (A-Z)
                            </div>
                            <div id="req-lowercase" class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" id="icon-lowercase" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One lowercase letter (a-z)
                            </div>
                            <div id="req-number" class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" id="icon-number" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One number (0-9)
                            </div>
                            <div id="req-special" class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" id="icon-special" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One special character (!@#$%^&*)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Property Information -->
                <div class="space-y-5">
                    <div class="pb-4 border-b-2 border-orange-200">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            Property Information
                        </h3>
                    </div>

                    <!-- Condo Name -->
                    <div>
                        <label for="condo_name" class="block text-sm font-semibold text-gray-700 mb-2">Condo Name</label>
                        <input type="text" id="condo_name" name="condo_name" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="e.g., Sunset Heights Condo" value="<?php echo isset($_POST['condo_name']) ? htmlspecialchars($_POST['condo_name']) : ''; ?>">
                    </div>

                    <!-- Branch Name -->
                    <div>
                        <label for="branch_name" class="block text-sm font-semibold text-gray-700 mb-2">Branch Name</label>
                        <input type="text" id="branch_name" name="branch_name" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="e.g., Makati Branch" value="<?php echo isset($_POST['branch_name']) ? htmlspecialchars($_POST['branch_name']) : ''; ?>">
                    </div>

                    <!-- Condo Address -->
                    <div>
                        <label for="condo_address" class="block text-sm font-semibold text-gray-700 mb-2">Complete Address</label>
                        <textarea id="condo_address" name="condo_address" required rows="3"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="Street address, barangay, city, province"><?php echo isset($_POST['condo_address']) ? htmlspecialchars($_POST['condo_address']) : ''; ?></textarea>
                    </div>

                    <!-- Social Media Link -->
                    <div>
                        <label for="social_media" class="block text-sm font-semibold text-gray-700 mb-2">Social Media Link (Optional)</label>
                        <input type="url" id="social_media" name="social_media"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="https://facebook.com/..." value="<?php echo isset($_POST['social_media']) ? htmlspecialchars($_POST['social_media']) : ''; ?>">
                    </div>

                    <!-- Valid ID 1 -->
                    <div>
                        <label for="valid_id1" class="block text-sm font-semibold text-gray-700 mb-2">Government ID (Primary)</label>
                        <input type="file" id="valid_id1" name="valid_id1" required accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent file:mr-3 file:py-1 file:px-3 file:bg-orange-500 file:text-white file:border-0 file:rounded-lg file:cursor-pointer transition">
                        <p class="text-xs text-gray-500 mt-1.5">Accepted: JPG, JPEG, PNG, PDF (Max 5MB)</p>
                    </div>

                    <!-- Valid ID 2 -->
                    <div>
                        <label for="valid_id2" class="block text-sm font-semibold text-gray-700 mb-2">Secondary ID</label>
                        <input type="file" id="valid_id2" name="valid_id2" required accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent file:mr-3 file:py-1 file:px-3 file:bg-orange-500 file:text-white file:border-0 file:rounded-lg file:cursor-pointer transition">
                        <p class="text-xs text-gray-500 mt-1.5">Accepted: JPG, JPEG, PNG, PDF (Max 5MB)</p>
                    </div>
                </div>
            </form>

            <!-- Submit Button (Full Width) -->
            <div class="mt-8 pt-6 border-t border-gray-300">
                <button type="submit" name="register" form="registerForm"
                    class="w-full inline-flex items-center justify-center rounded-lg px-6 py-3 text-white font-semibold shadow-lg hover:shadow-xl transition bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Submit Application
                </button>
            </div>

            <!-- Links -->
            <div class="mt-6 pt-6 border-t border-gray-300 text-center space-y-3">
                <p class="text-sm text-gray-600">Already have an account? 
                    <a href="login.php" class="text-orange-600 hover:text-orange-700 font-semibold hover:underline transition">Sign In</a>
                </p>
                <p class="text-sm text-gray-600">Looking to book a unit? 
                    <a href="register.php" class="text-orange-600 hover:text-orange-700 font-semibold hover:underline transition">Register as Renter</a>
                </p>
            </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePasswordVisibility(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            if (field.type === 'password') {
                field.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-4.803m5.596-3.856a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>';
            } else {
                field.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
            }
        }

        // Password strength validator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        function validatePassword(password) {
            const checks = {
                'req-length': password.length >= 8,
                'req-uppercase': /[A-Z]/.test(password),
                'req-lowercase': /[a-z]/.test(password),
                'req-number': /\d/.test(password),
                'req-special': /[\W_]/.test(password)
            };

            for (const [id, valid] of Object.entries(checks)) {
                const element = document.getElementById(id);
                const icon = document.getElementById('icon-' + id.split('-')[1]);
                if (valid) {
                    element.classList.remove('text-gray-600');
                    element.classList.add('text-green-600');
                    icon.classList.remove('text-gray-400');
                    icon.classList.add('text-green-500');
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
                } else {
                    element.classList.add('text-gray-600');
                    element.classList.remove('text-green-600');
                    icon.classList.add('text-gray-400');
                    icon.classList.remove('text-green-500');
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                }
            }
        }

        passwordInput.addEventListener('focus', function() {
            // Show requirements on focus for better UX
        });

        passwordInput.addEventListener('input', function() {
            validatePassword(this.value);
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(password)) {
                e.preventDefault();
                validatePassword(password);
                alert('Password does not meet all requirements.');
                passwordInput.focus();
                return;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                confirmPasswordInput.focus();
                return;
            }
        });
    </script>

</body>
</html>