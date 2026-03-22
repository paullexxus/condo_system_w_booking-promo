<?php
// Amenity Modal Form
// This file displays the modal for adding new amenities

$branches = [];
if ($_SESSION['role'] === 'admin') {
    // Admin can select any branch
    $query = "SELECT branch_id, branch_name FROM branches ORDER BY branch_name ASC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
} else {
    // Host can only select their own branch
    $query = "SELECT b.branch_id, b.branch_name FROM branches b JOIN users u ON b.branch_id = u.branch_id WHERE u.user_id = ? ORDER BY b.branch_name ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
    $stmt->close();
}
?>

<!-- Add New Amenity Modal -->
<div id="addAmenityModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h5 class="modal-title">Add New Amenity</h5>
            <button type="button" class="btn-close" onclick="closeAmenityModal()" aria-label="Close"></button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
            <form id="amenityForm">
                <!-- Amenity Name -->
                <div class="mb-3">
                    <label for="amenityName" class="form-label">Amenity Name <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="amenityName" 
                        name="amenity_name" 
                        placeholder="e.g., Swimming Pool, Gym, Meeting Room"
                        required
                        maxlength="100"
                    >
                    <small class="form-text text-muted">Enter a descriptive name for the amenity</small>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label for="amenityDescription" class="form-label">Description</label>
                    <textarea 
                        class="form-control" 
                        id="amenityDescription" 
                        name="description" 
                        placeholder="e.g., Olympic-size swimming pool with changing facilities"
                        rows="3"
                        maxlength="500"
                    ></textarea>
                    <small class="form-text text-muted">Optional: Provide details about the amenity</small>
                </div>

                <!-- Branch Selection (only for admin) -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="mb-3">
                    <label for="amenityBranch" class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" id="amenityBranch" name="branch_id" required>
                        <option value="">-- Select a Branch --</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>">
                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Select which branch this amenity belongs to</small>
                </div>
                <?php else: ?>
                <!-- For hosts, set hidden branch_id -->
                <input type="hidden" id="amenityBranch" name="branch_id" 
                    value="<?php echo isset($branches[0]['branch_id']) ? $branches[0]['branch_id'] : ''; ?>">
                <?php endif; ?>

                <!-- Hourly Rate -->
                <div class="mb-3">
                    <label for="amenityRate" class="form-label">Hourly Rate (PHP) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input 
                            type="number" 
                            class="form-control" 
                            id="amenityRate" 
                            name="hourly_rate" 
                            placeholder="0.00"
                            step="0.01"
                            min="0"
                            required
                        >
                    </div>
                    <small class="form-text text-muted">Set the hourly booking rate for this amenity</small>
                </div>

                <!-- Max Capacity -->
                <div class="mb-3">
                    <label for="amenityCapacity" class="form-label">Maximum Capacity</label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="amenityCapacity" 
                        name="max_capacity" 
                        placeholder="1"
                        min="1"
                        value="1"
                    >
                    <small class="form-text text-muted">Maximum number of people allowed at once</small>
                </div>

                <!-- Is Available Toggle -->
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="amenityAvailable" 
                            name="is_available" 
                            checked
                        >
                        <label class="form-check-label" for="amenityAvailable">
                            Available for Booking
                        </label>
                    </div>
                    <small class="form-text text-muted">Toggle to control if this amenity is available for bookings</small>
                </div>

                <!-- Error Message Display -->
                <div id="amenityErrorMsg" class="alert alert-danger" style="display: none; margin-top: 15px;"></div>

                <!-- Success Message Display -->
                <div id="amenitySuccessMsg" class="alert alert-success" style="display: none; margin-top: 15px;"></div>
            </form>
        </div>

        <!-- Modal Footer/Actions -->
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeAmenityModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="submitAmenityBtn" onclick="submitAmenityForm()">
                <i class="fas fa-plus"></i> Add Amenity
            </button>
        </div>
    </div>
</div>

<!-- Styles for Modal (if not already included in admin_dashboard.css) -->
<style>
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1050;
        backdrop-filter: blur(2px);
        animation: fadeIn 0.3s ease;
        opacity: 1;
    }

    .modal-content {
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90%;
        max-height: 85vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
    }

    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px 10px 0 0;
        color: white;
    }

    .modal-title {
        margin: 0;
        font-weight: 600;
        font-size: 1.25rem;
        color: white;
    }

    .btn-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: white;
        cursor: pointer;
        padding: 0;
        transition: transform 0.2s ease;
    }

    .btn-close:hover {
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 25px;
    }

    .modal-actions {
        padding: 15px 25px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #f9f9f9;
        border-radius: 0 0 10px 10px;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .form-label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border: 1.5px solid #e0e0e0;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #e0e0e0;
        color: #2c3e50;
    }

    .btn-secondary:hover {
        background: #d0d0d0;
    }

    .text-danger {
        color: #e74c3c;
    }

    .form-check-input {
        width: 1.5em;
        height: 1.5em;
        margin-top: 0.3em;
        cursor: pointer;
        accent-color: #667eea;
    }

    .input-group-text {
        background: #f0f0f0;
        border: 1.5px solid #e0e0e0;
        color: #2c3e50;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            max-height: 90vh;
        }

        .modal-body {
            padding: 15px;
        }

        .modal-header {
            padding: 15px;
        }

        .modal-actions {
            padding: 10px 15px;
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }
    }
