// Initialize DataTable
$(document).ready(function() {
    $('#branchesTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        responsive: true
    });
});

// Edit Branch Function
function editBranch(branch) {
    document.getElementById('edit_branch_id').value = branch.branch_id;
    document.getElementById('edit_branch_name').value = branch.branch_name;
    document.getElementById('edit_address').value = branch.address;
    document.getElementById('edit_city').value = branch.city;
    document.getElementById('edit_contact_number').value = branch.contact_number || '';
    document.getElementById('edit_email').value = branch.email || '';
    document.getElementById('edit_manager_id').value = branch.manager_id || '';
}

// View Branch Function
function viewBranch(branch) {
    const details = `
        <div class="branch-details">
            <div class="row mb-3">
                <div class="col-4"><strong>Branch Name:</strong></div>
                <div class="col-8">${branch.branch_name}</div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><strong>Address:</strong></div>
                <div class="col-8">${branch.address}</div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><strong>City:</strong></div>
                <div class="col-8">${branch.city}</div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><strong>Contact:</strong></div>
                <div class="col-8">${branch.contact_number || 'N/A'}</div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><strong>Email:</strong></div>
                <div class="col-8">${branch.email || 'N/A'}</div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><strong>Manager:</strong></div>
                <div class="col-8">${branch.manager_name || 'Not Assigned'}</div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><strong>Units:</strong></div>
                <div class="col-8">${branch.unit_count || 0}</div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><strong>Staff:</strong></div>
                <div class="col-8">${branch.staff_count || 0}</div>
            </div>
            <div class="row">
                <div class="col-4"><strong>Last Activity:</strong></div>
                <div class="col-8">${new Date(branch.last_activity).toLocaleDateString()}</div>
            </div>
        </div>
    `;
    document.getElementById('viewBranchDetails').innerHTML = details;
}

// Confirm Delete Function
function confirmDelete(branchId, branchName) {
    document.getElementById('delete_branch_id').value = branchId;
    document.getElementById('delete_branch_name').textContent = branchName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Export Functions
function exportBranches(format) {
    if (format === 'pdf') {
        window.print();
    } else if (format === 'csv') {
        $('.buttons-csv').click();
    }
}