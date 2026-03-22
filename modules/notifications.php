<?php
// BookIT Notification System
// Multi-branch Condo Rental Reservation System

include '../includes/session.php';
include '../includes/functions.php';

// All logged-in users can access their notifications
// (No specific role required - each user sees only their own notifications)

$message = '';
$error = '';

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_read'])) {
        $notificationId = $_POST['notification_id'];
        $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
        if (execute_query($sql, [$notificationId, $_SESSION['user_id']])) {
            $message = "Notification marked as read.";
        }
    }
    
    if (isset($_POST['mark_all_read'])) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        if (execute_query($sql, [$_SESSION['user_id']])) {
            $message = "All notifications marked as read.";
        }
    }
    
    if (isset($_POST['delete_notification'])) {
        $notificationId = $_POST['notification_id'];
        $sql = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
        if (execute_query($sql, [$notificationId, $_SESSION['user_id']])) {
            $message = "Notification deleted.";
        }
    }
}

// Kumuha ng user notifications
$notifications = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY created_at DESC LIMIT 50");

// Kumuha ng unread count
$unreadCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 0"));

// Function para gumawa ng Gmail URL
function createGmailUrl($subject, $message, $recipient = '') {
    $gmail_url = "https://mail.google.com/mail/?view=cm&fs=1" .
                 "&su=" . urlencode($subject) .
                 "&body=" . urlencode($message);
    
    if (!empty($recipient)) {
        $gmail_url .= "&to=" . urlencode($recipient);
    }
    
    return $gmail_url;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .shadow-soft {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card-modern {
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: white;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-luxury-primary {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 10px 20px rgba(243, 156, 18, 0.2);
        }

        .notification-unread {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.05), rgba(243, 156, 18, 0.05));
            border-left: 4px solid #f39c12;
        }
    </style>
</head>
</head>
<body class="bg-gray-50">
    <!-- Premium Navigation -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-soft">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="../public/index.php" class="flex items-center gap-3 text-2xl font-bold text-gray-900 hover:opacity-80 smooth-transition">
                    <img src="../assets/images/logo/bookit.png" alt="BookIT Logo" class="w-14 h-14 rounded-xl object-cover">
                    BookIT
                </a>

                <div class="hidden md:flex items-center gap-10">
                    <a href="../public/index.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Home</a>
                    <a href="../public/browse_units.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Browse</a>
                    <?php if (in_array($_SESSION['role'], ['renter'])): ?>
                        <a href="../renter/my_bookings.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Bookings</a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 smooth-transition">
                            <i class="fas fa-user-circle text-2xl"></i>
                            <span class="hidden sm:inline text-sm font-500"><?php echo htmlspecialchars(substr($_SESSION['fullname'], 0, 15)); ?></span>
                        </button>
                        <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                            <a href="notifications.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 first:rounded-t-lg smooth-transition border-b font-600 text-blue-600">
                                <i class="fas fa-bell mr-2"></i> Notifications
                            </a>
                            <?php if ($_SESSION['role'] == 'renter'): ?>
                                <a href="../renter/my_bookings.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 smooth-transition border-b">
                                    <i class="fas fa-calendar-check mr-2"></i> My Bookings
                                </a>
                            <?php endif; ?>
                            <a href="../renter/profile.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 smooth-transition border-b">
                                <i class="fas fa-cog mr-2"></i> Settings
                            </a>
                            <hr class="my-2">
                            <a href="../public/logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 last:rounded-b-lg smooth-transition font-500">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-12">
                </div>
            </div>
        </nav>

        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unreadCount['count'] > 0): ?>
                        <span class="badge bg-danger"><?php echo $unreadCount['count']; ?></span>
                    <?php endif; ?>
                </h2>
                
                <div class="d-flex gap-2">
                    <?php if ($unreadCount['count'] > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="../public/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Notifications List -->
            <?php if (mysqli_num_rows($notifications) > 0): ?>
                <div class="row">
                    <div class="col-md-8">
                        <?php while ($notification = mysqli_fetch_assoc($notifications)): 
                            $gmail_url = createGmailUrl(
                                $notification['title'],
                                $notification['message'],
                                $_SESSION['email']
                            );
                        ?>
                            <div class="card notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <div class="notification-icon icon-<?php echo $notification['type']; ?> me-3">
                                            <?php
                                            $icons = [
                                                'booking' => 'fas fa-calendar-check',
                                                'payment' => 'fas fa-credit-card',
                                                'reminder' => 'fas fa-clock',
                                                'system' => 'fas fa-cog',
                                                'alert' => 'fas fa-exclamation-triangle'
                                            ];
                                            $icon = $icons[$notification['type']] ?? 'fas fa-bell';
                                            echo '<i class="' . $icon . '"></i>';
                                            ?>
                                        </div>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="card-title mb-1">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="notification-badge" title="Unread">!</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if (!$notification['is_read']): ?>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                                    <button type="submit" name="mark_read" class="dropdown-item">
                                                                        <i class="fas fa-check"></i> Mark as Read
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a href="<?php echo $gmail_url; ?>" target="_blank" class="dropdown-item">
                                                                <i class="fab fa-google"></i> Open in Gmail
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" class="d-inline" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                                <button type="submit" name="delete_notification" class="dropdown-item text-danger">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <p class="card-text"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            
                                            <div class="action-buttons">
                                                <a href="<?php echo $gmail_url; ?>" 
                                                   target="_blank" 
                                                   class="btn gmail-btn btn-sm"
                                                   onclick="markAsReadOnGmail(<?php echo $notification['notification_id']; ?>)">
                                                    <i class="fab fa-google"></i> Open in Gmail
                                                </a>
                                                
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="copyToClipboard('<?php echo addslashes($notification['message']); ?>')">
                                                    <i class="fas fa-copy"></i> Copy Message
                                                </button>
                                                
                                                <?php if (!$notification['is_read']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                        <button type="submit" name="mark_read" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Mark Read
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> <?php echo formatDate($notification['created_at']); ?>
                                                </small>
                                                <small class="text-muted">
                                                    <i class="fas fa-paper-plane"></i> <?php echo ucfirst($notification['sent_via']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Notification Stats & Quick Actions -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie"></i> Notification Stats</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $stats = mysqli_query($conn, "
                                    SELECT type, COUNT(*) as count 
                                    FROM notifications 
                                    WHERE user_id = " . $_SESSION['user_id'] . " 
                                    GROUP BY type
                                ");
                                ?>
                                
                                <?php while ($stat = mysqli_fetch_assoc($stats)): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>
                                            <?php
                                            $icon = $icons[$stat['type']] ?? 'fas fa-bell';
                                            echo '<i class="' . $icon . ' me-1"></i>';
                                            echo ucfirst($stat['type']);
                                            ?>
                                        </span>
                                        <span class="badge bg-primary"><?php echo $stat['count']; ?></span>
                                    </div>
                                <?php endwhile; ?>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><strong>Total Notifications:</strong></span>
                                    <span class="badge bg-success"><?php echo mysqli_num_rows($notifications); ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><strong>Unread:</strong></span>
                                    <span class="badge bg-danger"><?php echo $unreadCount['count']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6><i class="fas fa-bolt"></i> Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($_SESSION['role'] == 'renter'): ?>
                                    <a href="../renter/reserve_unit.php" class="btn btn-primary btn-sm w-100 mb-2">
                                        <i class="fas fa-plus"></i> New Reservation
                                    </a>
                                    <a href="../renter/my_bookings.php" class="btn btn-info btn-sm w-100 mb-2">
                                        <i class="fas fa-calendar-check"></i> View Bookings
                                    </a>
                                    <a href="../renter/profile.php" class="btn btn-success btn-sm w-100">
                                        <i class="fas fa-user"></i> My Profile
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php" class="btn btn-primary btn-sm w-100 mb-2">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/admin/manage_branch.php" class="btn btn-success btn-sm w-100">
                                        <i class="fas fa-building"></i> Manage Branches
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Gmail Quick Access -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6><i class="fab fa-google"></i> Gmail Quick Access</h6>
                            </div>
                            <div class="card-body">
                                <a href="https://mail.google.com/mail/u/0/#inbox" target="_blank" class="btn btn-danger btn-sm w-100 mb-2">
                                    <i class="fab fa-google"></i> Open Gmail Inbox
                                </a>
                                <a href="https://mail.google.com/mail/u/0/#compose" target="_blank" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="fas fa-pen"></i> Compose New Email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <h5>No Notifications</h5>
                    <p class="text-muted">You don't have any notifications yet.</p>
                    <div class="mt-3">
                        <?php if ($_SESSION['role'] == 'renter'): ?>
                            <a href="../renter/reserve_unit.php" class="btn btn-primary me-2">
                                <i class="fas fa-plus"></i> Make Reservation
                            </a>
                        <?php endif; ?>
                        <a href="../public/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="..assets/js/modules/notifications.js"></script>
</body>
</html>