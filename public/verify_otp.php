<?php
// Verify OTP page - renter enters OTP sent via email
include_once '../includes/public_session.php';
include_once '../includes/functions.php';

$error = '';
$message = '';

// Ensure we have a reset user in session
if (empty($_SESSION['reset_user_id'])) {
    header('Location: forgot_password.php');
    exit;
}

$userId = (int)$_SESSION['reset_user_id'];

// Fetch user OTP info (include role)
$user = get_single_result("SELECT user_id, email, role FROM users WHERE user_id = ?", [$userId]);

if (!$user) {
    $error = 'Invalid reset session. Please start again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed.';
    } else {
        $entered = isset($_POST['otp']) ? strval($_POST['otp']) : '';
        $entered = trim($entered);
        if (!preg_match('/^[0-9]{6}$/', $entered)) {
            $error = 'Enter a 6-digit OTP.';
        } else {
            // Fetch latest unused otp_verifications row for this user and purpose
            $otpRow = get_single_result(
                "SELECT id, otp_code, expires_at, attempts, is_used FROM otp_verifications WHERE user_id = ? AND purpose = 'reset_password' AND is_used = 0 ORDER BY created_at DESC LIMIT 1",
                [$userId]
            );

            if (!$otpRow) {
                $error = 'No OTP found. Request a new code.';
            } else {
                $attempts = (int)($otpRow['attempts'] ?? 0);
                $maxAttempts = in_array($user['role'] ?? '', ['admin', 'host']) ? 3 : 5;
                if ($attempts >= $maxAttempts) {
                    $error = 'Maximum verification attempts exceeded. Please request a new OTP.';
                } else {
                    $now = date('Y-m-d H:i:s');
                    if (empty($otpRow['expires_at']) || $otpRow['expires_at'] < $now) {
                        $error = 'OTP expired. Please request a new code.';
                    } else {
                        // ...existing code...
                        // Verify hashed OTP using password_verify against otp_verifications.otp_code
                        if (password_verify($entered, $otpRow['otp_code'])) {
                            // success: mark this otp_verifications row as used and reset attempts
                            execute_query("UPDATE otp_verifications SET is_used = 1, attempts = 0 WHERE id = ?", [$otpRow['id']]);
                            $_SESSION['reset_verified'] = true;
                            header('Location: reset_password.php');
                            exit;
                        } else {
                            // increment attempts on the otp_verifications row
                            execute_query("UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?", [$otpRow['id']]);
                                $remaining = max(0, $maxAttempts - 1 - $attempts);
                                // Log failed attempt for privileged accounts
                                if (in_array($user['role'] ?? '', ['admin', 'host'])) {
                                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                                    $log = sprintf("[%s] OTP_FAILED user_id=%s email=%s role=%s ip=%s attempts=%s\n", date('c'), $userId, $user['email'], $user['role'] ?? 'unknown', $ip, $attempts + 1);
                                    @file_put_contents(__DIR__ . '/../logs/reset_attempts.log', $log, FILE_APPEND);
                                }
                                $error = 'Incorrect OTP. You have ' . $remaining . ' attempts remaining.';
                            }
                        }
                    }
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
    <title>Verify OTP - BookIT</title>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Verify OTP</h1>
                <p class="text-sm text-gray-500 text-center mt-2">Enter the 6-digit code sent to <?php echo htmlspecialchars($_SESSION['reset_email'] ?? 'your email'); ?></p>
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

            <!-- OTP Verification Form -->
            <form method="POST" id="otpForm" class="space-y-4" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- OTP Input Field -->
                <div>
                    <label for="otp" class="block text-sm font-medium text-gray-700 mb-1">6-digit OTP</label>
                    <input 
                        id="otp" 
                        name="otp" 
                        type="text" 
                        maxlength="6" 
                        pattern="[0-9]{6}" 
                        required
                        inputmode="numeric"
                        class="w-full rounded-lg border border-gray-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent placeholder-gray-400 text-center tracking-widest text-2xl font-semibold transition"
                        placeholder="------">
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button 
                        type="submit" 
                        name="verify_otp" 
                        class="w-full inline-flex items-center justify-center rounded-lg px-4 py-3 text-white font-medium shadow-sm hover:shadow-md transition"
                        style="background: linear-gradient(90deg,#7c3aed,#6d28d9);">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Verify OTP
                    </button>
                </div>

                <!-- Resend OTP Link -->
                <div class="text-center text-sm text-gray-500">
                    <a href="forgot_password.php" class="text-purple-700 hover:text-purple-800 hover:underline transition">Request New Code</a>
                </div>
            </form>
        </div>
    </div>

    <!-- OTP Input Script -->
    <script>
        // Auto-format OTP input to only allow numbers
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        // Client-side validation
        document.getElementById('otpForm').addEventListener('submit', function(e) {
            const otp = document.getElementById('otp').value.trim();
            
            if (!/^[0-9]{6}$/.test(otp)) {
                e.preventDefault();
                alert('Please enter a valid 6-digit OTP.');
                document.getElementById('otp').focus();
                return false;
            }
        });
    </script>
</body>
</html>
