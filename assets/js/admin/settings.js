// settings.js - System Settings Page Functionality

document.addEventListener('DOMContentLoaded', function() {
    initializeLogoUpload();
    initializeBannerUpload();
    initializeColorPickers();
    initializeTabNavigation();
});

// Logo Upload Preview
function initializeLogoUpload() {
    const logoFile = document.getElementById('logoFile');
    const logoPreview = document.getElementById('logoPreview');
    
    if (logoFile) {
        logoFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    logoPreview.src = event.target.result;
                    // In production, upload the file to server here
                    uploadLogoToServer(file);
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

// Banner Upload Preview
function initializeBannerUpload() {
    const bannerFile = document.getElementById('bannerFile');
    const bannerPreview = document.getElementById('bannerPreview');
    
    if (bannerFile) {
        bannerFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    bannerPreview.src = event.target.result;
                    // In production, upload the file to server here
                    uploadBannerToServer(file);
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

// Color Picker Sync
function initializeColorPickers() {
    const colorPickers = document.querySelectorAll('.color-picker-group');
    
    colorPickers.forEach(group => {
        const colorInput = group.querySelector('input[type="color"]');
        const textInput = group.querySelector('input[type="text"]');
        
        if (colorInput && textInput) {
            // Sync color picker to text input
            colorInput.addEventListener('change', function() {
                textInput.value = this.value.toUpperCase();
                applyThemeColor(colorInput.name, this.value);
            });
            
            // Sync text input to color picker
            textInput.addEventListener('change', function() {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    colorInput.value = this.value;
                    applyThemeColor(colorInput.name, this.value);
                }
            });
        }
    });
}

// Apply Theme Colors
function applyThemeColor(colorName, colorValue) {
    // In production, this would update the CSS variables
    // and send the new colors to the server
    console.log(`Applying ${colorName}: ${colorValue}`);
    
    // Example: Update CSS variable
    document.documentElement.style.setProperty('--theme-color', colorValue);
}

// Tab Navigation
function initializeTabNavigation() {
    const navTabs = document.querySelectorAll('.nav-tab');
    
    navTabs.forEach(tab => {
        // Active state is already set by PHP, just add smooth transitions
        tab.addEventListener('click', function(e) {
            // Allow default navigation to handle tab switching
        });
    });
}

// Test Email Function
function testEmail() {
    const senderEmail = document.querySelector('input[name="sender_email"]').value;
    const smtpHost = document.querySelector('input[name="smtp_host"]').value;
    
    if (!senderEmail || !smtpHost) {
        alert('Please fill in sender email and SMTP host first');
        return;
    }
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;
    
    // In production, make AJAX call to test email
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Test email sent successfully!');
    }, 2000);
}

// Backup Database
function createBackup() {
    if (!confirm('Create a new backup? This may take a few moments.')) return;
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating backup...';
    btn.disabled = true;
    
    // In production, make AJAX call
    fetch('../api/admin/create_backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            alert('Backup created successfully!');
            location.reload();
        } else {
            alert('Error creating backup: ' + data.message);
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error: ' + error.message);
    });
}

// Export Data
function exportData(format) {
    const dataType = document.querySelector('select[name]')?.value || 'all';
    
    window.location.href = `../api/admin/export_data.php?format=${format}&type=${dataType}`;
}

// Clear Cache
function clearCache() {
    if (!confirm('This will clear all cached data. Continue?')) return;
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
    btn.disabled = true;
    
    fetch('../api/admin/clear_cache.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Cache cleared successfully!');
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error: ' + error.message);
    });
}

// Clear System Logs
function clearLogs() {
    if (!confirm('WARNING: This will permanently delete all system logs. This action cannot be undone. Continue?')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
    btn.disabled = true;
    
    fetch('../api/admin/clear_logs.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            alert('All logs have been cleared.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error: ' + error.message);
    });
}

// View Activity Logs
function viewActivityLogs() {
    window.open('../api/admin/activity_logs.php', 'ActivityLogs', 'width=1000,height=700');
}

