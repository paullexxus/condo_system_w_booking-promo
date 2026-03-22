<?php
    // BookIT Branch Management
    // Multi-branch Condo Rental Reservation System

    include '../includes/session.php';
    include '../includes/functions.php';
    include_once '../config/db.php';
    checkRole(['admin']); // Tanging admin lang ang pwede

    $message = '';
    $error = '';

    // Check for session messages from previous redirect
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        $error = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_branch'])) {
            // Mag-add ng bagong branch
            $branch_name = sanitize_input($_POST['branch_name']);
            $address = sanitize_input($_POST['address']);
            $city = sanitize_input($_POST['city']);
            $contact_number = sanitize_input($_POST['contact_number']);
            $email = sanitize_input($_POST['email']);
            $host_id = !empty($_POST['host_id']) ? $_POST['host_id'] : null;
            
            $result = addBranch($branch_name, $address, $city, $contact_number, $email, $host_id);
            
            // Store message in session so it survives the redirect
            if (strpos($result, 'successfully') !== false) {
                $_SESSION['success_message'] = $result;
            } else {
                $_SESSION['error_message'] = $result;
            }
            
            // Refresh page to show new branch
            header("Location: manage_branch.php");
            exit();
        }
        
        if (isset($_POST['update_branch'])) {
            // Mag-update ng existing branch
            $branch_id = $_POST['branch_id'];
            $branch_name = sanitize_input($_POST['branch_name']);
            $address = sanitize_input($_POST['address']);
            $city = sanitize_input($_POST['city']);
            $contact_number = sanitize_input($_POST['contact_number']);
            $email = sanitize_input($_POST['email']);
            $host_id = !empty($_POST['host_id']) ? $_POST['host_id'] : null;
            
            $sql = "UPDATE branches SET branch_name = ?, address = ?, city = ?, contact_number = ?, email = ?, host_id = ? WHERE branch_id = ?";
            if (execute_query($sql, [$branch_name, $address, $city, $contact_number, $email, $host_id, $branch_id])) {
                $_SESSION['success_message'] = "Branch updated successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update branch!";
            }
            header("Location: manage_branch.php");
            exit();
        }
        
        // I-update ang delete_branch handler
