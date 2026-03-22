<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/email_integration.php';

$to = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'test@example.com';
echo "Sending test email to: $to\n";
$ok = sendEmailViaPhpMail($to, 'Admin', 'BookIT test', '<p>PHPMailer test from BookIT</p>');
echo $ok ? "SENT\n" : "FAILED\n";

?>
