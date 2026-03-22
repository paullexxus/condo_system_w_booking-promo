<?php
/**
 * Centralized email integration using PHPMailer (with mail() fallback)
 * Reads config from config/email.php if present, otherwise falls back to
 * SMTP_* constants in config/constants.php
 */

function get_email_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $cfg = [
        'smtp' => [
            'host' => defined('SMTP_HOST') ? SMTP_HOST : 'localhost',
            'port' => defined('SMTP_PORT') ? SMTP_PORT : 25,
            'username' => defined('SMTP_USER') ? SMTP_USER : 'officialbookitmanager@gmail.com',
            'password' => defined('SMTP_PASS') ? SMTP_PASS : 'nddefzlrysdjowju',
            'encryption' => defined('SMTP_SECURE') ? SMTP_SECURE : 'tls',
        ],
        'from' => [
            'email' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@bookit.com',
            'name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'BookIT',
        ]
    ];

    $file = __DIR__ . '/../config/email.php';
    if (file_exists($file)) {
        $fileCfg = include $file;
        if (is_array($fileCfg)) {
            if (!empty($fileCfg['smtp']) && is_array($fileCfg['smtp'])) {
                $cfg['smtp'] = array_merge($cfg['smtp'], $fileCfg['smtp']);
            }
            if (!empty($fileCfg['from']) && is_array($fileCfg['from'])) {
                $cfg['from'] = array_merge($cfg['from'], $fileCfg['from']);
            }
        }
    }

    return $cfg;
}

function sendEmailViaPhpMail($to, $to_name, $subject, $message) {
    $cfg = get_email_config();

    // Try PHPMailer (preferred)
    // Ensure Composer autoload is loaded so class_exists() can detect PHPMailer
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $smtp = $cfg['smtp'];

            $mail->isSMTP();
            if (!empty($smtp['host'])) $mail->Host = $smtp['host'];
            $mail->SMTPAuth = true;
            if (!empty($smtp['username'])) $mail->Username = $smtp['username'];
            if (!empty($smtp['password'])) $mail->Password = $smtp['password'];
            if (!empty($smtp['encryption'])) $mail->SMTPSecure = $smtp['encryption'];
            if (!empty($smtp['port'])) $mail->Port = (int)$smtp['port'];

            $from = $cfg['from'];
            $mail->setFrom($from['email'], $from['name']);
            $mail->addAddress($to, $to_name ?: '');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);

            $sent = $mail->send();
            if ($sent) {
                error_log("PHPMailer: Email sent to: $to");
                return true;
            } else {
                error_log('PHPMailer send() returned false: ' . ($mail->ErrorInfo ?? 'no ErrorInfo'));
            }
        } catch (\Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
        }
    }

    // Fallback to PHP mail()
    $from = $cfg['from'];
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $from['name'] . " <" . $from['email'] . ">\r\n";
    $headers .= "Reply-To: " . $from['email'] . "\r\n";

    $result = @mail($to, $subject, $message, $headers);
    if ($result) {
        error_log("mail(): Email sent to: $to");
    } else {
        error_log("mail() FAILED to: $to");
    }

    return $result;
}

function sendReservationConfirmationEmail($user_email, $user_name, $reservation) {
    $subject = "Reservation Confirmed - BookIT";
    $check_out = date('M d, Y', strtotime($reservation['check_out_date']));
    $check_in = date('M d, Y', strtotime($reservation['check_in_date']));
    $total = number_format($reservation['total_amount'], 2);
    $unit = htmlspecialchars($reservation['unit_number']);
    $branch = htmlspecialchars($reservation['branch_name']);
    $res_id = htmlspecialchars($reservation['reservation_id']);

    $message = "<!DOCTYPE html><html><body>" .
        "<p>Hello " . htmlspecialchars($user_name) . ",</p>" .
        "<p>Your reservation is confirmed! Reservation ID: #$res_id</p>" .
        "<p>Unit: $unit<br>Location: $branch<br>Check-in: $check_in<br>Check-out: $check_out<br>Total: P$total</p>" .
        "</body></html>";

    return sendEmailViaPhpMail($user_email, $user_name, $subject, $message);
}

function sendPaymentConfirmationEmail($user_email, $user_name, $amount) {
    $subject = "Payment Received - BookIT";
    $total = number_format($amount, 2);
    $message = "<!DOCTYPE html><html><body><p>Hello " . htmlspecialchars($user_name) . ",</p>" .
        "<p>Your payment of P$total has been successfully received.</p></body></html>";
    return sendEmailViaPhpMail($user_email, $user_name, $subject, $message);
}

function sendAdminBookingNotification($reservation, $admin_email = 'admin@bookit.com') {
    $subject = "New Booking - Unit " . ($reservation['unit_number'] ?? '');
    $message = "<!DOCTYPE html><html><body><p>A new reservation has been created. Reservation ID: " . htmlspecialchars($reservation['reservation_id'] ?? '') . "</p></body></html>";
    return sendEmailViaPhpMail($admin_email, 'Admin', $subject, $message);
}


