<?php
/**
 * System Constants
 * Central location para sa lahat ng system constants at configurations
 */

// Check if constants are already defined to avoid warnings
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'condo_rental_reservation_db');
}

// System Settings
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'BookIT');
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/BookIT');
}

if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', 'admin@bookit.com');
}

// File Upload Settings
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
}

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
}

if (!defined('ALLOWED_FILE_TYPES')) {
    define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
}

// Session Settings
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 7200); // 2 hours
}

if (!defined('COOKIE_LIFETIME')) {
    define('COOKIE_LIFETIME', 86400); // 24 hours
}

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'BOOKIT_SESSION');
}

// Security Settings
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}

if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}

if (!defined('LOCKOUT_TIME')) {
    define('LOCKOUT_TIME', 900); // 15 minutes
}

// Booking Settings
if (!defined('MIN_BOOKING_DAYS')) {
    define('MIN_BOOKING_DAYS', 1);
}

if (!defined('MAX_BOOKING_DAYS')) {
    define('MAX_BOOKING_DAYS', 30);
}

if (!defined('CANCELLATION_PERIOD')) {
    define('CANCELLATION_PERIOD', 24); // hours before check-in
}

if (!defined('SECURITY_DEPOSIT_PERCENTAGE')) {
    define('SECURITY_DEPOSIT_PERCENTAGE', 20);
}

// Pagination
if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', 10);
}

if (!defined('MAX_PAGINATION_LINKS')) {
    define('MAX_PAGINATION_LINKS', 5);
}

// Cache Settings
if (!defined('CACHE_ENABLED')) {
    define('CACHE_ENABLED', true);
}

if (!defined('CACHE_LIFETIME')) {
    define('CACHE_LIFETIME', 3600); // 1 hour
}

// API Settings
if (!defined('API_VERSION')) {
    define('API_VERSION', '1.0');
}

if (!defined('API_RATE_LIMIT')) {
    define('API_RATE_LIMIT', 100); // requests per hour
}

// Map provider settings (set MAP_PROVIDER to 'google' or 'leaflet')
if (!defined('MAP_PROVIDER')) {
    define('MAP_PROVIDER', getenv('MAP_PROVIDER') ?: 'google');
}

// Google Maps API Key (required if MAP_PROVIDER is 'google')
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: 'AIzaSyDPT7k-kxhEVCZTT5nU0-LYQ-cqkrRrwIo');
}

// Notification Settings
if (!defined('EMAIL_NOTIFICATIONS_ENABLED')) {
    define('EMAIL_NOTIFICATIONS_ENABLED', true);
}

// SMTP / Mailer settings (for PHPMailer) done configuring
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
}
if (!defined('SMTP_USER')) {
     // Prefer environment variable for SMTP username to avoid storing secrets in repo
     define('SMTP_USER', getenv('SMTP_USER') ?: 'your-email@gmail.com');
}
if (!defined('SMTP_PASS')) {
     // Prefer environment variable for SMTP password (use App Password for Gmail)
     define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls'); // 'tls' or 'ssl'
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'no-reply@bookit.com');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'BookIT');
}

if (!defined('SMS_NOTIFICATIONS_ENABLED')) {
    define('SMS_NOTIFICATIONS_ENABLED', false);
}

// Development Mode
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', getenv('DEBUG_MODE') ?: true);
}

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Initialize important paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . '/config');
}

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', ROOT_PATH . '/includes');
}

if (!defined('TEMPLATES_PATH')) {
    define('TEMPLATES_PATH', ROOT_PATH . '/templates');
}

if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', ROOT_PATH . '/logs');
}

// Create required directories if they don't exist
$required_dirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'profile_pictures/',
    UPLOAD_PATH . 'documents/',
    LOGS_PATH
];

foreach ($required_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Security headers (only send if not already sent)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Session configuration (only if session not started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_name(SESSION_NAME);
}

// Error logging configuration
ini_set('log_errors', 1);
ini_set('error_log', LOGS_PATH . '/php_errors.log');

// Check for duplicate includes
if (function_exists('check_duplicate_includes')) {
    function check_duplicate_includes() {
        static $included_files = [];
        $backtrace = debug_backtrace();
        $caller = $backtrace[1]['file'] ?? 'unknown';
        
        if (in_array($caller, $included_files)) {
            error_log("Duplicate include detected: $caller");
            return false;
        }
        
        $included_files[] = $caller;
        return true;
    }
}

// Safe include function
if (!function_exists('safe_include')) {
    function safe_include($file_path) {
        if (file_exists($file_path) && check_duplicate_includes()) {
            return include $file_path;
        }
        return false;
    }
}
?>
