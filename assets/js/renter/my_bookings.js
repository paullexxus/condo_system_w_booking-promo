function confirmRemove(reservationId) {
    document.getElementById('remove_reservation_id').value = reservationId;
    new bootstrap.Modal(document.getElementById('removeModal')).show();
}

function confirmCancel(reservationId) {
    document.getElementById('cancel_reservation_id').value = reservationId;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function showBookingDetailsModal(booking) {
    const content = `
        <div class="booking-details">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-home text-primary"></i> Unit Information</h6>
                    <dl class="row">
                        <dt class="col-sm-4">Unit Info:</dt>
                        <dd class="col-sm-8"><strong>${booking.unit_name ? booking.unit_name : 'Unit ' + booking.unit_number}</strong></dd>
                        <dt class="col-sm-4">Type:</dt>
                        <dd class="col-sm-8">${booking.unit_name ? booking.unit_name : (booking.unit_type || 'Unit')}</dd>
                        <dt class="col-sm-4">Branch:</dt>
                        <dd class="col-sm-8">${booking.branch_name}</dd>
                        <dt class="col-sm-4">Address:</dt>
                        <dd class="col-sm-8">${booking.address}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-calendar text-info"></i> Dates & Pricing</h6>
                    <dl class="row">
                        <dt class="col-sm-4">Check-in:</dt>
                        <dd class="col-sm-8"><strong>${new Date(booking.check_in_date).toLocaleDateString()}</strong></dd>
                        <dt class="col-sm-4">Check-out:</dt>
                        <dd class="col-sm-8"><strong>${new Date(booking.check_out_date).toLocaleDateString()}</strong></dd>
                        <dt class="col-sm-4">Total Amount:</dt>
                        <dd class="col-sm-8"><strong class="text-success">₱${parseFloat(booking.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></dd>
                        <dt class="col-sm-4">Security Deposit:</dt>
                        <dd class="col-sm-8">₱${parseFloat(booking.security_deposit).toLocaleString('en-US', {minimumFractionDigits: 2})}</dd>
                    </dl>
                </div>
            </div>
            ${booking.special_requests ? `
                <div class="alert alert-light border mt-3">
                    <h6><i class="fas fa-comment-dots"></i> Special Requests</h6>
                    <p class="mb-0">${booking.special_requests}</p>
                </div>
            ` : ''}
            <div class="alert alert-info mt-3">
                <strong>Status:</strong> ${booking.status.replace(/_/g, ' ').charAt(0).toUpperCase() + booking.status.replace(/_/g, ' ').slice(1)}<br>
                <strong>Payment Status:</strong> ${booking.payment_status.replace(/_/g, ' ').charAt(0).toUpperCase() + booking.payment_status.replace(/_/g, ' ').slice(1)}<br>
                <strong>Booked on:</strong> ${new Date(booking.created_at).toLocaleDateString()}
            </div>
        </div>
    `;
    
    document.getElementById('bookingDetailsContent').innerHTML = content;
    document.getElementById('detailsModalTitle').innerHTML = `Booking #${booking.reservation_id} - ${booking.unit_number}`;
    new bootstrap.Modal(document.getElementById('bookingDetailsModal')).show();
}