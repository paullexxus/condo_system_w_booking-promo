<?php
header('Content-Type: application/json');

include_once '../includes/session.php';
include_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'message' => 'Please log in to use promo codes.']);
    exit;
}

$code = strtoupper(trim($_POST['promo_code'] ?? ''));
$subtotal = isset($_POST['subtotal']) ? (float) $_POST['subtotal'] : 0.0;

if ($code === '') {
    echo json_encode(['valid' => false, 'message' => 'Please enter a promo code.']);
    exit;
}

if (!function_exists('get_single_result')) {
    echo json_encode(['valid' => false, 'message' => 'Database error.']);
    exit;
}

$unit_id = isset($_POST['unit_id']) ? (int) $_POST['unit_id'] : 0;
$host_id = 0;
if ($unit_id > 0) {
    if (function_exists('get_single_result')) {
        $unit = get_single_result("SELECT host_id FROM units WHERE unit_id = ?", [$unit_id]);
        if ($unit && isset($unit['host_id'])) {
            $host_id = (int) $unit['host_id'];
        }
    }
}

$promo = get_single_result("
    SELECT p.*, u.role as creator_role 
    FROM promos p 
    LEFT JOIN users u ON p.created_by = u.user_id 
    WHERE p.code = ? AND p.is_active = 1 LIMIT 1
", [$code]);

if (!$promo) {
    echo json_encode(['valid' => false, 'message' => 'Invalid promo code.']);
    exit;
}

if (isset($promo['creator_role']) && in_array($promo['creator_role'], ['host', 'manager'])) {
    if ($host_id === 0 || (int) $promo['created_by'] !== $host_id) {
        echo json_encode(['valid' => false, 'message' => 'This promo code is not valid for this unit.']);
        exit;
    }
}

// Check expiration date
if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < time()) {
    echo json_encode(['valid' => false, 'message' => 'Promo expired.']);
    exit;
}

// Check usage limit
if (!empty($promo['usage_limit']) && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
    echo json_encode(['valid' => false, 'message' => 'Promo fully used.']);
    exit;
}

// Calculate discount
$discount = 0.0;
$type = $promo['type'];
$value = (float) $promo['value'];

if ($type === 'percentage') {
    $discount = $subtotal * ($value / 100.0);
} else {
    // Fixed discount
    $discount = $value;
}

// Cap the discount at the subtotal amount
$discount = min(max(0.0, $discount), $subtotal);

echo json_encode([
    'valid' => true,
    'discount' => $discount,
    'message' => 'Promo code applied!',
    'type' => $type,
    'value' => $value
]);
?>