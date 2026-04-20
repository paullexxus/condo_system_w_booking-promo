<?php
// Host Reservations Management
// View, approve, cancel, and track reservations

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle reservation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['reservation_id'])) {
        $reservation_id = (int)$_POST['reservation_id'];
        $action = sanitize_input($_POST['action']);
        
        $res = get_single_result(
            "SELECT r.*, us.full_name as renter_name, us.user_id FROM reservations r 
             INNER JOIN units u ON r.unit_id = u.unit_id 
             INNER JOIN branches b ON r.branch_id = b.branch_id
             INNER JOIN users us ON r.user_id = us.user_id
             WHERE r.reservation_id = ? AND (u.host_id = ? OR b.host_id = ?)",
            [$reservation_id, $host_id, $host_id]
        );
        
        if ($res) {
            if ($action === 'approve') {
                execute_query("UPDATE reservations SET status = 'confirmed', approved_by = ?, approved_at = NOW() WHERE reservation_id = ?", [$host_id, $reservation_id]);
                $action_success = true;
                $action_message = "Reservation #$reservation_id approved.";
                sendNotification($res['user_id'], "Reservation Approved", "Your reservation #$reservation_id has been approved. You can now pay.", 'booking', 'system');
            } else if ($action === 'cancel') {
                $reason = sanitize_input($_POST['reason'] ?? 'Host cancelled');
                execute_query("UPDATE reservations SET status = 'cancelled', cancellation_reason = ? WHERE reservation_id = ?", [$reason, $reservation_id]);
                $action_success = true;
                $action_message = "Reservation #$reservation_id cancelled.";
                sendNotification($res['user_id'], "Reservation Cancelled", "Your reservation #$reservation_id was cancelled. Reason: $reason", 'booking', 'system');
            } else if ($action === 'checkin') {
                execute_query("UPDATE reservations SET status = 'checked_in', checked_in_by = ? WHERE reservation_id = ?", [$host_id, $reservation_id]);
                $action_success = true;
                $action_message = "Guest checked in.";
                sendNotification($res['user_id'], "Checked In", "Welcome! You've been checked in to reservation #$reservation_id.", 'booking', 'system');
            } else if ($action === 'checkout') {
                execute_query("UPDATE reservations SET status = 'completed', checked_out_by = ? WHERE reservation_id = ?", [$host_id, $reservation_id]);
                $action_success = true;
                $action_message = "Guest checked out.";
                sendNotification($res['user_id'], "Stay Completed", "Thank you for staying! Reservation #$reservation_id is now complete.", 'booking', 'system');
            }
        }
    }
}

