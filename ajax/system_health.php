<?php
// ajax/system_health.php
include '../includes/session.php';
include '../includes/functions.php';

checkRole(['admin']);

header('Content-Type: application/json');

$health = getSystemHealth();

// Additional metrics for elite dashboard
$stats = [
    'db_connection' => ($conn && !$conn->connect_error) ? 'OK' : 'FAIL',
    'failed_emails_24h' => 0,
    'unresolved_errors' => 0,
    'active_throttles' => 0,
    'last_errors' => [],
    'last_audit' => []
];

// Get unresolved critical errors
$error_res = get_multiple_results("SELECT message, severity, created_at FROM system_errors WHERE is_resolved = 0 ORDER BY created_at DESC LIMIT 5");
$stats['last_errors'] = $error_res;
$stats['unresolved_errors'] = count($get_multiple_results("SELECT error_id FROM system_errors WHERE is_resolved = 0"));

// Get failed emails (notifications with email_retries > 0 or expired retries)
$email_fails = get_single_result("SELECT COUNT(*) as count FROM notifications WHERE email_retries > 0 AND is_read = 0");
$stats['failed_emails_24h'] = (int)$email_fails['count'];

// Get active throttles
$throttles = get_single_result("SELECT COUNT(*) as count FROM request_throttles WHERE last_hit > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stats['active_throttles'] = (int)$throttles['count'];

// Get last 5 audit logs
$audit_res = get_multiple_results("SELECT action_type, details, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 5");
$stats['last_audit'] = $audit_res;

echo json_encode([
    'success' => true,
    'health' => $health,
    'metrics' => $stats
]);
?>
