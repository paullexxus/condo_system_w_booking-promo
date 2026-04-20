<?php
require_once '../includes/session.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
checkRole(['admin']);

$message = '';
$error = '';

/**
 * 🔒 5. Admin Payouts & Lifecycle Maintenance
 * Handles review and approval of host withdrawal requests.
 */

// Handle Payout Request Action (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_payout'])) {
    header('Content-Type: application/json');
    
    // 1. CSRF Protection
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Invalid security token. Please refresh the page.']);
        exit;
    }
    
    // 2. Idempotency Protection (Double Action Prevention)
    $idempotency_key = $_POST['idempotency_key'] ?? '';
    if (!isIdempotent($idempotency_key, 'payout_action')) {
        echo json_encode(['error' => 'This request is already being processed or has been completed.']);
        exit;
    }
    
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $admin_notes = sanitize_input($_POST['admin_notes']);
    
    $request = get_single_result("SELECT * FROM payout_requests WHERE id = ?", [$request_id]);
    
    if ($request && $request['status'] === 'pending') {
        $conn->begin_transaction();
        try {
            if ($action === 'approve') {
                // Mark as paid
                execute_query(
                    "UPDATE payout_requests SET status = 'paid', admin_notes = ?, processed_at = NOW() WHERE id = ?",
                    [$admin_notes, $request_id]
                );
                
                // 💰 Earnings Freeze Protection
                $amount_to_cover = (float)$request['amount'];
                $available_earnings = get_multiple_results(
                    "SELECT id, host_amount FROM host_earnings WHERE host_id = ? AND status = 'available' ORDER BY created_at ASC",
                    [$request['host_id']]
                );
                
                foreach ($available_earnings as $earning) {
                    if ($amount_to_cover <= 0) break;
                    $to_deduct = min($amount_to_cover, (float)$earning['host_amount']);
                    if ($to_deduct >= (float)$earning['host_amount']) {
                        execute_query("UPDATE host_earnings SET status = 'paid_out' WHERE id = ?", [$earning['id']]);
                    }
                    $amount_to_cover -= $to_deduct;
                }
                
                sendNotification($request['host_id'], 'Withdrawal Approved', 'Your withdrawal request for ' . format_currency($request['amount']) . ' has been approved and processed.', 'payment', 'system', $admin_notes);
                
                // 🧾 Audit Logging
                logAudit($_SESSION['user_id'], 'PAYOUT_APPROVE', 'payout_requests', $request_id, "Approved payout of " . format_currency($request['amount']) . " to Host ID: " . $request['host_id'], 'pending', 'paid');
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => "Payout approved successfully."]);
            } else {
                execute_query(
                    "UPDATE payout_requests SET status = 'rejected', admin_notes = ?, processed_at = NOW() WHERE id = ?",
                    [$admin_notes, $request_id]
                );
                sendNotification($request['host_id'], 'Withdrawal Rejected', 'Your withdrawal request for ' . format_currency($request['amount']) . ' was rejected.', 'payment', 'system', $admin_notes);
                
                // 🧾 Audit Logging
                logAudit($_SESSION['user_id'], 'PAYOUT_REJECT', 'payout_requests', $request_id, "Rejected payout of " . format_currency($request['amount']) . " for Host ID: " . $request['host_id'], 'pending', 'rejected');
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => "Payout rejected."]);
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => "Financial transaction failed: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => "Request not found or already processed."]);
    }
    exit;
}

// Stats for Platform Revenue
$platform_stats = [
    'total_earned' => (float)(get_single_result("SELECT SUM(platform_fee) FROM host_earnings WHERE status = 'paid_out'")['SUM(platform_fee)'] ?? 0),
    'pending' => (float)(get_single_result("SELECT SUM(platform_fee) FROM host_earnings WHERE status IN ('pending_review', 'pending_release', 'available')")['SUM(platform_fee)'] ?? 0),
    'host_balance' => (float)(get_single_result("SELECT SUM(host_amount) FROM host_earnings WHERE status = 'available'")['SUM(host_amount)'] ?? 0)
];

// Fetch Pending Requests
$pending_requests = get_multiple_results(
    "SELECT pr.*, u.full_name as host_name, pa.method, pa.account_details 
     FROM payout_requests pr 
     JOIN users u ON pr.host_id = u.user_id 
     JOIN payout_accounts pa ON pr.payout_account_id = pa.id 
     WHERE pr.status = 'pending' 
     ORDER BY pr.created_at ASC"
);

