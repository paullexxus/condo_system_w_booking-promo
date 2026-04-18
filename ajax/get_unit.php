<?php
// Get unit details for editing
header('Content-Type: application/json');

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

if (!$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid unit ID']);
    exit;
}

// Verify unit belongs to host
$unit = get_single_result("
    SELECT u.*, b.branch_name 
    FROM units u
    INNER JOIN branches b ON u.branch_id = b.branch_id
    WHERE u.unit_id = ? AND u.host_id = ?
", [$unit_id, $host_id]);

if (!$unit) {
    echo json_encode(['success' => false, 'message' => 'Unit not found']);
    exit;
}

$amenity_rows = get_multiple_results("SELECT amenity_id FROM unit_amenities WHERE unit_id = ?", [$unit_id]);
$unit['amenity_ids'] = array_map('intval', array_column($amenity_rows, 'amenity_id'));

echo json_encode(['success' => true, 'unit' => $unit]);
