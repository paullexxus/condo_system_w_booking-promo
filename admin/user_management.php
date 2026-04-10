<?php
// users/users.php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/session.php';
include_once __DIR__ . '/../includes/functions.php';
checkRole(['admin']);

$message = '';
$error = '';
// Check for session message
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying
}

// ==================== LOAD USERS WITH COMPREHENSIVE DATA ====================
$users = [];
try {
    // Search and filter parameters
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $where = "WHERE 1=1";
    $params = [];
    
    if ($q !== '') {
        $where .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_term = "%$q%";
        array_push($params, $search_term, $search_term, $search_term);
    }
    
    if ($role_filter !== 'all') {
        $where .= " AND u.role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter !== 'all') {
        $where .= " AND u.is_active = ?";
        $params[] = ($status_filter === 'active') ? 1 : 0;
    }

    // Exclude users with pending host applications
    $where .= " AND u.user_id NOT IN (SELECT user_id FROM host_applications WHERE status = 'pending')";

    // FIXED SQL QUERY - removed problematic joins
    $sql = "SELECT u.*, 
                   b.branch_name,
                   COUNT(DISTINCT r.reservation_id) AS total_reservations,
                   COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.reservation_id END) AS completed_reservations,
                   COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.reservation_id END) AS cancelled_reservations,
                   COALESCE(SUM(CASE WHEN r.status IN ('completed', 'confirmed') THEN r.total_amount ELSE 0 END), 0) AS total_revenue
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.branch_id
            LEFT JOIN reservations r ON u.user_id = r.user_id
            $where
            GROUP BY u.user_id
            ORDER BY u.created_at DESC";

    if (!empty($params)) {
        $users = get_multiple_results($sql, $params);
    } else {
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
    }

    // Get units count separately for host
    $units_count = [];
    try {
        $units_sql = "SELECT host_id, COUNT(*) as unit_count FROM units GROUP BY host_id";
        $units_result = $conn->query($units_sql);
        if ($units_result) {
            while ($row = $units_result->fetch_assoc()) {
                $units_count[$row['host_id']] = $row['unit_count'];
            }
        }
    } catch (Exception $e) {
        // Ignore if units table doesn't have host_id column
    }

    // Add units count to users data
    foreach ($users as &$user) {
        if ($user['role'] === 'host') {
            $user['total_units'] = $units_count[$user['user_id']] ?? 0;
        } else {
            $user['total_units'] = 0;
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// ==================== ADD USER ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = sanitize_input($_POST['role']);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $status = sanitize_input($_POST['status'] ?? 'active');

    // Check if email already exists
    $check_sql = "SELECT user_id FROM users WHERE email = ?";
    $existing_user = get_single_result($check_sql, [$email]);
    
    if ($existing_user) {
        $error = "Email already exists!";
    } else {
        $sql = "INSERT INTO users (full_name, email, password, role, phone, branch_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $is_active = ($status === 'active') ? 1 : 0;
        
        if (execute_query($sql, [$full_name, $email, $password, $role, $phone, $branch_id, $is_active])) {
            $new_user_id = $conn->insert_id;
            
            // Sync to branches table if assigned as host or manager
            if ($branch_id && $role === 'host') {
                execute_query("UPDATE branches SET host_id = ? WHERE branch_id = ?", [$new_user_id, $branch_id]);
            } elseif ($branch_id && $role === 'manager') {
                execute_query("UPDATE branches SET manager_id = ? WHERE branch_id = ?", [$new_user_id, $branch_id]);
            }
            
            $_SESSION['message'] = "User added successfully!";
            header("Location: user_management.php");
            exit;
        } else {
            $error = "Failed to add user!";
        }
    }
}

// ==================== UPDATE USER ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $role = sanitize_input($_POST['role'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    // Only allow branch_id for hosts and managers; clear for admin/renter
    $branch_id = (in_array($role, ['host', 'manager']) && !empty($_POST['branch_id'])) ? (int)$_POST['branch_id'] : null;
    $status = sanitize_input($_POST['status'] ?? 'active');

    if ($user_id <= 0) {
        $error = "Invalid user. Please try again.";
    } elseif (empty($full_name) || empty($email)) {
        $error = "Full name and email are required.";
    } else {
        // Validate role
        $valid_roles = ['admin', 'host', 'renter', 'manager'];
        if (!in_array($role, $valid_roles, true)) {
            $error = "Invalid role selected.";
        } else {
            // Check if email already exists (excluding current user)
            $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            $existing_user = get_single_result($check_sql, [$email, $user_id]);
            
            if ($existing_user) {
                $error = "Email already exists!";
            } else {
                $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, phone = ?, branch_id = ?, is_active = ? WHERE user_id = ?";
                $is_active = ($status === 'active') ? 1 : 0;
                
                if (execute_query($sql, [$full_name, $email, $role, $phone, $branch_id, $is_active, $user_id])) {
                    // Verify at least one row was updated
                    $updated = get_single_result("SELECT user_id FROM users WHERE user_id = ? AND role = ?", [$user_id, $role]);
                    if ($updated) {
                        $_SESSION['message'] = "User updated successfully!";
                        header("Location: user_management.php");
                        exit;
                    }
                    $error = "Update may not have applied. Please refresh and try again.";
                } else {
                    $error = "Failed to update user!";
                }
            }
        }
    }
}

