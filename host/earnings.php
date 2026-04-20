<?php
require_once '../includes/session.php';
require_once '../config/db.php';
require_once '../includes/functions.php';
checkRole(['host', 'manager', 'admin']);

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Payout Account Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payout'])) {
    $method = sanitize_input($_POST['method']);
    $account_name = sanitize_input($_POST['account_name']);
    $account_details = sanitize_input($_POST['account_details']);
    
    $existing = get_single_result("SELECT id FROM payout_accounts WHERE user_id = ? AND method = ?", [$user_id, $method]);
    
    if ($existing) {
        $sql = "UPDATE payout_accounts SET account_name = ?, account_details = ?, updated_at = NOW() WHERE id = ?";
        $params = [$account_name, $account_details, $existing['id']];
    } else {
        $sql = "INSERT INTO payout_accounts (user_id, method, account_name, account_details) VALUES (?, ?, ?, ?)";
        $params = [$user_id, $method, $account_name, $account_details];
    }
    
    if (execute_query($sql, $params)) {
        $message = "Payout account updated successfully.";
    } else {
        $error = "Failed to update payout account.";
    }
}

// Handle Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $amount = (float)$_POST['withdraw_amount'];
    $method = sanitize_input($_POST['payout_method']);
    
    $payout_acc = get_single_result("SELECT id FROM payout_accounts WHERE user_id = ? AND method = ?", [$user_id, $method]);
    $available_res = get_single_result("SELECT SUM(host_amount) as total FROM host_earnings WHERE host_id = ? AND status = 'available'", [$user_id]);
    $available_balance = (float)($available_res['total'] ?? 0);
    
    if (!$payout_acc) {
        $error = "Please set up your $method payout account first.";
    } elseif ($amount < 500) {
        $error = "Minimum withdrawal amount is ₱500.00.";
    } elseif ($amount > $available_balance) {
        $error = "Insufficient available balance.";
    } else {
        $idempotency_key = "WITHDRAW_" . $user_id . "_" . time(); 
        $sql = "INSERT INTO payout_requests (host_id, payout_account_id, amount, status, idempotency_key) VALUES (?, ?, ?, 'pending', ?)";
        if (execute_query($sql, [$user_id, $payout_acc['id'], $amount, $idempotency_key])) {
            $message = "Withdrawal request for " . format_currency($amount) . " submitted and is under review.";
        } else {
            $error = "Failed to submit withdrawal request.";
        }
    }
}

// Fetch Stats
$stats = [
    'total_earned' => (float)(get_single_result("SELECT SUM(host_amount) as s FROM host_earnings WHERE host_id = ? AND status = 'paid_out'", [$user_id])['s'] ?? 0),
    'pending' => (float)(get_single_result("SELECT SUM(host_amount) as s FROM host_earnings WHERE host_id = ? AND status IN ('pending_review', 'pending_release')", [$user_id])['s'] ?? 0),
    'available' => (float)(get_single_result("SELECT SUM(host_amount) as s FROM host_earnings WHERE host_id = ? AND status = 'available'", [$user_id])['s'] ?? 0)
];

// Fetch Transactions
$transactions = get_multiple_results(
    "SELECT he.*, r.check_in_date, r.check_out_date, u.unit_number 
     FROM host_earnings he 
     JOIN reservations r ON he.reservation_id = r.reservation_id 
     JOIN units u ON r.unit_id = u.unit_id 
     WHERE he.host_id = ? 
     ORDER BY he.created_at DESC LIMIT 30",
    [$user_id]
);

// Fetch Payout Accounts
$payout_accounts = [];
$pa_res = get_multiple_results("SELECT * FROM payout_accounts WHERE user_id = ?", [$user_id]);
foreach ($pa_res as $pa) { $payout_accounts[$pa['method']] = $pa; }

$page_title = 'Earnings Dashboard';

