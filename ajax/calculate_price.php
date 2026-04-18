<?php
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$unit_id = (int) ($input['unit_id'] ?? 0);
$check_in = sanitize_input($input['check_in_date'] ?? '');
$check_out = sanitize_input($input['check_out_date'] ?? '');
$promo_code = strtoupper(trim($input['promo_code'] ?? ''));
$addon_ids = $input['addon_ids'] ?? [];

if ($unit_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$in_dt = DateTime::createFromFormat('Y-m-d', $check_in);
$out_dt = DateTime::createFromFormat('Y-m-d', $check_out);
if (!$in_dt || !$out_dt || $out_dt <= $in_dt) {
    echo json_encode(['success' => false, 'error' => 'Invalid dates']);
    exit;
}
$nights = (int) $out_dt->diff($in_dt)->days;

$unit = get_single_result("SELECT price_per_night, price_per_month, pricing_type, host_id FROM units WHERE unit_id = ?", [$unit_id]);
if (!$unit) {
    echo json_encode(['success' => false, 'error' => 'Unit not found']);
    exit;
}

// 1. Get Base Rate
$pricing_type = $unit['pricing_type'] ?? 'nightly';

if ($pricing_type === 'nightly' || $pricing_type === 'daily') {
    $base_rate = (float) ($unit['price_per_night'] ?? 0);
} else {
    $base_rate = (float) ($unit['price_per_month'] ?? 0) / 30;
}

// Hardcoded pricing overrides eliminated. Rely solely on dynamic computation.
$base_rate = max(0, $base_rate);

// 2. Fetch Active Pricing Rules
$rules = get_multiple_results(
    "SELECT rule_type, adjustment_type, adjustment_value, start_date, end_date
     FROM unit_pricing_rules
     WHERE unit_id = ? AND is_active = 1",
    [$unit_id]
);

function is_weekend(DateTime $d): bool
{
    $day = (int) $d->format('w');
    return $day === 0 || $day === 6;
}
function apply_adjustment(float $amount, string $type, float $value): float
{
    if ($type === 'fixed')
        return $amount + $value;
    return $amount + ($amount * ($value / 100.0));
}
function rule_applies(array $rule, DateTime $d): bool
{
    if (($rule['rule_type'] ?? '') === 'weekend')
        return is_weekend($d);
    if (($rule['rule_type'] ?? '') === 'date_range') {
        if (empty($rule['start_date']) || empty($rule['end_date']))
            return false;
        $s = DateTime::createFromFormat('Y-m-d', $rule['start_date']);
        $e = DateTime::createFromFormat('Y-m-d', $rule['end_date']);
        if (!$s || !$e)
            return false;
        return $d >= $s && $d <= $e;
    }
    return false;
}

// 3. Compute nightly rates with dynamic rules
$nightly_breakdown = [];
$subtotal = 0.0;
$cursor = clone $in_dt;
for ($i = 0; $i < $nights; $i++) {
    $current_date = $cursor->format('Y-m-d');
    $night_rate = $base_rate;
    $applied_rule = null;

    // Date-range rules take priority, then weekend rules.
    foreach ($rules as $rule) {
        if (($rule['rule_type'] ?? '') === 'date_range' && rule_applies($rule, $cursor)) {
            $night_rate = apply_adjustment($base_rate, (string) ($rule['adjustment_type'] ?? 'fixed'), (float) ($rule['adjustment_value'] ?? 0));
            $applied_rule = $rule;
            break;
        }
    }
    if ($applied_rule === null) {
        foreach ($rules as $rule) {
            if (($rule['rule_type'] ?? '') === 'weekend' && rule_applies($rule, $cursor)) {
                $night_rate = apply_adjustment($base_rate, (string) ($rule['adjustment_type'] ?? 'fixed'), (float) ($rule['adjustment_value'] ?? 0));
                $applied_rule = $rule;
                break;
            }
        }
    }

    $night_rate = max(0.0, $night_rate);
    $subtotal += $night_rate;
    $nightly_breakdown[] = [
        'date' => $current_date,
        'rate' => round($night_rate, 2),
        'base_rate' => round($base_rate, 2),
        'rule_type' => $applied_rule['rule_type'] ?? null,
        'adjustment_type' => $applied_rule['adjustment_type'] ?? null,
        'adjustment_value' => isset($applied_rule['adjustment_value']) ? (float) $applied_rule['adjustment_value'] : null
    ];

    $cursor->modify('+1 day');
}

// 4. Add Add-ons
$addons_total = 0.0;
$valid_addons = [];
if (!empty($addon_ids) && is_array($addon_ids)) {
    $ids = array_map('intval', $addon_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // We bind $unit_id then the IDs
    $params = array_merge([$unit_id], $ids);
    $selectedAddons = get_multiple_results("SELECT price, name FROM unit_addons WHERE unit_id = ? AND addon_id IN ($placeholders) AND is_active = 1", $params);
    foreach ($selectedAddons as $a) {
        $addons_total += (float) $a['price'];
        $valid_addons[] = ['name' => $a['name'], 'price' => (float) $a['price']];
    }
}

$pre_promo_total = $subtotal + $addons_total;

// 5. Promo code
$discount = 0.0;
$promo_error = null;
if ($promo_code !== '') {
    $promo = get_single_result("SELECT p.*, u.role as creator_role FROM promos p LEFT JOIN users u ON p.created_by = u.user_id WHERE p.code = ? AND p.is_active = 1 LIMIT 1", [$promo_code]);
    if (!$promo) {
        $promo_error = 'Invalid promo code';
    } else {
        if (isset($promo['creator_role']) && in_array($promo['creator_role'], ['host', 'manager'], true)) {
            if (!isset($unit['host_id']) || (int) $promo['created_by'] !== (int) $unit['host_id']) {
                $promo_error = 'Invalid promo code';
            }
        }
        if (!$promo_error && !empty($promo['expires_at']) && strtotime($promo['expires_at']) < time()) {
            $promo_error = 'Promo expired';
        }
        if (!$promo_error && !empty($promo['usage_limit']) && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
            $promo_error = 'Promo fully used';
        }

        if (!$promo_error) {
            $type = $promo['type'];
            $value = (float) $promo['value'];
            if ($type === 'percentage') {
                $discount = $pre_promo_total * ($value / 100.0);
            } else {
                $discount = $value;
            }
            $discount = min(max(0.0, $discount), $pre_promo_total);
        }
    }
}

$total = $pre_promo_total - $discount;

echo json_encode([
    'success' => true,
    'nights' => $nights,
    'subtotal' => $subtotal,
    'addons_total' => $addons_total,
    'discount' => $discount,
    'total' => $total,
    'promo_error' => $promo_error,
    'addons_details' => $valid_addons,
    'nightly_breakdown' => $nightly_breakdown
]);
