<?php
/**
 * Hardened AJAX Handler: Fetch Unit Data for Admin Moderation
 * Features: Concurrency Locking, Data Completeness Validation, and Snapshot Integrity.
 */
header('Content-Type: application/json');

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';

// 1. ELITE SECURITY: Role Protection
// Only admins can access moderation data
checkRole(['admin']);

$admin_id = $_SESSION['user_id'];
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

if (!$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid unit ID']);
    exit;
}

try {
    // 2. CONCURRENCY CONTROL: Review Lock & Takeover Logic
    $lock_check = get_single_result("
        SELECT u.review_locked_by, u.review_locked_at, a.full_name as admin_name 
        FROM units u 
        LEFT JOIN users a ON u.review_locked_by = a.user_id 
        WHERE u.unit_id = ?
    ", [$unit_id]);

    $is_locked = false;
    $lock_message = "";
    
    if ($lock_check && $lock_check['review_locked_by']) {
        $locked_at = strtotime($lock_check['review_locked_at']);
        $now = time();
        $is_expired = ($now - $locked_at) > (30 * 60); // 30 minutes threshold

        if ($lock_check['review_locked_by'] != $admin_id) {
            if (!$is_expired) {
                // Flash-lock is active by another admin
                echo json_encode([
                    'success' => false, 
                    'locked' => true, 
                    'locked_by' => $lock_check['admin_name'],
                    'message' => "This unit is currently being reviewed by {$lock_check['admin_name']}. Please try again later."
                ]);
                exit;
            } else {
                // Lock expired - allow takeover
                $lock_message = "Previous review session by {$lock_check['admin_name']} expired. You have taken over the review.";
            }
        }
    }

    // Acquire/Refresh Lock
    execute_query("UPDATE units SET review_locked_by = ?, review_locked_at = NOW() WHERE unit_id = ?", [$admin_id, $unit_id]);

    // 3. DATA FETCHING: Comprehensive Snapshot
    $unit = get_single_result("
        SELECT u.*, b.branch_name, h.full_name as host_name, h.email as host_email, h.phone as host_phone
        FROM units u
        JOIN branches b ON u.branch_id = b.branch_id
        JOIN users h ON u.host_id = h.user_id
        WHERE u.unit_id = ?
    ", [$unit_id]);

    if (!$unit) {
        throw new Exception("Unit not found");
    }

    // Amenities
    $amenity_rows = get_multiple_results("
        SELECT a.name 
        FROM unit_amenities ua 
        JOIN amenities a ON ua.amenity_id = a.id 
        WHERE ua.unit_id = ?
    ", [$unit_id]);
    $unit['amenities'] = array_column($amenity_rows, 'name');

    // Images
    $unit['images'] = get_multiple_results("SELECT image_id, image_path FROM unit_images WHERE unit_id = ?", [$unit_id]);

    // Flags
    $unit['flags'] = get_multiple_results("
        SELECT f.*, u.full_name as flagged_by_name 
        FROM unit_flags f 
        JOIN users u ON f.flagged_by = u.user_id 
        WHERE f.unit_id = ? 
        ORDER BY f.created_at DESC
    ", [$unit_id]);

    // 4. DATA COMPLETENESS VALIDATION (Fail-Safe Rules)
    $validation_errors = [];
    $price = ($unit['pricing_type'] === 'monthly') ? (float)$unit['price_per_month'] : (float)$unit['price_per_night'];
    
    if ($price <= 0) $validation_errors[] = "Missing or invalid price.";
    if (empty($unit['images'])) $validation_errors[] = "No images uploaded.";
    if ((int)$unit['max_occupancy'] <= 0) $validation_errors[] = "Invalid maximum capacity.";
    if (empty($unit['description'])) $validation_errors[] = "Missing unit description.";

    // Logic: Block approval if critical validation fails
    $can_approve = empty($validation_errors);

    echo json_encode([
        'success' => true,
        'unit' => $unit,
        'lock_message' => $lock_message,
        'can_approve' => $can_approve,
        'validation_errors' => $validation_errors,
        'timeline' => [
            'submitted_at' => $unit['created_at'],
            'review_started_at' => date('Y-m-d H:i:s'),
            'status' => $unit['approval_status']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
