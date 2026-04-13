<?php
// ajax/automation_api.php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !in_array($_SESSION['role'], ['host', 'manager', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$host_id = $_SESSION['user_id'];

if ($action === 'get_templates') {
    $sql = "SELECT * FROM notification_templates WHERE host_id = ? ORDER BY template_id DESC";
    $templates = get_multiple_results($sql, [$host_id]);
    echo json_encode(['success' => true, 'templates' => $templates]);
    exit;
}

if ($action === 'save_template') {
    $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $title = sanitize_input($_POST['title'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');
    $category = sanitize_input($_POST['category'] ?? 'house_rules');
    $trigger_event = sanitize_input($_POST['trigger_event'] ?? 'manual_send');

    if (empty($title) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Title and Message are required.']);
        exit;
    }

    if ($template_id > 0) {
        $sql = "UPDATE notification_templates SET title = ?, message = ?, category = ?, trigger_event = ? WHERE template_id = ? AND host_id = ?";
        $params = [$title, $message, $category, $trigger_event, $template_id, $host_id];
    } else {
        $sql = "INSERT INTO notification_templates (title, message, category, trigger_event, host_id) VALUES (?, ?, ?, ?, ?)";
        $params = [$title, $message, $category, $trigger_event, $host_id];
    }

    if (execute_query($sql, $params)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

if ($action === 'delete_template') {
    $template_id = (int)($_POST['template_id'] ?? 0);
    
    if (execute_query("DELETE FROM notification_templates WHERE template_id = ? AND host_id = ?", [$template_id, $host_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete template.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
