<?php
// admin/notifications.php
include '../includes/session.php';
include_once '../config/db.php';
include '../includes/functions.php';
checkRole(['admin']);

$user_id = $_SESSION['user_id'];

// Mark specific notification as read if requested
if (isset($_GET['read_id'])) {
    $read_id = (int) $_GET['read_id'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $read_id AND user_id = $user_id");
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");
    header("Location: notifications.php");
    exit;
}

// Determine active filter
$filter_param = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where_clauses = ["user_id = ?"];
$params = [$user_id];
$types = "i";

if ($filter_param !== 'archived') {
    $where_clauses[] = "is_archived = 0";
    if ($filter_param === 'urgent') {
        $where_clauses[] = "priority = 'urgent'";
    } elseif ($filter_param === 'overdue') {
        $where_clauses[] = "priority = 'overdue'";
    } elseif ($filter_param === 'pending') {
        $where_clauses[] = "(type = 'approval' OR title LIKE '%pending%')";
    } elseif ($filter_param === 'verification') {
        $where_clauses[] = "(title LIKE '%verification%' OR title LIKE '%rejected%')";
    } elseif ($filter_param === 'unread') {
        $where_clauses[] = "is_read = 0";
    }
} else {
    $where_clauses[] = "is_archived = 1";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch notifications logic
$query = "
    SELECT * FROM notifications 
    WHERE $where_sql 

    ORDER BY is_read ASC,
             CASE priority WHEN 'overdue' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END ASC,
             created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Notifications - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <style>
        .page-header { background: #fff; padding: 25px 30px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0; }
        .page-title { font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0; }
        .main-content { padding: 30px; margin-left: 230px; }
        
        .notification-card {
            background: #fff;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.2s ease;
        }
        
        .notification-card.unread {
            background: #f8fbff;
            border-left: 4px solid #3498db;
        }
        
        .notification-card.urgent {
            background: #fff9f9;
            border-left: 4px solid #e67e22;
        }
        
        .notification-card.overdue {
            background: #fff5f5;
            border-left: 4px solid #e74c3c;
        }

        .notif-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            background: #f1f5f9;
            color: #64748b;
        }

        .notif-icon.system { background: #e0f2fe; color: #0284c7; }
        .notif-icon.urgent { background: #fef08a; color: #a16207; }
        .notif-icon.overdue { background: #fee2e2; color: #b91c1c; }

        .notif-content { flex: 1; }
        .notif-title { font-weight: 600; color: #1e293b; margin-bottom: 5px; font-size: 16px; }
        .notif-message { color: #64748b; font-size: 14px; margin-bottom: 10px; }
        .notif-meta { font-size: 12px; color: #94a3b8; display: flex; gap: 15px; }

        .badge-priority-urgent { background: #f59e0b; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-priority-overdue { background: #e74c3c; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body class="bg-light">
<div class="d-flex w-100 min-vh-100">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content flex-grow-1">
        <div class="page-header mb-4 shadow-sm">
            <div>
                <h1 class="page-title"><i class="fas fa-bell me-2 text-primary"></i> Notifications Inbox</h1>
                <p class="text-muted mb-0 mt-1">Manage system alerts, approvals, and escalations.</p>
            </div>
            <form method="POST">
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                    <i class="fas fa-check-double me-2"></i> Mark All as Read
                </button>
            </form>
        </div>
        
        <!-- Filter Bar -->
        <div class="mb-4 d-flex gap-2 filter-bar">
            <a href="?filter=all" class="btn btn-sm btn-outline-secondary filter-btn" data-filter="all">All Active</a>
            <a href="?filter=unread" class="btn btn-sm btn-outline-primary filter-btn" data-filter="unread">Unread</a>
            <a href="?filter=urgent" class="btn btn-sm btn-outline-warning filter-btn" data-filter="urgent">Urgent</a>
            <a href="?filter=overdue" class="btn btn-sm btn-outline-danger filter-btn" data-filter="overdue">Overdue</a>
            <a href="?filter=pending" class="btn btn-sm btn-outline-info filter-btn" data-filter="pending">Pending Units</a>
            <a href="?filter=verification" class="btn btn-sm btn-outline-dark filter-btn" data-filter="verification">Verifications</a>
            <a href="?filter=archived" class="btn btn-sm btn-link text-muted ms-auto filter-btn" data-filter="archived"><i class="fas fa-archive"></i> Archived View</a>
        </div>

        <div class="notifications-list">
            <?php if (empty($notifs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3 opacity-25"></i>
                    <h4 class="text-muted">You're all caught up!</h4>
                    <p class="text-muted">You have no notifications.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifs as $n): 
                    $is_unread = $n['is_read'] == 0;
                    $classes = [];
                    if ($is_unread) $classes[] = 'unread';
                    if ($n['priority'] === 'urgent') $classes[] = 'urgent';
                    if ($n['priority'] === 'overdue') $classes[] = 'overdue';
                    
                    $icon_class = 'fa-bell text-muted';
                    $bg_class = 'system';
                    
                    if (strpos(strtolower($n['title']), 'pending') !== false || $n['type'] == 'approval') {
                        $icon_class = 'fa-clipboard-check text-primary';
                    }
                    if ($n['priority'] === 'urgent') {
                        $icon_class = 'fa-exclamation-circle text-warning';
                        $bg_class = 'urgent';
                    }
                    if ($n['priority'] === 'overdue') {
                        $icon_class = 'fa-exclamation-triangle text-danger';
                        $bg_class = 'overdue';
                    }
                ?>
                <div class="notification-card <?= implode(' ', $classes) ?>">
                    <div class="notif-icon <?= $bg_class ?>">
                        <i class="fas <?= $icon_class ?>"></i>
                    </div>
                    <div class="notif-content">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="notif-title">
                                    <?= htmlspecialchars($n['title']) ?>
                                    <?php if ($n['priority'] === 'urgent'): ?>
                                        <span class="badge-priority-urgent ms-2">Urgent (Day 3+)</span>
                                    <?php endif; ?>
                                    <?php if ($n['priority'] === 'overdue'): ?>
                                        <span class="badge-priority-overdue ms-2">Overdue (Day 7+)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notif-message border-start border-3 border-secondary ps-2 mb-2"><?= htmlspecialchars($n['message']) ?></div>
                            </div>
                            <div class="text-end">
                                <?php if ($n['redirect_url']): ?>
                                    <a href="<?= htmlspecialchars($n['redirect_url']) ?>" class="btn btn-sm btn-primary mt-1 shadow-sm">
                                        <i class="fas fa-eye me-1"></i> View Let's Go
                                    </a>
                                <?php endif; ?>
                                <?php if ($is_unread): ?>
                                    <br>
                                    <a href="?read_id=<?= $n['notification_id'] ?>" class="text-decoration-none text-muted small mt-2 d-inline-block">Mark read</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="notif-meta mt-2">
                            <span><i class="far fa-clock me-1"></i> <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></span>
                            <?php if (!$is_unread): ?>
                                <span class="text-success"><i class="fas fa-check me-1"></i> Read</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        let currFilter = urlParams.get('filter');
        
        // Use cached filter if no query sent
        if (!currFilter && window.localStorage) {
            const cachedFilter = localStorage.getItem('notifFilterPreference');
            if (cachedFilter && cachedFilter !== 'all') {
                window.location.href = '?filter=' + cachedFilter;
                return;
            }
            currFilter = 'all';
        }
        
        // Cache current selection
        if (currFilter && window.localStorage) {
            localStorage.setItem('notifFilterPreference', currFilter);
        }
        
        // Make the active button filled
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.dataset.filter === currFilter) {
                btn.classList.remove(/btn-outline-[a-z]+/);
                btn.classList.add(btn.className.replace('outline-', ''));
                btn.classList.add('active');
            }
        });
    });
</script>
</body>
</html>
