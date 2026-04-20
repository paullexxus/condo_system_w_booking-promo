<?php
/**
 * Hardened AJAX Handler: Process Unit Moderation Decision
 * Actions: approve, reject, flag
 * Features: Double Validation, CSRF Protection, Audit Logging, and Notifications.
 */
header('Content-Type: application/json');

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';

// 1. ELITE SECURITY: Role Protection
checkRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 2. CSRF & IDEMPOTENCY PROTECTION
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh.']);
    exit;
}

$idempotency_key = $_POST['idempotency_key'] ?? '';
if (!isIdempotent($idempotency_key, 'unit_moderation')) {
    echo json_encode(['success' => false, 'message' => 'Duplicate request detected. Action already processed.']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$unit_id = isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0;
$action = $_POST['action'] ?? ''; // approve, reject, flag
$reason = sanitize_input($_POST['reason'] ?? '');
$flag_details = sanitize_input($_POST['flag_details'] ?? '');
$flag_type = sanitize_input($_POST['flag_type'] ?? 'General');

if (!$unit_id || !in_array($action, ['approve', 'reject', 'flag'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required moderation data.']);
    exit;
}

try {
    $conn->begin_transaction();

    // Fetch initial state for audit & host notification
    $unit = get_single_result("SELECT unit_name, host_id, approval_status FROM units WHERE unit_id = ?", [$unit_id]);
    if (!$unit) throw new Exception("Unit not found");

    if ($action === 'approve' || $action === 'reject') {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        if ($action === 'reject' && empty($reason)) {
            throw new Exception("Rejection requires a mandatory reason.");
        }

        // 3. DOUBLE VALIDATION: Ensure status transition only from pending
        $stmt = $conn->prepare("
            UPDATE units 
            SET approval_status = ?, 
                rejection_reason = ?, 
                reviewed_at = NOW(),
                review_locked_by = NULL, 
                review_locked_at = NULL 
            WHERE unit_id = ? AND approval_status = 'pending'
        ");
        $stmt->bind_param("ssi", $new_status, $reason, $unit_id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Action failed. Unit may have already been moderated by another admin.");
        }

        // 4. AUDIT LOGGING
        logAudit($admin_id, 'unit_moderation', 'unit', $unit_id, "Decision: " . strtoupper($action) . ". Reason: $reason", $unit['approval_status'], $new_status);

        // 5. NOTIFICATIONS
        $notif_title = ($action === 'approve') ? "Unit Approved!" : "Unit Rejection Notice";
        $notif_msg = ($action === 'approve') 
            ? "Good news! Your unit '{$unit['unit_name']}' has been approved and is now live." 
            : "Your unit '{$unit['unit_name']}' was rejected. Reason: $reason";
        
        sendNotification($unit['host_id'], $notif_title, $notif_msg, 'unit', 'system');

    } else if ($action === 'flag') {
        if (empty($flag_details)) throw new Exception("Flagging requires prohibited content details.");

        // Insert into flags table (does not change approval_status)
        execute_query("INSERT INTO unit_flags (unit_id, flag_type, details, flagged_by) VALUES (?, ?, ?, ?)", 
            [$unit_id, $flag_type, $flag_details, $admin_id]);

        // Log the flag in audit
        logAudit($admin_id, 'unit_flagged', 'unit', $unit_id, "Flagged for: $flag_type. Details: $flag_details");
        
        // Lock remains active for further review or we can release it
        // User requirements imply "Takeover/Expiration" so we might release it 
        // after flagging if the admin is done "flagging" and moving on.
        execute_query("UPDATE units SET review_locked_by = NULL, review_locked_at = NULL WHERE unit_id = ?", [$unit_id]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Unit successfully " . ($action === 'flag' ? 'flagged' : $action . 'd') . "."]);

} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
