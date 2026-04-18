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

$amenity_rows = get_multiple_results("SELECT amenity_id FROM unit_amenities WHERE unit_id = ?", [$unit_id]);
$amenities = array_map('intval', array_column($amenity_rows, 'amenity_id'));

// Build response
$response = [
    'unit_id' => $unit['unit_id'],
    'unit_name' => $unit['unit_name'],
    'branch_id' => $unit['branch_id'],
    'description' => $unit['description'],
    'pricing_type' => $unit['pricing_type'] ?? 'nightly',
    'price_per_night' => $unit['price_per_night'] ?? 0,
    'price_per_month' => $unit['price_per_month'] ?? 0,
    'price' => (($unit['pricing_type'] ?? 'nightly') === 'monthly') ? number_format(($unit['price_per_month'] ?? 0) / 30, 2, '.', '') : number_format($unit['price_per_night'] ?? 0, 2, '.', ''),
    'capacity' => $unit['max_occupancy'],
    'is_available' => $unit['is_available'],
    'amenities' => $amenities,
    'amenity_ids' => $amenities
];

echo json_encode($response);
?>
