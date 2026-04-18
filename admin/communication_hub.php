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
    $act = $_POST['moderate_action']; 
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
    } elseif ($act === 'remove') {
        execute_query("UPDATE messages SET deleted_by_admin = 1 WHERE message_id = ?", [$msg_id]);
        execute_query("INSERT INTO message_logs (message_id, action) VALUES (?, 'admin_removed')", [$msg_id]);
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

// Action: Manage Prohibited Words
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['word_action'])) {
    $act = $_POST['word_action'];
    if ($act === 'add') {
        $word = trim($_POST['new_word'] ?? '');
        $severity = in_array($_POST['severity'] ?? '', ['mild', 'severe']) ? $_POST['severity'] : 'severe';
        if (!empty($word)) {
            execute_query("INSERT INTO prohibited_words (word, severity) VALUES (?, ?)", [$word, $severity]);
        }
    } elseif ($act === 'delete') {
        $word_id = (int)($_POST['word_id'] ?? 0);
        execute_query("DELETE FROM prohibited_words WHERE word_id = ?", [$word_id]);
    }
    header("Location: communication_hub.php?status=WordListUpdated");
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

// Group messages by sender
$grouped_messages = [];
foreach ($messages as $msg) {
    $sid = $msg['sender_id'];
    if (!isset($grouped_messages[$sid])) {
        $grouped_messages[$sid] = [
            'sender_id' => $sid,
            'sender_name' => $msg['sender_name'],
            'sender_role' => $msg['sender_role'],
            'sender_can_message' => $msg['sender_can_message'],
            'has_flagged' => false,
            'has_blocked' => false,
            'messages' => []
        ];
    }
    if ($msg['moderation_status'] == 'flagged') $grouped_messages[$sid]['has_flagged'] = true;
    if ($msg['moderation_status'] == 'blocked') $grouped_messages[$sid]['has_blocked'] = true;
    $grouped_messages[$sid]['messages'][] = $msg;
}

// Fetch Prohibited Words
$prohibited_words_list = get_multiple_results("SELECT * FROM prohibited_words ORDER BY severity DESC, word ASC");
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

        <?php if(isset($_GET['status']) && $_GET['status'] == 'WordListUpdated'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Prohibited Word List updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-satellite-dish text-primary me-2"></i>Communication Hub
                    <?php if($stats['flagged_count'] > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.5em; vertical-align: middle;"><?php echo number_format($stats['flagged_count']); ?> New</span>
                    <?php endif; ?>
                </h2>
                <p class="text-muted mb-0">System Overview, Moderation, and Engagement Automation.</p>
            </div>
            <div>
                <button type="button" class="btn btn-dark shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#profanityModal"><i class="fas fa-ban me-1"></i> Manage Profanity List</button>
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
                            <?php if(empty($grouped_messages)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">No messages found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($grouped_messages as $sender_id => $group): ?>
                                    <tr data-bs-toggle="collapse" data-bs-target="#group-<?php echo $sender_id; ?>" style="cursor: pointer;" class="<?php echo $group['has_flagged'] ? 'table-warning' : ($group['has_blocked'] ? 'table-danger bg-opacity-25' : 'bg-white'); ?> border-bottom">
                                        <td colspan="4" class="px-4 py-3">
                                            <strong><i class="fas fa-chevron-down mr-2 text-muted"></i> <?php echo htmlspecialchars($group['sender_name']); ?></strong> 
                                            <span class="badge bg-secondary ms-1"><?php echo ucfirst($group['sender_role']); ?></span>
                                            <span class="ms-3 text-muted" style="font-size: 0.9em;">Total Messages: <?php echo count($group['messages']); ?></span>
                                            <?php if($group['has_flagged']): ?><span class="badge bg-warning text-dark ms-2"><i class="fas fa-flag"></i> Needs Moderation</span><?php endif; ?>
                                        </td>
                                        <td colspan="2" class="px-4 py-3 text-end">
                                            <form method="POST" class="d-inline-block m-0" onclick="event.stopPropagation();">
                                                <input type="hidden" name="target_user" value="<?php echo $group['sender_id']; ?>">
                                                <?php if($group['sender_can_message'] == 1): ?>
                                                    <button type="submit" name="user_action" value="mute" class="btn btn-sm btn-outline-danger" title="Block User from Messaging"><i class="fas fa-volume-mute"></i> Mute User</button>
                                                <?php else: ?>
                                                    <button type="submit" name="user_action" value="unmute" class="btn btn-sm btn-success" title="Unblock User"><i class="fas fa-volume-up"></i> Unmute User</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="p-0 border-0">
                                            <div class="collapse" id="group-<?php echo $sender_id; ?>">
                                                <table class="table table-sm m-0" style="table-layout: fixed; word-wrap: break-word;">
                                                    <tbody>
                                                        <?php foreach($group['messages'] as $msg): 
                                                            $decryptedText = decrypt_message($msg['message']);
                                                            $is_flagged = $msg['moderation_status'] == 'flagged';
                                                            $is_blocked = $msg['moderation_status'] == 'blocked';
                                                            $deleted_everyone = isset($msg['is_deleted_everyone']) ? $msg['is_deleted_everyone'] : 0;
                                                            $deleted_admin = isset($msg['deleted_by_admin']) ? $msg['deleted_by_admin'] : 0;
                                                        ?>
                                                            <tr class="<?php echo $is_flagged ? 'table-warning bg-opacity-10' : ($is_blocked ? 'table-danger bg-opacity-10 text-muted' : ($deleted_admin ? 'table-secondary opacity-50' : '')); ?>">
                                                                <td class="px-4 text-nowrap" style="width: 15%;"><small><?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?></small></td>
                                                                <td class="px-4" style="width: 15%;">
                                                                    <span class="text-muted" style="font-size: 0.85em;">To:</span><br>
                                                                    <strong><?php echo htmlspecialchars($msg['receiver_name']); ?></strong><br>
                                                                    <span class="badge bg-light text-dark border"><?php echo ucfirst($msg['receiver_role']); ?></span>
                                                                </td>
                                                                <td class="px-4 py-3" style="width: 45%;">
                                                                    <?php if ($is_blocked): ?>
                                                                        <div class="badge bg-danger mb-1"><i class="fas fa-ban"></i> [Blocked by Admin]</div><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($deleted_everyone): ?>
                                                                        <div class="badge bg-secondary mb-1"><i class="fas fa-trash"></i> [Deleted by User for Everyone]</div><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($deleted_admin): ?>
                                                                        <div class="badge bg-dark mb-1"><i class="fas fa-gavel"></i> [Removed by Admin]</div><br>
                                                                    <?php endif; ?>
                                                                    <div class="d-inline-block p-2 rounded <?php echo $is_blocked ? 'text-decoration-line-through text-muted' : ''; ?>" style="max-height: 150px; overflow-y: auto; width: 100%; background-color: rgba(255,255,255,0.7); border:1px solid #dee2e6;">
                                                                        <?php echo nl2br(htmlspecialchars($decryptedText)); ?>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 text-center" style="width: 10%;">
                                                                    <?php 
                                                                    echo match($msg['moderation_status']) {
                                                                        'clean' => '<span class="badge bg-success">Clean</span>',
                                                                        'flagged' => '<span class="badge bg-warning text-dark"><i class="fas fa-flag"></i> Flagged</span>',
                                                                        'blocked' => '<span class="badge bg-danger"><i class="fas fa-ban"></i> Blocked</span>',
                                                                        default => '<span class="badge bg-secondary">Unknown</span>'
                                                                    };
                                                                    ?>
                                                                </td>
                                                                <td class="px-4 text-end" style="width: 15%;">
                                                                    <form method="POST" class="d-inline m-0">
                                                                        <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                                        <?php if (!$is_blocked): ?>
                                                                            <button type="submit" name="moderate_action" value="blocked" class="btn btn-sm btn-outline-danger" title="Block Message"><i class="fas fa-ban"></i></button>
                                                                        <?php endif; ?>
                                                                        <?php if ($msg['moderation_status'] != 'clean'): ?>
                                                                            <button type="submit" name="moderate_action" value="clean" class="btn btn-sm btn-outline-success" title="Mark as Clean"><i class="fas fa-check"></i></button>
                                                                        <?php endif; ?>
                                                                        <?php if (!$deleted_admin): ?>
                                                                            <button type="submit" name="moderate_action" value="remove" class="btn btn-sm btn-outline-dark" title="Unilaterally Remove Message from Users"><i class="fas fa-trash-alt"></i></button>
                                                                        <?php endif; ?>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
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

<!-- Profanity Management Modal -->
<div class="modal fade" id="profanityModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title"><i class="fas fa-ban me-2"></i> Manage Prohibited Words</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Add New Word</h6>
                                <form method="POST">
                                    <input type="hidden" name="word_action" value="add">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Word / Phrase</label>
                                        <input type="text" name="new_word" class="form-control" required placeholder="e.g. damn">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Severity Rule</label>
                                        <select name="severity" class="form-select">
                                            <option value="mild">Mild (Masks word w/ asterisks)</option>
                                            <option value="severe">Severe (Blocks message completely)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i> Add to Filter</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm" style="height: 350px; overflow-y: auto;">
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle m-0">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-4">Word</th>
                                            <th>Severity</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($prohibited_words_list)): ?>
                                            <tr><td colspan="3" class="text-muted text-center py-4">No prohibited words configured.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($prohibited_words_list as $w): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold"><?php echo htmlspecialchars($w['word']); ?></td>
                                                    <td>
                                                        <?php if($w['severity'] == 'severe'): ?>
                                                            <span class="badge bg-danger">Severe</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Mild</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <form method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete this word from the filter?');">
                                                            <input type="hidden" name="word_action" value="delete">
                                                            <input type="hidden" name="word_id" value="<?php echo $w['word_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