// View System Logs
function viewSystemLogs() {
    window.open('../api/admin/system_logs.php', 'SystemLogs', 'width=1000,height=700');
}

// Upload Files to Server (Placeholder)
function uploadLogoToServer(file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', 'logo');
    
    // In production, implement actual file upload
    console.log('Uploading logo...', file);
}

function uploadBannerToServer(file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', 'banner');
    
    // In production, implement actual file upload
    console.log('Uploading banner...', file);
}

// Add Admin Account
function addAdminAccount() {
    const modal = new bootstrap.Modal(document.getElementById('addAdminModal'));
    modal.show();
}

// Edit Admin Account
function editAdminAccount(adminId) {
    // In production, populate modal with admin data and show edit form
    const modal = new bootstrap.Modal(document.getElementById('editAdminModal'));
    modal.show();
}

// Disable Admin Account
function disableAdminAccount(adminId) {
    if (!confirm('Are you sure you want to disable this admin account?')) return;
    
    fetch(`../api/admin/manage_admins.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=disable&admin_id=${adminId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Admin account disabled successfully');
            location.reload();
        }
    })
    .catch(error => alert('Error: ' + error.message));
}

// Edit Permissions for Role
function editRolePermissions(role) {
    // In production, show modal to edit permissions for this role
    const modal = new bootstrap.Modal(document.getElementById('editPermissionsModal'));
    
    // Pre-populate role name
    document.getElementById('roleNameDisplay').textContent = role;
    
    modal.show();
}

// Edit Email Template
function editEmailTemplate(templateType) {
    const modal = new bootstrap.Modal(document.getElementById('editTemplateModal'));
    
    // Fetch template content
    fetch(`../api/admin/get_email_template.php?type=${templateType}`)
    .then(response => response.json())
    .then(data => {
        document.getElementById('templateName').textContent = templateType;
        document.getElementById('templateContent').value = data.template;
        modal.show();
    });
}

// Restore from Backup
function restoreFromBackup() {
    const backupId = prompt('Enter backup ID to restore:');
    if (!backupId) return;
    
    if (!confirm('WARNING: Restoring from backup will overwrite current data. Continue?')) {
        return;
    }
    
    // In production, make AJAX call to restore
    fetch('../api/admin/restore_backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ backup_id: backupId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Backup restored successfully. System will reload...');
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(error => alert('Error: ' + error.message));
}

// View Backup History
function viewBackupHistory() {
    window.open('../api/admin/backup_history.php', 'BackupHistory', 'width=900,height=600');
}

// Add Branch (Redirect to manage_branch.php)
function addBranch() {
    window.location.href = 'manage_branch.php';
}

// Edit Branch
function editBranch(branchId) {
    window.location.href = `manage_branch.php?edit=${branchId}`;
}

// Delete Branch
function deleteBranch(branchId, branchName) {
    if (!confirm(`Delete branch "${branchName}"? This action cannot be undone.`)) return;
    
    fetch('../api/admin/delete_branch.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ branch_id: branchId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Branch deleted successfully');
            location.reload();
        }
    })
    .catch(error => alert('Error: ' + error.message));
}

// Add Custom Amenity
function addCustomAmenity() {
    const amenityName = prompt('Enter amenity name:');
    if (!amenityName) return;
    
    // In production, add to amenities list
    const amenityList = document.querySelector('.amenities-list');
    const newItem = document.createElement('div');
    newItem.className = 'amenity-item';
    newItem.innerHTML = `
        <div class="amenity-checkbox">
            <input type="checkbox" checked>
            <label>${amenityName}</label>
        </div>
        <input type="number" class="amenity-price" placeholder="Price" value="0">
    `;
    amenityList.appendChild(newItem);
    alert('Amenity added! Don\'t forget to save changes.');
}

// Reset to Default Theme
function resetToDefault() {
    if (!confirm('Reset all UI customization to default? This cannot be undone.')) return;
    
    fetch('../api/admin/reset_theme.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Theme reset to default');
            location.reload();
        }
    })
    .catch(error => alert('Error: ' + error.message));
}