// ==================== DELETE USER ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    $sql = "UPDATE users SET is_active = 0 WHERE user_id = ?";
    if (execute_query($sql, [$user_id])) {
        $_SESSION['message'] = "User deactivated successfully!";
        header("Location: user_management.php");
        exit;
    } else {
        $error = "Failed to deactivate user!";
    }
}

// NOTE: Legacy quick-reset/backdoor password reset handlers were removed
// to enforce a single secure OTP-based password recovery flow.
// Admins can no longer set default passwords via this UI.
// Use the OTP-based flow (`forgot_password.php` -> `verify_otp.php` -> `reset_password.php`) for all account
// password recovery. Privileged accounts require verified & active email and reset attempts are logged.

// ==================== SUSPEND USER ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['suspend_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    $sql = "UPDATE users SET is_active = 0 WHERE user_id = ?";
    if (execute_query($sql, [$user_id])) {
        $_SESSION['message'] = "User suspended successfully!";
        header("Location: user_management.php");
        exit();
    } else {
        $error = "Failed to suspend user!";
    }
}

// ==================== ACTIVATE USER ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['activate_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    $sql = "UPDATE users SET is_active = 1 WHERE user_id = ?";
    if (execute_query($sql, [$user_id])) {
        $_SESSION['message'] = "User activated successfully!";
        header("Location: user_management.php");
        exit();
    } else {
        $error = "Failed to activate user!";
    }
}

// Get branches for host assignment
$branches = get_multiple_results("SELECT branch_id, branch_name FROM branches WHERE is_active = 1");

// Calculate statistics
$total_users = count($users);
$admin_count = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$host_count = count(array_filter($users, fn($u) => $u['role'] === 'host'));
$manager_count = count(array_filter($users, fn($u) => $u['role'] === 'manager'));
$renter_count = count(array_filter($users, fn($u) => $u['role'] === 'renter'));
$active_users = count(array_filter($users, fn($u) => $u['is_active'] == 1));
$inactive_users = $total_users - $active_users;

