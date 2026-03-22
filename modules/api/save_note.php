<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reservation_id = $_POST['reservation_id'] ?? 0;
$note_text = $_POST['note_text'] ?? '';
$note_type = $_POST['note_type'] ?? 'general';
$user_id = $_SESSION['user_id'];

if (!$reservation_id || !$note_text) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $query = "INSERT INTO reservation_notes (reservation_id, user_id, note_type, note_text) VALUES (?, ?, ?, ?)";
    $result = execute_query($query, [$reservation_id, $user_id, $note_type, $note_text]);
    
    echo json_encode(['success' => $result, 'message' => $result ? 'Note saved' : 'Failed to save note']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>