<?php
/**
 * Add New Amenity API Endpoint
 * Handles the creation of new amenities
 * 
 * Expected POST data (JSON):
 * {
 *     "amenity_name": "string",
 *     "description": "string",
 *     "branch_id": "integer",
 *     "hourly_rate": "decimal",
 *     "max_capacity": "integer",
 *     "is_available": "boolean"
 * }
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start session
session_start();

// Include database connection
require_once '../../../config/db.php';
require_once '../../../includes/auth.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}

// Check if method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON data
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$amenity_name = isset($input['amenity_name']) ? trim($input['amenity_name']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
$hourly_rate = isset($input['hourly_rate']) ? floatval($input['hourly_rate']) : 0;
$max_capacity = isset($input['max_capacity']) ? intval($input['max_capacity']) : 1;
$is_available = isset($input['is_available']) ? intval($input['is_available']) : 1;

// Validation
$errors = [];

if (empty($amenity_name)) {
    $errors[] = 'Amenity name is required';
}

if (strlen($amenity_name) < 3) {
    $errors[] = 'Amenity name must be at least 3 characters';
}

if (strlen($amenity_name) > 100) {
    $errors[] = 'Amenity name must not exceed 100 characters';
}

if ($hourly_rate < 0) {
    $errors[] = 'Hourly rate must be greater than or equal to 0';
}

if ($branch_id <= 0) {
    $errors[] = 'Please select a valid branch';
}

if ($max_capacity < 1) {
    $errors[] = 'Maximum capacity must be at least 1';
}

// If validation errors exist, return them
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

// Verify branch exists and user has access
$branch_query = "SELECT b.branch_id, b.branch_name, b.host_id FROM branches b WHERE b.branch_id = ?";
$stmt = $conn->prepare($branch_query);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $branch_id);
$stmt->execute();
$result = $stmt->get_result();
$branch = $result->fetch_assoc();
$stmt->close();

if (!$branch) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Branch not found']);
    exit;
}

// Check authorization
// Only admin can add amenities to any branch, hosts can only add to their own branches
if ($_SESSION['role'] !== 'admin' && $branch['host_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to add amenities to this branch']);
    exit;
}

// Insert amenity into database
$insert_query = "INSERT INTO amenities (branch_id, amenity_name, description, hourly_rate, max_capacity, is_available) 
                 VALUES (?, ?, ?, ?, ?, ?)";

$insert_stmt = $conn->prepare($insert_query);

if (!$insert_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$insert_stmt->bind_param('issdi', $branch_id, $amenity_name, $description, $hourly_rate, $max_capacity, $is_available);

if ($insert_stmt->execute()) {
    $amenity_id = $insert_stmt->insert_id;
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Amenity added successfully',
        'amenity_id' => $amenity_id,
        'data' => [
            'amenity_id' => $amenity_id,
            'amenity_name' => $amenity_name,
            'description' => $description,
            'branch_id' => $branch_id,
            'hourly_rate' => $hourly_rate,
            'max_capacity' => $max_capacity,
            'is_available' => $is_available
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add amenity: ' . $insert_stmt->error]);
}

$insert_stmt->close();
$conn->close();
?>
