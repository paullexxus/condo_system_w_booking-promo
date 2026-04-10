<?php
// Host Unit Management
// Add, edit, delete, and manage host's condo units

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

if (isset($_SESSION['action_message'])) {
    $action_message = $_SESSION['action_message'];
    $action_success = $_SESSION['action_success'] ?? false;
    unset($_SESSION['action_message']);
    unset($_SESSION['action_success']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        // Add New Unit
        if ($action === 'add_unit') {
            $unit_name = sanitize_input($_POST['unit_name']);
            $branch_id = sanitize_input($_POST['branch_id']);
            $description = sanitize_input($_POST['description'] ?? '');
            $price = sanitize_input($_POST['price']);
            $capacity = sanitize_input($_POST['capacity']);
            $status = sanitize_input($_POST['status'] ?? 'available');
            $pricing_type = sanitize_input($_POST['pricing_type'] ?? 'monthly');
            
            // New unit details
            $sqm = isset($_POST['sqm']) && $_POST['sqm'] !== '' ? (float)$_POST['sqm'] : null;
            $bed_type = sanitize_input($_POST['bed_type'] ?? '');
            $num_beds = isset($_POST['num_beds']) && $_POST['num_beds'] !== '' ? (int)$_POST['num_beds'] : 1;
            $num_bathrooms = isset($_POST['num_bathrooms']) && $_POST['num_bathrooms'] !== '' ? (int)$_POST['num_bathrooms'] : 1;
            
            $street_address = sanitize_input($_POST['street_address'] ?? '');
            $unit_number = sanitize_input($_POST['unit_number'] ?? '');
            $city = sanitize_input($_POST['city'] ?? '');
            $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
            $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

            // Determine allowed branches for this host at runtime
            $assignedBranch = get_single_result("SELECT b.* FROM branches b JOIN users u ON b.branch_id = u.branch_id WHERE u.user_id = ? LIMIT 1", [$host_id]);
            if ($assignedBranch) {
                $allowed = get_multiple_results("SELECT branch_id FROM branches WHERE branch_name = ? AND is_active = 1", [$assignedBranch['branch_name']]);
                $allowed_branch_ids_local = array_map(function($b){ return (int)$b['branch_id']; }, $allowed);
            } else {
                $host_branches = get_multiple_results("SELECT DISTINCT branch_id FROM units WHERE host_id = ?", [$host_id]);
                $allowed_branch_ids_local = array_map(function($b){ return (int)$b['branch_id']; }, $host_branches);
                if (empty($allowed_branch_ids_local)) {
                    $all = get_multiple_results("SELECT branch_id FROM branches WHERE is_active = 1");
                    $allowed_branch_ids_local = array_map(function($b){ return (int)$b['branch_id']; }, $all);
                }
            }

            // Validate branch selection
            if (!in_array((int)$branch_id, $allowed_branch_ids_local)) {
                $action_message = "You are not allowed to add units to the selected branch.";
                $action_success = false;
            } else {
                // Prepare unit data for duplicate detection
                $unit_data = [
                    'building_name' => $unit_name,
                    'street_address' => $_POST['street_address'] ?? '',
                    'unit_number' => $_POST['unit_number'] ?? '',
                    'city' => $_POST['city'] ?? '',
                    'latitude' => isset($_POST['latitude']) ? (float)$_POST['latitude'] : null,
                    'longitude' => isset($_POST['longitude']) ? (float)$_POST['longitude'] : null
                ];

                // Check for duplicates
                include_once '../includes/DuplicateDetectionEngine.php';
                $engine = new DuplicateDetectionEngine($conn);
                $analysis = $engine->analyzeUnitForDuplicates(null, $unit_data);

                if ($analysis['overall_risk'] >= 70) {
                    $action_message = "⚠️ High Risk: This listing appears to be a duplicate. Risk Score: " . round($analysis['overall_risk']) . "/100";
                    $action_success = false;
                } else {
                    // Insert unit with prepared statement (auto-approved so it's instantly visible to customers)
                    $stmt = $conn->prepare("INSERT INTO units (unit_name, host_id, branch_id, description, monthly_rate, pricing_type, max_occupancy, is_available, approval_status, created_at, sqm, bed_type, num_beds, num_bathrooms, street_address, unit_number, city, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $is_available = ($status === 'available' ? 1 : 0);
                    $stmt->bind_param("siisdsiidsiisssdd", $unit_name, $host_id, $branch_id, $description, $price, $pricing_type, $capacity, $is_available, $sqm, $bed_type, $num_beds, $num_bathrooms, $street_address, $unit_number, $city, $latitude, $longitude);

                    if ($stmt->execute()) {
                        $unit_id = $stmt->insert_id;

                        // Handle photo uploads
                        if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                            $photos_dir = '../uploads/unit_images/';
                            if (!is_dir($photos_dir)) mkdir($photos_dir, 0755, true);

                            $upload_count = 0;
                            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                                if ($upload_count >= 5) break;
                                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                                    $ext = strtolower(pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                                        $filename = 'unit_' . $unit_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                                        $filepath = $photos_dir . $filename;

                                        if (move_uploaded_file($tmp_name, $filepath)) {
                                            $upload_count++;
                                            // Compute image fingerprint
                                            include_once '../includes/ImageFingerprinting.php';
                                            $img_fp = new ImageFingerprinting($conn);
                                            $img_fp->registerImage($unit_id, $filepath, 'unit_photo');
                                        }
                                    }
                                }
                            }
                        }

                        $action_message = "Unit added successfully! It is now visible to renters.";
                        $action_success = true;
                    } else {
                        $action_message = "Failed to add unit: " . $stmt->error;
                        $action_success = false;
                    }
                }
            }
        }
        
        // Edit Unit
        else if ($action === 'edit_unit') {
            $unit_id = sanitize_input($_POST['unit_id']);
            $unit_name = sanitize_input($_POST['unit_name']);
            $description = sanitize_input($_POST['description'] ?? '');
            $price = sanitize_input($_POST['price']);
            $pricing_type = sanitize_input($_POST['pricing_type'] ?? 'monthly');
            $capacity = sanitize_input($_POST['capacity']);
            $status = sanitize_input($_POST['status']);
            
            // New unit details
            $sqm = isset($_POST['sqm']) && $_POST['sqm'] !== '' ? (float)$_POST['sqm'] : null;
            $bed_type = sanitize_input($_POST['bed_type'] ?? '');
            $num_beds = isset($_POST['num_beds']) && $_POST['num_beds'] !== '' ? (int)$_POST['num_beds'] : 1;
            $num_bathrooms = isset($_POST['num_bathrooms']) && $_POST['num_bathrooms'] !== '' ? (int)$_POST['num_bathrooms'] : 1;
            $street_address = sanitize_input($_POST['street_address'] ?? '');
            $unit_number = sanitize_input($_POST['unit_number'] ?? '');
            $city = sanitize_input($_POST['city'] ?? '');
            $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
            $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
            $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;
            
            // Verify unit belongs to host
            $unit = get_single_result("SELECT * FROM units WHERE unit_id = ? AND host_id = ?", [$unit_id, $host_id]);
            
            if ($unit) {
                $stmt = $conn->prepare("
                    UPDATE units 
                    SET unit_name = ?, 
                        description = ?, 
                        monthly_rate = ?, 
                        pricing_type = ?,
                        max_occupancy = ?,
                        is_available = ?,
                        sqm = ?,
                        bed_type = ?,
                        num_beds = ?,
                        num_bathrooms = ?,
                        street_address = ?,
                        unit_number = ?,
                        city = ?,
                        latitude = ?,
                        longitude = ?,
                        branch_id = ?
                    WHERE unit_id = ?
                ");
                $is_available = ($status === 'available' ? 1 : 0);
                $stmt->bind_param("ssdsiidsiisssddii", $unit_name, $description, $price, $pricing_type, $capacity, $is_available, $sqm, $bed_type, $num_beds, $num_bathrooms, $street_address, $unit_number, $city, $latitude, $longitude, $branch_id, $unit_id);
                
                if ($stmt->execute()) {
                    // Update geolocation if provided
                    if ($latitude && $longitude) {
                        include_once '../includes/GeolocationValidation.php';
                        $geo = new GeolocationValidation($conn);
                        $geo->registerGeolocation($unit_id, $latitude, $longitude);
                    }
                    
                    // Handle photo uploads (for changing/updating photos)
                    if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                        // Remove old images to perfectly mirror the new upload selection
                        $old_images = get_multiple_results("SELECT image_path FROM unit_images WHERE unit_id = ?", [$unit_id]);
                        foreach ($old_images as $old) {
                            if (file_exists($old['image_path'])) {
                                unlink($old['image_path']);
                            }
                        }
                        $conn->query("DELETE FROM unit_images WHERE unit_id = " . (int)$unit_id);

                        $photos_dir = '../uploads/unit_images/';
                        if (!is_dir($photos_dir)) mkdir($photos_dir, 0755, true);

                        $upload_count = 0;
                        foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                            if ($upload_count >= 5) break;
                            if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                                $ext = strtolower(pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                                    $filename = 'unit_' . $unit_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                                    $filepath = $photos_dir . $filename;

                                    if (move_uploaded_file($tmp_name, $filepath)) {
                                        $upload_count++;
                                        // Compute image fingerprint
                                        include_once '../includes/ImageFingerprinting.php';
                                        $img_fp = new ImageFingerprinting($conn);
                                        $img_fp->registerImage($unit_id, $filepath, 'unit_photo');
                                    }
                                }
                            }
                        }
                    }
                    
                    $action_message = "Unit updated successfully!";
                    $action_success = true;
                } else {
                    $action_message = "Failed to update unit: " . $stmt->error;
                    $action_success = false;
                }
            } else {
                $action_message = "Unit not found or you don't have permission to edit it";
                $action_success = false;
            }
        }
        
        // Delete Unit
        else if ($action === 'delete_unit') {
            $unit_id = sanitize_input($_POST['unit_id']);
            
            // Verify unit belongs to host
            $unit = get_single_result("SELECT * FROM units WHERE unit_id = ? AND host_id = ?", [$unit_id, $host_id]);
            
            if ($unit) {
                $conn->query("DELETE FROM units WHERE unit_id = $unit_id");
                
                $action_message = "Unit deleted successfully!";
                $action_success = true;
            } else {
                $action_message = "Unit not found or you don't have permission to delete it";
            }
        }
        
        // Prevent form resubmission on page refresh (PRG pattern)
        if ($action_message !== '') {
            $_SESSION['action_message'] = $action_message;
            $_SESSION['action_success'] = $action_success;
            header("Location: unit_management.php");
            exit;
        }
    }
}

