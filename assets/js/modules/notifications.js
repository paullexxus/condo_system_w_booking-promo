// BookIT Notifications JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Notification interactions
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            // Mark as read when clicked
            const notificationId = this.dataset.notificationId;
            if (notificationId && !this.classList.contains('read')) {
                markAsRead(notificationId);
            }
        });
    });
    
    // Mark all as read functionality
    const markAllReadBtn = document.querySelector('button[name="mark_all_read"]');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                item.classList.add('read');
            });
        });
    }
    
    // Delete notification functionality
    const deleteButtons = document.querySelectorAll('button[name="delete_notification"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this notification?')) {
                this.closest('form').submit();
            }
        });
    });
    
    // Auto-refresh notifications (optional)
    // setInterval(() => {
    //     location.reload();
    // }, 60000); // Refresh every minute
    
    // Notification animations
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach((notification, index) => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(-20px)';
        notification.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, index * 100);
    });
    
    // Filter notifications by type
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filterType = this.dataset.filter;
            const notifications = document.querySelectorAll('.notification-item');
            
            notifications.forEach(notification => {
                if (filterType === 'all' || notification.dataset.type === filterType) {
                    notification.style.display = 'block';
                } else {
                    notification.style.display = 'none';
                }
            });
            
            // Update active filter button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Real-time notification count update
    function updateNotificationCount() {
        const unreadCount = document.querySelectorAll('.notification-item:not(.read)').length;
        const countBadge = document.querySelector('.notification-count');
        if (countBadge) {
            countBadge.textContent = unreadCount;
            countBadge.style.display = unreadCount > 0 ? 'inline' : 'none';
        }
    }
    
    updateNotificationCount();
});

function markAsRead(notificationId) {
    // Send AJAX request to mark as read
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `mark_read=1&notification_id=${notificationId}`
    })
    .then(response => response.text())
    .then(data => {
        // Update UI
        const notification = document.querySelector(`[data-notification-id="${notificationId}"]`);
        if (notification) {
            notification.classList.add('read');
            updateNotificationCount();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}