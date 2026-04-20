<?php
// Payment Management - Host Earnings Dashboard
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Platform Fee Config
$fee_setting = get_single_result("SELECT setting_value FROM system_settings WHERE setting_key = 'admin_revenue_percent'");
$fee_percent = $fee_setting ? (float)$fee_setting['setting_value'] : 10;
$host_multiplier = (100 - $fee_percent) / 100;

// POST Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize_input($_POST['action']);
    
    if ($action === 'add_payout_account') {
        $method = sanitize_input($_POST['method']);
        $account_name = sanitize_input($_POST['account_name']);
        $account_number = sanitize_input($_POST['account_number']);
        $p_acc_col = get_payout_account_col();
        
        $sql = "INSERT INTO payout_accounts (user_id, method, account_name, $p_acc_col, is_default, created_at) VALUES (?, ?, ?, ?, 0, NOW())";
        if (execute_query($sql, [$host_id, $method, $account_name, $account_number])) {
            $action_message = "Payout account linked successfully!";
            $action_success = true;
        } else {
            $action_message = "Failed to link account.";
        }
    }
    else if ($action === 'request_withdrawal') {
        $amount = (float)$_POST['amount'];
        $account_id = (int)$_POST['account_id'];
        $p_acc_col = get_payout_account_col();
        $account = get_single_result("SELECT * FROM payout_accounts WHERE id = ? AND user_id = ?", [$account_id, $host_id]);
        
        if ($account && $amount >= 500) {
            $acc_val = $account[$p_acc_col] ?? '';
            $details = "Method: {$account['method']}\nName: {$account['account_name']}\nDetails: $acc_val";
            $p_user_col = get_payout_user_col();
            
            $sql = "INSERT INTO payouts ($p_user_col, amount, method, account_details, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
            if (execute_query($sql, [$host_id, $amount, $account['method'], $details])) {
                $action_message = "Withdrawal request submitted for approval.";
                $action_success = true;
                execute_query("INSERT INTO notifications (user_id, title, message, type, source) SELECT user_id, 'Withdrawal Requested', 'Host #$host_id requested ₱$amount', 'system', 'system' FROM users WHERE role = 'admin'");
            }
        }
    }
}

// Data Fetching
$p_acc_col = get_payout_account_col();
$payout_accounts = get_multiple_results("SELECT * FROM payout_accounts WHERE user_id = ? ORDER BY created_at DESC", [$host_id]);

