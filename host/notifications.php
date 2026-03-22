<?php
// Host Notifications
// View booking alerts, payment updates, and maintenance reminders

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle notification actions via form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        if ($action === 'mark_read' && isset($_POST['notification_id'])) {
            $notif_id = (int)sanitize_input($_POST['notification_id']);
            $conn->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $notif_id AND user_id = $host_id");
            $action_message = "Marked as read";
            $action_success = true;
        } else if ($action === 'mark_all_read') {
            $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $host_id");
            $action_message = "All notifications marked as read";
            $action_success = true;
        } else if ($action === 'delete' && isset($_POST['notification_id'])) {
            $notif_id = (int)sanitize_input($_POST['notification_id']);
            $conn->query("DELETE FROM notifications WHERE notification_id = $notif_id AND user_id = $host_id");
            $action_message = "Notification deleted";
            $action_success = true;
        }
    }
}

// Get all notifications for this host
$notifications = get_multiple_results("
    SELECT * FROM notifications 
    WHERE user_id = $host_id
    ORDER BY created_at DESC
    LIMIT 50
");

// Get notification counts
$unread_count = $conn->query(
    "SELECT COUNT(*) as cnt FROM notifications 
     WHERE user_id = $host_id AND is_read = 0"
)->fetch_assoc()['cnt'];

// Group notifications by type
$booking_notifications = [];
$payment_notifications = [];
$maintenance_notifications = [];
$system_notifications = [];

foreach ($notifications as $notif) {
    if (strpos(strtolower($notif['type']), 'booking') !== false) {
        $booking_notifications[] = $notif;
    } elseif (strpos(strtolower($notif['type']), 'payment') !== false) {
        $payment_notifications[] = $notif;
    } elseif (strpos(strtolower($notif['type']), 'maintenance') !== false) {
        $maintenance_notifications[] = $notif;
    } else {
        $system_notifications[] = $notif;
    }
}

$page_title = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | BookIT Host</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin-common.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content { padding: 30px; }
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .btn-header {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .btn-header:hover {
            background: #f5f5f5;
        }
        
        .notifications-container {
            max-width: 800px;
        }
        
        .notification-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notification-item {
            background: white;
            border-left: 4px solid #3498db;
            padding: 16px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .notification-item.unread {
            background: #f0f8ff;
            border-left-color: #dc3545;
        }
        
        .notification-item.booking {
            border-left-color: #3498db;
        }
        
        .notification-item.payment {
            border-left-color: #27ae60;
        }
        
        .notification-item.maintenance {
            border-left-color: #f39c12;
        }
        
        .notification-item.system {
            border-left-color: #95a5a6;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        
        .notification-message {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .notification-time {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        
        .notification-actions {
            display: flex;
            gap: 8px;
            margin-left: 10px;
        }
        
        .notification-actions button {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 14px;
            padding: 4px 8px;
            transition: color 0.3s;
        }
        
        .notification-actions button:hover {
            color: #2c3e50;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            background: white;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .icon-booking { color: #3498db; }
        .icon-payment { color: #27ae60; }
        .icon-maintenance { color: #f39c12; }
        .icon-system { color: #95a5a6; }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                <div class="header-actions">
                    <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo $unread_count; ?> Unread</span>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn-header" onclick="return confirm('Mark all as read?')">Mark all as read</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="notifications-container">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No notifications yet</p>
                    </div>
                <?php else: ?>
                    <!-- Booking Notifications -->
                    <?php if (!empty($booking_notifications)): ?>
                    <div class="notification-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-check icon-booking"></i>
                            Booking Alerts (<?php echo count($booking_notifications); ?>)
                        </div>
                        <?php foreach ($booking_notifications as $notif): ?>
                        <div class="notification-item booking <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo round($diff / 60) . ' minutes ago';
                                    } elseif ($diff < 86400) {
                                        echo round($diff / 3600) . ' hours ago';
                                    } else {
                                        echo date('M d, Y', $time);
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="notification-actions">
                                <?php if (!$notif['is_read']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Mark as read" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Delete" onclick="return confirm('Delete this notification?')" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Payment Notifications -->
                    <?php if (!empty($payment_notifications)): ?>
                    <div class="notification-section">
                        <div class="section-title">
                            <i class="fas fa-credit-card icon-payment"></i>
                            Payment Updates (<?php echo count($payment_notifications); ?>)
                        </div>
                        <?php foreach ($payment_notifications as $notif): ?>
                        <div class="notification-item payment <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo round($diff / 60) . ' minutes ago';
                                    } elseif ($diff < 86400) {
                                        echo round($diff / 3600) . ' hours ago';
                                    } else {
                                        echo date('M d, Y', $time);
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="notification-actions">
                                <?php if (!$notif['is_read']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Mark as read" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Delete" onclick="return confirm('Delete this notification?')" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Maintenance Notifications -->
                    <?php if (!empty($maintenance_notifications)): ?>
                    <div class="notification-section">
                        <div class="section-title">
                            <i class="fas fa-tools icon-maintenance"></i>
                            Maintenance Reminders (<?php echo count($maintenance_notifications); ?>)
                        </div>
                        <?php foreach ($maintenance_notifications as $notif): ?>
                        <div class="notification-item maintenance <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo round($diff / 60) . ' minutes ago';
                                    } elseif ($diff < 86400) {
                                        echo round($diff / 3600) . ' hours ago';
                                    } else {
                                        echo date('M d, Y', $time);
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="notification-actions">
                                <?php if (!$notif['is_read']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Mark as read" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Delete" onclick="return confirm('Delete this notification?')" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- System Notifications -->
                    <?php if (!empty($system_notifications)): ?>
                    <div class="notification-section">
                        <div class="section-title">
                            <i class="fas fa-info-circle icon-system"></i>
                            System Notifications (<?php echo count($system_notifications); ?>)
                        </div>
                        <?php foreach ($system_notifications as $notif): ?>
                        <div class="notification-item system <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo round($diff / 60) . ' minutes ago';
                                    } elseif ($diff < 86400) {
                                        echo round($diff / 3600) . ' hours ago';
                                    } else {
                                        echo date('M d, Y', $time);
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="notification-actions">
                                <?php if (!$notif['is_read']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Mark as read" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                    <button type="submit" title="Delete" onclick="return confirm('Delete this notification?')" style="background:none;border:none;color:#999;cursor:pointer;padding:4px 8px;transition:color 0.3s;" onmouseover="this.style.color='#2c3e50'" onmouseout="this.style.color='#999'">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
