<?php
// API: return booked date ranges for a unit
include_once dirname(__FILE__, 3) . '/includes/public_session.php';
include_once dirname(__FILE__, 3) . '/includes/functions.php';
header('Content-Type: application/json');

$unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;
if ($unit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing unit_id']);
    exit;
}

try {
    // Get reservations that block the unit (confirmed/checked_in)
    $rows = get_multiple_results(
        "SELECT check_in_date, check_out_date FROM reservations WHERE unit_id = ? AND status IN ('confirmed','checked_in')",
        [$unit_id]
    );

    $ranges = [];
    foreach ($rows as $r) {
        $ranges[] = ['start' => $r['check_in_date'], 'end' => $r['check_out_date']];
    }

    echo json_encode(['success' => true, 'ranges' => $ranges]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
