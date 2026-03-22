<?php
// AJAX endpoint: get_host_location.php
// Returns JSON: { status: 'ok', latitude: x, longitude: y, host_name: 'Name' }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/db.php';

try {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }

    // Try to get latitude/longitude from users table if columns exist
    $row = get_single_result('SELECT * FROM users WHERE user_id = ?', [$user_id]);
    if ($row) {
        $lat = isset($row['latitude']) ? $row['latitude'] : null;
        $lng = isset($row['longitude']) ? $row['longitude'] : null;
        $name = isset($row['fullname']) ? $row['fullname'] : ($row['user_name'] ?? '');

        // If lat/lng present return
        if (!empty($lat) && !empty($lng)) {
            echo json_encode(['status' => 'ok', 'latitude' => (float)$lat, 'longitude' => (float)$lng, 'host_name' => $name]);
            exit;
        }
    }

    // Fallback: check host_verification table for coordinates
    $hv = get_single_result('SELECT * FROM host_verification WHERE host_id = ? LIMIT 1', [$user_id]);
    if ($hv) {
        $lat = $hv['last_known_latitude'] ?? ($hv['latitude'] ?? null);
        $lng = $hv['last_known_longitude'] ?? ($hv['longitude'] ?? null);
        $name = $_SESSION['fullname'] ?? '';
        if (!empty($lat) && !empty($lng)) {
            echo json_encode(['status' => 'ok', 'latitude' => (float)$lat, 'longitude' => (float)$lng, 'host_name' => $name]);
            exit;
        }
    }

    // Nothing found
    echo json_encode(['status' => 'ok', 'latitude' => null, 'longitude' => null, 'host_name' => $_SESSION['fullname'] ?? '']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>