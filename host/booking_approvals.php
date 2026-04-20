<?php
// BookIT Host Booking Approval System
include_once '../includes/session.php';
include_once '../includes/functions.php';
checkRole(['host', 'manager']);
include_once '../config/file_paths.php';

$host_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get host's branch
$hostBranch = get_single_result("SELECT b.* FROM branches b JOIN users u ON b.branch_id = u.branch_id WHERE u.user_id = ?", [$host_id]);

if (!$hostBranch) {
    die("Access Denied: No branch assigned.");
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_booking'])) {
        $rid = (int)$_POST['reservation_id'];
        if (approveReservation($rid, $host_id)) $message = "Booking #$rid approved.";
        else $error = "Failed to approve booking.";
    }
    if (isset($_POST['reject_booking'])) {
        $rid = (int)$_POST['reservation_id'];
        $reason = sanitize_input($_POST['rejection_reason'] ?? 'Rejected by host');
        if (rejectReservation($rid, $host_id, $reason)) $message = "Booking #$rid rejected.";
        else $error = "Failed to reject booking.";
    }
    if (isset($_POST['approve_payment'])) {
        $pid = (int)$_POST['payment_id'];
        $payment = get_single_result("SELECT * FROM payments WHERE payment_id = ?", [$pid]);
        if ($payment) {
            execute_query("UPDATE payments SET payment_status = 'completed' WHERE payment_id = ?", [$pid]);
            execute_query("UPDATE reservations SET payment_status = 'paid', status = 'confirmed', confirmed_at = NOW() WHERE reservation_id = ?", [$payment['reservation_id']]);
            $message = "Payment #$pid approved.";
        }
    }
}

// Fetch Data
$pendingBookings = get_multiple_results("SELECT r.*, u.unit_number, u.unit_type, us.full_name as renter_name FROM reservations r JOIN units u ON r.unit_id = u.unit_id JOIN users us ON r.user_id = us.user_id WHERE r.branch_id = ? AND r.status = 'awaiting_approval' ORDER BY r.created_at DESC", [$hostBranch['branch_id']]);
$pendingPayments = get_multiple_results("SELECT p.*, r.user_id, u.unit_number, us.full_name as renter_name FROM payments p JOIN reservations r ON p.reservation_id = r.reservation_id JOIN units u ON r.unit_id = u.unit_id JOIN users us ON r.user_id = us.user_id WHERE r.branch_id = ? AND p.payment_status = 'pending'", [$hostBranch['branch_id']]);
$approvedBookings = get_multiple_results("SELECT r.*, u.unit_number, us.full_name as renter_name FROM reservations r JOIN units u ON r.unit_id = u.unit_id JOIN users us ON r.user_id = us.user_id WHERE r.branch_id = ? AND r.status IN ('approved','confirmed') ORDER BY r.approved_at DESC LIMIT 20", [$hostBranch['branch_id']]);

$page_title = 'Booking Approvals';
include '../templates/host_layout_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-check-double me-2"></i>Approvals & Verification</h1>
        <p class="text-muted">Review pending bookings and payment proofs for <?php echo htmlspecialchars($hostBranch['branch_name']); ?></p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<ul class="nav nav-pills mb-4 gap-2" id="approvalTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active rounded-pill px-4" data-bs-toggle="pill" data-bs-target="#tab-pending">Pending Bookings (<?php echo count($pendingBookings); ?>)</button>
    </li>
    <li class="nav-item">
        <button class="nav-link rounded-pill px-4" data-bs-toggle="pill" data-bs-target="#tab-payments">Payment Proofs (<?php echo count($pendingPayments); ?>)</button>
    </li>
    <li class="nav-item">
        <button class="nav-link rounded-pill px-4" data-bs-toggle="pill" data-bs-target="#tab-history">Recent History</button>
    </li>
</ul>

