    <?php
    // BookIT Unit Management
    // Multi-branch Condo Rental Reservation System

    include '../includes/session.php';
    include '../includes/functions.php';
    include_once '../config/db.php';
    checkRole(['admin']); // Only admin can access

    $message = '';
    $error = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_unit'])) {
            // Add new unit - USING YOUR EXISTING COLUMNS
            $unit_number = sanitize_input($_POST['unit_number']);
            $unit_type = sanitize_input($_POST['unit_type']);
            $branch_id = (int)$_POST['branch_id'];
            
            $pricing_type = sanitize_input($_POST['pricing_type'] ?? 'nightly');
            $price_per_night = (float)($_POST['price_per_night'] ?? 0);
            $price_per_month = (float)($_POST['price_per_month'] ?? 0);
            $floor_number = !empty($_POST['floor_number']) ? (int)$_POST['floor_number'] : null;
            $max_occupancy = (int)$_POST['max_occupancy'];
            $security_deposit = (float)$_POST['security_deposit'];
            $description = sanitize_input($_POST['description']);
            $building_name = isset($_POST['building_name']) ? sanitize_input($_POST['building_name']) : '';
            $street_address = isset($_POST['street_address']) ? sanitize_input($_POST['street_address']) : '';
            $city = isset($_POST['city']) ? sanitize_input($_POST['city']) : '';
            $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
            $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
            
            $sqm = isset($_POST['sqm']) && $_POST['sqm'] !== '' ? (float)$_POST['sqm'] : null;
            $bed_type = sanitize_input($_POST['bed_type'] ?? '');
            $num_beds = isset($_POST['num_beds']) && $_POST['num_beds'] !== '' ? (int)$_POST['num_beds'] : 1;
            $num_bathrooms = isset($_POST['num_bathrooms']) && $_POST['num_bathrooms'] !== '' ? (int)$_POST['num_bathrooms'] : 1;
            
            // Fetch host_id for the selected branch
            $branch_info = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$branch_id]);
            $host_id = $branch_info ? $branch_info['host_id'] : null;

            // Proceed to insert unit
            $sql = "INSERT INTO units (unit_number, unit_type, branch_id, host_id, pricing_type, price_per_night, price_per_month, floor_number, max_occupancy, security_deposit, description, sqm, bed_type, num_beds, num_bathrooms) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
            if (execute_query($sql, [$unit_number, $unit_type, $branch_id, $host_id, $pricing_type, $price_per_night, $price_per_month, $floor_number, $max_occupancy, $security_deposit, $description, $sqm, $bed_type, $num_beds, $num_bathrooms])) {
                $message = "Unit added successfully!";
            } else {
                $error = "Failed to add unit!";
            }
        }
        
        if (isset($_POST['update_unit'])) {
            // Update existing unit - USING YOUR EXISTING COLUMNS
            $unit_id = (int)$_POST['unit_id'];
            $unit_number = sanitize_input($_POST['unit_number']);
            $unit_type = sanitize_input($_POST['unit_type']);
            $branch_id = (int)$_POST['branch_id'];
            $security_deposit = (float)$_POST['security_deposit'];
            $description = sanitize_input($_POST['description']);
            
            $pricing_type = sanitize_input($_POST['pricing_type'] ?? 'nightly');
            $price_per_night = (float)($_POST['price_per_night'] ?? 0);
            $price_per_month = (float)($_POST['price_per_month'] ?? 0);
            
            $floor_number = !empty($_POST['floor_number']) ? (int)$_POST['floor_number'] : null;
            $max_occupancy = (int)$_POST['max_occupancy'];
            
            $sqm = isset($_POST['sqm']) && $_POST['sqm'] !== '' ? (float)$_POST['sqm'] : null;
            $bed_type = sanitize_input($_POST['bed_type'] ?? '');
            $num_beds = isset($_POST['num_beds']) && $_POST['num_beds'] !== '' ? (int)$_POST['num_beds'] : 1;
            $num_bathrooms = isset($_POST['num_bathrooms']) && $_POST['num_bathrooms'] !== '' ? (int)$_POST['num_bathrooms'] : 1;

            $owner_row = get_single_result("SELECT host_id FROM units WHERE unit_id = ?", [$unit_id]);
            $notify_id = (int) ($owner_row['host_id'] ?? 0);
            if ($notify_id <= 0) {
                $branch_info = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$branch_id]);
                $notify_id = $branch_info ? (int) $branch_info['host_id'] : 0;
            }

            // Do not overwrite host_id on edit — avoids 0 / wrong branch host unlinking the listing from the owner.
            $sql = "UPDATE units SET unit_number = ?, unit_type = ?, branch_id = ?, 
                    pricing_type = ?, price_per_night = ?, price_per_month = ?, floor_number = ?, max_occupancy = ?, security_deposit = ?, description = ?, sqm = ?, bed_type = ?, num_beds = ?, num_bathrooms = ?
                    WHERE unit_id = ?";
            
            if (execute_query($sql, [$unit_number, $unit_type, $branch_id, $pricing_type, $price_per_night, $price_per_month, $floor_number, $max_occupancy, $security_deposit, $description, $sqm, $bed_type, $num_beds, $num_bathrooms, $unit_id])) {
                $message = "Unit updated successfully!";
                if ($notify_id > 0) {
                    sendNotification(
                        $notify_id,
                        'Unit updated by admin',
                        'Your listing was updated from the admin Unit Management page. Check Host → Unit Management for the latest details.',
                        'system',
                        'system',
                        null
                    );
                }
            } else {
                $error = "Failed to update unit!";
            }
        }
        
        if (isset($_POST['delete_unit'])) {
            // Delete unit (soft delete) - USING YOUR EXISTING is_available COLUMN
            $unit_id = (int)$_POST['unit_id'];
            $sql = "UPDATE units SET is_available = 0 WHERE unit_id = ?";
            if (execute_query($sql, [$unit_id])) {
                $message = "Unit deleted successfully!";
                
                // Fetch unit & host info to notify the host
                $u_info = get_single_result("SELECT u.unit_number, b.host_id FROM units u JOIN branches b ON u.branch_id = b.branch_id WHERE u.unit_id = ?", [$unit_id]);
                if ($u_info && $u_info['host_id']) {
                    sendNotification($u_info['host_id'], "Unit Deleted by Admin", "Admin has removed your Unit #{$u_info['unit_number']} from the system. It is now marked unavailable.", "system", "system", null);
                }
            } else {
                $error = "Failed to delete unit!";
            }
        }
        
        if (isset($_POST['change_status'])) {
            // Change unit status - USING YOUR EXISTING is_available COLUMN
            $unit_id = (int)$_POST['unit_id'];
            $status = sanitize_input($_POST['status']);
            
            // Convert status to is_available (1 for available, 0 for occupied/maintenance)
            $is_available = ($status === 'available') ? 1 : 0;
            
            $sql = "UPDATE units SET is_available = ? WHERE unit_id = ?";
            if (execute_query($sql, [$is_available, $unit_id])) {
                $message = "Unit status updated successfully!";
                
                // Notify Host
                $u_info = get_single_result("SELECT u.unit_number, b.host_id FROM units u JOIN branches b ON u.branch_id = b.branch_id WHERE u.unit_id = ?", [$unit_id]);
                if ($u_info && $u_info['host_id']) {
                    sendNotification($u_info['host_id'], "Unit Status Updated", "Admin has changed the status of your Unit #{$u_info['unit_number']} to " . strtoupper($status) . ".", "system", "system", null);
                }
            } else {
                $error = "Failed to update unit status!";
            }
        }
        
        if (isset($_POST['toggle_instant'])) {
            // Toggle instant booking for a unit
            $unit_id = (int)($_POST['unit_id'] ?? 0);
            $instant = isset($_POST['instant']) && $_POST['instant'] == '1' ? 1 : 0;

            // CSRF check if token provided
            if (!empty($_POST['csrf_token']) && !verifyCSRFToken($_POST['csrf_token'])) {
                $error = 'Invalid CSRF token.';
            } else {
                $sql = "UPDATE units SET instant_booking = ? WHERE unit_id = ?";
                if (execute_query($sql, [$instant, $unit_id])) {
                    $message = 'Instant booking setting updated successfully.';
                } else {
                    $error = 'Failed to update instant booking setting.';
                }
            }
        }
    }

    // PHP function to get unit details
    function getUnitDetails($unit_id) {
        global $conn;
        
        $unit = get_single_result("
            SELECT u.*, b.branch_name, b.city as location 
            FROM units u 
            LEFT JOIN branches b ON u.branch_id = b.branch_id 
            WHERE u.unit_id = ?
        ", [$unit_id]);

        if (!$unit) return null;

        // Fetch performance metrics
        $metrics = get_single_result("
            SELECT 
                COUNT(*) as total_bookings,
                COUNT(CASE WHEN status IN ('confirmed', 'checked_in') THEN 1 END) as active_bookings,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                SUM(total_amount) as total_revenue
            FROM reservations 
            WHERE unit_id = ?
        ", [$unit_id]);

        $rating_data = get_single_result("SELECT AVG(rating) as avg_rating FROM reviews WHERE unit_id = ?", [$unit_id]);
        $metrics['avg_rating'] = $rating_data ? $rating_data['avg_rating'] : null;

        // Fetch recent bookings
        $recent_bookings = get_multiple_results("
            SELECT r.reservation_id, r.check_in_date, r.check_out_date, r.status, r.total_amount, u.full_name as user_name
            FROM reservations r 
            LEFT JOIN users u ON r.user_id = u.user_id 
            WHERE r.unit_id = ? 
            ORDER BY r.created_at DESC 
            LIMIT 5
        ", [$unit_id]);

        return [
            'unit' => $unit,
            'metrics' => $metrics,
            'recent_bookings' => $recent_bookings
        ];
    }

    // ==================== FETCH UNITS WITH PERFORMANCE METRICS ====================
    $units = [];
    try {
        // Search and filter parameters
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $branch_filter = isset($_GET['branch']) ? $_GET['branch'] : 'all';
        $type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
        $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
        $instant_filter = isset($_GET['instant']) ? $_GET['instant'] : 'all';
        
        // IMPORTANT: Only show units from ACTIVE branches
        $where = "WHERE u.is_available = 1 AND b.is_active = 1";
        $params = [];
        
        if ($q !== '') {
            $where .= " AND (u.unit_number LIKE ? OR u.description LIKE ?)";
            $search_term = "%$q%";
            array_push($params, $search_term, $search_term);
        }
        
        if ($branch_filter !== 'all') {
            $where .= " AND u.branch_id = ?";
            $params[] = $branch_filter;
        }
        
        if ($type_filter !== 'all') {
            $where .= " AND u.unit_type = ?";
            $params[] = $type_filter;
        }

        if ($instant_filter === 'instant') {
            $where .= " AND u.instant_booking = 1";
        }

        // UPDATED SQL QUERY - Only include revenue from ACTIVE branches
        $sql = "SELECT 
                    u.*, 
                    u.price_per_night, u.price_per_month, u.pricing_type, u.instant_booking,
                    CASE 
                        WHEN u.is_available = 1 THEN 'available'
                        WHEN u.is_available = 0 THEN 'occupied'
                        ELSE 'maintenance'
                    END as status,
                    b.branch_name,
                    b.is_active as branch_active,
                    (SELECT COUNT(*) FROM reservations r WHERE r.unit_id = u.unit_id AND r.branch_id = b.branch_id) as total_bookings,
                    (SELECT COUNT(*) FROM reservations r WHERE r.unit_id = u.unit_id AND r.status IN ('confirmed', 'checked_in') AND r.branch_id = b.branch_id) as active_bookings,
                    (SELECT COUNT(*) FROM reservations r WHERE r.unit_id = u.unit_id AND r.status = 'completed' AND r.branch_id = b.branch_id) as completed_bookings,
                    (SELECT SUM(r.total_amount) FROM reservations r WHERE r.unit_id = u.unit_id AND r.status IN ('confirmed', 'checked_in', 'completed') AND r.branch_id = b.branch_id) as total_revenue,
                    (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.unit_id = u.unit_id) as avg_rating,
                    COALESCE(
                        ROUND(
                            (SELECT COUNT(*) FROM reservations r 
                             WHERE r.unit_id = u.unit_id 
                             AND r.status IN ('confirmed', 'checked_in', 'completed')
                             AND r.branch_id = b.branch_id) * 100.0 / 
                            NULLIF((SELECT COUNT(*) FROM reservations r WHERE r.unit_id = u.unit_id AND r.branch_id = b.branch_id), 0)
                        , 2)
                    , 0) as occupancy_rate
                FROM units u
                LEFT JOIN branches b ON u.branch_id = b.branch_id
                $where
                ORDER BY u.created_at DESC";

        // Execute query and fetch units
        $units = get_multiple_results($sql, $params);

        // Rest of your existing code...
        
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
        error_log($error);
    }

    // Get available data for filters - ONLY ACTIVE BRANCHES
    $branches = get_multiple_results("SELECT branch_id, branch_name FROM branches WHERE is_active = 1");
    $unit_types = get_multiple_results("SELECT DISTINCT unit_type FROM units WHERE unit_type IS NOT NULL");

    // Calculate overall statistics - UPDATED to exclude inactive branches
    $total_units = count($units);
    $available_units = count(array_filter($units, fn($u) => $u['is_available'] == 1 && $u['branch_active'] == 1));
    $occupied_units = count(array_filter($units, fn($u) => $u['is_available'] == 0 && $u['branch_active'] == 1));
    $maintenance_units = 0;
    $total_revenue = array_sum(array_column($units, 'total_revenue'));
    $avg_occupancy = $total_units > 0 ? round(array_sum(array_column($units, 'occupancy_rate')) / $total_units, 1) : 0;

    // Get unit details for modal if view_unit parameter is set
    $view_unit_data = null;
    if (isset($_GET['view_unit'])) {
        $view_unit_data = getUnitDetails((int)$_GET['view_unit']);
    }
    ?>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Management - BookIT Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css"> 
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin/unit_management.css">
    </head>
    <body>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="content">
        <div class="page-header">
            <div class="page-title">
                <h1>Unit Management</h1>
                <p>Manage all units across branches and their performance</p>
            </div>
            <div class="page-actions">
                <button type="button" class="btn-refresh" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn-export" onclick="exportUnits('pdf')">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button type="button" class="btn-export" onclick="exportUnits('csv')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                    <i class="fas fa-plus"></i> Add Unit
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message || $error): ?>
            <div aria-live="polite" aria-atomic="true" class="position-relative">
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div id="feedbackToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header <?php echo $error ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                            <strong class="me-auto"><?php echo $error ? 'Error' : 'Success' ?></strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">
                            <?php echo $error ? htmlspecialchars($error) : htmlspecialchars($message); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="card">
                <div class="card-icon"><i class="fas fa-home"></i></div>
                <div class="card-info">
                    <h3><?= $total_units ?></h3>
                    <p>Total Units</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-check-circle"></i></div>
                <div class="card-info">
                    <h3><?= $available_units ?></h3>
                    <p>Available</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-user-friends"></i></div>
                <div class="card-info">
                    <h3><?= $occupied_units ?></h3>
                    <p>Occupied</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-tools"></i></div>
                <div class="card-info">
                    <h3><?= $maintenance_units ?></h3>
                    <p>Maintenance</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                <div class="card-info">
                    <h3><?= $avg_occupancy ?>%</h3>
                    <p>Avg Occupancy</p>
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

        <!-- Search & Filter Section -->
        <div class="filter-section">
            <form method="GET" class="search-form">
                <div class="search-group">
                    <input type="text" name="q" placeholder="Search units by number or description" 
                        value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>"
                        class="form-control">
                </div>
                
                <div class="filter-group">
                    <select name="branch" class="form-select">
                        <option value="all">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['branch_id'] ?>" 
                                <?= (isset($_GET['branch']) && $_GET['branch'] == $branch['branch_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['branch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="type" class="form-select">
                        <option value="all">All Types</option>
                        <?php foreach ($unit_types as $type): ?>
                            <option value="<?= $type['unit_type'] ?>" 
                                <?= (isset($_GET['type']) && $_GET['type'] == $type['unit_type']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['unit_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="status" class="form-select">
                        <option value="all">All Status</option>
                        <option value="available" <?= (isset($_GET['status']) && $_GET['status'] === 'available') ? 'selected' : '' ?>>Available</option>
                        <option value="occupied" <?= (isset($_GET['status']) && $_GET['status'] === 'occupied') ? 'selected' : '' ?>>Occupied</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="instant" class="form-select">
                        <option value="all">All Booking Types</option>
                        <option value="instant" <?= (isset($_GET['instant']) && $_GET['instant'] === 'instant') ? 'selected' : '' ?>>Instant Only</option>
                    </select>
                </div>

                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="unit_management.php" class="btn-clear">Clear Filters</a>
            </form>
        </div>

        <!-- Units Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Unit Details & Performance</div>
                <div class="table-info">
                    Showing <?= count($units) ?> unit(s)
                </div>
            </div>
            <table id="unitsTable" class="display table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Unit Details</th>
                        <th>Branch</th>
                        <th>Price</th>
                        <th>Performance</th>
                        <th>Status</th>
                                <th>Instant</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units as $unit): ?>
                    <tr>
                        <td>#U<?= str_pad($unit['unit_id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($unit['unit_number']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($unit['unit_type']) ?></small><br>
                            <small>Floor: <?= $unit['floor_number'] ?? 'N/A' ?> • Capacity: <?= $unit['max_occupancy'] ?> persons</small>
                        </td>
                        <td>
                            <span class="badge branch-badge"><?= htmlspecialchars($unit['branch_name']) ?></span>
                        </td>
                        <td>
                            <?php if (($unit['pricing_type'] ?? 'nightly') === 'monthly'): ?>
                                <strong>₱<?= number_format($unit['price_per_month'] ?? 0, 2) ?></strong><br>
                                <span class="badge bg-info mt-1">Monthly</span>
                            <?php else: ?>
                                <strong>₱<?= number_format($unit['price_per_night'] ?? 0, 2) ?></strong><br>
                                <span class="badge bg-primary mt-1">Nightly</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="performance-metrics">
                                <div class="metric-row">
                                    <span class="metric-label">Bookings:</span>
                                    <span class="metric-value"><?= $unit['total_bookings'] ?? 0 ?></span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Occupancy:</span>
                                    <span class="metric-value"><?= $unit['occupancy_rate'] ?? 0 ?>%</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Revenue:</span>
                                    <strong>₱<?= number_format($unit['total_revenue'] ?? 0, 2) ?></strong>
                                </div>
                                <?php if ($unit['avg_rating']): ?>
                                <div class="metric-row">
                                    <span class="metric-label">Rating:</span>
                                    <span class="metric-value">
                                        <i class="fas fa-star text-warning"></i> <?= number_format($unit['avg_rating'], 1) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge status-<?= $unit['status'] ?? 'available' ?>">
                                <?= ucfirst($unit['status'] ?? 'available') ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="btn-view" 
                                        onclick="viewUnit(<?= $unit['unit_id'] ?>)" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn-edit" 
                                        onclick="editUnit(<?= $unit['unit_id'] ?>)" 
                                        title="Edit Unit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-status" 
                                        onclick="changeStatus(<?= $unit['unit_id'] ?>, '<?= $unit['unit_number'] ?>')" 
                                        title="Change Status">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button type="button" class="btn-delete" 
                                        onclick="confirmDelete(<?= $unit['unit_id'] ?>, '<?= htmlspecialchars($unit['unit_number']) ?>')" 
                                        title="Delete Unit">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <form method="POST" class="d-inline instant-toggle-form" style="display:inline-block;">
                                <input type="hidden" name="unit_id" value="<?= (int)$unit['unit_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="toggle_instant" value="1">
                                <input type="hidden" name="instant" value="<?= $unit['instant_booking'] ? '1' : '0' ?>" class="instant-hidden">
                                <div class="form-check form-switch">
                                    <input class="form-check-input instant-switch" type="checkbox" role="switch" id="instant_switch_<?= $unit['unit_id'] ?>" <?= $unit['instant_booking'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="instant_switch_<?= $unit['unit_id'] ?>">&nbsp;</label>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- ==================== MODALS SECTION ==================== -->
    <!-- Add Unit Modal -->
    <div class="modal fade" id="addUnitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Unit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Unit Number *</label>
                                    <input type="text" class="form-control" name="unit_number" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Unit Type *</label>
                                    <select class="form-select" name="unit_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Studio">Studio</option>
                                        <option value="1 Bedroom">1 Bedroom</option>
                                        <option value="2 Bedrooms">2 Bedrooms</option>
                                        <option value="3 Bedrooms">3 Bedrooms</option>
                                        <option value="Penthouse">Penthouse</option>
                                        <option value="Executive Suite">Executive Suite</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Branch *</label>
                                    <select class="form-select" name="branch_id" required>
                                        <option value="">Select Branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?= $branch['branch_id'] ?>">
                                                <?= htmlspecialchars($branch['branch_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 border p-3 rounded">
                                    <label class="form-label fw-bold"><i class="fas fa-tag"></i> Pricing Model & Rate *</label>
                                    <div class="d-flex gap-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="pricing_type" id="add_pricing_nightly" value="nightly" checked autocomplete="off">
                                            <label class="form-check-label" for="add_pricing_nightly">Nightly</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="pricing_type" id="add_pricing_monthly" value="monthly" autocomplete="off">
                                            <label class="form-check-label" for="add_pricing_monthly">Monthly</label>
                                        </div>
                                    </div>
                                    <div id="add_nightly_input">
                                        <label class="form-label">Price per Night (₱) *</label>
                                        <input type="number" class="form-control" name="price_per_night" step="0.01" min="0">
                                    </div>
                                    <div id="add_monthly_input" class="d-none">
                                        <label class="form-label">Price per Month (₱) *</label>
                                        <input type="number" class="form-control" name="price_per_month" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        document.querySelectorAll('input[name="pricing_type"]').forEach(e => {
                            e.addEventListener('change', function() {
                                if(this.value === 'nightly') {
                                    document.getElementById('add_nightly_input').classList.remove('d-none');
                                    document.getElementById('add_monthly_input').classList.add('d-none');
                                } else {
                                    document.getElementById('add_nightly_input').classList.add('d-none');
                                    document.getElementById('add_monthly_input').classList.remove('d-none');
                                }
                            });
                        });
                        </script>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Floor Number</label>
                                    <input type="number" class="form-control" name="floor_number" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Max Occupancy *</label>
                                    <input type="number" class="form-control" name="max_occupancy" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Security Deposit</label>
                                    <input type="number" class="form-control" name="security_deposit" step="0.01" min="0" value="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Describe the unit features and location..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Floor Area (sqm)</label>
                                    <input type="number" class="form-control" name="sqm" placeholder="e.g., 35.5" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Bed Type</label>
                                    <select class="form-select" name="bed_type">
                                        <option value="">Select Bed Type</option>
                                        <option value="single">Single Bed</option>
                                        <option value="double deck">Double Deck / Bunk Bed</option>
                                        <option value="queen">Queen Size</option>
                                        <option value="king">King Size</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Number of Beds</label>
                                    <input type="number" class="form-control" name="num_beds" placeholder="e.g., 1" min="1" value="1">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Bathrooms</label>
                                    <input type="number" class="form-control" name="num_bathrooms" placeholder="e.g., 1" min="1" value="1">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Unit Photos</label>
                                    <input type="file" class="form-control" name="unit_photos[]" id="unit_photos" accept="image/jpeg,image/png" multiple>
                                    <small class="text-muted">Drag photos here or click to browse. Support: JPG, PNG (Max 5MB per image)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Capacity (Guests)</label>
                                    <input type="number" class="form-control" name="max_occupancy" min="1" placeholder="e.g., 4" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="available">Available</option>
                                        <option value="unavailable">Unavailable</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Amenities</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="wifi"> <span class="form-check-label">WiFi</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="ac"> <span class="form-check-label">Air Conditioning</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="kitchen"> <span class="form-check-label">Kitchen</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="tv"> <span class="form-check-label">TV</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="washing_machine"> <span class="form-check-label">Washing Machine</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="pool"> <span class="form-check-label">Pool</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="gym"> <span class="form-check-label">Gym</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="parking"> <span class="form-check-label">Parking</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Building / Property Name</label>
                                    <input type="text" class="form-control" name="building_name" placeholder="e.g. Sunrise Residences">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Street Address</label>
                                    <input type="text" class="form-control" name="street_address" placeholder="Street, block, etc.">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">City / Municipality</label>
                                    <input type="text" class="form-control" name="city" placeholder="City">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Map Location</label>
                                    <div id="addUnitMap" style="height:250px;border:1px solid #ddd;border-radius:4px;"></div>
                                    <small class="text-muted">Click on map to place pin; the coordinates are saved with the unit.</small>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="latitude" id="unit_latitude" value="">
                        <input type="hidden" name="longitude" id="unit_longitude" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_unit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Unit Modal -->
    <div class="modal fade" id="viewUnitModal" tabindex="-1" aria-labelledby="viewUnitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewUnitModalLabel">
                        <i class="fas fa-eye me-2"></i>Unit Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewUnitModalBody">
                    <!-- Content will be populated by PHP/JavaScript -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Select a unit to view details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-warning" onclick="editSelectedUnit()">
                        <i class="fas fa-edit me-2"></i>Edit Unit
                    </button>
                    <button type="button" class="btn btn-primary" onclick="createBookingForUnit()">
                        <i class="fas fa-calendar-plus me-2"></i>Create Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Unit Modal -->
    <div class="modal fade" id="editUnitModal" tabindex="-1" aria-labelledby="editUnitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="editUnitModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Unit
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="editUnitModalBody">
                    <!-- Content will be populated by AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-warning" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading unit details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="unit_id" id="status_unit_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-sync-alt"></i> Change Unit Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Change status for unit: <strong id="status_unit_name"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" name="status" required>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="unit_id" id="delete_unit_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this unit?</p>
                        <p><strong>Unit:</strong> <span id="delete_unit_name"></span></p>
                        <p class="text-muted">This action will remove the unit from the system. Existing reservations will be preserved.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_unit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Unit
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

    <script src="../assets/js/admin/unit_management.js?v=<?= time() ?>"></script>

    <script>
    // Auto-submit instant toggle form when switch changes
    $(document).ready(function(){
        $(document).on('change', '.instant-switch', function(){
            var $form = $(this).closest('form');
            var checked = $(this).is(':checked') ? '1' : '0';
            $form.find('input.instant-hidden').val(checked);
            $form.submit();
        });
    });
    </script>

    <!-- Unified Leaflet Implementation (Remove Google Maps for Defense-Grade Stability) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
    // PHP to JavaScript data passing for View Modal
    const viewUnitData = <?= isset($view_unit_data) ? json_encode($view_unit_data) : 'null' ?>;
    const currentViewUnitId = <?= isset($_GET['view_unit']) ? (int)$_GET['view_unit'] : 'null' ?>;
    </script>
    <script>
    // Show toast if feedback exists
    document.addEventListener('DOMContentLoaded', function(){
        var toastEl = document.getElementById('feedbackToast');
        if (toastEl) {
            var toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
        }
    });
    </script>
    </body>
    </html>