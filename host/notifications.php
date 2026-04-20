<?php
// Host Notifications
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'mark_read' && isset($_POST['id'])) {
        execute_query("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", [(int) $_POST['id'], $host_id]);
    } else if ($action === 'mark_all_read') {
        execute_query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$host_id]);
    } else if ($action === 'delete' && isset($_POST['id'])) {
        execute_query("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?", [(int) $_POST['id'], $host_id]);
    }
}

$notifications = get_multiple_results("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100", [$host_id]);
$unread_count = get_single_result("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$host_id])['cnt'];

$page_title = 'Notifications & Alerts';
include '../templates/host_layout_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-bell me-2 text-primary"></i>Activity Center</h1>
        <p class="text-muted">Stay updated with bookings, payments, and system alerts</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <?php if ($unread_count > 0): ?>
            <form method="POST"><input type="hidden" name="action" value="mark_all_read"><button
                    class="btn btn-outline-secondary btn-sm rounded-pill px-3">Mark All Read</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-8">
        <?php if (empty($notifications)): ?>
            <div class="card-modern text-center py-5 border-0 shadow-sm">
                <div class="py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted opacity-25 mb-4"></i>
                    <h5 class="fw-bold text-dark">No notifications yet</h5>
                    <p class="text-muted small">We'll alert you when something important happens in your properties.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($notifications as $n):
                    $icon = 'fa-info-circle';
                    $bg_class = 'bg-blue-500';
                    $text_class = 'text-primary';

                    if (stripos($n['type'], 'booking') !== false) {
                        $icon = 'fa-calendar-check';
                        $bg_class = 'bg-blue-500';
                        $text_class = 'text-primary';
                    } elseif (stripos($n['type'], 'payment') !== false) {
                        $icon = 'fa-credit-card';
                        $bg_class = 'bg-emerald-500';
                        $text_class = 'text-success';
                    } elseif (stripos($n['type'], 'system') !== false) {
                        $icon = 'fa-cog';
                        $bg_class = 'bg-slate-500';
                        $text_class = 'text-muted';
                    } elseif (stripos($n['type'], 'maintenance') !== false) {
                        $icon = 'fa-tools';
                        $bg_class = 'bg-amber-500';
                        $text_class = 'text-warning';
                    }
                    ?>
                    <div
                        class="card-modern border-0 shadow-sm rounded-4 <?php echo !$n['is_read'] ? 'border-start border-primary border-4' : 'opacity-75'; ?> hover-lift transition">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex gap-3">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary"
                                    style="width: 50px; height: 50px; display: grid; place-items: center;">
                                    <i class="fas <?php echo $icon; ?> fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold <?php echo !$n['is_read'] ? 'text-primary' : 'text-dark'; ?>">
                                        <?php echo htmlspecialchars($n['title']); ?></h6>
                                    <p class="small mb-2 text-muted"><?php echo htmlspecialchars($n['message']); ?></p>
                                    <div class="d-flex align-items-center gap-3">
                                        <small class="text-muted"><i
                                                class="far fa-clock me-1 opacity-50"></i><?php echo time_ago($n['created_at']); ?></small>
                                        <?php if (!$n['is_read']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?php echo $n['notification_id']; ?>">
                                                <button class="btn btn-link p-0 text-primary fw-bold text-decoration-none"
                                                    style="font-size: 0.7rem;">Dismiss <i
                                                        class="fas fa-check-circle ms-1"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown"><i
                                        class="fas fa-ellipsis-h opacity-50"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                    <li>
                                        <form method="POST"><input type="hidden" name="action" value="delete"><input
                                                type="hidden" name="id" value="<?php echo $n['notification_id']; ?>"><button
                                                class="dropdown-item text-danger small py-2"><i
                                                    class="fas fa-trash-alt me-2"></i>Remove</button></form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card-modern border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-4"><i class="fas fa-chart-pie text-primary me-2"></i>Activity Summary</h6>
            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                <span class="small text-muted fw-bold">Unread Messages</span>
                <span class="badge bg-primary rounded-pill px-3"><?php echo $unread_count; ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small text-muted fw-bold">Total History</span>
                <span class="badge bg-light text-dark rounded-pill px-3"><?php echo count($notifications); ?></span>
            </div>
            <hr class="my-4 opacity-10">
            <button class="btn btn-primary w-100 rounded-pill shadow-sm" onclick="window.location.href='messaging.php'">
                <i class="fas fa-comments me-2"></i>Go to Inbox
            </button>
        </div>
    </div>
</div>
</div>
</div>

<?php include '../templates/host_layout_footer.php'; ?>