// Fetch Stats for filters
$status_counts = [];
$statuses = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled'];
foreach ($statuses as $s) {
    $where = ($s === 'confirmed') ? "r.status IN ('confirmed','approved')" : "r.status = '$s'";
    $row = get_single_result("
        SELECT COUNT(*) as cnt FROM reservations r 
        INNER JOIN units u ON r.unit_id = u.unit_id 
        INNER JOIN branches b ON r.branch_id = b.branch_id
        WHERE (u.host_id = ? OR b.host_id = ?) AND $where", [$host_id, $host_id]);
    $status_counts[$s] = $row['cnt'] ?? 0;
}

// Fetch all reservations
$all_reservations = get_multiple_results("
    SELECT 
        r.*, u.unit_number, u.unit_type, u.max_occupancy,
        us.full_name as renter_name, us.email as renter_email, us.phone as renter_phone,
        COALESCE(p.paid, 0) as paid_amt
    FROM reservations r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN branches b ON r.branch_id = b.branch_id
    INNER JOIN users us ON r.user_id = us.user_id
    LEFT JOIN (SELECT reservation_id, SUM(amount) as paid FROM payments WHERE payment_status = 'completed' GROUP BY reservation_id) p ON p.reservation_id = r.reservation_id
    WHERE (u.host_id = $host_id OR b.host_id = $host_id)
    ORDER BY r.check_in_date DESC
");

$page_title = 'Bookings & Reservations';

ob_start();
?>
<style>
    .res-card { transition: var(--transition); border: 1px solid #eee; border-radius: 12px; background: white; margin-bottom: 20px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .res-card:hover { transform: translateY(-3px); box-shadow: var(--card-shadow); }
    .res-status { font-size: 0.75rem; font-weight: bold; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-confirmed, .status-approved { background: #d4edda; color: #155724; }
    .status-checked_in { background: #cfe2ff; color: #084298; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .status-completed { background: #e2e3e5; color: #383d41; }
    
    .timeline-steps { display: flex; justify-content: space-between; position: relative; margin: 20px 0; padding: 0 10px; }
    .timeline-steps::before { content: ''; position: absolute; top: 50%; left: 10px; right: 10px; height: 2px; background: #eee; z-index: 1; transform: translateY(-50%); }
    .step-dot { width: 12px; height: 12px; border-radius: 50%; background: #eee; border: 2px solid white; z-index: 2; box-shadow: 0 0 0 2px #eee; }
    .step-dot.active { background: var(--accent); box-shadow: 0 0 0 2px var(--accent); }
    .step-dot.completed { background: var(--success); box-shadow: 0 0 0 2px var(--success); }

    .filter-pills { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; }
    .filter-btn { border: 1px solid #ddd; background: white; padding: 6px 16px; border-radius: 30px; font-size: 0.85rem; font-weight: 600; white-space: nowrap; transition: var(--transition); }
    .filter-btn.active { background: var(--secondary); color: white; border-color: var(--secondary); }
    .filter-btn:hover:not(.active) { background: #f8f9fa; }
</style>
<?php
$extra_css = ob_get_clean();

include '../templates/host_layout_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-alt me-2"></i>Manage Reservations</h1>
        <p class="text-muted">Review and update guest stays</p>
    </div>
</div>

<?php if ($action_message): ?>
    <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo $action_message; ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="row g-2 mb-4 overflow-auto flex-nowrap pb-2">
    <div class="col-auto">
        <button class="filter-btn active glass-panel border rounded-pill px-4 py-2" onclick="filterRes('all', this)">All (<?php echo array_sum($status_counts); ?>)</button>
    </div>
    <?php foreach ($status_counts as $st => $count): ?>
        <div class="col-auto">
            <button class="filter-btn glass-panel border rounded-pill px-4 py-2" onclick="filterRes('<?php echo $st; ?>', this)">
                <?php echo ucfirst($st); ?> (<?php echo $count; ?>)
            </button>
        </div>
    <?php endforeach; ?>
</div>

<!-- Reservations List -->
<div class="row g-4" id="reservations-container">
    <?php foreach ($all_reservations as $res): 
        $st = strtolower($res['status']);
        $filter_st = ($st === 'approved') ? 'confirmed' : $st;
        $paid = (float)$res['paid_amt'];
        $total = (float)$res['total_amount'];
        $bal = max(0, $total - $paid);
    ?>
    <div class="col-xl-4 col-md-6 res-item" data-status="<?php echo $filter_st; ?>">
        <div class="card-modern shadow-sm border-0 h-100 p-0 overflow-hidden d-flex flex-column">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-50">
                <span class="small fw-bold text-muted">#<?php echo $res['reservation_id']; ?> • Unit <?php echo $res['unit_number']; ?></span>
                <span class="status-badge badge-<?php echo $st; ?>"><?php echo $st; ?></span>
            </div>
            
            <div class="p-4 flex-grow-1">
                <h5 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($res['renter_name']); ?></h5>
                <div class="small text-muted mb-4 pb-2 border-bottom">
                    <i class="fas fa-envelope me-1 opacity-50"></i> <?php echo $res['renter_email']; ?> • <i class="fas fa-phone me-1 opacity-50"></i> <?php echo $res['renter_phone']; ?>
                </div>

                <div class="row g-2 mb-4 text-center">
                    <div class="col-6 border-end">
                        <div class="text-muted small uppercase fw-bold mb-1" style="font-size: 0.6rem;">CHECK IN</div>
                        <div class="fw-bold small"><?php echo date('M d, Y', strtotime($res['check_in_date'])); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small uppercase fw-bold mb-1" style="font-size: 0.6rem;">CHECK OUT</div>
                        <div class="fw-bold small"><?php echo date('M d, Y', strtotime($res['check_out_date'])); ?></div>
                    </div>
                </div>

                <div class="p-3 border rounded-3 mb-4 bg-light">
                    <div class="d-flex justify-content-between small mb-1"><span>Total Bill:</span><span>₱<?php echo number_format($total, 2); ?></span></div>
                    <div class="d-flex justify-content-between small mb-1"><span>Paid:</span><span class="text-success fw-bold">₱<?php echo number_format($paid, 2); ?></span></div>
                    <div class="d-flex justify-content-between fw-bold pt-2 border-top mt-2"><span>Balance:</span><span class="<?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>">₱<?php echo number_format($bal, 2); ?></span></div>
                </div>

                <?php if ($res['special_requests']): ?>
                    <div class="small p-3 bg-amber-50 text-amber-900 border border-amber-100 rounded-3 mb-3 text-center">
                        <div class="fw-bold x-small uppercase mb-1 opacity-50">Special Requests</div>
                        <i class="fas fa-comment-dots me-2"></i> "<?php echo htmlspecialchars($res['special_requests']); ?>"
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-4 border-top mt-auto bg-light bg-opacity-50">
                <div class="d-flex gap-2">
                    <?php if ($st === 'pending'): ?>
                        <form method="POST" class="flex-grow-1"><input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-primary rounded-pill w-100 shadow-sm"><i class="fas fa-check me-2"></i>Approve</button></form>
                        <button class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="rejectRes(<?php echo $res['reservation_id']; ?>)">Reject</button>
                    <?php elseif (in_array($st, ['confirmed', 'approved'])): ?>
                        <form method="POST" class="flex-grow-1"><input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>"><input type="hidden" name="action" value="checkin"><button class="btn btn-success rounded-pill w-100 shadow-sm"><i class="fas fa-sign-in-alt me-2"></i>Check-In</button></form>
                    <?php elseif ($st === 'checked_in'): ?>
                        <form method="POST" class="flex-grow-1"><input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>"><input type="hidden" name="action" value="checkout"><button class="btn btn-info text-white rounded-pill w-100 shadow-sm"><i class="fas fa-sign-out-alt me-2"></i>Check-Out</button></form>
                    <?php else: ?>
                        <button class="btn btn-light rounded-pill w-100 border text-muted" disabled><?php echo ucfirst($st); ?></button>
                    <?php endif; ?>
                    <a href="messaging.php?user=<?php echo $res['user_id']; ?>" class="btn glass-panel border rounded-circle flex-shrink-0" style="width: 38px; height: 38px; padding: 7px;"><i class="fas fa-envelope text-primary"></i></a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; if (empty($all_reservations)) echo '<div class="col-12 text-center py-5"><div class="card-modern py-5"><i class="fas fa-folder-open fa-3x text-muted opacity-25"></i><p class="h5 mt-3 text-muted">No reservations found.</p></div></div>'; ?>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST">
                <div class="modal-header bg-danger text-white border-0 py-3">
                    <h5 class="modal-title fw-bold">Reject Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="reservation_id" id="rejectId">
                    <input type="hidden" name="action" value="cancel">
                    <p class="text-muted small mb-3">Please provide a reason for rejecting this booking. This will be sent to the guest.</p>
                    <label class="form-label text-muted small fw-bold">REASON FOR REJECTION</label>
                    <textarea name="reason" class="form-control bg-light border-0" rows="4" required placeholder="e.g. Unit under maintenance, double booking..."></textarea>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Keep Booking</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 shadow-sm">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function filterRes(status, btn) {
        $('.filter-btn').removeClass('active');
        $(btn).addClass('active');
        if (status === 'all') {
            $('.res-item').fadeIn();
        } else {
            $('.res-item').hide();
            $(`.res-item[data-status="${status}"]`).fadeIn();
        }
    }

    function rejectRes(id) {
        $('#rejectId').val(id);
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }
</script>

<?php include '../templates/host_layout_footer.php'; ?>
