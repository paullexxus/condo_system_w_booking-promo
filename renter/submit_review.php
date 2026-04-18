<?php
// Handle review submissions from renters
include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../config/db.php';
checkRole(['renter']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_bookings.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['flash_error'] = 'Security validation failed.';
    header('Location: my_bookings.php');
    exit;
}

$reservationId = (int)($_POST['reservation_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = sanitize_input($_POST['comment'] ?? '');

if ($reservationId <= 0 || $rating < 1 || $rating > 5) {
    $_SESSION['flash_error'] = 'Invalid review submission.';
    header('Location: my_bookings.php');
    exit;
}

// Verify reservation belongs to user and has checked out
$res = get_single_result("SELECT r.*, u.unit_id FROM reservations r JOIN units u ON r.unit_id = u.unit_id WHERE r.reservation_id = ? AND r.user_id = ?", [$reservationId, $_SESSION['user_id']]);
if (!$res) {
    $_SESSION['flash_error'] = 'Reservation not found.';
    header('Location: my_bookings.php');
    exit;
}

$checkOut = new DateTime($res['check_out_date']);
$now = new DateTime();
$interval = $now->diff($checkOut);
// Allow reviews up to 14 days after check-out
if ($checkOut > $now || $interval->days > 14) {
    $_SESSION['flash_error'] = 'Reviews can only be left within 14 days after check-out.';
    header('Location: my_bookings.php');
    exit;
}

// Prevent duplicate review for this reservation by this user
$existing = get_single_result("SELECT review_id FROM reviews WHERE reservation_id = ? AND user_id = ?", [$reservationId, $_SESSION['user_id']]);
if ($existing) {
    $_SESSION['flash_error'] = 'You have already submitted a review for this booking.';
    header('Location: my_bookings.php');
    exit;
}

// Auto-Moderation: Prohibited Words Check
$prohibitedWords = ['scam', 'fake', 'bullshit', 'fuck', 'shit', 'asshole', 'bitch', 'crap'];
$isApproved = 1; // Default to approved
foreach ($prohibitedWords as $word) {
    if (stripos($comment, $word) !== false) {
        $isApproved = 0; // Flag for manual admin review
        break;
    }
}

// Insert review
try {
    $unitId = $res['unit_id'] ?? null;
    execute_query("INSERT INTO reviews (user_id, unit_id, reservation_id, rating, comment, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())", [$_SESSION['user_id'], $unitId, $reservationId, $rating, $comment, $isApproved]);
    
    if ($isApproved === 1) {
        $_SESSION['flash_success'] = 'Thank you! Your review has been published.';
    } else {
        $_SESSION['flash_success'] = 'Your review was submitted but is currently under moderation due to our community guidelines.';
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Failed to submit review.';
}

header('Location: my_bookings.php');
exit;
