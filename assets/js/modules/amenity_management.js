// Amenity Management JavaScript
class AmenityManager {
    constructor() {
        this.init();
    }

    init() {
        this.initializeEventListeners();
        this.initializeTooltips();
        this.initializeFilters();
    }

    initializeEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterAmenities();
            });
        }

        // Filter functionality
        const branchFilter = document.getElementById('branchFilter');
        const statusFilter = document.getElementById('statusFilter');
        const sortSelect = document.getElementById('sortSelect');

        if (branchFilter) {
            branchFilter.addEventListener('change', () => {
                this.filterAmenities();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.filterAmenities();
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                this.sortAmenities();
            });
        }

        // Reset filters
        const resetFilters = document.getElementById('resetFilters');
        const resetEmptyState = document.getElementById('resetEmptyState');

        if (resetFilters) {
            resetFilters.addEventListener('click', () => {
                this.resetFilters();
            });
        }

        if (resetEmptyState) {
            resetEmptyState.addEventListener('click', () => {
                this.resetFilters();
            });
        }

        // Edit amenity buttons
        document.querySelectorAll('.edit-amenity').forEach(button => {
            button.addEventListener('click', (e) => {
                this.handleEditAmenity(e);
            });
        });

        // Toggle availability buttons
        document.querySelectorAll('.toggle-availability').forEach(button => {
            button.addEventListener('click', (e) => {
                this.handleToggleAvailability(e);
            });
        });

        // Delete amenity buttons
        document.querySelectorAll('.delete-amenity').forEach(button => {
            button.addEventListener('click', (e) => {
                this.handleDeleteAmenity(e);
            });
        });

        // View bookings buttons
        document.querySelectorAll('.view-bookings').forEach(button => {
            button.addEventListener('click', (e) => {
                this.handleViewBookings(e);
            });
        });

        // Form submission
        const addAmenityForm = document.getElementById('addAmenityForm');
        if (addAmenityForm) {
            addAmenityForm.addEventListener('submit', (e) => {
                this.handleAddAmenity(e);
            });
        }
    }

    initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        this.tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    initializeFilters() {
        // Initialize filter values from URL parameters if needed
        const urlParams = new URLSearchParams(window.location.search);
        const branchFilter = urlParams.get('branch');
        const statusFilter = urlParams.get('status');
        
        const branchSelect = document.getElementById('branchFilter');
        const statusSelect = document.getElementById('statusFilter');
        
        if (branchFilter && branchSelect) {
            branchSelect.value = branchFilter;
        }
        if (statusFilter && statusSelect) {
            statusSelect.value = statusFilter;
        }
        
        this.filterAmenities();
    }

    filterAmenities() {
        const searchInput = document.getElementById('searchInput');
        const branchFilter = document.getElementById('branchFilter');
        const statusFilter = document.getElementById('statusFilter');
        
        if (!searchInput || !branchFilter || !statusFilter) return;
        
        const searchTerm = searchInput.value.toLowerCase();
        const branchValue = branchFilter.value;
        const statusValue = statusFilter.value;
        
        let visibleRows = 0;
        
        document.querySelectorAll('.amenity-row').forEach(row => {
            const amenityName = row.getAttribute('data-name');
            const branchId = row.getAttribute('data-branch-id');
            const status = row.getAttribute('data-status');
            
            const matchesSearch = amenityName.includes(searchTerm);
            const matchesBranch = !branchValue || branchId === branchValue;
            const matchesStatus = !statusValue || status === statusValue;
            
            if (matchesSearch && matchesBranch && matchesStatus) {
                row.style.display = '';
                visibleRows++;
            } else {
                row.style.display = 'none';
            }
        });
        
        this.toggleEmptyState(visibleRows === 0);
    }

    sortAmenities() {
        const sortSelect = document.getElementById('sortSelect');
        if (!sortSelect) return;
        
        const sortBy = sortSelect.value;
        const table = document.getElementById('amenitiesTable');
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('.amenity-row'));
        
        rows.sort((a, b) => {
            switch (sortBy) {
                case 'name':
                    return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                case 'date':
                    return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
                case 'branch':
                    return a.querySelector('.branch-badge').textContent.localeCompare(b.querySelector('.branch-badge').textContent);
                case 'fee':
                    return parseFloat(b.getAttribute('data-fee')) - parseFloat(a.getAttribute('data-fee'));
                default:
                    return 0;
            }
        });
        
        // Remove existing rows
        rows.forEach(row => row.remove());
        
        // Append sorted rows
        rows.forEach(row => tbody.appendChild(row));
        
        this.filterAmenities(); // Re-apply filters after sorting
    }

    resetFilters() {
        const searchInput = document.getElementById('searchInput');
        const branchFilter = document.getElementById('branchFilter');
        const statusFilter = document.getElementById('statusFilter');
        const sortSelect = document.getElementById('sortSelect');
        
        if (searchInput) searchInput.value = '';
        if (branchFilter) branchFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        if (sortSelect) sortSelect.value = 'name';
        
        this.filterAmenities();
        this.sortAmenities();
    }

    toggleEmptyState(show) {
        const emptyState = document.getElementById('emptyState');
        const table = document.querySelector('.table-responsive');
        
        if (!emptyState || !table) return;
        
        if (show) {
            table.style.display = 'none';
            emptyState.style.display = 'block';
        } else {
            table.style.display = 'block';
            emptyState.style.display = 'none';
        }
    }

    async handleEditAmenity(event) {
        const amenityId = event.currentTarget.getAttribute('data-amenity-id');
        
        try {
            const response = await fetch(`../actions/get_amenity.php?id=${amenityId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateEditModal(data.amenity);
            } else {
                this.showAlert('Error loading amenity data', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error loading amenity data', 'danger');
        }
    }

    populateEditModal(amenity) {
        // This would populate an edit modal - you'll need to create this modal
        console.log('Edit amenity:', amenity);
        // Implement edit modal population here
        this.showAlert('Edit functionality would open here', 'info');
    }

    async handleToggleAvailability(event) {
        const button = event.currentTarget;
        const amenityId = button.getAttribute('data-amenity-id');
        const action = button.getAttribute('data-action');
        const amenityName = button.closest('.amenity-row').querySelector('.amenity-name strong').textContent;
        
        const confirmMessage = action === 'enable' 
            ? `Are you sure you want to enable "${amenityName}"?`
            : `Are you sure you want to disable "${amenityName}"?`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        try {
            const response = await fetch('../actions/toggle_amenity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `amenity_id=${amenityId}&action=${action}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                this.showAlert(data.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error updating amenity availability', 'danger');
        }
    }

    async handleDeleteAmenity(event) {
        const button = event.currentTarget;
        const amenityId = button.getAttribute('data-amenity-id');
        const amenityName = button.getAttribute('data-amenity-name');
        
        if (!confirm(`Are you sure you want to delete "${amenityName}"? This action cannot be undone.`)) {
            return;
        }
        
        try {
            const response = await fetch('../actions/delete_amenity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `amenity_id=${amenityId}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                this.showAlert(data.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error deleting amenity', 'danger');
        }
    }

    handleViewBookings(event) {
        const amenityId = event.currentTarget.getAttribute('data-amenity-id');
        // Redirect to bookings page or show bookings modal
        window.location.href = `../admin/amenity_bookings.php?amenity_id=${amenityId}`;
    }

    async handleAddAmenity(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert(data.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('addAmenityModal'));
                modal.hide();
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                this.showAlert(data.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error adding amenity', 'danger');
        }
    }

    showAlert(message, type) {
        // Remove existing alerts
        const existingAlert = document.querySelector('.alert-message');
        if (existingAlert) {
            existingAlert.remove();
        }

        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-message`;
        alert.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(alert);

        // Remove alert after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

// Initialize the amenity manager when the page loads
document.addEventListener('DOMContentLoaded', function() {
    new AmenityManager();
});