if (isset($_POST['delete_branch'])) {
    // Mag-delete ng branch (soft delete) at i-update ang mga units
    $branch_id = $_POST['branch_id'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // 1. I-soft delete ang branch
        $sql = "UPDATE branches SET is_active = 0 WHERE branch_id = ?";
        if (!execute_query($sql, [$branch_id])) {
            throw new Exception("Failed to deactivate branch");
        }
        
        // 2. I-deactivate ang lahat ng units sa branch na ito
        $sql = "UPDATE units SET is_available = 0 WHERE branch_id = ?";
        if (!execute_query($sql, [$branch_id])) {
            throw new Exception("Failed to deactivate units");
        }
        
        // 3. I-cancel ang lahat ng active reservations sa branch na ito
        $sql = "UPDATE reservations SET status = 'cancelled' 
                WHERE branch_id = ? AND status IN ('pending', 'confirmed')";
        if (!execute_query($sql, [$branch_id])) {
            throw new Exception("Failed to cancel reservations");
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Branch and all associated units deactivated successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to deactivate branch: " . $e->getMessage();
    }
    
    header("Location: manage_branch.php");
    exit();
}
    } // End of POST handling

    // ==================== FETCH BRANCHES WITH PERFORMANCE METRICS ====================
    $branches = [];
    try {
        $sql = "SELECT b.*, 
                    u.full_name as host_name,
                    (SELECT COUNT(*) FROM units WHERE branch_id = b.branch_id) as unit_count,
                    (SELECT COUNT(*) FROM users WHERE branch_id = b.branch_id AND role = 'host') as staff_count,
                    (SELECT COUNT(*) FROM reservations WHERE branch_id = b.branch_id) as total_bookings,
                    (SELECT COUNT(DISTINCT unit_id) FROM reservations WHERE branch_id = b.branch_id AND status IN ('confirmed', 'checked_in')) as booked_units,
                    (SELECT SUM(total_amount) FROM reservations WHERE branch_id = b.branch_id AND status IN ('confirmed', 'checked_in', 'completed')) as total_revenue,
                    b.created_at as last_activity
                FROM branches b
                LEFT JOIN users u ON b.host_id = u.user_id
                WHERE b.is_active = 1
                ORDER BY b.created_at DESC";
        
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Calculate performance metrics
                $row['occupancy_rate'] = $row['unit_count'] > 0 ? 
                    min(round(($row['booked_units'] / $row['unit_count']) * 100, 1), 100) : 0;
                $row['avg_booking_value'] = $row['total_bookings'] > 0 ? 
                    round($row['total_revenue'] / $row['total_bookings'], 2) : 0;
                
                $branches[] = $row;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    // Kumuha ng available hosts
    $hosts = get_multiple_results("SELECT user_id, full_name FROM users WHERE role = 'host' AND is_active = 1");

    // Get pending verifications count
    $pending_count = 0;

    // Handle hosts result
    $hosts_data = [];
    if (is_object($hosts)) {
        $hosts_data = $hosts;
    } elseif (is_array($hosts)) {
        $hosts_data = $hosts;
    }

    // Calculate overall statistics
    $total_revenue = array_sum(array_column($branches, 'total_revenue'));
    $total_bookings = array_sum(array_column($branches, 'total_bookings'));
    $avg_occupancy = count($branches) > 0 ? round(array_sum(array_column($branches, 'occupancy_rate')) / count($branches), 1) : 0;
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>Branch Management - BookIT</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="../assets/css/sidebar-common.css" rel="stylesheet">
        <link href="../assets/css/admin/manage_branch.css" rel="stylesheet">
        <link href="../assets/css/modals.css" rel="stylesheet">
        <!-- DataTables -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    </head>
    <body>

    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content flex-grow-1">
        <div class="page-header">
            <div>
                <h1 class="page-title mb-1">
                    <i class="fas fa-building me-2"></i>Branch Management
                </h1>
                <p class="text-muted">Manage all branch locations and their operations</p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn-refresh" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn-export" onclick="exportBranches('pdf')">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button type="button" class="btn-export" onclick="exportBranches('csv')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                    <i class="fas fa-plus"></i> Add Branch
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

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="card">
                <div class="card-icon"><i class="fas fa-building"></i></div>
                <div class="card-info">
                    <h3><?= count($branches) ?></h3>
                    <p>Total Branches</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                <div class="card-info">
                    <h3><?= $avg_occupancy ?>%</h3>
                    <p>Avg Occupancy Rate</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="card-info">
                    <h3><?= $total_bookings ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="card-info">
                    <h3>₱<?= number_format($total_revenue, 2) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>

        <!-- Branches Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Branch Details & Performance</div>
            </div>
            <table id="branchesTable" class="display table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Branch Name</th>
                        <th>Location</th>
                        <th>Host</th>
                        <th>Performance</th>
                        <th>Units</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
    <tr>
        <td>#B<?= str_pad($branch['branch_id'], 4, '0', STR_PAD_LEFT) ?></td>
        <td>
            <strong><?= htmlspecialchars($branch['branch_name']) ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($branch['email'] ?? '') ?></small>
        </td>
        <td>
            <?= htmlspecialchars($branch['city']) ?><br>
            <small class="text-muted"><?= htmlspecialchars($branch['contact_number'] ?? '') ?></small>
        </td>
        <td>
            <?php if ($branch['host_name']): ?>
                <span class="badge badge-success"><?= htmlspecialchars($branch['host_name']) ?></span>
            <?php else: ?>
                <span class="badge badge-secondary">Not Assigned</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="performance-metrics">
                <div class="occupancy-rate">
                    <span class="rate-value"><?= $branch['occupancy_rate'] ?>%</span>
                    <small>Occupancy</small>
                </div>
                <div class="progress" style="height: 5px; width: 80px;">
                    <div class="progress-bar <?= $branch['occupancy_rate'] >= 70 ? 'bg-success' : ($branch['occupancy_rate'] >= 40 ? 'bg-warning' : 'bg-danger') ?>" 
                        style="width: <?= $branch['occupancy_rate'] ?>%">
                    </div>
                </div>
            </div>
        </td>
        <td>
            <span class="badge badge-info">
                <?= $branch['unit_count'] ?> Units
        </span>
        </td>
        <td>
            <span class="badge badge-primary">
                <?= $branch['total_bookings'] ?> Bookings
            </span>
        </td>
        <td>
            <span class="revenue-amount">
                ₱<?= number_format($branch['total_revenue'] ?? 0, 2) ?>
            </span>
        </td>
        <td>
            <div class="action-buttons">
                <button type="button" class="btn-view" 
                        data-bs-toggle="modal" 
                        data-bs-target="#viewBranchModal"
                        onclick="viewBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)" 
                        title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn-edit" 
                        data-bs-toggle="modal" 
                        data-bs-target="#editBranchModal"
                        onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)" 
                        title="Edit Branch">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn-delete" 
                        onclick="confirmDelete(<?= $branch['branch_id'] ?>, '<?= $branch['branch_name'] ?>')" 
                        title="Delete Branch">
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
    </div>    <?php foreach ($branches as $branch): ?>
        <tr>
            <!-- Branch data displays here -->
        </tr>
    <?php endforeach; ?>

    <!-- Add Branch Modal -->
    <div class="modal fade" id="addBranchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Branch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Branch Name *</label>
                            <input type="text" class="form-control" name="branch_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address *</label>
                            <textarea class="form-control" name="address" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">City *</label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Host</label>
                            <select class="form-select" name="host_id">
                                <option value="">Select Host (Optional)</option>
                                <?php 
                                if (is_object($hosts_data)) {
                                    $host->data_seek(0); // Reset pointer
                                    while ($host = $hosts_data->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $host['user_id']; ?>">
                                        <?php echo $host['full_name']; ?>
                                    </option>
                                <?php endwhile; 
                                } elseif (is_array($hosts_data)) {
                                    foreach ($hosts_data as $host): 
                                ?>
                                    <option value="<?php echo $host['user_id']; ?>">
                                        <?php echo $host['full_name']; ?>
                                    </option>
                                <?php endforeach; 
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_branch" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Branch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div class="modal fade" id="editBranchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="branch_id" id="edit_branch_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Branch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Branch Name *</label>
                            <input type="text" class="form-control" name="branch_name" id="edit_branch_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address *</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">City *</label>
                            <input type="text" class="form-control" name="city" id="edit_city" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" id="edit_contact_number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">host</label>
                            <select class="form-select" name="host_id" id="edit_host_id">
                                <option value="">Select host (Optional)</option>
                                <?php 
                                if (is_object($hosts_data)) {
                                    $hosts_data->data_seek(0); // Reset pointer
                                    while ($host = $hosts_data->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $host['user_id']; ?>">
                                        <?php echo $host['full_name']; ?>
                                    </option>
                                <?php endwhile; 
                                } elseif (is_array($hosts_data)) {
                                    foreach ($hosts_data as $host): 
                                ?>
                                    <option value="<?php echo $host['user_id']; ?>">
                                        <?php echo $host['full_name']; ?>
                                    </option>
                                <?php endforeach; 
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_branch" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Branch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Branch Modal -->
    <div class="modal fade" id="viewBranchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Branch Details & Performance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewBranchDetails">
                    <!-- Branch details will be populated via JavaScript -->
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
                    <input type="hidden" name="branch_id" id="delete_branch_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to deactivate this branch?</p>
                        <p><strong>Branch:</strong> <span id="delete_branch_name"></span></p>
                        <p class="text-muted">This action will deactivate the branch and make it unavailable for new reservations.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_branch" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Deactivate Branch
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
    <script src="../assets/js/admin/manage_branch.js"></script>
    </body>
    </html>
