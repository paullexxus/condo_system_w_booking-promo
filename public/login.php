<?php
// BookIT Login System
// Multi-branch Condo Rental Reservation System

// CRITICAL: Set error handlers FIRST before anything else
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "PHP ERROR [$errno]: $errstr in $errfile:$errline\n";
    return true;
});

// Ensure constants (session name, security headers) are set before sessions start
require_once __DIR__ . '/../config/constants.php';
session_start();

include_once '../config/db.php';
include_once '../includes/security.php';
clearLoginPageCache();

require_once '../includes/components/form_errors.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Google OAuth Configuration
$oauth_config = include '../config/OAuth.php';
$google_client_id = $oauth_config['google']['client_id'];
$google_client_secret = $oauth_config['google']['client_secret'];
$google_redirect_uri = 'http://localhost/BookIT/public/login.php';

// Forgot password is handled on a separate page: public/forgot_password.php

// Handle Login
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // FIXED: Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    }
    // Validate inputs
    else if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } 
    // Bulletproof #4: Bruteforce Throttling
    else if (!throttleRequest('login', 5, 600)) {
        $error = "Too many login attempts. Please try again in 10 minutes.";
        logSystemError("Brute force attempt detected", ['email' => $email]);
    }
    else {
        // Check if user exists and is active
        $query = "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (isset($user['is_suspended']) && $user['is_suspended'] == 1) {
                $error = "Your account has been suspended. Please contact support.";
            } else {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // CRITICAL Bulletproof #3: Session Hardening
                    session_regenerate_id(true);
                    rotateCSRFToken(); // Rotate on state change
                    
                    // CRITICAL Bulletproof #1: Fingerprinting
                    $fingerprint_subnet = explode('.', $_SERVER['REMOTE_ADDR']);
                    $subnet = (count($fingerprint_subnet) >= 3) ? $fingerprint_subnet[0].'.'.$fingerprint_subnet[1].'.'.$fingerprint_subnet[2] : $_SERVER['REMOTE_ADDR'];
                    $_SESSION['fingerprint'] = md5($subnet . $_SERVER['HTTP_USER_AGENT']);
                    
                    // Store session data
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['fullname'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Redirect by role
                    if ($user['role'] == 'admin') {
                        header("Location: ../admin/admin_dashboard.php");
                    } elseif ($user['role'] == 'manager' || $user['role'] == 'host') {
                        header("Location: ../host/host_dashboard.php");
                    } elseif ($user['role'] == 'renter') {
                        header("Location: index.php");
                    } else {
                        header("Location: login.php");
                    }
                    exit();
                } else {
                    $error = "Incorrect password.";
                }
            }
        } else {
            $error = "No account found with that email or account is inactive.";
        }
        $stmt->close();
    }
}

// Handle Google OAuth callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange code for access token
    $token_url = "https://oauth2.googleapis.com/token";
    $token_data = [
        'code' => $code,
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri' => $google_redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $token_response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($token_response, true);
    
    if (isset($token_data['access_token'])) {
        // Get user info from Google
        $user_info_url = "https://www.googleapis.com/oauth2/v2/userinfo";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_info_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token_data['access_token']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $user_info_response = curl_exec($ch);
        curl_close($ch);
        
        $user_info = json_decode($user_info_response, true);
        
        if (isset($user_info['email'])) {
            $google_email = $user_info['email'];
            $google_name = $user_info['name'] ?? 'Google User';
            $google_picture = $user_info['picture'] ?? '';
            
            // Check if user exists in database
            $query = "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $google_email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && mysqli_num_rows($result) == 1) {
                // User exists, check suspension then log them in
                $user = mysqli_fetch_assoc($result);
                
                if (isset($user['is_suspended']) && $user['is_suspended'] == 1) {
                    $error = "Your account has been suspended. Please contact support.";
                } else {
                    // Start secure session
                    session_regenerate_id(true);
                
                // Store session data
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['fullname'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['login_method'] = 'google';
                
                // Redirect by role
                if ($user['role'] == 'admin') {
                    header("Location: ../admin/admin_dashboard.php");
                } elseif ($user['role'] == 'manager' || $user['role'] == 'host') {
                    header("Location: ../host/host_dashboard.php");
                } elseif ($user['role'] == 'renter') {
                    header("Location: index.php");
                } else {
                    header("Location: login.php");
                }
                exit();
                } // End of is_suspended else block
            } else {
                // User doesn't exist, create new account as renter
                // Check if email already exists (inactive account)
                $check_query = "SELECT user_id FROM users WHERE email = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("s", $google_email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Email exists but account is inactive, activate it
                    $update_query = "UPDATE users SET is_active = 1, full_name = ? WHERE email = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ss", $google_name, $google_email);
                    
                    if ($update_stmt->execute()) {
                        // Get the updated user
                        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("s", $google_email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = mysqli_fetch_assoc($result);
                        
                        // Start secure session
                        session_regenerate_id(true);
                        
                        // Store session data
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['fullname'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['login_method'] = 'google';
                        
                        header("Location: index.php");
                        exit();
                    }
                    $update_stmt->close();
                } else {
                    // Create new user
                    $default_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $phone = ''; // Google doesn't provide phone number
                    
                    $insert_query = "INSERT INTO users (full_name, email, password, phone, role, is_active, created_at) 
                                   VALUES (?, ?, ?, ?, 'renter', 1, NOW())";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("ssss", $google_name, $google_email, $default_password, $phone);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        
                        // Start secure session
                        session_regenerate_id(true);
                        
                        // Store session data
                        $_SESSION['user_id'] = $new_user_id;
                        $_SESSION['fullname'] = $google_name;
                        $_SESSION['role'] = 'renter';
                        $_SESSION['email'] = $google_email;
                        $_SESSION['login_method'] = 'google';
                        
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "Failed to create account with Google login.";
                    }
                }
                $check_stmt->close();
            }
            $stmt->close();
        } else {
            $error = "Failed to get user information from Google.";
        }
    } else {
        $error = "Failed to authenticate with Google.";
    }
}

