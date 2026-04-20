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

// Handle form submissions (Add / Edit / Delete) - Kept largely original logic for robustness
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        if ($action === 'add_unit' || $action === 'edit_unit') {
            // Core fields
            $unit_id = (int)($_POST['unit_id'] ?? 0);

            // 🔐 Snapshot Integrity: Block edit if unit is pending moderation
            if ($action === 'edit_unit' && $unit_id > 0) {
                $status_check = get_single_result("SELECT approval_status FROM units WHERE unit_id = ? AND host_id = ?", [$unit_id, $host_id]);
                if ($status_check && $status_check['approval_status'] === 'pending') {
                    $_SESSION['action_message'] = "Locked: Cannot edit unit while pending moderation.";
                    $_SESSION['action_success'] = false;
                    header("Location: unit_management.php");
                    exit;
                }
            }
            $unit_name = sanitize_input($_POST['unit_name'] ?? '');
            $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
            $description = sanitize_input($_POST['description'] ?? '');
            $price_per_night = (float)($_POST['price_per_night'] ?? 0);
            $price_per_month = (float)($_POST['price_per_month'] ?? 0);
            $pricing_type = sanitize_input($_POST['pricing_type'] ?? 'nightly');
            $capacity = (int)($_POST['max_occupancy'] ?? 1);
            $street_address = sanitize_input($_POST['street_address'] ?? '');
            $unit_number = sanitize_input($_POST['unit_number'] ?? '');
            $city = sanitize_input($_POST['city'] ?? '');
            $latitude = (float)($_POST['latitude'] ?? 0);
            $longitude = (float)($_POST['longitude'] ?? 0);

            // New Airbnb-level metadata with server-side validation
            $prop_type = sanitize_input($_POST['property_type'] ?? 'Condo');
            $bed_config = sanitize_input($_POST['bed_config'] ?? 'Studio');
            $bed_details = sanitize_input($_POST['bed_details'] ?? '');
            $bath_count = (float)($_POST['bathroom_count'] ?? 1.0);
            $area = !empty($_POST['floor_area']) ? (float)$_POST['floor_area'] : null;
            $check_in = sanitize_input($_POST['check_in_time'] ?? '14:00:00');
            $check_out = sanitize_input($_POST['check_out_time'] ?? '12:00:00');
            $min_stay = (int)($_POST['min_stay'] ?? 1);
            $max_stay = (int)($_POST['max_stay'] ?? 30);
            $deposit = (float)($_POST['security_deposit'] ?? 0.00);
            $rules = sanitize_input($_POST['house_rules'] ?? '');
            $utilities = sanitize_input($_POST['utility_info'] ?? '');
            $parking = sanitize_input($_POST['parking_info'] ?? 'None');
            $booking_type = sanitize_input($_POST['booking_type'] ?? 'instant');
            $visibility = sanitize_input($_POST['status_visibility'] ?? 'active');

            if ($action === 'add_unit') {
                $sql = "INSERT INTO units (
                            unit_name, host_id, branch_id, description, 
                            price_per_night, price_per_month, pricing_type, max_occupancy, 
                            is_available, approval_status, street_address, unit_number, city, 
                            latitude, longitude, property_type, bed_config, bed_details, 
                            bathroom_count, floor_area, check_in_time, check_out_time, 
                            min_stay, max_stay, security_deposit, house_rules, utility_info, 
                            parking_info, booking_type, status_visibility, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siisddsisssddsssddssiidsssss", 
                    $unit_name, $host_id, $branch_id, $description, 
                    $price_per_night, $price_per_month, $pricing_type, $capacity, 
                    $street_address, $unit_number, $city, 
                    $latitude, $longitude, $prop_type, $bed_config, $bed_details, 
                    $bath_count, $area, $check_in, $check_out, 
                    $min_stay, $max_stay, $deposit, $rules, $utilities, 
                    $parking, $booking_type, $visibility
                );
            } else {
                $sql = "UPDATE units SET 
                            unit_name = ?, branch_id = ?, description = ?, 
                            price_per_night = ?, price_per_month = ?, pricing_type = ?, max_occupancy = ?, 
                            street_address = ?, unit_number = ?, city = ?, 
                            latitude = ?, longitude = ?, property_type = ?, bed_config = ?, 
                            bed_details = ?, bathroom_count = ?, floor_area = ?, 
                            check_in_time = ?, check_out_time = ?, min_stay = ?, max_stay = ?, 
                            security_deposit = ?, house_rules = ?, utility_info = ?, 
                            parking_info = ?, booking_type = ?, status_visibility = ?, 
                            approval_status = 'pending' 
                        WHERE unit_id = ? AND host_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sisddsisssddsssddssiidsssssii", 
                    $unit_name, $branch_id, $description, 
                    $price_per_night, $price_per_month, $pricing_type, $capacity, 
                    $street_address, $unit_number, $city, 
                    $latitude, $longitude, $prop_type, $bed_config, 
                    $bed_details, $bath_count, $area, 
                    $check_in, $check_out, $min_stay, $max_stay, 
                    $deposit, $rules, $utilities, 
                    $parking, $booking_type, $visibility, 
                    $unit_id, $host_id
                );
            }

            if ($stmt->execute()) {
                $uid = ($action === 'add_unit') ? $stmt->insert_id : $unit_id;
                
                // Sync Amenities
                if (isset($_POST['amenities']) && is_array($_POST['amenities'])) {
                    syncUnitAmenities($uid, array_map('intval', $_POST['amenities']));
                }

                $action_message = "Unit " . ($action === 'add_unit' ? "added" : "updated") . " successfully and is pending approval.";
                $action_success = true;
            } else {
                $action_message = "Failed to process unit: " . $stmt->error;
            }
        }
        else if ($action === 'delete_unit') {
            $unit_id = (int)$_POST['unit_id'];

            // 🔐 Snapshot Integrity: Block delete if unit is pending moderation
            $status_check = get_single_result("SELECT approval_status FROM units WHERE unit_id = ? AND host_id = ?", [$unit_id, $host_id]);
            if ($status_check && $status_check['approval_status'] === 'pending') {
                $_SESSION['action_message'] = "Locked: Cannot delete unit while pending moderation.";
                $_SESSION['action_success'] = false;
                header("Location: unit_management.php");
                exit;
            }

            if (execute_query("DELETE FROM units WHERE unit_id = ? AND host_id = ?", [$unit_id, $host_id])) {
                $action_message = "Unit deleted successfully.";
                $action_success = true;
            }
        }

        $_SESSION['action_message'] = $action_message;
        $_SESSION['action_success'] = $action_success;
        header("Location: unit_management.php");
        exit;
    }
}

