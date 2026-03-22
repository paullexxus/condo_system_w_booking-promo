<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reservation_id = $_POST['id'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$reservation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
    exit;
}

try {
    $query = "UPDATE reservations SET status = 'confirmed', approved_by = ?, approved_at = NOW() WHERE reservation_id = ?";
    $result = execute_query($query, [$user_id, $reservation_id]);
    
    echo json_encode(['success' => $result, 'message' => $result ? 'Reservation approved' : 'Failed to approve']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>