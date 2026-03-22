<?php
// get_edit_unit_form.php
require_once '../includes/session.php';
require_once '../includes/components/database.php';
require_once '../includes/components/validation.php';
require_once '../includes/components/form_errors.php';
checkRole(['admin']);

if (!isset($_GET['unit_id'])) {
    set_error('Unit ID is required');
    echo '<div class="alert alert-danger">Unit ID is required</div>';
    exit;
}

$unit_id = (int)$_GET['unit_id'];
$db = DatabaseHelper::getInstance();

// Fetch unit details
$unit = $db->getOne("
    SELECT u.*, b.branch_name 
    FROM units u 
    LEFT JOIN branches b ON u.branch_id = b.branch_id 
    WHERE u.unit_id = ?", 
    [$unit_id]);

if (!$unit) {
    echo '<div class="alert alert-danger">Unit not found</div>';
    exit;
}

// Get branches for dropdown
$branches = get_multiple_results("SELECT branch_id, branch_name FROM branches WHERE is_active = 1");
?>

<form method="POST" action="update_unit.php" id="editUnitForm">
    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
    
    <!-- Show any form errors -->
    <?php show_form_errors(); ?>
    
    <!-- Unit Header -->
    <div class="edit-unit-header bg-warning bg-opacity-10 rounded p-3 mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="mb-1 text-warning">
                    <i class="fas fa-edit me-2"></i>Editing Unit
                </h6>
                <h5 class="mb-0"><?= htmlspecialchars($unit['unit_number']) ?></h5>
                <small class="text-muted"><?= htmlspecialchars($unit['unit_type']) ?> • <?= htmlspecialchars($unit['branch_name']) ?></small>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge <?= $unit['is_available'] ? 'bg-success' : 'bg-warning' ?>">
                    <?= $unit['is_available'] ? 'AVAILABLE' : 'OCCUPIED' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Unit Number *</label>
                <input type="text" class="form-control" name="unit_number" 
                       value="<?= htmlspecialchars($unit['unit_number']) ?>" 
                       required>
                <div class="form-text">Unique identifier for the unit</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Unit Type *</label>
                <select class="form-select" name="unit_type" required>
                    <option value="">Select Type</option>
                    <option value="Studio" <?= $unit['unit_type'] == 'Studio' ? 'selected' : '' ?>>Studio</option>
                    <option value="1 Bedroom" <?= $unit['unit_type'] == '1 Bedroom' ? 'selected' : '' ?>>1 Bedroom</option>
                    <option value="2 Bedrooms" <?= $unit['unit_type'] == '2 Bedrooms' ? 'selected' : '' ?>>2 Bedrooms</option>
                    <option value="3 Bedrooms" <?= $unit['unit_type'] == '3 Bedrooms' ? 'selected' : '' ?>>3 Bedrooms</option>
                    <option value="Penthouse" <?= $unit['unit_type'] == 'Penthouse' ? 'selected' : '' ?>>Penthouse</option>
                    <option value="Executive Suite" <?= $unit['unit_type'] == 'Executive Suite' ? 'selected' : '' ?>>Executive Suite</option>
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
                        <option value="<?= $branch['branch_id'] ?>" 
                            <?= $unit['branch_id'] == $branch['branch_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['branch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Price per Night (₱) *</label>
                <input type="number" class="form-control" name="price" 
                       step="0.01" min="0" 
                       value="<?= number_format($unit['monthly_rate'] ?? 0, 2, '.', '') ?>" 
                       required>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Floor Number</label>
                <input type="number" class="form-control" name="floor_number" 
                       min="1" 
                       value="<?= $unit['floor_number'] ?? '' ?>">
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Max Occupancy *</label>
                <input type="number" class="form-control" name="max_occupancy" 
                       min="1" 
                       value="<?= $unit['max_occupancy'] ?>" 
                       required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Security Deposit</label>
                <input type="number" class="form-control" name="security_deposit" 
                       step="0.01" min="0" 
                       value="<?= number_format($unit['security_deposit'] ?? 0, 2, '.', '') ?>">
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Floor Area (sqm)</label>
                <input type="number" class="form-control" name="sqm" 
                       step="0.01" min="0" 
                       value="<?= isset($unit['sqm']) ? number_format($unit['sqm'], 2, '.', '') : '' ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Bed Type</label>
                <select class="form-select" name="bed_type">
                    <option value="">Select</option>
                    <option value="single" <?= ($unit['bed_type'] ?? '') == 'single' ? 'selected' : '' ?>>Single</option>
                    <option value="double deck" <?= ($unit['bed_type'] ?? '') == 'double deck' ? 'selected' : '' ?>>Double Deck</option>
                    <option value="queen" <?= ($unit['bed_type'] ?? '') == 'queen' ? 'selected' : '' ?>>Queen</option>
                    <option value="king" <?= ($unit['bed_type'] ?? '') == 'king' ? 'selected' : '' ?>>King</option>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Beds</label>
                <input type="number" class="form-control" name="num_beds" 
                       min="1" 
                       value="<?= $unit['num_beds'] ?? 1 ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="mb-3">
                <label class="form-label">Bathrooms</label>
                <input type="number" class="form-control" name="num_bathrooms" 
                       min="1" 
                       value="<?= $unit['num_bathrooms'] ?? 1 ?>">
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="3" 
                  placeholder="Describe the unit features and location..."><?= htmlspecialchars($unit['description'] ?? '') ?></textarea>
    </div>

    <!-- Unit Metadata -->
    <div class="unit-metadata bg-light rounded p-3 mt-4">
        <h6 class="text-muted mb-3">
            <i class="fas fa-info-circle me-2"></i>Unit Information
        </h6>
        <div class="row">
            <div class="col-md-6">
                <small class="text-muted">Unit ID:</small>
                <div class="fw-bold">#U<?= str_pad($unit['unit_id'], 4, '0', STR_PAD_LEFT) ?></div>
            </div>
            <div class="col-md-6">
                <small class="text-muted">Status:</small>
                <div class="fw-bold"><?= $unit['is_available'] ? 'Available' : 'Occupied' ?></div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <small class="text-muted">Created:</small>
                <div><?= date('M j, Y', strtotime($unit['created_at'])) ?></div>
            </div>
            <div class="col-md-6">
                <small class="text-muted">Last Updated:</small>
                <div><?= date('M j, Y', strtotime($unit['updated_at'] ?? $unit['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="modal-footer mt-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Cancel
        </button>
        <button type="submit" name="update_unit" class="btn btn-warning">
            <i class="fas fa-save me-2"></i>Update Unit
        </button>
    </div>
</form>

<script>
// Client-side validation functions
function validateUnitNumber(unitNumber) {
    if (!unitNumber || unitNumber.trim() === '') return false;
    // Must start with a letter, followed by alphanumeric characters (like A101, w202).
    return /^[a-zA-Z][a-zA-Z0-9\-\s]*$/.test(unitNumber.trim());
}

function validatePrice(price) {
    return !isNaN(price) && parseFloat(price) >= 0;
}

// Form validation and submission
// Form validation and submission
$(document).ready(function() {
    $('#editUnitForm').on('submit', function(e) {
        e.preventDefault();
        
        // Client-side validation
        let hasErrors = false;
        const unitNumber = $('input[name="unit_number"]').val();
        const price = $('input[name="price"]').val();
        
        if (!validateUnitNumber(unitNumber)) {
            $('input[name="unit_number"]').addClass('is-invalid');
            $('input[name="unit_number"]').next('.invalid-feedback').remove();
            $('input[name="unit_number"]').after('<div class="invalid-feedback">Invalid unit number format (e.g., A101, w202)</div>');
            hasErrors = true;
        }
        
        if (!validatePrice(price)) {
            $('input[name="price"]').addClass('is-invalid');
            $('input[name="price"]').next('.invalid-feedback').remove();
            $('input[name="price"]').after('<div class="invalid-feedback">Price must be a valid number (0 or greater)</div>');
            hasErrors = true;
        }
        
        if (hasErrors) {
            return;
        }
        
        const formData = $(this).serialize();
        
        // Show loading state
        $('#editUnitModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-warning" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Updating unit...</p>
            </div>
        `);
        
        $.ajax({
            url: 'update_unit.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success message and close modal
                    $('#editUnitModalBody').html(`
                        <div class="text-center py-4">
                            <div class="text-success mb-3">
                                <i class="fas fa-check-circle fa-3x"></i>
                            </div>
                            <h5 class="text-success">Unit Updated Successfully!</h5>
                            <p class="text-muted">${response.message}</p>
                            <button type="button" class="btn btn-success mt-3" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh Page
                            </button>
                        </div>
                    `);
                    
                    // Auto close modal after 2 seconds
                    setTimeout(() => {
                        $('#editUnitModal').modal('hide');
                        location.reload();
                    }, 2000);
                } else {
                    // Show error message
                    $('#editUnitModalBody').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${response.message}
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-warning" onclick="editUnit(${response.unit_id})">
                                <i class="fas fa-edit me-2"></i>Try Again
                            </button>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#editUnitModalBody').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Failed to update unit. Please try again.
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-warning" onclick="editUnit(<?= $unit_id ?>)">
                            <i class="fas fa-edit me-2"></i>Try Again
                        </button>
                    </div>
                `);
            }
        });
    });

    $('input[name="max_occupancy"]').on('input', function() {
        if (this.value && parseInt(this.value) <= 0) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
});
</script>

<style>
.edit-unit-header {
    border-left: 4px solid #ffc107;
}

.unit-metadata {
    border: 1px solid #e9ecef;
}

.form-control.is-invalid {
    border-color: #dc3545;
}
</style>