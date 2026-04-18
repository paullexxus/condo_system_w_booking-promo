<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'admin';

// Convert 'manager' role to 'host' for consistency
if ($user_role === 'manager') {
    $user_role = 'host';
}

$unread_notifs = 0;
$urgent_notifs = 0;
if (isset($_SESSION['user_id'])) {
    global $conn;
    if (isset($conn)) {
        $user_id = (int)$_SESSION['user_id'];
        $notif_res = $conn->query("
            SELECT 
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as total_unread,
                SUM(CASE WHEN is_read = 0 AND priority IN ('urgent', 'overdue') THEN 1 ELSE 0 END) as total_urgent
            FROM notifications 
            WHERE user_id = $user_id
        ");
        if ($notif_res && $row = $notif_res->fetch_assoc()) {
            $unread_notifs = (int)$row['total_unread'];
            $urgent_notifs = (int)$row['total_urgent'];
        }
    }
}

// Define which page should be active for each menu item
$menu_items = [
    'admin_dashboard.php' => 'Dashboard',
    'host_dashboard.php' => 'Dashboard',
    'manage_branch.php' => 'Branch Management',
    'user_management.php' => 'User Management',
    'unit_management.php' => 'Unit Management',
    'reservations.php' => 'Reservation Management',
    'reservation_management.php' => 'Reservation Management',
    'payment_management.php' => 'Payment Management',
    'amenity_management.php' => 'Amenity Management',
    'amenity_requests.php' => 'Amenity Requests',
    'reports.php' => 'Reports',
    'settings.php' => 'Settings'
];

// Function to check if menu item is active
function isActive($page, $current_page, $menu_items) {
    return $page === $current_page;
}

// Alternative method: check by page name pattern
function isActivePattern($menu_key, $current_page) {
    $admin_patterns = [
        'Dashboard' => ['admin_dashboard.php'],
        'Branch Management' => ['manage_branch.php', 'add_branch.php', 'edit_branch.php'],
        'User Management' => ['user_management.php', 'add_user.php', 'edit_user.php'],
        'Host Verifications' => ['host_verifications.php'],
        'Unit Management' => ['unit_management.php', 'add_unit.php', 'edit_unit.php', 'pending_units.php'],
        'Reservation Management' => ['reservations.php', 'reservation_details.php'],
        'Payment Management' => ['payment_management.php', 'payment_details.php'],
        'Amenity Management' => ['amenity_management.php', 'amenity_bookings.php', 'add_amenity.php', 'edit_amenity.php'],
        'Reports' => ['reports.php', 'report_generator.php'],
        'Notifications' => ['notifications.php'],
        'Settings' => ['settings.php', 'profile.php']
    ];

    $host_patterns = [
        'Dashboard' => ['host_dashboard.php'],
        'Unit Management' => ['unit_management.php', 'add_unit.php', 'edit_unit.php'],
        'Reservations' => ['reservations.php', 'reservation_details.php', 'reservation_calendar.php'],
        'Payments' => ['payment_management.php', 'payment_details.php'],
        'Amenities' => ['amenities.php', 'amenity_requests.php', 'amenity_details.php'],
        'Feedback' => ['reviews.php', 'feedback.php'],
        'Notifications' => ['notifications.php'],
        'Profile' => ['profile.php', 'settings.php']
    ];
    
    $patterns = ($GLOBALS['user_role'] === 'host' || $GLOBALS['user_role'] === 'manager') ? $host_patterns : $admin_patterns;
    
    return isset($patterns[$menu_key]) && in_array($current_page, $patterns[$menu_key]);
}
?>

<!-- =================== BUILT-IN SIDEBAR =================== -->
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-building"></i>
        <span><?php echo ($user_role === 'host' || $user_role === 'manager') ? 'BookIT Host' : 'BookIT Admin'; ?></span>
    </div>
    <nav class="sidebar-menu">
        <ul>
            <?php if ($user_role === 'host' || $user_role === 'manager'): ?>
                <!-- HOST/MANAGER MENU -->
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/host_dashboard.php" 
                       class="<?php echo isActivePattern('Dashboard', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> 
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/unit_management.php" 
                       class="<?php echo isActivePattern('Unit Management', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> 
                        <span>Unit Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/reservations.php" 
                       class="<?php echo isActivePattern('Reservations', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i> 
                        <span>Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/payment_management.php" 
                       class="<?php echo isActivePattern('Payments', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-credit-card"></i> 
                        <span>Payments</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/amenities.php" 
                       class="<?php echo isActivePattern('Amenities', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-star"></i> 
                        <span>Amenities</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/reviews.php" 
                       class="<?php echo isActivePattern('Feedback', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i> 
                        <span>Feedback & Reviews</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/messages.php" 
                       class="<?php echo $current_page === 'messages.php' || $current_page === 'automation_settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-inbox"></i> 
                        <span>Messages</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/notifications.php" 
                       class="<?php echo isActivePattern('Notifications', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i> 
                        <span>Notifications</span>
                        <?php if (isset($unread_notifs) && $unread_notifs > 0): ?>
                            <?php $badgeColor = (isset($urgent_notifs) && $urgent_notifs > 0) ? '#e74c3c' : '#3498db'; ?>
                            <span class="badge" style="margin-left: auto; background-color: <?php echo $badgeColor; ?>; color: white; padding: 2px 6px; font-size: 11px; border-radius: 10px;"><?php echo $unread_notifs; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/host/profile.php" 
                       class="<?php echo isActivePattern('Profile', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i> 
                        <span>Profile & Settings</span>
                    </a>
                </li>
            <?php else: ?>
                <!-- ADMIN MENU -->
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php" 
                       class="<?php echo isActivePattern('Dashboard', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> 
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/manage_branch.php" 
                       class="<?php echo isActivePattern('Branch Management', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-code-branch"></i>
                        <span>Branch Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/user_management.php" 
                       class="<?php echo isActivePattern('User Management', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> 
                        <span>User Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/host_verifications.php" 
                       class="<?php echo isActivePattern('Host Verifications', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-id-card-alt"></i> 
                        <span>Host Verifications</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/unit_management.php" 
                       class="<?php echo isActivePattern('Unit Management', $current_page) && $current_page !== 'pending_units.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> 
                        <span>Unit Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/pending_units.php" 
                       class="<?php echo $current_page === 'pending_units.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i> 
                        <span>Pending Units</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/modules/reservations.php" 
                       class="<?php echo isActivePattern('Reservation Management', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i> 
                        <span>Reservation Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/modules/payment_management.php" 
                       class="<?php echo isActivePattern('Payment Management', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-credit-card"></i> 
                        <span>Payment Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/modules/amenities.php" 
                       class="<?php echo isActivePattern('Amenity Management', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-swimming-pool"></i> 
                        <span>Amenity Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/bookings_promos.php" 
                       class="<?php echo $current_page === 'bookings_promos.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i> 
                        <span>Bookings & Promos</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/reports.php" 
                       class="<?php echo isActivePattern('Reports', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> 
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/communication_hub.php" 
                       class="<?php echo $current_page === 'communication_hub.php' ? 'active' : ''; ?>">
                        <i class="fas fa-satellite-dish"></i> 
                        <span>Comms Hub</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/notifications.php" 
                       class="<?php echo isActivePattern('Notifications', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i> 
                        <span>Notifications</span>
                        <?php if (isset($unread_notifs) && $unread_notifs > 0): ?>
                            <?php $badgeColor = (isset($urgent_notifs) && $urgent_notifs > 0) ? '#e74c3c' : '#3498db'; ?>
                            <span class="badge" style="margin-left: auto; background-color: <?php echo $badgeColor; ?>; color: white; padding: 2px 6px; font-size: 11px; border-radius: 10px;"><?php echo $unread_notifs; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/admin/settings.php" 
                       class="<?php echo isActivePattern('Settings', $current_page) ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> 
                        <span>Settings</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- =================== PROFILE SECTION =================== -->
    <div class="sidebar-profile">
        <div class="profile-info">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-details">
                <span class="profile-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Admin'); ?></span>
                <span class="profile-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Admin')); ?></span>
            </div>
        </div>
        <a href="<?php echo SITE_URL; ?>/public/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>

<?php if ($user_role === 'admin'): ?>
<!-- Global Admin Notification Escalation System -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Escalate only once every 5 minutes per session to prevent spamming AJAX
    const lastCheck = sessionStorage.getItem('lastEscalationCheck');
    const now = new Date().getTime();
    if (lastCheck && (now - parseInt(lastCheck)) < 5 * 60 * 1000) {
        return; 
    }
    sessionStorage.setItem('lastEscalationCheck', now.toString());

    fetch('<?php echo SITE_URL; ?>/ajax/admin_escalation.php')
        .then(r => r.json())
        .then(data => {
            if (data.error) return; // Unauthorized or other error

            if (data.urgent_count > 0 || data.overdue_count > 0) {
                // Try to inject banner into standard dashboard header if available
                const mainContent = document.querySelector('.page-header') || document.querySelector('.dashboard-header');
                if (mainContent && !document.getElementById('escalationBanner')) {
                    const banner = document.createElement('div');
                    banner.id = 'escalationBanner';
                    banner.style = "background: #ffeaa7; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 30px; border-radius: 5px; color: #d35400; font-weight: bold; display: flex; align-items: center; gap: 10px;";
                    banner.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ⚠️ You have (${data.urgent_count + data.overdue_count}) pending reviews older than 3 days. Please review immediately. <a href="<?php echo SITE_URL; ?>/admin/notifications.php" style="margin-left:auto; background: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;">Go to Notifications</a>`;
                    mainContent.parentNode.insertBefore(banner, mainContent.nextSibling);
                }
            }
            
            // Show popups
            if (data.new_popups && data.new_popups.length > 0) {
                let popupHtml = '<ul style="text-align:left; font-size:14px;">';
                data.new_popups.forEach(p => {
                    popupHtml += `<li><strong>${p.title}</strong>: ${p.message}</li>`;
                });
                popupHtml += '</ul>';
                
                Swal.fire({
                    title: '⚠️ Action Required',
                    html: popupHtml,
                    icon: 'warning',
                    confirmButtonText: 'View Notifications',
                    confirmButtonColor: '#e74c3c',
                    showCancelButton: true,
                    cancelButtonText: 'Later'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.location.href = '<?php echo SITE_URL; ?>/admin/notifications.php';
                    }
                });
            }
        })
        .catch(e => console.error("Escalation check failed", e));
});
</script>
<?php endif; ?>