// Get last login data separately for users who have it
$last_login_data = [];
try {
    // Check if last_login column exists
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($check_column->num_rows > 0) {
        $login_sql = "SELECT user_id, last_login FROM users WHERE last_login IS NOT NULL";
        $login_result = $conn->query($login_sql);
        if ($login_result) {
            while ($row = $login_result->fetch_assoc()) {
                $last_login_data[$row['user_id']] = $row['last_login'];
            }
        }
    }
} catch (Exception $e) {
    // Ignore error if last_login column doesn't exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin/user_management.css" />
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container-fluid">
    <div class="row">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="content">
    <div class="page-header">
        <div class="page-title">
            <h1>User Management</h1>
            <p>Manage all users and their roles in the system</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn-refresh" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button type="button" class="btn-export" onclick="exportUsers('pdf')">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <button type="button" class="btn-export" onclick="exportUsers('csv')">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
            <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add User
            </button>
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

    <!-- Overview Section -->
    <div class="overview-section">
        <div class="summary-cards">
            <div class="card">
                <div class="card-icon"><i class="fas fa-users"></i></div>
                <div class="card-info">
                    <h3><?= $total_users ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-user-shield"></i></div>
                <div class="card-info">
                    <h3><?= $admin_count ?></h3>
                    <p>Admins</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-user-tie"></i></div>
                <div class="card-info">
                    <h3><?= $host_count ?></h3>
                    <p>Hosts</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-id-badge"></i></div>
                <div class="card-info">
                    <h3><?= $manager_count ?></h3>
                    <p>Managers</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-user"></i></div>
                <div class="card-info">
                    <h3><?= $renter_count ?></h3>
                    <p>Renters</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-user-check"></i></div>
                <div class="card-info">
                    <h3><?= $active_users ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-user-slash"></i></div>
                <div class="card-info">
                    <h3><?= $inactive_users ?></h3>
                    <p>Suspended Users</p>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h4>User Roles Distribution</h4>
                </div>
                <canvas id="rolesChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h4>Account Status</h4>
                </div>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Search & Filter Section -->
    <div class="filter-section">
        <form method="GET" class="search-form">
            <div class="search-group">
                <input type="text" name="q" placeholder="Search users by name, email or phone" 
                       value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>"
                       class="form-control">
            </div>
            
            <div class="filter-group">
                <select name="role" class="form-select">
                    <option value="all">All Roles</option>
                    <option value="admin" <?= (isset($_GET['role']) && $_GET['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                    <option value="host" <?= (isset($_GET['role']) && $_GET['role'] === 'host') ? 'selected' : '' ?>>Host</option>
                    <option value="manager" <?= (isset($_GET['role']) && $_GET['role'] === 'manager') ? 'selected' : '' ?>>Manager</option>
                    <option value="renter" <?= (isset($_GET['role']) && $_GET['role'] === 'renter') ? 'selected' : '' ?>>Renter</option>
                </select>
            </div>

            <div class="filter-group">
                <select name="status" class="form-select">
                    <option value="all">All Status</option>
                    <option value="active" <?= (isset($_GET['status']) && $_GET['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (isset($_GET['status']) && $_GET['status'] === 'inactive') ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>

            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="user_management.php" class="btn-clear">Clear Filters</a>
        </form>
    </div>

    <!-- Users Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">User Details</div>
            <div class="table-info">
                Showing <?= count($users) ?> user(s)
            </div>
        </div>
        <table id="usersTable" class="display table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Activity</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): 
                    $last_login = $last_login_data[$user['user_id']] ?? null;
                ?>
                <tr class="<?= $user['is_active'] ? '' : 'suspended-user' ?>" 
                    data-user-id="<?= $user['user_id'] ?>">
                    <td>#U<?= str_pad($user['user_id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                    <td>
                        <span class="badge 
                            <?= $user['role'] === 'admin' ? 'badge-danger' : 
                               ($user['role'] === 'host' ? 'badge-warning' : 
                               ($user['role'] === 'manager' ? 'badge-info' : 'badge-primary')) ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?></td>
                    <td>
                        <div class="activity-info">
                            <?php if ($user['role'] === 'renter'): ?>
                                <small><?= $user['total_reservations'] ?> Reservations</small>
                            <?php elseif ($user['role'] === 'host'): ?>
                                <small><?= $user['total_units'] ?> Units</small>
                            <?php else: ?>
                                <small>System Admin</small>
                            <?php endif; ?>
                            <?php if ($last_login): ?>
                                <br><small class="text-muted">Last: <?= date('M d', strtotime($last_login)) ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $user['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $user['is_active'] ? 'Active' : 'Suspended' ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <div class="action-buttons">
                            <button type="button" class="btn-view" 
                                    onclick="viewUser(<?= $user['user_id'] ?>)" 
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn-edit" 
                                    onclick="editUser(<?= $user['user_id'] ?>)" 
                                    title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>
                            <!-- Password reset via admin UI has been disabled for security. Use OTP recovery. -->
                            <?php if ($user['is_active']): ?>
                                <button type="button" class="btn-suspend" 
                                        onclick="suspendUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')" 
                                        title="Suspend User">
                                    <i class="fas fa-user-slash"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-activate" 
                                        onclick="activateUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')" 
                                        title="Activate User">
                                    <i class="fas fa-user-check"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn-delete" 
                                    onclick="confirmDelete(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')" 
                                    title="Delete User">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required onchange="toggleBranchField(this)">
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="host">Host</option>
                                    <option value="manager">Manager</option>
                                    <option value="renter">Renter</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3" id="branchField" style="display: none;">
                        <label class="form-label">Branch Assignment (for Hosts)</label>
                        <select class="form-select" name="branch_id">
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['branch_id'] ?>">
                                    <?= htmlspecialchars($branch['branch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="user_management.php">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editUserForm">
                    <!-- Form will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user"></i> User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewUserDetails">
                <!-- Details will be loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete this user?</p>
                    <p><strong>User:</strong> <span id="delete_user_name"></span></p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Required Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
    // Chart Data
    const rolesData = {
        admin: <?= $admin_count ?>,
        host: <?= $host_count ?>,
        manager: <?= $manager_count ?>,
        renter: <?= $renter_count ?>
    };

    const statusData = {
        active: <?= $active_users ?>,
        inactive: <?= $inactive_users ?>
    };

    // Branches data and base URL for JavaScript
    const branches = <?= json_encode($branches) ?>;
    const SITE_URL = <?= json_encode(defined('SITE_URL') ? SITE_URL : '') ?>;
</script>

<script src="../assets/js/admin/user_management.js"></script>
</body>
</html>