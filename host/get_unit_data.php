<?php
// API endpoint to fetch unit data for editing/viewing
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

header('Content-Type: application/json');

if (!isset($_GET['unit_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unit ID required']);
    exit;
}

$unit_id = sanitize_input($_GET['unit_id']);
$host_id = $_SESSION['user_id'];

// Verify unit belongs to host
$unit = get_single_result("
    SELECT * FROM units 
    WHERE unit_id = ? AND host_id = ?
", [$unit_id, $host_id]);

if (!$unit) {
    http_response_code(404);
    echo json_encode(['error' => 'Unit not found']);
    exit;
}

// Amenities not currently stored at unit level
$amenities = [];

// Build response
$response = [
    'unit_id' => $unit['unit_id'],
    'unit_name' => $unit['unit_name'],
    'branch_id' => $unit['branch_id'],
    'description' => $unit['description'],
    'price' => isset($unit['monthly_rate']) ? number_format($unit['monthly_rate'] / 30, 2) : 0,
    'capacity' => $unit['max_occupancy'],
    'is_available' => $unit['is_available'],
    'amenities' => $amenities
];

echo json_encode($response);
?>