// Balance Logic
$rev = get_single_result("
    SELECT SUM(p.amount) as raw_earned
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.reservation_id
    JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = ? AND p.payment_status IN ('paid','completed','verified')
      AND r.status IN ('completed','checked_out')
      AND r.check_out_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
", [$host_id]);

$p_user_col = get_payout_user_col();
$withdrawn = get_single_result("SELECT SUM(amount) as total FROM payouts WHERE $p_user_col = ? AND status IN ('pending', 'processing', 'completed')", [$host_id]);

$available_balance = ((float)($rev['raw_earned'] ?? 0) * $host_multiplier) - (float)($withdrawn['total'] ?? 0);

// Transactions
$transactions = get_multiple_results("
    SELECT 'Payment' as type, p.amount * $host_multiplier as net, p.payment_date as date, 'Available' as status
    FROM payments p JOIN reservations r ON p.reservation_id = r.reservation_id JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = $host_id AND p.payment_status IN ('paid','completed','verified')
    UNION ALL
    SELECT 'Payout' as type, -amount as net, created_at as date, status as status
    FROM payouts WHERE $p_user_col = $host_id
    ORDER BY date DESC LIMIT 30
");

$page_title = 'Payouts & Financials';
include '../templates/host_layout_header.php';
?>

<!-- Page Header -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h1 class="page-title"><i class="fas fa-hand-holding-usd pe-2"></i>Financials & Payouts</h1>
        <p class="text-muted small mt-1">Review your earnings history and manage withdrawal requests</p>
    </div>
    <div class="col-md-5 text-md-end">
        <span class="badge glass-panel text-muted border px-3 py-2">
            <i class="fas fa-percent me-2 text-warning"></i>Standard Platform Fee: <?php echo $fee_percent; ?>%
        </span>
    </div>
</div>

<?php if ($action_message): ?>
    <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?> alert-dismissible fade show"><?php echo $action_message; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card-modern shadow-sm border-start border-success border-5">
            <div class="small fw-bold text-muted text-uppercase mb-1">Withdrawable Balance</div>
            <div class="h2 fw-bold text-success mb-3">₱<?php echo number_format($available_balance, 2); ?></div>
            <button class="btn btn-primary rounded-pill px-4" onclick="new bootstrap.Modal(document.getElementById('withdrawModal')).show()" <?php echo ($available_balance < 500 || empty($payout_accounts)) ? 'disabled' : ''; ?>>
                <i class="fas fa-hand-holding-usd me-2"></i> Request Payout
            </button>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-modern shadow-sm border-start border-primary border-5">
            <div class="small fw-bold text-muted text-uppercase mb-1">Total Net Earnings</div>
            <div class="h2 fw-bold mb-1">₱<?php echo number_format(((float)($rev['raw_earned'] ?? 0) * $host_multiplier), 2); ?></div>
            <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i> Net of platform commissions</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-modern shadow-sm border-0 p-0 overflow-hidden">
            <div class="p-4 border-bottom bg-light">
                <h5 class="mb-0 fw-bold"><i class="fas fa-history text-primary me-2"></i>Recent Transactions</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light bg-opacity-50 text-muted small fw-bold">
                        <tr>
                            <th class="ps-4">DATE</th>
                            <th>DESCRIPTION</th>
                            <th class="text-end">NET AMOUNT</th>
                            <th class="text-center pe-4">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): 
                            $is_payout = $t['type'] === 'Payout';
                        ?>
                        <tr>
                            <td class="small text-muted"><?php echo date('M d, Y', strtotime($t['date'])); ?></td>
                            <td><i class="fas <?php echo $is_payout ? 'fa-arrow-up text-danger' : 'fa-arrow-down text-success'; ?> me-2"></i> <?php echo $t['type']; ?></td>
                            <td class="text-end fw-bold <?php echo $is_payout ? 'text-danger' : 'text-success'; ?>">
                                <?php echo ($is_payout ? '-' : '+') . '₱' . number_format(abs($t['net']), 2); ?>
                            </td>
                            <td class="text-center"><span class="badge bg-light text-dark rounded-pill px-3"><?php echo $t['status']; ?></span></td>
                        </tr>
                        <?php endforeach; if(empty($transactions)) echo '<tr><td colspan="4" class="text-center py-4 text-muted">No transactions found.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Payout Accounts</h5>
                <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="new bootstrap.Modal(document.getElementById('addAccountModal')).show()"><i class="fas fa-plus"></i></button>
            </div>
            <div class="card-body pt-0">
                <?php foreach ($payout_accounts as $acc): ?>
                <div class="p-3 bg-light rounded-3 mb-2 border">
                    <div class="d-flex justify-content-between">
                        <b><?php echo htmlspecialchars($acc['method']); ?></b>
                        <i class="fas fa-university text-muted opacity-50"></i>
                    </div>
                    <div class="small text-muted"><?php echo htmlspecialchars($acc['account_name']); ?></div>
                    <code class="small text-primary"><?php echo htmlspecialchars($acc[$p_acc_col] ?? ''); ?></code>
                </div>
                <?php endforeach; if(empty($payout_accounts)) echo '<p class="text-center text-muted py-3">No payout accounts linked.</p>'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Account -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0">
            <form method="POST">
                <input type="hidden" name="action" value="add_payout_account">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Link E-Wallet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Platform</label>
                        <select name="method" class="form-select" required>
                            <option value="GCash">GCash</option>
                            <option value="PayMaya">PayMaya</option>
                            <option value="PayPal">PayPal</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="account_name" class="form-control" placeholder="Account Name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account No. / Email</label>
                        <input type="text" name="account_number" class="form-control" placeholder="0917-xxx-xxxx" required>
                    </div>
                </div>
                <div class="modal-footer p-0 border-0">
                    <button type="submit" class="btn btn-primary w-100 rounded-0 py-3">Link Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Withdrawal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0">
            <form method="POST">
                <input type="hidden" name="action" value="request_withdrawal">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Request Transfer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success py-2 text-center small mb-3">
                        Available: <b>₱<?php echo number_format($available_balance, 2); ?></b>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₱)</label>
                        <input type="number" name="amount" class="form-control" min="500" max="<?php echo floor($available_balance); ?>" required>
                        <small class="text-muted">Minimum withdrawal: ₱500</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Destination</label>
                        <select name="account_id" class="form-select" required>
                            <?php foreach ($payout_accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['method'] . ' (' . ($acc[$p_acc_col] ?? '') . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer p-0 border-0">
                    <button type="submit" class="btn btn-success w-100 rounded-0 py-3">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/host_layout_footer.php'; ?>
