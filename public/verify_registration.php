<?php
// Verify Registration OTP
include_once '../includes/public_session.php';
include_once '../includes/functions.php';

$error = '';
$message = '';

// Ensure we have a verify session
if (empty($_SESSION['verify_user_id'])) {
    header('Location: register.php');
    exit;
}

$userId = (int)$_SESSION['verify_user_id'];

$user = get_single_result("SELECT user_id, email, otp_code, otp_expiry, otp_attempts FROM users WHERE user_id = ?", [$userId]);

if (!$user) {
    $error = 'Invalid verification session. Please start again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed.';
    } else {
        $entered = sanitize_input($_POST['otp'] ?? '');
        if (!preg_match('/^[0-9]{6}$/', $entered)) {
            $error = 'Enter a 6-digit OTP.';
        } else {
            $user = get_single_result("SELECT user_id, email, otp_code, otp_expiry, otp_attempts FROM users WHERE user_id = ?", [$userId]);
            if (!$user || empty($user['otp_code'])) {
                $error = 'No OTP found. Please request a new code.';
            } else {
                $attempts = (int)($user['otp_attempts'] ?? 0);
                if ($attempts >= 5) {
                    $error = 'Maximum verification attempts exceeded. Please request a new OTP.';
                } else {
                    $now = date('Y-m-d H:i:s');
                    if ($user['otp_expiry'] < $now) {
                        $error = 'OTP expired. Please request a new code.';
                    } else if (hash_equals(trim($user['otp_code']), $entered)) {
                        // success: activate account
                        execute_query("UPDATE users SET otp_attempts = 0, otp_code = NULL, otp_expiry = NULL, is_active = 1 WHERE user_id = ?", [$userId]);
                        unset($_SESSION['verify_user_id']);
                        unset($_SESSION['verify_email']);
                        $message = 'Your account has been verified. You may now login.';
                    } else {
                        execute_query("UPDATE users SET otp_attempts = otp_attempts + 1 WHERE user_id = ?", [$userId]);
                        $remaining = 4 - $attempts;
                        $error = 'Incorrect OTP. You have ' . max(0, $remaining) . ' attempts remaining.';
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verify Registration - BookIT</title>
    <link href="../assets/css/auth/forgot.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <h2>Verify Your Email</h2>
        <p>We sent a 6-digit code to <?php echo htmlspecialchars($_SESSION['verify_email'] ?? 'your email'); ?>. Enter it below to activate your account.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <div class="text-center mt-3"><a href="login.php">Back to Login</a></div>
        <?php else: ?>
        <form method="POST" id="otpForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="mb-3">
                <label class="form-label">OTP Code</label>
                <input type="text" name="otp" id="otp" class="form-control" placeholder="123456" maxlength="6" required>
            </div>
            <div class="d-grid">
                <button type="submit" name="verify_otp" class="btn btn-primary">Verify OTP</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
