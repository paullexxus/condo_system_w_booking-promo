<?php
// Forgot Password - Send OTP
// Form to request a password reset OTP sent to registered email

include_once '../includes/public_session.php';
include_once '../includes/functions.php';
include_once '../config/file_paths.php';
// Ensure mail helper is available
include_once __DIR__ . '/../includes/email_integration.php';
// No role check here — allow guests to request OTP

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $email = sanitize_input($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if user exists (include role and active status)
            $user = get_single_result("SELECT user_id, email, full_name, role, is_active FROM users WHERE email = ?", [$email]);
            if (!$user) {
                $error = 'No account found with that email.';
            } else {
                // For privileged roles, enforce stricter checks
                if (in_array($user['role'], ['admin', 'host'])) {
                    if (empty($user['is_active'])) {
                        $error = 'Account is inactive. Contact support.';
                    } else {
                        // Check if email verification column exists and require it if present
                        global $conn;
                        $colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
                        if ($colRes && $colRes->num_rows > 0) {
                            $meta = get_single_result("SELECT email_verified FROM users WHERE user_id = ?", [$user['user_id']]);
                            if (empty($meta['email_verified'])) {
                                $error = 'Account email is not verified. Please verify email before resetting password.';
                            }
                        }
                    }
                }

                // If an error was set above for privileged users, stop before generating OTP
                if (!empty($error)) {
                    // log the blocked reset attempt for auditing
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $log = sprintf("[%s] BLOCKED_RESET_REQUEST user_id=%s email=%s role=%s ip=%s\n", date('c'), $user['user_id'], $user['email'], $user['role'] ?? 'unknown', $ip);
                    @file_put_contents(__DIR__ . '/../logs/reset_attempts.log', $log, FILE_APPEND);
                }

                // Generate 6-digit OTP (plaintext for email only)
                try {
                    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                } catch (Exception $e) {
                    $otp = rand(100000, 999999);
                }

                // Expiry: 10 minutes from now
                $expirySeconds = 10 * 60;
                $otpExpiry = date('Y-m-d H:i:s', time() + $expirySeconds);

                // Hash the OTP before saving (do not store plaintext)
                $otpHash = password_hash($otp, PASSWORD_DEFAULT);

                // Save hashed OTP into `otp_verifications` table (separate table for OTPs)
                // purpose = 'reset_password', attempts = 0, is_used = 0
                $saved = execute_query(
                    "INSERT INTO otp_verifications (user_id, email, otp_code, purpose, expires_at, attempts, is_used) VALUES (?, ?, ?, 'reset_password', ?, 0, 0)",
                    [$user['user_id'], $user['email'], $otpHash, $otpExpiry]
                );

                if ($saved) {
                    // Save email and user_id in session for next steps
                    $_SESSION['reset_user_id'] = $user['user_id'];
                    $_SESSION['reset_email'] = $user['email'];

                    // Send OTP via email (uses mail(); configure SMTP on server)
                    $to = $user['email'];
                    $subject = 'Your BookIT Password Reset Code';
                    $messageBody = "Hello " . ($user['full_name'] ?? '') . ",\n\n" .
                        "We received a request to reset your password. Use the following OTP (one-time code) to verify your identity:\n\n" .
                        "OTP: " . $otp . "\n\n" .
                        "This code will expire in 10 minutes. If you did not request this, please ignore this email.\n\n" .
                        "— BookIT Team";

                    $headers = 'From: no-reply@localhost' . "\r\n" .
                        'Reply-To: no-reply@localhost' . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();

                    // Send via PHPMailer helper (falls back to mail())
                    if (function_exists('sendEmailViaPhpMail')) {
                        $mailSent = sendEmailViaPhpMail($to, $user['full_name'], $subject, nl2br(htmlspecialchars($messageBody)) );
                    } else {
                        $mailSent = @mail($to, $subject, $messageBody, $headers);
                    }

                    // Log the OTP issuance for auditing (timestamp + IP)
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $log = sprintf("[%s] OTP_ISSUED user_id=%s email=%s role=%s ip=%s mail_sent=%s\n", date('c'), $user['user_id'], $user['email'], $user['role'] ?? 'unknown', $ip, $mailSent ? '1' : '0');
                    @file_put_contents(__DIR__ . '/../logs/reset_attempts.log', $log, FILE_APPEND);

                    if ($mailSent) {
                        $message = 'OTP sent to your email. Please check your inbox.';
                    } else {
                        $message = 'OTP generated and saved. (Mail delivery failed - check server SMTP settings.)';
                    }

                    // Redirect user to verify page to enter OTP
                    header('Location: verify_otp.php');
                    exit;
                } else {
                    $error = 'Failed to save OTP. Please try again.';
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
    <title>Forgot Password</title>
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
            <div class="flex flex-col items-center mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">Forgot your password?</h1>
                <p class="text-sm text-gray-500 text-center mt-2">Enter your email and we'll send a one-time code to reset your password.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Enter your email</label>
                    <input id="email" name="email" type="email" required
                        class="w-full rounded-lg border border-gray-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent placeholder-gray-400"
                        placeholder="name@example.com">
                </div>

                <div>
                    <button type="submit" name="send_otp" class="w-full inline-flex items-center justify-center rounded-lg px-4 py-3 text-white font-medium shadow-sm"
                        style="background: linear-gradient(90deg,#7c3aed,#6d28d9);">
                        Send OTP
                    </button>
                </div>

                <div class="text-center text-sm text-gray-500">
                    <a href="login.php" class="text-purple-700 hover:underline">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
