<?php
// AJAX endpoint: check_duplicate.php
// Expects POST: building_name, street_address, unit_number, city, latitude, longitude, host_id, image_paths (JSON array)

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/DuplicateDetectionEngine.php';

try {
    $post = $_POST;

    $unit_data = [];
    $unit_data['building_name'] = isset($post['building_name']) ? trim($post['building_name']) : '';
    $unit_data['street_address'] = isset($post['street_address']) ? trim($post['street_address']) : '';
    $unit_data['unit_number'] = isset($post['unit_number']) ? trim($post['unit_number']) : '';
    $unit_data['city'] = isset($post['city']) ? trim($post['city']) : '';
    $unit_data['latitude'] = isset($post['latitude']) ? (float)$post['latitude'] : null;
    $unit_data['longitude'] = isset($post['longitude']) ? (float)$post['longitude'] : null;
    $unit_data['host_id'] = isset($post['host_id']) ? (int)$post['host_id'] : null;

    // image_paths can be sent as JSON string or multiple form fields
    $image_paths = [];
    if (!empty($post['image_paths'])) {
        $decoded = json_decode($post['image_paths'], true);
        if (is_array($decoded)) {
            $image_paths = $decoded;
        } else {
            // try to interpret as comma-separated
            $image_paths = array_map('trim', explode(',', $post['image_paths']));
        }
    }
    $unit_data['image_paths'] = $image_paths;

    // instantiate engine
    $engine = new DuplicateDetectionEngine($conn);
    $analysis = $engine->analyzeUnitForDuplicates(0, $unit_data);

    echo json_encode([
        'status' => 'ok',
        'analysis' => $analysis
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

?>