// Fetch all units for this host
$units = get_multiple_results("
    SELECT u.*, b.branch_name, COUNT(r.reservation_id) as total_bookings
    FROM units u
    INNER JOIN branches b ON u.branch_id = b.branch_id
    LEFT JOIN reservations r ON u.unit_id = r.unit_id AND r.status IN ('confirmed', 'checked_in')
    WHERE u.host_id = ?
    GROUP BY u.unit_id
    ORDER BY u.created_at DESC
", [$host_id]);

// Fetch branches for dropdown
// Determine allowed branches for this host.
// Hosts are assigned to a branch by admin (branches.host_id). Hosts may be allowed
// to operate across branches with the same brand (branch_name). We fetch the
// assigned branch and then load all branches with the same branch_name.
$assignedBranch = get_single_result("SELECT b.* FROM branches b JOIN users u ON b.branch_id = u.branch_id WHERE u.user_id = ? LIMIT 1", [$host_id]);
if ($assignedBranch) {
    // Load all branches that share the same brand/name (different locations)
    $branches = get_multiple_results("SELECT * FROM branches WHERE branch_name = ? AND is_active = 1 ORDER BY city", [$assignedBranch['branch_name']]);
} else {
    // Fallback: show branches this host already has units in
    $branches = get_multiple_results(
        "SELECT DISTINCT b.* FROM branches b
         INNER JOIN units u ON b.branch_id = u.branch_id
         WHERE u.host_id = ?",
        [$host_id]
    );

    // If still empty, show all active branches (admin may allow assigning)
    if (empty($branches)) {
        $branches = get_multiple_results("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name, city");
    }
}

// Build allowed branch IDs for quick validation when saving units
$allowed_branch_ids = array_map(function($b){ return (int)$b['branch_id']; }, $branches);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Management - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css">
    <link rel="stylesheet" href="../assets/css/host/unit_management.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
        }
        
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        .content {
            flex: 1;
            padding: 30px;
            max-width: 100%;
            width: 100%;
            margin-left: 280px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1><i class="fas fa-building"></i> Unit Management</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddUnitModal()">
                        <i class="fas fa-plus"></i> Add New Unit
                    </button>
                </div>
            </div>
            
            <!-- Success/Error Alert -->
            <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="searchInput" placeholder="Unit name or ID...">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Under Maintenance</option>
                    </select>
                </div>
                <div class="view-toggle" style="margin-left: auto;">
                    <button class="view-btn active" data-view="grid">
                        <i class="fas fa-th"></i> Grid
                    </button>
                    <button class="view-btn" data-view="list">
                        <i class="fas fa-list"></i> List
                    </button>
                </div>
            </div>
            
            <!-- Units Container -->
            <?php if (!empty($units)): ?>
            <div class="units-container" id="unitsContainer">
                <?php foreach ($units as $unit): 
                    $status = $unit['is_available'] ? 'available' : 'maintenance';
                    if ($unit['total_bookings'] > 0) {
                        $status = 'occupied';
                    }
                    $status_label = ucfirst(str_replace('_', ' ', $status));
                ?>
                <div class="unit-card" data-unit-id="<?php echo $unit['unit_id']; ?>" data-status="<?php echo $status; ?>" data-name="<?php echo strtolower($unit['unit_name']); ?>">
                    <div class="unit-image">
                        <?php 
                        $image_path = get_single_result("SELECT image_path FROM unit_images WHERE unit_id = ? ORDER BY created_at DESC LIMIT 1", [$unit['unit_id']]);
                        if ($image_path && !empty($image_path['image_path'])): 
                        ?>
                            <img src="<?php echo htmlspecialchars($image_path['image_path']); ?>" alt="<?php echo htmlspecialchars($unit['unit_name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px 12px 0 0;">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; ?>
                        <span class="unit-status status-<?php echo $status; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </div>
                    
                    <div class="unit-content">
                        <div class="unit-header">
                            <div class="unit-name"><?php echo htmlspecialchars($unit['unit_name']); ?></div>
                            <div class="unit-branch"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($unit['branch_name']); ?></div>
                        </div>
                        
                        <?php if ($unit['description']): ?>
                        <div style="font-size: 13px; color: #666; margin-bottom: 12px; line-height: 1.4;">
                            <?php echo htmlspecialchars(substr($unit['description'], 0, 80)) . (strlen($unit['description']) > 80 ? '...' : ''); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="unit-info">
                            <div class="info-item">
                                <div class="info-label">Base Rate</div>
                                <div class="info-value">₱<?php echo number_format($unit['monthly_rate'] ?? 0); ?> / <?php echo ($unit['pricing_type'] ?? 'monthly') === 'monthly' ? 'month' : 'night'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Capacity</div>
                                <div class="info-value"><?php echo $unit['max_occupancy']; ?> Guests</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total Bookings</div>
                                <div class="info-value"><?php echo $unit['total_bookings']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Updated</div>
                                <div class="info-value"><?php echo date('M d', strtotime($unit['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="unit-actions">
                            <button class="btn btn-info btn-sm" onclick="openViewModal(<?php echo $unit['unit_id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?php echo $unit['unit_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a class="btn btn-success btn-sm" href="property_settings.php?unit_id=<?php echo $unit['unit_id']; ?>&tab=pricing">
                                <i class="fas fa-sliders-h"></i> Pricing
                            </a>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteConfirm(<?php echo $unit['unit_id']; ?>, '<?php echo htmlspecialchars($unit['unit_name']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-home"></i>
                <h2>No Units Yet</h2>
                <p>Start by adding your first condo unit to manage bookings and reservations.</p>
                <button class="btn btn-primary" onclick="openAddUnitModal()">
                </i> Add Your First Unit
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add/Edit Unit Modal -->
    <div id="unitModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Unit</h5>
                    <button type="button" class="btn-close" onclick="closeUnitModal()"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="unitForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add_unit">
                <input type="hidden" name="unit_id" id="unitId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Branch</label>
                        <select name="branch_id" id="branchSelect" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['branch_id']; ?>">
                                    <?php echo htmlspecialchars($branch['branch_name'] . ' — ' . ($branch['city'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unit Name / No.</label>
                        <input type="text" name="unit_name" id="unitName" placeholder="e.g., Unit 101 or Penthouse" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Street Address</label>
                        <input type="text" name="street_address" id="streetAddress" placeholder="e.g., 123 Main Street" required>
                    </div>
                    <div class="form-group">
                        <label>Unit Number</label>
                        <input type="text" name="unit_number" id="unitNumber" placeholder="e.g., 101, 201, Suite A" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" id="city" placeholder="e.g., Manila, Makati" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map"></i> Property Location</label>
                    <div id="unitMap" style="width: 100%; height: 300px; border-radius: 8px; border: 2px solid #dee2e6; margin-bottom: 10px; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); display: flex; align-items: center; justify-content: center;">
                        <div style="text-align: center; color: #666;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #3498db; margin-bottom: 10px; display: block;"></i>
                            <p>Loading map...</p>
                        </div>
                    </div>
                    <small class="form-text text-muted d-block mb-2">Click on map to select property location</small>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="text" class="form-control" id="latDisplay" placeholder="Latitude">
                        </div>
                        <div class="col-6">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="text" class="form-control" id="lngDisplay" placeholder="Longitude">
                        </div>
                    </div>
                </div>
                
                
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Unit Photos</label>
                    <div style="border: 2px dashed #ddd; border-radius: 6px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s;" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: #3498db; margin-bottom: 10px; display: block;"></i>
                        <div style="font-weight: 500; color: #2c3e50; margin-bottom: 5px;">Drag photos here or click to browse</div>
                        <div style="font-size: 12px; color: #999;">Support: JPG, PNG (Max 5MB per image)</div>
                        <input type="file" name="photos[]" id="photoInput" multiple accept="image/*" style="display: none;">
                    </div>
                    <div id="photoPreview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px;"></div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Price Rate (₱)</label>
                        <input type="number" name="price" id="price" placeholder="e.g., 2500" required>
                    </div>
                    <div class="form-group">
                        <label>Pricing Type</label>
                        <select name="pricing_type" id="pricingType" required>
                            <option value="daily">Daily / Nightly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Capacity (Guests)</label>
                        <input type="number" name="capacity" id="capacity" placeholder="e.g., 4" min="1" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Floor Area (sqm)</label>
                        <input type="number" name="sqm" id="sqm" placeholder="e.g., 35.5" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Bed Type</label>
                        <select name="bed_type" id="bedType">
                            <option value="">Select Bed Type</option>
                            <option value="single">Single Bed</option>
                            <option value="double deck">Double Deck / Bunk Bed</option>
                            <option value="queen">Queen Size</option>
                            <option value="king">King Size</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Number of Beds</label>
                        <input type="number" name="num_beds" id="numBeds" placeholder="e.g., 1" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label>Number of Bathrooms</label>
                        <input type="number" name="num_bathrooms" id="numBathrooms" placeholder="e.g., 1" min="1" value="1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status" required>
                        <option value="available">Available</option>
                        <option value="maintenance">Under Maintenance</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Amenities</label>
                    <div class="amenities-list">
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="WiFi" id="amenity-wifi">
                            <label for="amenity-wifi">WiFi</label>
                        </div>
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="Air Conditioning" id="amenity-ac">
                            <label for="amenity-ac">Air Conditioning</label>
                        </div>
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="Kitchen" id="amenity-kitchen">
                            <label for="amenity-kitchen">Kitchen</label>
                        </div>
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="TV" id="amenity-tv">
                            <label for="amenity-tv">TV</label>
                        </div>
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="Washing Machine" id="amenity-washer">
                            <label for="amenity-washer">Washing Machine</label>
                        </div>
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="Pool" id="amenity-pool">
                            <label for="amenity-pool">Pool</label>
                        </div>
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="Gym" id="amenity-gym">
                            <label for="amenity-gym">Gym</label>
                        </div>
                        <div class="amenity-checkbox">
                            <input type="checkbox" name="amenities" value="Parking" id="amenity-parking">
                            <label for="amenity-parking">Parking</label>
                        </div>
                    </div>
                </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeUnitModal()">Cancel</button>
                    <button type="submit" form="unitForm" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Unit
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Unit Modal -->
    <div id="viewModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unit Details</h5>
                    <button type="button" class="btn-close" onclick="closeViewModal()"></button>
                </div>
                <div class="modal-body" id="viewContent"></div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content confirmation-modal">
            <div class="modal-header" style="justify-content: center; margin-bottom: 30px;">
                <span><i class="fas fa-exclamation-triangle" style="color: #e74c3c; margin-right: 10px;"></i> Confirm Delete</span>
            </div>
            <p>Are you sure you want to delete <strong id="deleteUnitName"></strong>?</p>
            <p style="color: #999; font-size: 13px;">This action cannot be undone.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete_unit">
                <input type="hidden" name="unit_id" id="deleteUnitId">
                <div class="form-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirm()" style="min-width: 120px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="min-width: 120px;">Delete Unit</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../assets/js/host/unit_management.js"></script>

    <script>
        // Modal Management Functions
        function openAddUnitModal() {
            document.getElementById('unitForm').reset();
            document.getElementById('formAction').value = 'add_unit';
            document.getElementById('modalTitle').textContent = 'Add New Unit';
            document.getElementById('unitId').value = '';
            mapInitialized = false;
            const unitModal = new bootstrap.Modal(document.getElementById('unitModal'));
            unitModal.show();
        }

        function openEditModal(unitId) {
            // Fetch unit data and populate form
            fetch(`../ajax/get_unit.php?unit_id=${unitId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const unit = data.unit;
                        document.getElementById('unitForm').reset();
                        document.getElementById('formAction').value = 'edit_unit';
                        document.getElementById('modalTitle').textContent = 'Edit Unit';
                        document.getElementById('unitId').value = unitId;
                        document.getElementById('unitName').value = unit.unit_name;
                        document.getElementById('branchSelect').value = unit.branch_id;
                        document.getElementById('streetAddress').value = unit.street_address || '';
                        document.getElementById('unitNumber').value = unit.unit_number || '';
                        document.getElementById('city').value = unit.city || '';
                        document.getElementById('latitude').value = unit.latitude || '';
                        document.getElementById('longitude').value = unit.longitude || '';
                        document.getElementById('latDisplay').value = unit.latitude || '';
                        document.getElementById('lngDisplay').value = unit.longitude || '';
                        document.getElementById('price').value = unit.monthly_rate || 0;
                        document.getElementById('pricingType').value = unit.pricing_type || 'monthly';
                        document.getElementById('capacity').value = unit.max_occupancy;
                        document.getElementById('status').value = unit.is_available ? 'available' : 'maintenance';
                        document.getElementById('sqm').value = unit.sqm || '';
                        document.getElementById('bedType').value = unit.bed_type || '';
                        document.getElementById('numBeds').value = unit.num_beds || 1;
                        document.getElementById('numBathrooms').value = unit.num_bathrooms || 1;
                        
                        mapInitialized = false;
                        const unitModal = new bootstrap.Modal(document.getElementById('unitModal'));
                        unitModal.show();
                    }
                });
        }

        function closeUnitModal() {
            const unitModal = bootstrap.Modal.getInstance(document.getElementById('unitModal'));
            if (unitModal) unitModal.hide();
        }

        function openViewModal(unitId) {
            fetch(`../ajax/get_unit_view.php?unit_id=${unitId}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('viewContent').innerHTML = html;
                    const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
                    viewModal.show();
                });
        }

        function closeViewModal() {
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
            if (viewModal) viewModal.hide();
        }

        function openDeleteConfirm(unitId, unitName) {
            document.getElementById('deleteUnitName').textContent = unitName;
            document.getElementById('deleteUnitId').value = unitId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
    <script>
        // Initialize map when modal opens
        let unitMap;
        let unitMarker;
        const defaultCenter = [14.5995, 121.0855]; // Manila, PH
        let mapInitialized = false;

        function initUnitMap() {
            if (mapInitialized || !document.getElementById('unitMap')) return;
            
            const mapElement = document.getElementById('unitMap');
            mapElement.innerHTML = '';
            
            try {
                unitMap = L.map('unitMap').setView(defaultCenter, 13);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(unitMap);

                // Load saved coordinates if editing
                const savedLat = document.getElementById('latitude').value;
                const savedLng = document.getElementById('longitude').value;
                if (savedLat && savedLng) {
                    const savedPosition = [parseFloat(savedLat), parseFloat(savedLng)];
                    unitMap.setView(savedPosition, 16);
                    placeUnitMarker(savedPosition[0], savedPosition[1]);
                }

                // Click map to place marker
                unitMap.on('click', function(e) {
                    placeUnitMarker(e.latlng.lat, e.latlng.lng);
                    reverseGeocode(e.latlng.lat, e.latlng.lng);
                });

                const streetAddress = document.getElementById('streetAddress');
                const cityInput = document.getElementById('city');
                const latDisplay = document.getElementById('latDisplay');
                const lngDisplay = document.getElementById('lngDisplay');
                
                function reverseGeocode(lat, lon) {
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.address) {
                                const city = data.address.city || data.address.town || data.address.village || data.address.county || '';
                                const road = data.address.road || '';
                                const house_number = data.address.house_number || '';
                                const suburb = data.address.suburb || data.address.neighbourhood || '';
                                
                                let street = [];
                                if (house_number) street.push(house_number);
                                if (road) street.push(road);
                                if (suburb) street.push(suburb);
                                
                                if (streetAddress && street.length > 0) streetAddress.value = street.join(', ');
                                if (cityInput && city) cityInput.value = city;
                            }
                        }).catch(e => console.log('Reverse geocoding error', e));
                }

                // Set up simple manual geocoding if place input loses focus
                function geocodeAddress() {
                    const street = streetAddress ? streetAddress.value : '';
                    const city = cityInput ? cityInput.value : '';
                    const addr = `${street}, ${city}, Philippines`.trim();
                    
                    if (addr.length > 5 && addr !== ', , Philippines') {
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr)}&limit=1`)
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.length > 0) {
                                    const lat = parseFloat(data[0].lat);
                                    const lon = parseFloat(data[0].lon);
                                    unitMap.setView([lat, lon], 16);
                                    placeUnitMarker(lat, lon);
                                }
                            }).catch(e => console.log('Geocoding error', e));
                    }
                }

                let geocodeTimeout;
                function debouncedGeocode() {
                    clearTimeout(geocodeTimeout);
                    geocodeTimeout = setTimeout(geocodeAddress, 800);
                }
                
                if(streetAddress) streetAddress.addEventListener('input', debouncedGeocode);
                if(cityInput) cityInput.addEventListener('input', debouncedGeocode);
                
                function updateMapFromCoordinates() {
                    const lat = parseFloat(latDisplay.value);
                    const lng = parseFloat(lngDisplay.value);
                    
                    if (!isNaN(lat) && !isNaN(lng)) {
                        unitMap.setView([lat, lng], 16);
                        placeUnitMarker(lat, lng);
                        reverseGeocode(lat, lng);
                    }
                }
                
                let coordTimeout;
                function debouncedCoordUpdate() {
                    clearTimeout(coordTimeout);
                    coordTimeout = setTimeout(updateMapFromCoordinates, 1000);
                }
                
                if (latDisplay) latDisplay.addEventListener('input', debouncedCoordUpdate);
                if (lngDisplay) lngDisplay.addEventListener('input', debouncedCoordUpdate);
                
                mapInitialized = true;
                
                // Fix map rendering issues in Bootstrap modals
                setTimeout(() => {
                    unitMap.invalidateSize();
                }, 100);
            } catch (error) {
                console.error('Map initialization failed:', error);
                mapElement.innerHTML = '<div style=\"display: flex; align-items: center; justify-content: center; height: 100%; flex-direction: column;\"><i class=\"fas fa-exclamation-circle\" style=\"font-size: 40px; color: #e74c3c; margin-bottom: 10px;\"></i><p style=\"color: #666; margin: 0;\">Map error</p><p style=\"font-size: 12px; color: #999; margin: 5px 0 0 0;\">Please enter coordinates manually</p></div>';
                mapInitialized = true;
            }
        }

        function placeUnitMarker(lat, lng) {
            if (unitMarker) {
                unitMap.removeLayer(unitMarker);
            }

            unitMarker = L.marker([lat, lng]).addTo(unitMap);
            unitMarker.bindPopup('Property Location').openPopup();

            // Update fields
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
            document.getElementById('latDisplay').value = lat.toFixed(6);
            document.getElementById('lngDisplay').value = lng.toFixed(6);
        }

        // Initialize map when modal opens
        document.addEventListener('DOMContentLoaded', function() {
            const unitModal = document.getElementById('unitModal');
            if (unitModal) {
                unitModal.addEventListener('shown.bs.modal', function() {
                    initUnitMap();
                    if(unitMap) { setTimeout(() => unitMap.invalidateSize(), 500); }
                });
            }
        });
    </script>
</body>
</html>
