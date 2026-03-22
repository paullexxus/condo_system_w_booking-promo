// Payment Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializePaymentManagement();
});

function initializePaymentManagement() {
    initializeStatusUpdate();
    initializePaymentDetails();
    initializeFilters();
    initializeExportModal();
    initializeAutoCloseAlerts();
    initializePrintFunctionality();
    initializeMobileFilters();
    initializeTooltips();
}

// Status Update Functionality
function initializeStatusUpdate() {
    const statusSelect = document.getElementById('new_status_select');
    const refundSection = document.getElementById('refundReasonSection');
    
    if (statusSelect && refundSection) {
        statusSelect.addEventListener('change', function() {
            refundSection.style.display = this.value === 'refunded' ? 'block' : 'none';
        });
    }

    // Set payment ID for status update
    document.querySelectorAll('.update-status').forEach(button => {
        button.addEventListener('click', function() {
            const paymentId = this.getAttribute('data-payment-id');
            const currentStatus = this.getAttribute('data-current-status');
            
            document.getElementById('update_payment_id').value = paymentId;
            
            const statusSelect = document.getElementById('new_status_select');
            if (statusSelect) {
                statusSelect.value = currentStatus;
                const event = new Event('change');
                statusSelect.dispatchEvent(event);
            }
        });
    });
}

// Payment Details Functionality
function initializePaymentDetails() {
    document.querySelectorAll('.view-payment').forEach(button => {
        button.addEventListener('click', function() {
            const paymentId = this.getAttribute('data-payment-id');
            const modalContent = document.getElementById('paymentDetailsContent');
            
            showLoading(modalContent);
            loadPaymentDetails(paymentId, modalContent);
        });
    });
}