</style>

<!-- JavaScript Functions -->
<script>
    /**
     * Close the amenity modal
     */
    function closeAmenityModal() {
        const modal = document.getElementById('addAmenityModal');
        if (modal) {
            modal.style.display = 'none';
            modal.style.opacity = '1';
            document.body.style.overflow = 'auto';
            document.body.style.backdropFilter = 'none';
            // Reset form
            document.getElementById('amenityForm').reset();
            document.getElementById('amenityErrorMsg').style.display = 'none';
            document.getElementById('amenitySuccessMsg').style.display = 'none';
        }
    }

    /**
     * Open the amenity modal
     */
    function openAmenityModal() {
        const modal = document.getElementById('addAmenityModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Validate the amenity form
     */
    function validateAmenityForm() {
        const form = document.getElementById('amenityForm');
        const amenityName = document.getElementById('amenityName').value.trim();
        const amenityRate = parseFloat(document.getElementById('amenityRate').value);
        const amenityBranch = document.getElementById('amenityBranch').value;

        // Clear previous messages
        document.getElementById('amenityErrorMsg').style.display = 'none';
        document.getElementById('amenitySuccessMsg').style.display = 'none';

        // Validate required fields
        if (!amenityName) {
            showAmenityError('Please enter an amenity name');
            return false;
        }

        if (amenityName.length < 3) {
            showAmenityError('Amenity name must be at least 3 characters');
            return false;
        }

        if (!amenityRate || amenityRate < 0) {
            showAmenityError('Please enter a valid hourly rate');
            return false;
        }

        if (!amenityBranch) {
            showAmenityError('Please select a branch');
            return false;
        }

        return true;
    }

    /**
     * Display error message in modal
     */
    function showAmenityError(message) {
        const errorDiv = document.getElementById('amenityErrorMsg');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        // Auto-hide after 5 seconds
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }

    /**
     * Display success message in modal
     */
    function showAmenitySuccess(message) {
        const successDiv = document.getElementById('amenitySuccessMsg');
        successDiv.textContent = message;
        successDiv.style.display = 'block';
        // Auto-hide after 5 seconds
        setTimeout(() => {
            successDiv.style.display = 'none';
        }, 5000);
    }

    /**
     * Submit the amenity form via AJAX
     */
    function submitAmenityForm() {
        if (!validateAmenityForm()) {
            return;
        }

        const formData = new FormData(document.getElementById('amenityForm'));
        const submitBtn = document.getElementById('submitAmenityBtn');
        const originalBtnText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

        // Convert FormData to object for easier handling
        const data = {
            amenity_name: formData.get('amenity_name'),
            description: formData.get('description') || '',
            branch_id: formData.get('branch_id'),
            hourly_rate: formData.get('hourly_rate'),
            max_capacity: formData.get('max_capacity') || 1,
            is_available: formData.get('is_available') ? 1 : 0
        };

        fetch('<?php echo SITE_URL; ?>/includes/api/amenity/add_amenity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;

            if (result.success) {
                showAmenitySuccess('Amenity added successfully!');
                document.getElementById('amenityForm').reset();
                
                // Reload amenities table after 1.5 seconds
                setTimeout(() => {
                    closeAmenityModal();
                    // Reload the page or refresh the amenities table
                    location.reload();
                }, 1500);
            } else {
                showAmenityError(result.message || 'Failed to add amenity. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            showAmenityError('An error occurred. Please try again.');
        });
    }

    /**
     * Close modal when pressing ESC key
     */
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('addAmenityModal');
            if (modal && modal.style.display === 'flex') {
                closeAmenityModal();
            }
        }
    });

    /**
     * Close modal when clicking outside of it
     */
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('addAmenityModal');
        if (event.target === modal) {
            closeAmenityModal();
        }
    });

    // Add Bootstrap modal trigger support
    document.addEventListener('DOMContentLoaded', function() {
        // Bootstrap modal data attributes support
        document.addEventListener('click', function(e) {
            if (e.target.getAttribute('data-bs-target') === '#addAmenityModal' || 
                e.target.closest('[data-bs-target="#addAmenityModal"]')) {
                openAmenityModal();
            }
        });
    });
</script>
