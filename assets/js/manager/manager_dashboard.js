document.addEventListener('DOMContentLoaded', function() {
            console.log('Manager dashboard loaded successfully');
            
            // Approve buttons event listeners
            document.querySelectorAll('.approve-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-reservation-id');
                    console.log('Approve clicked for reservation:', reservationId);
                    document.getElementById('approve_reservation_id').value = reservationId;
                    const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
                    approveModal.show();
                });
            });

            // Cancel buttons event listeners
            document.querySelectorAll('.cancel-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reservationId = this.getAttribute('data-reservation-id');
                    console.log('Cancel clicked for reservation:', reservationId);
                    document.getElementById('cancel_reservation_id').value = reservationId;
                    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
                    cancelModal.show();
                });
            });
        });