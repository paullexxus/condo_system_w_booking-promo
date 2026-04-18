<?php
// admin/communication_hub.php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/encryption.php';

checkRole(['admin']);

// Fetch basic stats
$stats = get_single_result("
    SELECT 
        COUNT(message_id) as total_messages,
        SUM(CASE WHEN is_encrypted = 1 THEN 1 ELSE 0 END) as encrypted_count,
        SUM(CASE WHEN moderation_status = 'flagged' THEN 1 ELSE 0 END) as flagged_count
    FROM messages
");

// Fetch active templates count
$templates_count = get_single_result("SELECT COUNT(*) as cnt FROM notification_templates")['cnt'];

// Action: Moderate message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_action'])) {
    $msg_id = (int)$_POST['message_id'];
    $act = $_POST['moderate_action']; // 'clean' or 'blocked'
    if (in_array($act, ['clean', 'blocked', 'flagged'])) {
        execute_query("UPDATE messages SET moderation_status = ? WHERE message_id = ?", [$act, $msg_id]);
        
        // Log action
        execute_query("INSERT INTO message_logs (message_id, action) VALUES (?, ?)", [$msg_id, $act]);
        
        // Notify Host if blocked
        if ($act === 'blocked') {
            $msg_data = get_single_result("SELECT m.*, u1.role as sender_role, u2.role as receiver_role FROM messages m JOIN users u1 ON m.sender_id = u1.user_id JOIN users u2 ON m.receiver_id = u2.user_id WHERE m.message_id = ?", [$msg_id]);
            $host_id = null;
            if ($msg_data['sender_role'] == 'host') $host_id = $msg_data['sender_id'];
            elseif ($msg_data['receiver_role'] == 'host') $host_id = $msg_data['receiver_id'];
            
            if ($host_id) {
                sendNotification($host_id, 'Message Blocked', 'Admin has blocked a message in your conversation for violating terms.', 'system', 'system', null);
            }
        }
    }
    header("Location: communication_hub.php?status=ActionApplied");
    exit;
}

// Action: Mute/Unmute User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    $target_user = (int)$_POST['target_user'];
    $act = $_POST['user_action']; // 'mute' or 'unmute'
    if (in_array($act, ['mute', 'unmute'])) {
        $val = ($act == 'mute') ? 0 : 1;
        execute_query("UPDATE users SET can_message = ? WHERE user_id = ?", [$val, $target_user]);
    }
    header("Location: communication_hub.php?status=UserActionApplied");
    exit;
}

// Action: Trigger Automation
if (isset($_GET['run_automation'])) {
    // Run the engine manually
    $output = file_get_contents(SITE_URL . "/cron/automation_engine.php?type=html");
    $_SESSION['automation_output'] = $output;
    header("Location: communication_hub.php?status=EngineRun");
    exit;
}

// Pagination & Filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : 'all';
$filter_sql = "";
$queryParams = [];
if ($role_filter != 'all') {
    $filter_sql = " WHERE u1.role = ? OR u2.role = ? ";
    $queryParams = [$role_filter, $role_filter];
}

$total_msgs = get_single_result("SELECT COUNT(*) as cnt FROM messages m JOIN users u1 ON m.sender_id = u1.user_id JOIN users u2 ON m.receiver_id = u2.user_id $filter_sql", $queryParams)['cnt'];
$total_pages = ceil($total_msgs / $limit);