// Fetch Data
$units = get_multiple_results("
    SELECT u.*, b.branch_name, COUNT(r.reservation_id) as active_bookings
    FROM units u
    JOIN branches b ON u.branch_id = b.branch_id
    LEFT JOIN reservations r ON u.unit_id = r.unit_id AND r.status IN ('confirmed', 'checked_in')
    WHERE u.host_id = ?
    GROUP BY u.unit_id
", [$host_id]);

$branches = get_multiple_results("SELECT branch_id, branch_name, city FROM branches WHERE is_active = 1");
$amenities_catalog = get_multiple_results("SELECT * FROM amenities ORDER BY name ASC");

$page_title = 'Unit Management';
include '../templates/host_layout_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title mb-1"><i class="fas fa-building me-2"></i>My Units</h1>
        <p class="text-muted">Manage your property listings and approval status</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openAddUnitModal()">
            <i class="fas fa-plus me-2"></i> Add New Unit
        </button>
    </div>
</div>

<?php if ($action_message): ?>
    <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo $action_message; ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Units Grid -->
<div class="stats-grid"> <!-- Using grid-unified.css grid logic -->
    <?php foreach ($units as $unit): 
        $status_class = match($unit['approval_status']) { 'approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary' };
    ?>
    <div class="card-modern p-0 overflow-hidden flex-column align-items-stretch hover-lift" style="min-height: 440px;">
        <div class="position-relative" style="height: 180px;">
            <?php 
                $img = get_single_result("SELECT image_path FROM unit_images WHERE unit_id = ? LIMIT 1", [$unit['unit_id']]);
                if ($img):
            ?>
                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="w-100 h-100" style="object-fit: cover;">
            <?php else: ?>
                <div class="w-100 h-100 bg-light d-flex align-items-center justify-content-center"><i class="fas fa-building fa-3x text-muted opacity-25"></i></div>
            <?php endif; ?>
            <span class="badge bg-<?php echo $status_class; ?> position-absolute top-0 end-0 m-3 shadow-sm">
                <?php echo strtoupper($unit['approval_status']); ?>
            </span>
        </div>
        
        <div class="p-4 flex-grow-1">
            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($unit['unit_name']); ?></h5>
            <p class="text-muted small mb-3"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($unit['branch_name']); ?> • #<?php echo $unit['unit_number']; ?></p>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-primary fw-bold" style="font-size: 1.1rem;">
                    ₱<?php echo number_format($unit['price_per_night'] ?: $unit['price_per_month'], 0); ?>
                    <small class="text-muted fw-normal" style="font-size: 0.75rem;">/<?php echo $unit['pricing_type']; ?></small>
                </div>
                <div class="small text-muted"><i class="fas fa-users me-1"></i> <?php echo $unit['max_occupancy']; ?> Max</div>
            </div>
            
            <div class="small p-2 bg-light rounded border mb-3">
                <div class="d-flex justify-content-between mb-1"><span>Active Bookings</span><span class="badge bg-info text-dark"><?php echo $unit['active_bookings']; ?></span></div>
                <div class="d-flex justify-content-between"><span>Availability</span><span class="text-<?php echo $unit['is_available'] ? 'success' : 'danger'; ?>"><?php echo $unit['is_available'] ? 'Online' : 'Hidden'; ?></span></div>
            </div>
        </div>

        <div class="px-4 pb-4 mt-auto">
            <div class="d-grid gap-2">
                <a href="property_settings.php?unit_id=<?php echo $unit['unit_id']; ?>" class="btn btn-primary btn-sm rounded-pill shadow-sm">
                    <i class="fas fa-cog me-1"></i> Pricing & Configuration
                </a>
                <div class="row g-2">
                    <div class="col-8">
                        <?php if ($unit['approval_status'] === 'pending'): ?>
                            <button class="btn btn-outline-secondary w-100 btn-sm rounded-pill disabled" title="Locked: Under moderation review">
                                <i class="fas fa-lock me-1"></i> Locked (Pending)
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100 btn-sm rounded-pill" onclick="openEditModal(<?php echo $unit['unit_id']; ?>)">
                                <i class="fas fa-edit me-1"></i> Edit Details
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-outline-danger w-100 btn-sm rounded-pill <?php echo $unit['approval_status'] === 'pending' ? 'disabled' : ''; ?>" 
                                onclick="<?php echo $unit['approval_status'] === 'pending' ? 'return false;' : "openDeleteConfirm({$unit['unit_id']}, '" . addslashes($unit['unit_name']) . "')"; ?>"
                                <?php echo $unit['approval_status'] === 'pending' ? 'title="Locked"' : ''; ?>>
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; if (empty($units)) echo '<div class="col-12 text-center py-5"><i class="fas fa-building fa-5x text-muted mb-3 opacity-25"></i><p class="h4 text-muted">No units found. Click "Add New Unit" to begin.</p></div>'; ?>
</div>

<!-- Modal: Add/Edit Unit -->
<div class="modal fade" id="unitModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="unitForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Unit Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add_unit">
                    <input type="hidden" name="unit_id" id="unitId">

                    <!-- Tabbed Navigation with mobile scroll -->
                    <ul class="nav nav-pills mb-4 bg-light p-2 rounded flex-nowrap overflow-auto" id="unitTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active py-2 px-3 small fw-bold text-nowrap" data-bs-toggle="tab" data-bs-target="#tab-general" type="button">1. General</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 px-3 small fw-bold text-nowrap" data-bs-toggle="tab" data-bs-target="#tab-config" type="button">2. Configuration</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 px-3 small fw-bold text-nowrap" data-bs-toggle="tab" data-bs-target="#tab-rules" type="button">3. Rules & Pricing</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 px-3 small fw-bold text-nowrap" data-bs-toggle="tab" data-bs-target="#tab-amenities" type="button">4. Amenities</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="unitTabsContent">
                        <!-- TAB 1: GENERAL -->
                        <div class="tab-pane fade show active" id="tab-general">
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label fw-bold small uppercase">Property / Building Name</label>
                                    <input type="text" name="unit_name" id="unitName" class="form-control bg-light" placeholder="e.g. Amaia Skies Tower 1" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold small uppercase">Branch</label>
                                    <select name="branch_id" id="branchSelect" class="form-select bg-light" required>
                                        <?php foreach ($branches as $b): ?>
                                            <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small uppercase">Description</label>
                                    <textarea name="description" id="unitDesc" class="form-control bg-light" rows="3" placeholder="Tell guests what makes your place special..."></textarea>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label fw-bold small uppercase">Street Address</label>
                                    <input type="text" name="street_address" id="streetAddress" class="form-control bg-light" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small uppercase">Unit #</label>
                                    <input type="text" name="unit_number" id="unitNumber" class="form-control bg-light" placeholder="e.g. 10B" required>
                                </div>
                                <div class="col-12 mt-4">
                                    <label class="form-label fw-bold small uppercase text-primary border-bottom pb-2 w-100"><i class="fas fa-map-marker-alt me-1"></i> Precise Location Picker</label>
                                    <div id="unitMap" class="map-loading mb-2" style="height: 300px; border-radius: 12px; border: 1px solid #ddd;"></div>
                                    <div class="row g-2">
                                        <div class="col-6"><input type="text" name="latitude" id="latitude" class="form-control form-control-sm bg-light text-center" readonly required placeholder="Lat"></div>
                                        <div class="col-6"><input type="text" name="longitude" id="longitude" class="form-control form-control-sm bg-light text-center" readonly required placeholder="Lng"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: CONFIGURATION -->
                        <div class="tab-pane fade" id="tab-config">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small uppercase">Property Type</label>
                                    <select name="property_type" id="propType" class="form-select bg-light">
                                        <option value="Condo">Condo</option>
                                        <option value="Apartment">Apartment</option>
                                        <option value="Studio">Studio Unit</option>
                                        <option value="House">House</option>
                                        <option value="Room">Room Only</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small uppercase">Bed Configuration</label>
                                    <select name="bed_config" id="bedConfig" class="form-select bg-light">
                                        <option value="Studio">Studio Layout</option>
                                        <option value="1BR">1 Bedroom</option>
                                        <option value="2BR">2 Bedroom</option>
                                        <option value="Shared">Shared Room</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small uppercase">Sleeping Arrangements</label>
                                    <input type="text" name="bed_details" id="bedDetails" class="form-control bg-light" placeholder="e.g. 1 Queen Bed, 1 Sofa Bed">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small uppercase">Bathrooms</label>
                                    <input type="number" step="0.5" name="bathroom_count" id="bathCount" class="form-control bg-light" value="1.0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small uppercase">Area (sqm)</label>
                                    <input type="number" name="floor_area" id="floorArea" class="form-control bg-light" placeholder="e.g. 28">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small uppercase">Max Capacity</label>
                                    <input type="number" name="capacity" id="capacity" class="form-control bg-light" min="1" required>
                                </div>
                                <div class="col-12 mt-3">
                                    <label class="form-label fw-bold small uppercase">Parking Availability</label>
                                    <select name="parking_info" id="parkingInfo" class="form-select bg-light">
                                        <option value="None">No Parking</option>
                                        <option value="Included">Included</option>
                                        <option value="Paid">Paid Parking</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: RULES & PRICING -->
                        <div class="tab-pane fade" id="tab-rules">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small uppercase text-primary">Base Price (₱)</label>
                                    <input type="number" name="price" id="price" class="form-control border-primary" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small uppercase text-primary">Pricing Mode</label>
                                    <select name="pricing_type" id="pricingType" class="form-select border-primary">
                                        <option value="nightly">Per Night</option>
                                        <option value="monthly">Per Month</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small uppercase">Security Deposit (₱)</label>
                                    <input type="number" name="security_deposit" id="deposit" class="form-control bg-light" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small uppercase">Booking Type</label>
                                    <select name="booking_type" id="bookingType" class="form-select bg-light">
                                        <option value="instant">Instant Book</option>
                                        <option value="manual">Manual Approval</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small uppercase">Min Stay</label>
                                    <input type="number" name="min_stay" id="minStay" class="form-control bg-light" value="1">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small uppercase">Max Stay</label>
                                    <input type="number" name="max_stay" id="maxStay" class="form-control bg-light" value="30">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small uppercase">Check-In</label>
                                    <input type="time" name="check_in_time" id="checkIn" class="form-control bg-light" value="14:00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small uppercase">Check-Out</label>
                                    <input type="time" name="check_out_time" id="checkOut" class="form-control bg-light" value="12:00">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small uppercase">House Rules</label>
                                    <textarea name="house_rules" id="houseRules" class="form-control bg-light" rows="2" placeholder="e.g. No smoking, No pets..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small uppercase">Visibility</label>
                                    <select name="status_visibility" id="visibility" class="form-select bg-light">
                                        <option value="active">Active (Visible)</option>
                                        <option value="hidden">Hidden</option>
                                        <option value="maintenance">Under Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: AMENITIES -->
                        <div class="tab-pane fade" id="tab-amenities">
                            <label class="form-label fw-bold small uppercase mb-3">Essentials & Amenities</label>
                            <div class="row g-2">
                                <?php foreach ($amenities_catalog as $a): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check card-modern p-2 px-3 m-0 shadow-none border bg-light d-flex align-items-center gap-2">
                                            <input class="form-check-input ms-0" type="checkbox" name="amenities[]" value="<?php echo $a['id']; ?>" id="am-<?php echo $a['id']; ?>">
                                            <label class="form-check-label small mb-0 fw-bold" for="am-<?php echo $a['id']; ?>">
                                                <?php echo htmlspecialchars($a['name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4">
                                <label class="form-label fw-bold small uppercase">Internet & Utilities</label>
                                <textarea name="utility_info" id="utilityInfo" class="form-control bg-light" rows="2" placeholder="e.g. 50Mbps Fiber WiFi, Electricity included..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Delete Unit?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <input type="hidden" name="action" value="delete_unit">
                    <input type="hidden" name="unit_id" id="deleteUnitId">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3 opacity-25"></i>
                    <p class="mb-0">Are you sure you want to delete <b id="deleteUnitName"></b>?<br><small class="text-muted">This cannot be undone.</small></p>
                </div>
                <div class="modal-footer flex-nowrap p-0 border-0">
                    <button type="button" class="btn btn-light w-100 rounded-0" style="padding: 12px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger w-100 rounded-0" style="padding: 12px;">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
ob_start();
?>
<script>
    let unitModal;
    let deleteModal;
    let mapInitialized = false;

    $(document).ready(function() {
        unitModal = new bootstrap.Modal(document.getElementById('unitModal'));
        deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

        // Initialize Map when modal opens
        document.getElementById('unitModal').addEventListener('shown.bs.modal', function() {
            if (!mapInitialized) {
                const lat = parseFloat($('#latitude').val()) || 14.5995;
                const lng = parseFloat($('#longitude').val()) || 120.9842;
                
                BookIT.Map.init('unitMap', { center: [lat, lng], zoom: 15 });
                BookIT.Map.enablePicker((res) => {
                    $('#latitude').val(res.lat);
                    $('#longitude').val(res.lng);
                }, [lat, lng]);
                mapInitialized = true;
            }
        });
    });

    function openAddUnitModal() {
        $('#unitForm')[0].reset();
        $('#formAction').val('add_unit');
        $('#modalTitle').text('Add New Unit');
        $('#latitude').val('');
        $('#longitude').val('');
        mapInitialized = false;
        unitModal.show();
    }

    function openEditModal(unitId) {
        fetch(`../ajax/get_unit.php?unit_id=${unitId}`)
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    const u = data.unit;
                    $('#unitId').val(unitId);
                    $('#formAction').val('edit_unit');
                    $('#modalTitle').text('Edit: ' + u.unit_name);
                    $('#unitName').val(u.unit_name);
                    $('#branchSelect').val(u.branch_id);
                    $('#streetAddress').val(u.street_address);
                    $('#unitNumber').val(u.unit_number);
                    $('#latitude').val(u.latitude);
                    $('#longitude').val(u.longitude);
                    $('#price').val(u.pricing_type === 'monthly' ? u.price_per_month : u.price_per_night);
                    $('#pricingType').val(u.pricing_type);
                    $('#capacity').val(u.max_occupancy);
                    
                    // New Fields Population
                    $('#unitDesc').val(u.description);
                    $('#propType').val(u.property_type);
                    $('#bedConfig').val(u.bed_config);
                    $('#bedDetails').val(u.bed_details);
                    $('#bathCount').val(u.bathroom_count);
                    $('#floorArea').val(u.floor_area);
                    $('#deposit').val(u.security_deposit);
                    $('#bookingType').val(u.booking_type);
                    $('#minStay').val(u.min_stay);
                    $('#maxStay').val(u.max_stay);
                    $('#checkIn').val(u.check_in_time);
                    $('#checkOut').val(u.check_out_time);
                    $('#houseRules').val(u.house_rules);
                    $('#utilityInfo').val(u.utility_info);
                    $('#parkingInfo').val(u.parking_info);
                    $('#visibility').val(u.status_visibility);

                    // Sync Amenities
                    document.querySelectorAll('[name="amenities[]"]').forEach(cb => {
                        cb.checked = u.amenity_ids.includes(parseInt(cb.value));
                    });

                    // Reset to first tab
                    bootstrap.Tab.getInstance(document.querySelector('#unitTabs button[data-bs-target="#tab-general"]')).show();
                    
                    mapInitialized = false;
                    unitModal.show();
                }
            });
    }

    function openDeleteConfirm(id, name) {
        $('#deleteUnitId').val(id);
        $('#deleteUnitName').text(name);
        deleteModal.show();
    }
</script>
<?php
$extra_js = ob_get_clean();
include '../templates/host_layout_footer.php';
?>