// Extra CSS
ob_start();
?>
<style>
    .status-badge { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 50rem; font-weight: 600; text-transform: uppercase; }
    .badge-pending_review { background-color: #ffeeba; color: #856404; }
    .badge-pending_release { background-color: #d1ecf1; color: #0c5460; }
    .badge-available { background-color: #d4edda; color: #155724; }
    .badge-paid_out { background-color: #cce5ff; color: #004085; }
    .withdrawal-header { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
</style>
<?php
$extra_css = ob_get_clean();

include '../templates/host_layout_header.php';
?>

<!-- Page Header -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h1 class="page-title"><i class="fas fa-wallet pe-2"></i>Financial Overview</h1>
        <p class="text-muted small mt-1">Track your growth and manage your payouts securely</p>
    </div>
    <div class="col-md-5 text-md-end d-flex gap-2 justify-content-md-end mt-3 mt-md-0">
        <button class="btn glass-panel text-primary border rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#payoutSettingsModal">
            <i class="fas fa-cog me-2"></i> Payout Setup
        </button>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#withdrawModal" <?php echo $stats['available'] < 500 ? 'disabled' : ''; ?>>
            <i class="fas fa-money-bill-wave me-2"></i> Withdraw
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card-modern border-0 shadow-sm text-center">
            <div class="stat-icon bg-info bg-opacity-10 text-info mx-auto mb-3" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-check-circle fs-4"></i>
            </div>
            <h6 class="text-muted small fw-bold uppercase mb-1">Total Paid Out</h6>
            <h3 class="fw-bold mb-0"><?php echo format_currency($stats['total_earned']); ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-modern border-0 shadow-sm text-center">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-clock fs-4"></i>
            </div>
            <h6 class="text-muted small fw-bold uppercase mb-1">Pending Review</h6>
            <h3 class="fw-bold mb-0 text-warning"><?php echo format_currency($stats['pending']); ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-modern border-0 shadow-sm text-center bg-primary bg-opacity-10" style="border: 1px solid rgba(13, 110, 253, 0.1) !important;">
            <div class="stat-icon bg-primary text-white mx-auto mb-3 shadow-sm" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-wallet fs-4"></i>
            </div>
            <h6 class="text-primary small fw-bold uppercase mb-1">Available to Withdraw</h6>
            <h3 class="fw-bold mb-0 text-primary"><?php echo format_currency($stats['available']); ?></h3>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card-modern border-0 shadow-sm p-0 overflow-hidden mb-5">
    <div class="p-4 border-bottom d-flex align-items-center justify-content-between bg-light bg-opacity-50">
        <h5 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>Earnings History</h5>
        <span class="badge glass-panel text-muted border px-3">Recent 30 Records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <th class="ps-4">Booking ID</th>
                    <th>Unit Ref</th>
                    <th>Stay period</th>
                    <th>Subtotal</th>
                    <th>Commission</th>
                    <th>Your Earning</th>
                    <th class="pe-4">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="ps-4 font-monospace small">#<?php echo $tx['reservation_id']; ?></td>
                        <td><span class="badge border text-dark fw-normal"><?php echo htmlspecialchars($tx['unit_number']); ?></span></td>
                        <td><div class="small fw-500"><?php echo date('M d', strtotime($tx['check_in_date'])); ?> - <?php echo date('M d', strtotime($tx['check_out_date'])); ?></div></td>
                        <td><?php echo format_currency($tx['total_booking_amount']); ?></td>
                        <td><small class="text-danger fw-bold">-<?php echo format_currency($tx['platform_fee']); ?></small></td>
                        <td><strong class="text-success fs-6"><?php echo format_currency($tx['host_amount']); ?></strong></td>
                        <td class="pe-4"><span class="status-badge badge-<?php echo $tx['status']; ?>"><?php echo str_replace('_', ' ', $tx['status']); ?></span></td>
                    </tr>
                <?php endforeach; if (empty($transactions)) echo '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-receipt d-block mb-2 fs-3 opacity-25"></i>No transactions found.</td></tr>'; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Request Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Available Balance</label>
                        <input type="text" class="form-control bg-light" value="<?php echo format_currency($stats['available']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount to Withdraw (Min ₱500)</label>
                        <input type="number" name="withdraw_amount" class="form-control" min="500" max="<?php echo $stats['available']; ?>" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payout Method</label>
                        <select name="payout_method" class="form-select" required>
                            <?php foreach ($payout_accounts as $method => $acc): ?>
                                <option value="<?php echo $method; ?>"><?php echo ucfirst($method); ?> (<?php echo $acc['account_details']; ?>)</option>
                            <?php endforeach; if (empty($payout_accounts)) echo '<option disabled>No payout accounts configured</option>'; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="request_withdrawal" class="btn btn-primary" <?php echo empty($payout_accounts) ? 'disabled' : ''; ?>>Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payout Settings Modal -->
<div class="modal fade" id="payoutSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Payout Accounts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="payoutAccordion">
                    <?php 
                    $methods = ['gcash' => 'fa-mobile-alt', 'paymaya' => 'fa-credit-card', 'bank' => 'fa-university'];
                    foreach ($methods as $m => $icon): 
                        $acc = $payout_accounts[$m] ?? null;
                    ?>
                        <div class="accordion-item mb-2 border rounded overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $m; ?>">
                                    <i class="fas <?php echo $icon; ?> me-2 text-primary"></i> <?php echo ucfirst($m); ?>
                                    <?php if ($acc): ?> <span class="ms-auto badge bg-success me-3">Active</span> <?php endif; ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $m; ?>" class="accordion-collapse collapse" data-bs-parent="#payoutAccordion">
                                <div class="accordion-body">
                                    <form method="POST">
                                        <input type="hidden" name="method" value="<?php echo $m; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" name="account_name" class="form-control" value="<?php echo $acc['account_name'] ?? ''; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Account details (Phone/Email/No)</label>
                                            <input type="text" name="account_details" class="form-control" value="<?php echo $acc['account_details'] ?? ''; ?>" required>
                                        </div>
                                        <button type="submit" name="update_payout" class="btn btn-sm btn-primary">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/host_layout_footer.php'; ?>