// Fetch History
$history = get_multiple_results(
    "SELECT pr.*, u.full_name as host_name 
     FROM payout_requests pr 
     JOIN users u ON pr.host_id = u.user_id 
     WHERE pr.status != 'pending' 
     ORDER BY pr.processed_at DESC LIMIT 50"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout Management - BookIT Admin</title>
    <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="content">
                <div class="dashboard-header d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1>Finance & Payout Center</h1>
                        <p>Manage host withdrawal requests and track platform revenue.</p>
                    </div>
                </div>

        <?php if ($message): ?>
            <div class="alert alert-success border-0 shadow-sm"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Platform Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo format_currency($platform_stats['total_earned']); ?></div>
                <div class="stat-label">Revenue Realized</div>
                <i class="fas fa-hand-holding-usd fa-2x stat-icon text-success"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo format_currency($platform_stats['pending']); ?></div>
                <div class="stat-label">Pending Commission</div>
                <i class="fas fa-clock fa-2x stat-icon text-info"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo format_currency($platform_stats['host_balance']); ?></div>
                <div class="stat-label">Host Liability</div>
                <i class="fas fa-wallet fa-2x stat-icon text-primary"></i>
            </div>
        </div>

        <!-- Pending Payouts -->
        <div class="breakdown-card mb-5">
            <h3><i class="fas fa-clock me-2"></i> Pending Withdrawal Requests</h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Host</th>
                            <th>Method</th>
                            <th>Account Details</th>
                            <th>Requested Amount</th>
                            <th>Request Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $pr): ?>
                            <tr id="row-payout-<?php echo $pr['id']; ?>">
                                <td class="ps-4">
                                    <div class="fw-bold"><?php echo htmlspecialchars($pr['host_name']); ?></div>
                                    <small class="text-muted">ID: #<?php echo $pr['host_id']; ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark"><?php echo strtoupper($pr['method']); ?></span></td>
                                <td><code><?php echo htmlspecialchars($pr['account_details']); ?></code></td>
                                <td><strong class="text-success"><?php echo format_currency($pr['amount']); ?></strong></td>
                                <td><?php echo date('M d, Y H:i', strtotime($pr['created_at'])); ?></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#actionModal<?php echo $pr['id']; ?>">Review</button>
                                </td>
                            </tr>

                            <!-- Action Modal -->
                            <div class="modal fade" id="actionModal<?php echo $pr['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0">
                                        <form class="payout-form" data-id="<?php echo $pr['id']; ?>">
                                            <input type="hidden" name="action_payout" value="1">
                                            <input type="hidden" name="request_id" value="<?php echo $pr['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="idempotency_key" value="<?php echo uniqid('payout_', true); ?>">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title">Review Payout: #<?php echo $pr['id']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Withdrawal of <strong><?php echo format_currency($pr['amount']); ?></strong> for <strong><?php echo htmlspecialchars($pr['host_name']); ?></strong> via <strong><?php echo strtoupper($pr['method']); ?></strong>.</p>
                                                <div class="mb-3">
                                                    <label class="form-label font-weight-bold">Admin Notes / Voucher Ref</label>
                                                    <textarea name="admin_notes" class="form-control" placeholder="Optional notes..."></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Decision</label>
                                                    <select name="action" class="form-select" required>
                                                        <option value="approve">Approve & Mark as Paid</option>
                                                        <option value="reject">Reject Request</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success px-4 submit-btn">Confirm Transaction</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; if (empty($pending_requests)) echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3 d-block opacity-25"></i> No pending payout requests.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- History -->
        <div class="breakdown-card">
            <h3><i class="fas fa-history me-2"></i> Recent Payout History</h3>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.95rem;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Request #</th>
                            <th>Host</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Processed At</th>
                            <th class="pe-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): 
                            $status_class = $h['status'] === 'paid' ? 'bg-success' : ($h['status'] === 'rejected' ? 'bg-danger' : 'bg-warning');
                        ?>
                            <tr>
                                <td class="ps-4">#<?php echo $h['id']; ?></td>
                                <td><?php echo htmlspecialchars($h['host_name']); ?></td>
                                <td><strong><?php echo format_currency($h['amount']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo strtoupper($h['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($h['processed_at'])); ?></td>
                                <td class="pe-4 text-muted small"><?php echo htmlspecialchars($h['admin_notes']); ?></td>
                            </tr>
                        <?php endforeach; if (empty($history)) echo '<tr><td colspan="6" class="text-center py-4 text-muted">No payout history available.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.payout-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const requestId = this.getAttribute('data-id');
        const submitBtn = this.querySelector('.submit-btn');
        const modal = bootstrap.Modal.getInstance(document.getElementById('actionModal' + requestId));
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: result.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // Still reloading for now to update all stats, but AJAX row removal is possible
                });
                modal.hide();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.error || 'Something went wrong.'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Confirm Transaction';
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Request Failed',
                text: 'Could not connect to server.'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Confirm Transaction';
        }
    });
});
</script>
</body>
</html>