<div class="tab-content" id="approvalTabsContent">
    <!-- Pending Bookings -->
    <div class="tab-pane fade show active" id="tab-pending">
        <div class="row g-4">
            <?php foreach ($pendingBookings as $b): ?>
            <div class="col-xl-4 col-md-6">
                <div class="card h-100 shadow-sm border-0 border-top border-warning border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="badge bg-warning text-dark">Awaiting Approval</span>
                            <small class="text-muted">#<?php echo $b['reservation_id']; ?></small>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($b['renter_name']); ?></h5>
                        <p class="small text-muted mb-3"><i class="fas fa-building me-1"></i> Unit <?php echo $b['unit_number']; ?> (<?php echo $b['unit_type']; ?>)</p>
                        
                        <div class="bg-light p-3 rounded mb-3 small">
                            <div class="d-flex justify-content-between mb-1"><span>In:</span><b><?php echo date('M d', strtotime($b['check_in_date'])); ?></b></div>
                            <div class="d-flex justify-content-between mb-1"><span>Out:</span><b><?php echo date('M d', strtotime($b['check_out_date'])); ?></b></div>
                            <div class="d-flex justify-content-between border-top pt-1 mt-1"><span>Total Bill:</span><b>₱<?php echo number_format($b['total_amount'], 2); ?></b></div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <form method="POST" class="flex-grow-1"><input type="hidden" name="reservation_id" value="<?php echo $b['reservation_id']; ?>"><button type="submit" name="approve_booking" class="btn btn-success btn-sm w-100">Approve</button></form>
                            <button class="btn btn-outline-danger btn-sm" onclick="openRejectModal(<?php echo $b['reservation_id']; ?>)">Reject</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; if (empty($pendingBookings)) echo '<div class="col-12 text-center py-5"><p class="text-muted">No pending bookings.</p></div>'; ?>
        </div>
    </div>

    <!-- Payment Proofs -->
    <div class="tab-pane fade" id="tab-payments">
        <div class="row g-4">
            <?php foreach ($pendingPayments as $p): ?>
            <div class="col-xl-4 col-md-6">
                <div class="card h-100 shadow-sm border-0 border-top border-info border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="badge bg-info">Payment Verification</span>
                            <small class="text-muted">Ref: <?php echo $p['payment_id']; ?></small>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($p['renter_name']); ?></h5>
                        <p class="small text-muted mb-3">Booking #<?php echo $p['reservation_id']; ?> • Unit <?php echo $p['unit_number']; ?></p>
                        
                        <div class="alert alert-secondary py-2 small mb-3">
                            Amount: <b>₱<?php echo number_format($p['amount'], 2); ?></b> via <?php echo strtoupper($p['payment_method']); ?>
                        </div>

                        <?php 
                            $proofUrl = FileUploadHelper::getPaymentProofURL($p['payment_method']) . '/' . $p['transaction_reference'];
                        ?>
                        <a href="<?php echo $proofUrl; ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100 mb-3"><i class="fas fa-image me-1"></i> View Receipt</a>

                        <form method="POST"><input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>"><button type="submit" name="approve_payment" class="btn btn-primary btn-sm w-100">Verify & Confirm Booking</button></form>
                    </div>
                </div>
            </div>
            <?php endforeach; if (empty($pendingPayments)) echo '<div class="col-12 text-center py-5"><p class="text-muted">No payments pending verification.</p></div>'; ?>
        </div>
    </div>

    <!-- History Tab -->
    <div class="tab-pane fade" id="tab-history">
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Booking ID</th>
                            <th>Renter</th>
                            <th>Unit</th>
                            <th>Dates</th>
                            <th>Approved At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvedBookings as $h): ?>
                        <tr>
                            <td>#<?php echo $h['reservation_id']; ?></td>
                            <td><b><?php echo htmlspecialchars($h['renter_name']); ?></b></td>
                            <td>Unit <?php echo $h['unit_number']; ?></td>
                            <td class="small"><?php echo date('M d', strtotime($h['check_in_date'])); ?> - <?php echo date('M d', strtotime($h['check_out_date'])); ?></td>
                            <td class="small"><?php echo date('M d, g:i A', strtotime($h['approved_at'])); ?></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?php echo $h['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="reservation_id" id="rejectId">
                    <label class="form-label">Reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="reject_booking" class="btn btn-danger w-100">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openRejectModal(id) {
        $('#rejectId').val(id);
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }
</script>

<?php include '../templates/host_layout_footer.php'; ?>
