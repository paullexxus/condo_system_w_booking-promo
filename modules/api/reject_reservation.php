<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reservation_id = $_POST['id'] ?? 0;
$reason = $_POST['reason'] ?? '';

if (!$reservation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
    exit;
}

try {
    $query = "UPDATE reservations SET status = 'cancelled', cancellation_reason = ? WHERE reservation_id = ?";
    $result = execute_query($query, [$reason, $reservation_id]);
    
    echo json_encode(['success' => $result, 'message' => $result ? 'Reservation rejected' : 'Failed to reject']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>