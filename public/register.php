<?php
include_once "../includes/auth.php";
include_once "../includes/functions.php";

$error = '';
$success = '';

// Clear alert messages after they've been displayed once
if (isset($_SESSION['alert_shown'])) {
    $error = '';
    $success = '';
    unset($_SESSION['alert_shown']);
}

if (isset($_POST['register'])) {
    $_SESSION['alert_shown'] = true;
    // FIXED: Use proper validation function from functions.php
    $fullname = sanitize_input($_POST['fullname'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    }
    // Validate Terms and Conditions acceptance (Backend enforcement)
    else if (!isset($_POST['terms'])) {
        $error = "You must accept the Terms & Conditions to register.";
    }
    // Input validation using the validation function
    else if (!empty($error = registerValidation($fullname, $email, $password, $confirm_password, $phone))) {
        // Error already set by registerValidation
    }
    else {
        // Check if email exists using prepared statement
        $check_query = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
        $existing_user = get_single_result($check_query, [$email]);
        
        if ($existing_user) {
            $error = "Email already registered! <a href='login.php' class='alert-link'>Login here</a> or use a different email.";
        }
        else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // FIXED: Use prepared statement for user insertion with terms_accepted=1
            $insert_query = "INSERT INTO users (full_name, email, password, phone, role, is_active, terms_accepted) VALUES (?, ?, ?, ?, 'renter', 1, 1)";
            $result = execute_query($insert_query, [$fullname, $email, $hashed_password, $phone]);

            if ($result) {
                // Newly created user - generate email verification OTP and mark inactive until verified
                $new_user_id = $conn->insert_id ?? null;
                if (!$new_user_id) {
                    // try to fetch by email as fallback
                    $row = get_single_result("SELECT user_id FROM users WHERE email = ? LIMIT 1", [$email]);
                    $new_user_id = $row['user_id'] ?? null;
                }

                if ($new_user_id) {
                    // Log the registration event in audit logs
                    logAudit($new_user_id, 'User Registration', 'user', $new_user_id, "User registered and accepted Terms & Conditions. IP: " . $_SERVER['REMOTE_ADDR']);
                }

                // Generate OTP
                try {
                    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                } catch (Exception $e) {
                    $otp = rand(100000, 999999);
                }
                $expiry = date('Y-m-d H:i:s', time() + 10 * 60);

                // Attempt to save otp fields (ignore ALTER errors if DB lacks columns)
                @execute_query("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_code VARCHAR(10) NULL, ADD COLUMN IF NOT EXISTS otp_expiry DATETIME NULL, ADD COLUMN IF NOT EXISTS otp_attempts INT DEFAULT 0");
                if ($new_user_id) {
                    execute_query("UPDATE users SET otp_code = ?, otp_expiry = ?, otp_attempts = 0, is_active = 0 WHERE user_id = ?", [$otp, $expiry, $new_user_id]);
                }

                // Send verification email using PHPMailer helper (falls back to mail())
                $subject = 'Verify your BookIT account';
                $body = "Hello " . htmlspecialchars($fullname) . ",\n\n";
                $body .= "Thank you for registering. Use the following one-time code to verify your email and activate your account:\n\n";
                $body .= "OTP: " . $otp . "\n\nThis code expires in 10 minutes.\n\n— BookIT Team";

                if (function_exists('sendEmailViaPhpMail')) {
                    sendEmailViaPhpMail($email, $fullname, $subject, nl2br(htmlspecialchars($body)));
                } else {
                    @mail($email, $subject, $body, "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n");
                }

                // Store verification session and inform user
                $_SESSION['verify_user_id'] = $new_user_id;
                $_SESSION['verify_email'] = $email;
                $success = "Account created. A verification code was sent to your email. Please check your inbox to activate your account.";
                $fullname = '';
                $email = '';
                $phone = '';
            }
            else {
                $error = "Registration failed. Please try again or contact support.";
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
    <title>Create Account - BookIT</title>
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
        <div class="w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Header with brand gradient -->
            <div class="bg-gradient-to-r from-orange-500 via-red-500 to-orange-600 px-8 pt-10 pb-8 text-center">
                <div class="flex items-center justify-center w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl mb-4 mx-auto">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold text-white">Create Account</h1>
                <p class="text-orange-100 text-base mt-2">Join BookIT and start your rental journey today</p>
            </div>

            <!-- Form content -->
            <div class="p-8 sm:p-10">

            <!-- Inline JS Alerts (hidden by default) -->
            <div id="jsErrorBox" class="mb-6 min-h-0 hidden">
                <div class="rounded-xl bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700 flex items-start animate-in fade-in slide-in-from-top shadow-sm">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span id="jsErrorMsg"></span>
                </div>
            </div>
            <div id="jsSuccessBox" class="mb-6 min-h-0 hidden">
                <div class="rounded-xl bg-green-50 border-l-4 border-green-500 p-4 text-sm text-green-700 flex items-start animate-in fade-in slide-in-from-top shadow-sm">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span id="jsSuccessMsg"></span>
                </div>
            </div>

            <!-- Registration Form -->
            <form action="" method="POST" id="registerForm" class="space-y-5">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- Full Name -->
                <div>
                    <label for="fullname" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="fullname" name="fullname" required
                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                        placeholder="Enter your full name" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                    <p class="text-xs text-gray-500 mt-1.5">Must be at least 2 characters</p>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="email" name="email" required
                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                        placeholder="your-email@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <!-- Phone Number -->
                <div>
                    <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required
                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                        placeholder="+63 9xxxxxxxxx" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    <p class="text-xs text-gray-500 mt-1.5">Format: +63xxxxxxxxxx or 0xxxxxxxxxx</p>
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

                <!-- Terms & Conditions -->
                <div class="flex items-start mt-4">
                    <div class="flex items-center h-5">
                        <input id="terms" name="terms" type="checkbox" required
                            class="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-orange-300">
                    </div>
                    <label for="terms" class="ml-2 text-sm font-medium text-gray-900">
                        I agree to the <a href="#" onclick="openModal('termsModal'); return false;" class="text-orange-600 hover:underline">Terms and Conditions</a> and Privacy Policy.
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="register" 
                    class="w-full inline-flex items-center justify-center rounded-lg px-6 py-3 text-white font-semibold shadow-lg hover:shadow-xl transition mt-8 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    Create Account
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">or</span>
                </div>
            </div>

            <!-- Sign In Link -->
            <div class="text-center mb-6">
                <p class="text-sm text-gray-600">Already have an account? 
                    <a href="login.php" class="text-orange-600 hover:text-orange-700 font-semibold hover:underline transition">Sign In</a>
                </p>
            </div>

            <!-- Register as Host Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">Want to become a host? 
                    <a href="host_register.php" class="text-orange-600 hover:text-orange-700 font-semibold hover:underline transition">Register as Host</a>
                </p>
            </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Terms and Conditions</h3>
                <button onclick="closeModal('termsModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 space-y-4 text-sm text-gray-600">
                <h4 class="font-bold text-gray-900">1. Introduction</h4>
                <p>Welcome to BookIT. By registering, you agree to abide by our policies regarding property rental and reservations.</p>
                
                <h4 class="font-bold text-gray-900">2. Booking and Payments</h4>
                <p>All bookings require a valid payment method. Confirmed reservations are bound by our 10-minute payment hold window. Failure to submit verified payments within this window will result in automated forfeiture of your held reservation dates.</p>
                
                <h4 class="font-bold text-gray-900">3. Cancellations</h4>
                <p>Cancellations are subject to the specific unit's cancellation policy (Flexible, Moderate, Strict). Booking Service Fees are non-refundable.</p>

                <h4 class="font-bold text-gray-900">4. User Conduct</h4>
                <p>Abusive behavior, prohibited language in reviews or messages, and fraudulent payments will result in permanent account suspension and potential legal action.</p>
            </div>
            <div class="p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl flex justify-end">
                <button onclick="document.getElementById('terms').checked = true; closeModal('termsModal');" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-lg font-semibold transition">I Accept</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // Close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('termsModal');
            if (event.target === modal) {
                closeModal('termsModal');
            }
        }

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
            const termsChecked = document.getElementById('terms').checked;
            const errorBox = document.getElementById('jsErrorBox');
            const errorMsg = document.getElementById('jsErrorMsg');

            // Hide boxes by default
            errorBox.classList.add('hidden');

            if (!termsChecked) {
                e.preventDefault();
                errorMsg.textContent = 'You must agree to the Terms and Conditions.';
                errorBox.classList.remove('hidden');
                return;
            }

            if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(password)) {
                e.preventDefault();
                validatePassword(password);
                errorMsg.textContent = 'Password does not meet all requirements.';
                errorBox.classList.remove('hidden');
                passwordInput.focus();
                return;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                errorMsg.textContent = 'Passwords do not match.';
                errorBox.classList.remove('hidden');
                confirmPasswordInput.focus();
                return;
            }
        });
    </script>
</body>
</html>