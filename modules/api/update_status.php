<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reservation_id = $_POST['id'] ?? 0;
$status = $_POST['status'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$reservation_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Set checked_in_by or checked_out_by based on status
    $extra_fields = '';
    $extra_params = [];
    
    if ($status === 'checked-in') {
        $extra_fields = ', checked_in_by = ?';
        $extra_params[] = $user_id;
    } elseif ($status === 'checked-out') {
        $extra_fields = ', checked_out_by = ?';
        $extra_params[] = $user_id;
    }
    
    $query = "UPDATE reservations SET status = ? $extra_fields WHERE reservation_id = ?";
    $params = array_merge([$status], $extra_params, [$reservation_id]);
    
    $result = execute_query($query, $params);
    
    echo json_encode(['success' => $result, 'message' => $result ? 'Status updated' : 'Failed to update status']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>