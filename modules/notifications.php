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

    <!-- Main Content Container -->
    <div class="max-w-3xl mx-auto px-4 pt-10 pb-24">
        
        <!-- Page Header Section -->
        <div class="flex flex-col md:flex-row md:items-end justify-between border-b border-gray-100 pb-6 mb-6">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight mb-2">Notifications</h1>
                <p class="text-gray-500 font-medium text-lg">Stay updated with your bookings and reservations.</p>
            </div>
            
            <?php if ($unreadCount['count'] > 0): ?>
            <div class="mt-4 md:mt-0">
                <form method="POST">
                    <button type="submit" name="mark_all_read" class="text-sm font-bold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 py-2 px-4 rounded-xl transition-colors">
                        <i class="fas fa-check-double mr-1"></i> Mark all as read
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Notification Tabs (Future-ready) -->
        <div class="mb-8 overflow-x-auto hide-scrollbar">
            <ul class="flex whitespace-nowrap gap-8 border-b border-gray-200">
                <li>
                    <a href="#" class="inline-block pb-4 text-blue-600 border-b-2 border-blue-600 font-bold text-base px-1">All</a>
                </li>
                <li>
                    <a href="#" class="inline-block pb-4 text-gray-500 hover:text-gray-900 font-semibold text-base px-1 transition-colors">Bookings</a>
                </li>
                <li>
                    <a href="#" class="inline-block pb-4 text-gray-500 hover:text-gray-900 font-semibold text-base px-1 transition-colors">Payments</a>
                </li>
                <li>
                    <a href="#" class="inline-block pb-4 text-gray-500 hover:text-gray-900 font-semibold text-base px-1 transition-colors">Messages</a>
                </li>
            </ul>
        </div>

        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 shadow-sm flex items-center justify-between">
                <span class="font-medium"><i class="fas fa-check-circle mr-2"></i> <?php echo $message; ?></span>
                <button type="button" onclick="this.parentElement.style.display='none'" class="text-green-500 hover:text-green-700"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 shadow-sm flex items-center justify-between">
                <span class="font-medium"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?></span>
                <button type="button" onclick="this.parentElement.style.display='none'" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- Content Area -->
        <?php if (mysqli_num_rows($notifications) > 0): ?>
            <div class="bg-white rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 overflow-hidden">
                <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
                    <!-- Notification Item -->
                    <div class="p-6 md:p-8 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors flex gap-5 <?php echo !$notification['is_read'] ? 'bg-blue-50/40' : ''; ?>">
                        
                        <!-- Small icon avatar -->
                        <div class="shrink-0 w-14 h-14 rounded-full flex items-center justify-center text-2xl <?php 
                            $types = [
                                'booking' => 'bg-green-100 text-green-600', 
                                'payment' => 'bg-blue-100 text-blue-600', 
                                'reminder' => 'bg-orange-100 text-orange-600', 
                                'system' => 'bg-gray-100 text-gray-600'
                            ];
                            echo $types[$notification['type']] ?? 'bg-blue-100 text-blue-600';
                        ?>">
                            <?php 
                            $icons = [
                                'booking' => 'fas fa-calendar-check', 
                                'payment' => 'fas fa-credit-card', 
                                'reminder' => 'fas fa-clock', 
                                'system' => 'fas fa-cog'
                            ];
                            echo '<i class="' . ($icons[$notification['type']] ?? 'fas fa-bell') . '"></i>';
                            ?>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-grow min-w-0">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-2 gap-1 sm:gap-4">
                                <h4 class="font-bold text-gray-900 text-lg leading-tight">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h4>
                                <!-- Small timestamp on the right -->
                                <span class="shrink-0 text-sm font-semibold text-gray-400 whitespace-nowrap hidden sm:block">
                                    <?php echo date('M j, g:i a', strtotime($notification['created_at'])); ?>
                                </span>
                            </div>
                            
                            <!-- Notification message text -->
                            <p class="text-gray-600 text-base leading-relaxed mb-4">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>
                            
                            <?php if (!empty($notification['admin_message'])): ?>
                            <div class="bg-gray-50 border-l-4 <?php echo (isset($notification['status']) && $notification['status'] == 'approved') ? 'border-green-500' : 'border-red-500'; ?> p-4 rounded-r-lg mb-4">
                                <h5 class="text-sm font-bold text-gray-700 mb-1">Admin Note:</h5>
                                <p class="text-gray-600 italic text-sm">"<?php echo htmlspecialchars($notification['admin_message']); ?>"</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($notification['status']) && $notification['status'] == 'rejected'): ?>
                            <div class="mb-4">
                                <a href="../renter/my_application.php" class="inline-flex items-center gap-2 bg-orange-100 text-orange-700 font-bold py-2.5 px-5 rounded-xl hover:bg-orange-200 transition-colors shadow-sm text-sm">
                                    <i class="fas fa-edit"></i> Edit & Resubmit
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <?php if (!$notification['is_read']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                        <button type="submit" name="mark_read" class="text-sm font-bold text-blue-600 hover:text-blue-800">
                                            Mark as read
                                        </button>
                                    </form>
                                    <!-- Mobile dots indicator for unread -->
                                    <span class="inline-block w-2.5 h-2.5 bg-blue-600 rounded-full sm:hidden"></span>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                        <button type="submit" name="delete_notification" class="text-sm font-bold text-gray-400 hover:text-red-500 transition-colors" onclick="return confirm('Delete this notification?')">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                                
                                <span class="shrink-0 text-xs font-semibold text-gray-400 whitespace-nowrap sm:hidden">
                                    <?php echo date('M j, g:i a', strtotime($notification['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div class="mt-8 text-center">
                <a href="../public/index.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-blue-600 font-semibold transition-colors">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>

        <?php else: ?>
            <!-- Empty State Card -->
            <div class="bg-white rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 p-12 md:p-20 flex flex-col items-center justify-center text-center mt-4">
                
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-bell-slash text-4xl text-gray-300"></i>
                </div>
                
                <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">No notifications yet</h3>
                <p class="text-gray-500 max-w-sm mb-10 text-lg leading-relaxed">
                    Your application updates will appear here once reviewed by the admin.
                </p>
                
                <?php if ($_SESSION['role'] == 'renter'): ?>
                <a href="../public/browse_units.php" class="py-4 px-8 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-[0_8px_20px_rgb(37,99,235,0.25)] transition-all transform hover:-translate-y-0.5 inline-block mb-6 w-full sm:w-auto">
                    Browse Listings / Make Reservation
                </a>
                <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php" class="py-4 px-8 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-[0_8px_20px_rgb(37,99,235,0.25)] transition-all transform hover:-translate-y-0.5 inline-block mb-6 w-full sm:w-auto">
                    Go to Dashboard
                </a>
                <?php endif; ?>
                
                <a href="../public/index.php" class="text-gray-500 hover:text-blue-600 font-bold transition-colors">
                    Back to Home
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="..assets/js/modules/notifications.js"></script>
</body>
</html>