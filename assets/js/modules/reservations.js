// Reservation Management JavaScript
const reservationManager = {
    config: {
        userRole: 'admin',
        baseUrl: window.location.origin + '/BookIT/modules'
    },

    init: function(config) {
        // Merge configuration
        Object.assign(this.config, config);
        
        // Initialize event listeners
        this.initializeEventListeners();
        
        // Initialize charts if admin
        if (this.config.userRole === 'admin' && this.config.monthlyData.length > 0) {
            this.initializeReservationChart(this.config.monthlyData);
        }
        
        console.log('Reservation Manager initialized for:', this.config.userRole);
    },

    initializeEventListeners: function() {
        // Delegate events for dynamic buttons
        document.addEventListener('click', (e) => {
            // Approve button
            if (e.target.closest('.approve-btn')) {
                const reservationId = e.target.closest('.approve-btn').dataset.id;
                this.approveReservation(reservationId);
            }
            
            // Reject button
            if (e.target.closest('.reject-btn')) {
                const reservationId = e.target.closest('.reject-btn').dataset.id;
                this.showRejectModal(reservationId);
            }
            
            // Notes button
            if (e.target.closest('.notes-btn')) {
                const reservationId = e.target.closest('.notes-btn').dataset.id;
                this.showNotesModal(reservationId);
            }
            
            // Special requests button
            if (e.target.closest('.view-requests')) {
                const requests = e.target.closest('.view-requests').dataset.requests;
                this.showSpecialRequests(requests);
            }
            
            // Status update buttons
            if (e.target.closest('.update-status')) {
                const button = e.target.closest('.update-status');
                const reservationId = button.dataset.id;
                const newStatus = button.dataset.status;
                this.updateReservationStatus(reservationId, newStatus);
            }
            
            // View details button
            if (e.target.closest('.view-details')) {
                const reservationId = e.target.closest('.view-details').dataset.id;
                this.showReservationDetails(reservationId);
            }
            
            // Edit button
            if (e.target.closest('.edit-reservation')) {
                const reservationId = e.target.closest('.edit-reservation').dataset.id;
                this.editReservation(reservationId);
            }
        });
        
        // Handle confirm rejection button
        const confirmRejectBtn = document.getElementById('confirmRejectBtn');
        if (confirmRejectBtn) {
            confirmRejectBtn.addEventListener('click', () => {
                this.confirmRejectReservation();
            });
        }
    },

    // APPROVE RESERVATION
    approveReservation: function(reservationId) {
        if (confirm('Are you sure you want to approve this reservation?')) {
            fetch(`${this.config.baseUrl}/api/approve_reservation.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${reservationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification('Reservation approved successfully!', 'success');
                    this.refreshReservations();
                } else {
                    this.showNotification('Failed to approve reservation.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showNotification('Error approving reservation.', 'error');
            });
        }
    },

    // REJECT RESERVATION - Show Modal
    showRejectModal: function(reservationId) {
        document.getElementById('rejectReservationId').value = reservationId;
        document.getElementById('rejectionReason').value = '';
        const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
        modal.show();
    },

    // Confirm rejection after entering reason
    confirmRejectReservation: function() {
        const reservationId = document.getElementById('rejectReservationId').value;
        const reason = document.getElementById('rejectionReason').value;
        
        if (!reason.trim()) {
            alert('Please enter a rejection reason.');
            return;
        }
        
        fetch(`${this.config.baseUrl}/api/reject_reservation.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${reservationId}&reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Reservation rejected successfully!', 'success');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
                modal.hide();
                this.refreshReservations();
            } else {
                this.showNotification('Failed to reject reservation.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showNotification('Error rejecting reservation.', 'error');
        });
    },

    // NOTES MANAGEMENT
    showNotesModal: function(reservationId) {
        fetch(`${this.config.baseUrl}/api/get_notes.php?id=${reservationId}`)
            .then(response => response.json())
            .then(data => {
                const modalHtml = `
                    <div class="modal fade" id="notesModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reservation Notes - RES-${reservationId}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Add New Note:</label>
                                        <textarea class="form-control" id="newNoteText" rows="3" placeholder="Enter your note here..."></textarea>
                                    </div>
                                    <button class="btn btn-primary" onclick="reservationManager.saveNote(${reservationId})">
                                        <i class="fas fa-save me-2"></i>Save Note
                                    </button>
                                    
                                    <hr>
                                    
                                    <h6>Existing Notes:</h6>
                                    <div id="notesList">
                                        ${data.notes && data.notes.length > 0 ? 
                                            data.notes.map(note => `
                                                <div class="card mb-2">
                                                    <div class="card-body py-2">
                                                        <div class="d-flex justify-content-between">
                                                            <small class="text-muted">${note.created_at} - ${note.note_type}</small>
                                                            <small class="text-muted">By User ${note.user_id}</small>
                                                        </div>
                                                        <p class="mb-0">${note.note_text}</p>
                                                    </div>
                                                </div>
                                            `).join('') : 
                                            '<p class="text-muted">No notes yet.</p>'
                                        }
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('modals-container').innerHTML = modalHtml;
                const modal = new bootstrap.Modal(document.getElementById('notesModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                this.showNotification('Error loading notes.', 'error');
            });
    },

    saveNote: function(reservationId) {
        const noteText = document.getElementById('newNoteText').value;
        if (!noteText.trim()) {
            alert('Please enter a note.');
            return;
        }

        fetch(`${this.config.baseUrl}/api/save_note.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `reservation_id=${reservationId}&note_text=${encodeURIComponent(noteText)}&note_type=${this.config.userRole}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Note saved successfully!', 'success');
                // Refresh notes list
                this.showNotesModal(reservationId);
            } else {
                this.showNotification('Failed to save note.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showNotification('Error saving note.', 'error');
        });
    },

    // SPECIAL REQUESTS
    showSpecialRequests: function(requests) {
        const modalHtml = `
            <div class="modal fade" id="requestsModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Special Requests</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>${requests}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modals-container').innerHTML = modalHtml;
        const modal = new bootstrap.Modal(document.getElementById('requestsModal'));
        modal.show();
    },

    // CALENDAR VIEW
    initializeBookingCalendar: function() {
        const calendarEl = document.getElementById('booking-calendar');
        if (!calendarEl) return;

        // Initialize FullCalendar
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: `${this.config.baseUrl}/api/get_calendar_events.php`,
            eventClick: (info) => {
                this.showReservationDetails(info.event.id);
            },
            eventColor: '#3788d8',
            eventTextColor: '#ffffff'
        });
        
        calendar.render();
    },

    // STATUS UPDATES
    updateReservationStatus: function(reservationId, newStatus) {
        const statusMessages = {
            'checked-in': 'Are you sure you want to check-in this reservation?',
            'checked-out': 'Are you sure you want to check-out this reservation?',
            'cancelled': 'Are you sure you want to cancel this reservation?'
        };

        if (confirm(statusMessages[newStatus] || 'Update status?')) {
            fetch(`${this.config.baseUrl}/api/update_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${reservationId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification(`Reservation ${newStatus.replace('-', ' ')} successfully!`, 'success');
                    this.refreshReservations();
                } else {
                    this.showNotification('Failed to update status.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showNotification('Error updating status.', 'error');
            });
        }
    },

    // CHART INITIALIZATION
    initializeReservationChart: function(monthlyData) {
        const ctx = document.getElementById('reservationChart');
        if (!ctx) return;

        const labels = monthlyData.map(item => item.month).reverse();
        const counts = monthlyData.map(item => item.count).reverse();

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reservations',
                    data: counts,
                    borderColor: '#3788d8',
                    backgroundColor: 'rgba(55, 136, 216, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    },

    // UTILITY FUNCTIONS
    refreshReservations: function() {
        window.location.reload();
    },

    showNotification: function(message, type = 'info') {
        // Simple notification implementation
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'info': 'alert-info',
            'warning': 'alert-warning'
        }[type] || 'alert-info';

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Insert at the top of the content
        const content = document.querySelector('.content .container-fluid');
        content.insertBefore(alertDiv, content.firstChild);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
    },

    // PLACEHOLDER FUNCTIONS FOR FUTURE IMPLEMENTATION
    showNewReservationModal: function() {
        this.showNotification('New reservation feature coming soon!', 'info');
    },

    showReservationDetails: function(reservationId) {
        this.showNotification(`Reservation details for RES-${reservationId} coming soon!`, 'info');
    },

    editReservation: function(reservationId) {
        this.showNotification(`Edit reservation RES-${reservationId} coming soon!`, 'info');
    },

    exportToExcel: function() {
        this.showNotification('Excel export feature coming soon!', 'info');
    },

    generatePDFReport: function() {
        this.showNotification('PDF report generation coming soon!', 'info');
    },

    showNotificationModal: function() {
        this.showNotification('Notification system coming soon!', 'info');
    }
};

// Make it available globally
window.reservationManager = reservationManager;