// Generate Google OAuth URL properly
$google_oauth_params = [
    'client_id' => $google_client_id,
    'redirect_uri' => $google_redirect_uri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account'
];
$google_oauth_url = "https://accounts.google.com/o/oauth2/auth?" . http_build_query($google_oauth_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BookIT</title>
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
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-orange-500 via-red-500 to-orange-600 px-8 py-12 text-white">
                <div class="flex flex-col items-center mb-4">
                    <h1 class="text-3xl font-bold">Welcome</h1>
                    <p class="text-sm text-white/90 text-center mt-1">Sign in to your account</p>
                </div>
            </div>

            <!-- Form Container -->
            <div class="p-8 sm:p-10">

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-4 rounded-lg bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700 flex items-start">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="mb-4 rounded-lg bg-green-50 border-l-4 border-green-500 p-4 text-sm text-green-700 flex items-start">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Google Login Button -->
            <?php if ($google_client_id !== 'YOUR_GOOGLE_CLIENT_ID_HERE'): ?>
            <a href="<?php echo $google_oauth_url; ?>" class="w-full inline-flex items-center justify-center rounded-lg px-4 py-3 border border-gray-300 text-gray-700 font-medium shadow-sm hover:bg-gray-50 transition mb-4">
                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Login with Google
            </a>

            <div class="relative mb-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">or sign in with email</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" id="loginForm" class="space-y-5" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition"
                        placeholder="name@example.com">
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent placeholder-gray-400 transition pr-10"
                            placeholder="••••••••">
                        <button type="button" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600 transition" onclick="togglePasswordVisibility('password')">
                            <!-- Eye Icon (shown when password is hidden) -->
                            <svg id="password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <!-- Eye Slash Icon (shown when password is visible) -->
                            <svg id="password-eye-slash" class="w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Forgot Password Link -->
                <div class="text-right">
                    <a href="forgot_password.php" class="text-sm text-orange-600 hover:text-orange-700 hover:underline transition font-medium">Forgot password?</a>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button 
                        type="submit" 
                        name="login" 
                        class="w-full inline-flex items-center justify-center rounded-lg px-4 py-3 text-white font-semibold shadow-lg hover:shadow-xl transition bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600">
                        Sign In
                    </button>
                </div>
            </form>

            <!-- Divider -->
            <div class="my-6 border-t border-gray-300"></div>

            <!-- Register Link -->
            <div class="text-center text-sm text-gray-600">
                Don't have an account?
                <a href="register.php" class="text-orange-600 hover:text-orange-700 hover:underline font-semibold transition">Create one</a>
            </div>

            <!-- Home Link -->
            <div class="mt-4 text-center text-sm">
                <a href="index.php" class="text-gray-500 hover:text-gray-700 hover:underline transition">← Back to Home</a>
            </div>
            </div>
        </div>
    </div>

    <!-- Password Visibility Toggle -->
    <script>
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const eyeIcon = document.getElementById(fieldId + '-eye');
            const eyeSlashIcon = document.getElementById(fieldId + '-eye-slash');
            const isPassword = field.type === 'password';
            
            field.type = isPassword ? 'text' : 'password';
            
            // Toggle icons
            if (eyeIcon && eyeSlashIcon) {
                eyeIcon.classList.toggle('hidden');
                eyeSlashIcon.classList.toggle('hidden');
            }
        }

        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                document.getElementById('email').focus();
                return false;
            }
            
            if (!password) {
                e.preventDefault();
                alert('Please enter your password.');
                document.getElementById('password').focus();
                return false;
            }
        });
    </script>
</body>
</html>