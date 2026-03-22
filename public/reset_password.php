<?php
// Reset Password - After OTP verification
include_once '../includes/public_session.php';
include_once '../includes/functions.php';

$error = '';
$message = '';

// Require verified OTP
if (empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_verified'])) {
    header('Location: forgot_password.php');
    exit;
}

$userId = (int)$_SESSION['reset_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed.';
    } else {
        $pw1 = $_POST['password'] ?? '';
        $pw2 = $_POST['confirm_password'] ?? '';
        if (strlen($pw1) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else if ($pw1 !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($pw1, PASSWORD_DEFAULT);
            $updated = execute_query("UPDATE users SET password = ? WHERE user_id = ?", [$hash, $userId]);
            if ($updated) {
                // Mark any outstanding reset_password OTPs as used to invalidate them
                execute_query("UPDATE otp_verifications SET is_used = 1 WHERE user_id = ? AND purpose = 'reset_password'", [$userId]);

                // Clear reset session keys
                unset($_SESSION['reset_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_email']);
                $message = 'Password reset successful. Redirecting to login...';
                header('Refresh:3; url=login.php');
            } else {
                $error = 'Failed to update password. Please try again.';
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
    <title>Reset Password - BookIT</title>
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
<body class="min-h-screen bg-gradient-to-br from-indigo-600 to-purple-500 font-sans">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8 sm:p-10">
            <!-- Logo Section -->
            <div class="flex flex-col items-center mb-8">
                <div class="flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-full mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Create New Password</h1>
                <p class="text-sm text-gray-500 text-center mt-2">Secure your account with a strong password</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700 flex items-start">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if ($message): ?>
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700 flex items-start">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Reset Form -->
            <form method="POST" id="resetForm" class="space-y-4" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- New Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <div class="relative">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            required
                            class="w-full rounded-lg border border-gray-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="At least 8 characters">
                        <button type="button" class="absolute right-3 top-3 text-gray-500 hover:text-gray-700" onclick="togglePasswordVisibility('password')">
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
                    <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long</p>
                </div>

                <!-- Confirm Password Field -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <input 
                            id="confirm_password" 
                            name="confirm_password" 
                            type="password" 
                            required
                            class="w-full rounded-lg border border-gray-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent placeholder-gray-400 transition"
                            placeholder="Re-enter your password">
                        <button type="button" class="absolute right-3 top-3 text-gray-500 hover:text-gray-700" onclick="togglePasswordVisibility('confirm_password')">
                            <!-- Eye Icon (shown when password is hidden) -->
                            <svg id="confirm_password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <!-- Eye Slash Icon (shown when password is visible) -->
                            <svg id="confirm_password-eye-slash" class="w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button 
                        type="submit" 
                        name="reset_password" 
                        class="w-full inline-flex items-center justify-center rounded-lg px-4 py-3 text-white font-medium shadow-sm hover:shadow-md transition"
                        style="background: linear-gradient(90deg,#7c3aed,#6d28d9);">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        Reset Password
                    </button>
                </div>

                <!-- Back to Login Link -->
                <div class="text-center text-sm text-gray-500">
                    <a href="login.php" class="text-purple-700 hover:text-purple-800 hover:underline transition">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Password Visibility Toggle Script -->
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
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const pw1 = document.getElementById('password').value;
            const pw2 = document.getElementById('confirm_password').value;
            
            if (pw1.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters.');
                document.getElementById('password').focus();
                return false;
            }
            
            if (pw1 !== pw2) {
                e.preventDefault();
                alert('Passwords do not match.');
                document.getElementById('confirm_password').focus();
                return false;
            }
        });
    </script>
</body>
</html>