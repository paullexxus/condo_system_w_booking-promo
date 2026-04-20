<?php
/**
 * AJAX Handler for Real-time Price Computation
 * Proxies to the authoritative BookIT_PricingEngine
 */
include_once __DIR__ . '/../includes/pricing_engine.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$unit_id = (int)($input['unit_id'] ?? 0);
$check_in = $input['check_in_date'] ?? '';
$check_out = $input['check_out_date'] ?? '';
$promo_code = trim($input['promo_code'] ?? '');
$addon_ids = isset($input['addon_ids']) ? array_map('intval', (array)$input['addon_ids']) : [];
$adults = (int)($input['adults'] ?? 1);
$children = (int)($input['children'] ?? 0);
$total_guests = $adults + $children;

if ($unit_id <= 0 || empty($check_in) || empty($check_out)) {
    echo json_encode(['success' => false, 'error' => 'Incomplete booking parameters']);
    exit;
}

// Call Authoritative Engine
$result = BookIT_PricingEngine::calculatePrice($unit_id, $check_in, $check_out, $total_guests, $addon_ids, $promo_code);

echo json_encode($result);
