// User Management JavaScript - Fixed Version
document.addEventListener("DOMContentLoaded", function() {
    // Initialize DataTable
    $('#usersTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "Search users:",
            lengthMenu: "Show _MENU_ users per page"
        },
        autoWidth: false,
        columnDefs: [
            { targets: '_all', defaultContent: '' }
        ]
    });

    // Initialize Charts
    initializeCharts();
});

// Initialize Charts
function initializeCharts() {
    // Roles Distribution Chart
    const rolesCtx = document.getElementById('rolesChart');
    if (rolesCtx) {
        new Chart(rolesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Admins', 'Hosts', 'Managers', 'Renters'],
                datasets: [{
                    data: [rolesData.admin, rolesData.host, rolesData.manager, rolesData.renter],
                    backgroundColor: [
                        '#e74c3c', // admin - red
                        '#f39c12', // host - orange
                        '#9b59b6', // manager - purple
                        '#3498db'  // renter - blue
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Active Users', 'Suspended Users'],
                datasets: [{
                    data: [statusData.active, statusData.inactive],
                    backgroundColor: [
                        '#27ae60', // active - green
                        '#e74c3c'  // suspended - red
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Toggle Branch Field based on Role Selection
function toggleBranchField(selectElement) {
    const branchField = document.getElementById('branchField');
    if (selectElement.value === 'host' || selectElement.value === 'manager') {
        branchField.style.display = 'block';
    } else {
        branchField.style.display = 'none';
    }
}

// View User Details - FIXED VERSION (reads from table cells)
async function viewUser(userId) {
    try {
        // Find the table row for this user
        const userIDText = `#U${userId.toString().padStart(4, '0')}`;
        const rows = document.querySelectorAll('#usersTable tbody tr');
        
        let targetRow = null;
        
        // Search through all rows to find the matching user
        for (const row of rows) {
            const firstCell = row.cells[0];
            if (firstCell && firstCell.textContent.trim() === userIDText) {
                targetRow = row;
                break;
            }
        }
        
        if (!targetRow) {
            throw new Error('User data not found in table');
        }
        
        const cells = targetRow.cells;
        
        // Extract data from table cells
        const userData = {
            user_id: userId,
            full_name: cells[1].querySelector('strong') ? 
                       cells[1].querySelector('strong').textContent.trim() : 
                       cells[1].textContent.trim(),
            email: cells[2].textContent.trim(),
            phone: cells[3].textContent.trim() || 'N/A',
            role: cells[4].querySelector('.badge') ? 
                  cells[4].querySelector('.badge').textContent.trim().toLowerCase() : 
                  'renter',
            branch_name: cells[5].textContent.trim() || 'N/A',
            is_active: cells[7].querySelector('.badge') ? 
                      cells[7].querySelector('.badge').textContent.trim() === 'Active' : 
                      false,
            created_at: cells[8].textContent.trim(),
        };
        
        // Get activity data from the activity cell
        const activityText = cells[6].textContent.trim();
        
        // Extract reservation/unit counts
        if (userData.role === 'renter') {
            const reservationMatch = activityText.match(/(\d+)\s*Reservations/);
            userData.total_reservations = reservationMatch ? parseInt(reservationMatch[1]) : 0;
            userData.completed_reservations = 0;
            userData.cancelled_reservations = 0;
        } else if (userData.role === 'host' || userData.role === 'manager') {
            const unitsMatch = activityText.match(/(\d+)\s*Units/);
            userData.total_units = unitsMatch ? parseInt(unitsMatch[1]) : 0;
            userData.total_bookings = 0;
            userData.total_revenue = 0;
        }
        
        showUserDetails(userData);
        
    } catch (error) {
        console.error('Error:', error);
        alert('Error loading user details: ' + error.message);
    }
}

// Show User Details in Modal
function showUserDetails(userData) {
    const detailsHtml = `
        <div class="user-details-grid">
            <div class="detail-section">
                <h6>Personal Information</h6>
                <div class="detail-item">
                    <span class="detail-label">Full Name:</span>
                    <span class="detail-value">${escapeHtml(userData.full_name)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${escapeHtml(userData.email)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">${escapeHtml(userData.phone || 'N/A')}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Role:</span>
                    <span class="detail-value">
                        <span class="badge ${getRoleBadgeClass(userData.role)}">
                            ${escapeHtml(userData.role)}
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Branch:</span>
                    <span class="detail-value">${escapeHtml(userData.branch_name || 'N/A')}</span>
                </div>
            </div>

            <div class="detail-section">
                <h6>Account Information</h6>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="badge ${userData.is_active ? 'badge-success' : 'badge-danger'}">
                            ${userData.is_active ? 'Active' : 'Suspended'}
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date Registered:</span>
                    <span class="detail-value">${formatDate(userData.created_at)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Last Login:</span>
                    <span class="detail-value">${userData.last_login ? formatDateTime(userData.last_login) : 'Never'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">User ID:</span>
                    <span class="detail-value">#U${userData.user_id.toString().padStart(4, '0')}</span>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h6>Activity Summary</h6>
            <div class="activity-stats">
                ${userData.role === 'renter' ? `
                    <div class="stat-item">
                        <span class="stat-number">${userData.total_reservations || 0}</span>
                        <span class="stat-label">Total Reservations</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${userData.completed_reservations || 0}</span>
                        <span class="stat-label">Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${userData.cancelled_reservations || 0}</span>
                        <span class="stat-label">Cancelled</span>
                    </div>
                ` : ['host', 'manager'].includes(userData.role) ? `
                    <div class="stat-item">
                        <span class="stat-number">${userData.total_units || 0}</span>
                        <span class="stat-label">Managed Units</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">₱${(userData.total_revenue || 0).toLocaleString()}</span>
                        <span class="stat-label">Total Revenue</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${userData.total_bookings || 0}</span>
                        <span class="stat-label">Branch Bookings</span>
                    </div>
                ` : `
                    <div class="stat-item">
                        <span class="stat-number">System</span>
                        <span class="stat-label">Administrator</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">Full</span>
                        <span class="stat-label">Access</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">All</span>
                        <span class="stat-label">Permissions</span>
                    </div>
                `}
            </div>
        </div>
    `;

    document.getElementById('viewUserDetails').innerHTML = detailsHtml;
    new bootstrap.Modal(document.getElementById('viewUserModal')).show();
}

// Edit User - fetch real data from API instead of scraping table
async function editUser(userId) {
    try {
        const apiUrl = (typeof SITE_URL !== 'undefined' && SITE_URL ? SITE_URL + '/' : '../') + 'includes/api/get_user_details.php?user_id=' + userId;
        const response = await fetch(apiUrl);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const userData = {
            user_id: data.user_id,
            full_name: data.full_name || '',
            email: data.email || '',
            phone: data.phone || '',
            role: (data.role || 'renter').toLowerCase(),
            branch_id: data.branch_id || null,
            is_active: data.is_active == 1,
        };

        showEditForm(userData);
    } catch (error) {
        console.error('Error:', error);
        alert('Error loading user data: ' + error.message);
    }
}

// Show Edit Form
function showEditForm(userData) {
    const formHtml = `
        <input type="hidden" name="user_id" value="${userData.user_id}">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="full_name" value="${escapeHtml(userData.full_name)}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" value="${escapeHtml(userData.email)}" required>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="${escapeHtml(userData.phone || '')}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Role *</label>
                    <select class="form-select" name="role" required onchange="toggleEditBranchField(this)">
                        <option value="admin" ${userData.role === 'admin' ? 'selected' : ''}>Admin</option>
                        <option value="host" ${userData.role === 'host' ? 'selected' : ''}>Host</option>
                        <option value="manager" ${userData.role === 'manager' ? 'selected' : ''}>Manager</option>
                        <option value="renter" ${userData.role === 'renter' ? 'selected' : ''}>Renter</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" ${userData.is_active ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${!userData.is_active ? 'selected' : ''}>Suspended</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="mb-3" id="editBranchField" style="${['host', 'manager'].includes(userData.role) ? 'display: block;' : 'display: none;'}">
            <label class="form-label">Branch Assignment</label>
            <select class="form-select" name="branch_id">
                <option value="">Select Branch</option>
                ${branches.map(branch => `
                    <option value="${branch.branch_id}" ${userData.branch_id == branch.branch_id ? 'selected' : ''}>
                        ${escapeHtml(branch.branch_name)}
                    </option>
                `).join('')}
            </select>
        </div>
    `;

    document.getElementById('edit_user_id').value = userData.user_id;
    document.getElementById('editUserForm').innerHTML = formHtml;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

// Toggle Branch Field in Edit Form
function toggleEditBranchField(selectElement) {
    const branchField = document.getElementById('editBranchField');
    const branchSelect = branchField ? branchField.querySelector('select[name="branch_id"]') : null;
    if (selectElement.value === 'host' || selectElement.value === 'manager') {
        branchField.style.display = 'block';
    } else {
        branchField.style.display = 'none';
        if (branchSelect) branchSelect.value = '';
    }
}

// NOTE: Admin quick-reset via the UI has been disabled for security reasons.
// The server-side default-password reset handler was removed. Use the OTP-based
// recovery flow (`forgot_password.php` -> `verify_otp.php` -> `reset_password.php`) instead.

// Suspend User - WORKING VERSION
function suspendUser(userId, userName) {
    if (confirm(`Are you sure you want to SUSPEND ${userName}?\n\nSuspended users cannot login to the system.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="suspend_user" value="true">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Activate User - WORKING VERSION
function activateUser(userId, userName) {
    if (confirm(`Are you sure you want to ACTIVATE ${userName}?\n\nActivated users will be able to login again.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="activate_user" value="true">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Confirm Delete
function confirmDelete(userId, userName) {
    if (confirm(`Permanently delete user ${userName}? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_user" value="true">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Export Users
function exportUsers(format) {
    if (format === 'pdf') {
        window.print();
    } else if (format === 'csv') {
        $('.buttons-csv').click();
    }
}

// Utility Functions
function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return unsafe.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    } catch (e) {
        return dateString;
    }
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateString;
    }
}

function getRoleBadgeClass(role) {
    switch (role) {
        case 'admin': return 'badge-danger';
        case 'host': return 'badge-warning';
        case 'manager': return 'badge-info';
        case 'renter': return 'badge-primary';
        default: return 'badge-secondary';
    }
}