function showLoading(container) {
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="loading"></div>
            <p class="mt-2">Loading payment details...</p>
        </div>
    `;
}

function loadPaymentDetails(paymentId, container) {
    // For demo purposes - in production, you would make an AJAX call
    setTimeout(() => {
        container.innerHTML = generatePaymentDetailsHTML(paymentId);
    }, 1000);
}

function generatePaymentDetailsHTML(paymentId) {
    return `
        <div class="row">
            <div class="col-md-6">
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Payment ID</div>
                    <div class="payment-detail-value">#${paymentId}</div>
                </div>
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Reservation ID</div>
                    <div class="payment-detail-value">#1001</div>
                </div>
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Amount</div>
                    <div class="payment-detail-value"><strong>₱2,500.00</strong></div>
                </div>
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Payment Method</div>
                    <div class="payment-detail-value">
                        <span class="badge bg-info">Gcash</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Status</div>
                    <div class="payment-detail-value">
                        <span class="badge payment-status status-paid">Paid</span>
                    </div>
                </div>
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Payment Date</div>
                    <div class="payment-detail-value">${new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</div>
                </div>
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Transaction Reference</div>
                    <div class="payment-detail-value">GCASH-${paymentId}${Date.now()}</div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Renter Information</div>
                    <div class="payment-detail-value">
                        <strong>Juan Dela Cruz</strong><br>
                        juan.delacruz@email.com<br>
                        +63 912 345 6789
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <div class="payment-detail-item">
                    <div class="payment-detail-label">Property Information</div>
                    <div class="payment-detail-value">
                        <strong>Beachfront Condo Unit 5A</strong><br>
                        Condominium<br>
                        123 Beach Road, Boracay
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Last updated: ${new Date().toLocaleString()}
            </small>
        </div>
    `;
}

// Filter Functionality
function initializeFilters() {
    // Auto-apply filters on enter key
    const searchInputs = document.querySelectorAll('input[type="text"]');
    searchInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('button[name="apply_filters"]').click();
            }
        });
    });

    // Date range presets
    initializeDatePresets();
}

function initializeDatePresets() {
    const datePresets = document.getElementById('datePresets');
    if (!datePresets) return;

    datePresets.addEventListener('change', function() {
        const preset = this.value;
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');
        const today = new Date();

        switch(preset) {
            case 'today':
                startDate.value = today.toISOString().split('T')[0];
                endDate.value = today.toISOString().split('T')[0];
                break;
            case 'yesterday':
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                startDate.value = yesterday.toISOString().split('T')[0];
                endDate.value = yesterday.toISOString().split('T')[0];
                break;
            case 'this_week':
                const startOfWeek = new Date(today);
                startOfWeek.setDate(today.getDate() - today.getDay());
                startDate.value = startOfWeek.toISOString().split('T')[0];
                endDate.value = today.toISOString().split('T')[0];
                break;
            case 'this_month':
                startDate.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                endDate.value = today.toISOString().split('T')[0];
                break;
            case 'last_month':
                const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                startDate.value = firstDayLastMonth.toISOString().split('T')[0];
                endDate.value = lastDayLastMonth.toISOString().split('T')[0];
                break;
        }
    });
}

// Mobile Filters
function initializeMobileFilters() {
    const filterToggle = document.querySelector('.filter-toggle');
    const filterSection = document.querySelector('.filter-section');
    
    if (filterToggle && filterSection) {
        filterToggle.addEventListener('click', function() {
            filterSection.classList.toggle('show');
        });
    }
}

// Export Modal Functionality
function initializeExportModal() {
    const exportStartDate = document.querySelector('input[name="export_start_date"]');
    const exportEndDate = document.querySelector('input[name="export_end_date"]');
    
    if (exportStartDate && exportEndDate) {
        // Set default date range (last 30 days)
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - 30);
        
        exportStartDate.valueAsDate = startDate;
        exportEndDate.valueAsDate = endDate;
    }
}

// Auto-close alerts
function initializeAutoCloseAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
}

// Print Functionality
function initializePrintFunctionality() {
    const printButton = document.createElement('button');
    printButton.className = 'btn btn-outline-secondary me-2';
    printButton.innerHTML = '<i class="fas fa-print me-1"></i> Print';
    printButton.addEventListener('click', printTable);
    
    const cardHeader = document.querySelector('.card-header');
    if (cardHeader && document.querySelector('.btn-toolbar')) {
        document.querySelector('.btn-toolbar').prepend(printButton);
    }
}

function printTable() {
    const table = document.querySelector('table');
    const tableClone = table.cloneNode(true);
    
    // Remove action buttons for print
    const actionColumnIndex = tableClone.rows[0].cells.length - 1;
    tableClone.querySelectorAll('tr').forEach(row => {
        if (row.cells[actionColumnIndex]) {
            row.deleteCell(actionColumnIndex);
        }
    });
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Payment Report - BookIT</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; font-family: Arial, sans-serif; }
                    .table { font-size: 12px; border-collapse: collapse; width: 100%; }
                    .table th, .table td { border: 1px solid #dee2e6; padding: 8px; }
                    .table th { background-color: #3498db; color: white; }
                    .badge { font-size: 10px; padding: 4px 8px; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    @media print {
                        .btn { display: none; }
                        body { padding: 0; }
                    }
                    .report-header { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                    .report-title { font-size: 24px; font-weight: bold; color: #333; }
                    .report-date { color: #666; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class="report-header">
                    <div class="report-title">Payment Management Report</div>
                    <div class="report-date">Generated on: ${new Date().toLocaleDateString()}</div>
                </div>
                ${tableClone.outerHTML}
                <div class="mt-4 text-muted">
                    <small>Report generated by BookIT Payment Management System</small>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(() => window.close(), 500);
                    }
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}

// Tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Utility Functions
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Export to CSV
function exportToCSV() {
    const table = document.querySelector('table');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    const csvString = csv.join('\n');
    const filename = 'payments_' + new Date().toISOString().split('T')[0] + '.csv';
    
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Table Sorting
function initializeTableSorting() {
    const table = document.querySelector('table');
    if (!table) return;

    const headers = table.querySelectorAll('th');
    headers.forEach((header, index) => {
        if (!header.querySelector('.btn')) {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(index);
            });
        }
    });
}

function sortTable(columnIndex) {
    const table = document.querySelector('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const isNumeric = columnIndex === 6; // Amount column
    const isDate = columnIndex === 8; // Date column
    
    rows.sort((a, b) => {
        let aValue = a.cells[columnIndex].textContent.trim();
        let bValue = b.cells[columnIndex].textContent.trim();
        
        if (isNumeric) {
            aValue = parseFloat(aValue.replace('₱', '').replace(',', ''));
            bValue = parseFloat(bValue.replace('₱', '').replace(',', ''));
            return aValue - bValue;
        } else if (isDate) {
            aValue = new Date(aValue);
            bValue = new Date(bValue);
            return aValue - bValue;
        } else {
            return aValue.localeCompare(bValue);
        }
    });
    
    // Clear and re-append sorted rows
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    rows.forEach(row => tbody.appendChild(row));
}