$query_params_bound = $queryParams;
$messages = get_multiple_results("
    SELECT m.*, u1.full_name as sender_name, u2.full_name as receiver_name, 
           u1.role as sender_role, u2.role as receiver_role, 
           u1.can_message as sender_can_message, u2.can_message as receiver_can_message
    FROM messages m
    JOIN users u1 ON m.sender_id = u1.user_id
    JOIN users u2 ON m.receiver_id = u2.user_id
    $filter_sql
    ORDER BY m.sent_at DESC
    LIMIT $limit OFFSET $offset
", $query_params_bound);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Hub - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <style>
        .stat-card { border-radius: 12px; padding: 20px; color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .bg-gradient-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-blue { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); }
        .bg-gradient-orange { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .table-responsive { max-height: 600px; overflow-y: auto; }
        thead th { position: sticky; top: 0; background: #fff; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        
        @media (min-width: 769px) {
            .main-content { margin-left: 230px; width: calc(100% - 230px); }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body class="bg-light">

<div class="d-flex">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-4">
        
        <?php if(isset($_GET['status']) && $_GET['status'] == 'ActionApplied'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Moderation action applied successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['status']) && $_GET['status'] == 'EngineRun'): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <strong>Automation Engine Output:</strong> <br>
                <div class="p-3 bg-white border mt-2 rounded">
                    <?php echo $_SESSION['automation_output'] ?? 'No output received.'; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['automation_output']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-satellite-dish text-primary me-2"></i>Communication Hub</h2>
                <p class="text-muted mb-0">System Overview, Moderation, and Engagement Automation.</p>
            </div>
            <div>
                <a href="?run_automation=1" class="btn btn-warning shadow-sm"><i class="fas fa-bolt me-1"></i> Run Automation Engine Manually</a>
            </div>
        </div>

        <!-- System Overview Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card bg-gradient-purple">
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1">Total Messages</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['total_messages'] ?? 0); ?></h2>
                    <small class="text-white-50"><i class="fas fa-lock me-1"></i> <?php echo number_format($stats['encrypted_count'] ?? 0); ?> Encrypted</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-gradient-orange">
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1">Flagged / Reported</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['flagged_count'] ?? 0); ?></h2>
                    <small class="text-white-50">Requires moderation</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-gradient-blue">
                    <h6 class="text-white-50 text-uppercase fw-bold mb-1">Active Templates</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($templates_count); ?></h2>
                    <small class="text-white-50">Host triggers active</small>
                </div>
            </div>
        </div>

        <!-- Master Decryption List -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold"><i class="fas fa-shield-alt text-success me-2"></i>Global Conversation Moderation (Decrypted)</h5>
                    <small class="text-muted d-block mt-1">Admin override access activated. You are viewing decrypted E2E messages for moderation purposes.</small>
                </div>
                <div>
                    <form method="GET" class="d-flex gap-2">
                        <select name="role_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Conversations</option>
                            <option value="host" <?php echo $role_filter == 'host' ? 'selected' : ''; ?>>Host Conversations</option>
                            <option value="renter" <?php echo $role_filter == 'renter' ? 'selected' : ''; ?>>Renter Conversations</option>
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">Timestamp</th>
                                <th class="px-4">Sender</th>
                                <th class="px-4">Receiver</th>
                                <th class="px-4" style="width: 40%">Decrypted Message</th>
                                <th class="px-4 text-center">Status</th>
                                <th class="px-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($messages)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">No messages found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($messages as $msg): 
                                    $decryptedText = decrypt_message($msg['message']);
                                    $is_flagged = $msg['moderation_status'] == 'flagged';
                                    $is_blocked = $msg['moderation_status'] == 'blocked';
                                ?>
                                    <tr class="<?php echo $is_flagged ? 'table-warning' : ($is_blocked ? 'table-danger text-muted' : ''); ?>">
                                        <td class="px-4 text-nowrap"><small><?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?></small></td>
                                        <td class="px-4">
                                            <strong><?php echo htmlspecialchars($msg['sender_name']); ?></strong><br>
                                            <span class="badge bg-secondary mb-1"><?php echo ucfirst($msg['sender_role']); ?></span>
                                            <!-- Mute Toggle -->
                                            <form method="POST" class="d-inline-block">
                                                <input type="hidden" name="target_user" value="<?php echo $msg['sender_id']; ?>">
                                                <?php if($msg['sender_can_message'] == 1): ?>
                                                    <button type="submit" name="user_action" value="mute" class="btn btn-sm text-danger p-0" title="Block User from Messaging"><i class="fas fa-volume-mute"></i></button>
                                                <?php else: ?>
                                                    <button type="submit" name="user_action" value="unmute" class="btn btn-sm text-success p-0" title="Unblock User"><i class="fas fa-volume-up"></i> Muted</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        <td class="px-4">
                                            <strong><?php echo htmlspecialchars($msg['receiver_name']); ?></strong><br>
                                            <span class="badge bg-light text-dark border"><?php echo ucfirst($msg['receiver_role']); ?></span>
                                        </td>
                                        
                                        <?php
                                            // Dynamic alignment based on role
                                            $alignClass = 'text-start';
                                            $bgClass = 'bg-light';
                                            if ($msg['sender_role'] == 'host') { $alignClass = 'text-end'; $bgClass = 'bg-info bg-opacity-10'; }
                                            if ($msg['sender_role'] == 'admin') { $alignClass = 'text-center'; $bgClass = 'bg-success bg-opacity-10'; }
                                        ?>
                                        <td class="px-4 py-3 <?php echo $alignClass; ?> <?php echo $bgClass; ?>">
                                            <?php if ($is_blocked): ?>
                                                <div class="badge bg-danger mb-1"><i class="fas fa-ban"></i> [Blocked by Admin]</div><br>
                                            <?php endif; ?>
                                            <div class="d-inline-block p-2 rounded <?php echo $is_blocked ? 'text-decoration-line-through text-muted' : ''; ?>" style="max-width: 80%; background-color: rgba(255,255,255,0.7); border:1px solid #dee2e6;">
                                                <?php echo htmlspecialchars($decryptedText); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 text-center">
                                            <?php 
                                            echo match($msg['moderation_status']) {
                                                'clean' => '<span class="badge bg-success">Clean</span>',
                                                'flagged' => '<span class="badge bg-warning text-dark"><i class="fas fa-flag"></i> Flagged</span>',
                                                'blocked' => '<span class="badge bg-danger"><i class="fas fa-ban"></i> Blocked</span>',
                                                default => '<span class="badge bg-secondary">Unknown</span>'
                                            };
                                            ?>
                                        </td>
                                        <td class="px-4 text-end">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                <?php if (!$is_blocked): ?>
                                                    <button type="submit" name="moderate_action" value="blocked" class="btn btn-sm btn-outline-danger" title="Block Message"><i class="fas fa-ban"></i></button>
                                                <?php endif; ?>
                                                <?php if ($msg['moderation_status'] != 'clean'): ?>
                                                    <button type="submit" name="moderate_action" value="clean" class="btn btn-sm btn-outline-success" title="Mark as Clean"><i class="fas fa-check"></i></button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="p-3 border-top d-flex justify-content-center">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&role_filter=<?php echo $role_filter; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
