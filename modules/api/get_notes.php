<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

$reservation_id = $_GET['id'] ?? 0;

if (!$reservation_id) {
    echo json_encode(['notes' => []]);
    exit;
}

try {
    // Get notes from reservation_notes table
    $query = "SELECT * FROM reservation_notes WHERE reservation_id = ? ORDER BY created_at DESC";
    $notes = get_multiple_results($query, [$reservation_id]);
    
    echo json_encode(['notes' => $notes]);
} catch (Exception $e) {
    echo json_encode(['notes' => [], 'error' => $e->getMessage()]);
}
?>