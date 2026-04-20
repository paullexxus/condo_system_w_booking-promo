<?php
// Admin Revenue Dashboard
// Tracks the 10% platform fee and admin payouts

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']);

$admin_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Platform Fee Config
$fee_setting = get_single_result("SELECT setting_value FROM system_settings WHERE setting_key = 'admin_revenue_percent'");
$fee_percent = $fee_setting ? (float)$fee_setting['setting_value'] : 10;
$admin_multiplier = $fee_percent / 100;

// POST Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize_input($_POST['action']);
    
    // Add Payout Account
    if ($action === 'add_payout_account') {
        $method = sanitize_input($_POST['method']);
        $account_name = sanitize_input($_POST['account_name']);
        $account_details = sanitize_input($_POST['account_details']);
        
        $stmt = $conn->prepare("INSERT INTO payout_accounts (user_id, method, account_name, account_details, is_default, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->bind_param("isss", $admin_id, $method, $account_name, $account_details);
        
        if ($stmt->execute()) {
            $action_message = "Admin payout account added successfully!";
            $action_success = true;
        } else {
            $action_message = "Failed to add account: " . $stmt->error;
            $action_success = false;
        }
        $stmt->close();
    }
    
    // Request Withdrawal
    else if ($action === 'request_withdrawal') {
        $amount_requested = (float)$_POST['amount'];
        $account_id = (int)$_POST['account_id'];
        
        $account = get_single_result("SELECT * FROM payout_accounts WHERE id = ? AND user_id = ?", [$account_id, $admin_id]);
        
        if ($account && $amount_requested > 0) {
            // Verify Available Balance
            $revenue_data = execute_query("
                SELECT SUM(p.amount) as raw_earned
                FROM payments p
                JOIN reservations r ON p.reservation_id = r.reservation_id
                WHERE p.payment_status IN ('paid','completed','verified')
                  AND r.status IN ('completed','checked_out')
                  AND r.check_out_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")->fetch_assoc();
            
            $available_gross = (float)($revenue_data['raw_earned'] ?? 0) * $admin_multiplier;
            
            $withdrawn_data = execute_query("
                SELECT SUM(amount) as total_withdrawals 
                FROM payouts 
                WHERE user_id = ? AND status IN ('pending', 'processing', 'completed')
            ", [$admin_id])->fetch_assoc();
            
            $available_balance = $available_gross - (float)($withdrawn_data['total_withdrawals'] ?? 0);
            
            if ($amount_requested <= $available_balance) {
                $payout_details_str = "Method: {$account['method']}\nName: {$account['account_name']}\nDetails: {$account['account_details']}";
                $stmt = $conn->prepare("INSERT INTO payouts (user_id, amount, method, account_details, status, created_at) VALUES (?, ?, ?, ?, 'completed', NOW())");
                // For admin, status is automatically 'completed' since they own the platform
                $stmt->bind_param("idss", $admin_id, $amount_requested, $account['method'], $payout_details_str);
                
                if ($stmt->execute()) {
                    $action_message = "Withdrawal recorded successfully!";
                    $action_success = true;
                }
            } else {
                $action_message = "Requested amount exceeds available balance.";
                $action_success = false;
            }
        }
    }
}

// Data Fetching

// 1. Payout Accounts
$payout_accounts = get_multiple_results("SELECT * FROM payout_accounts WHERE user_id = ? ORDER BY created_at DESC", [$admin_id]);

// 2. Earnings Calculation
// Pending Release
$pending_release_data = get_single_result("
    SELECT SUM(p.amount) as raw_pending
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.reservation_id
    WHERE p.payment_status IN ('paid','completed','verified')
      AND (r.status NOT IN ('completed','checked_out') OR r.check_out_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
");

// Available 
$available_data = get_single_result("
    SELECT SUM(p.amount) as raw_available
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.reservation_id
    WHERE p.payment_status IN ('paid','completed','verified')
      AND r.status IN ('completed','checked_out')
      AND r.check_out_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
");

// Use centralized defensive column check
$p_user_col = get_payout_user_col();

$withdrawals_data = get_single_result("
    SELECT SUM(amount) as withdrawn 
    FROM payouts 
    WHERE $p_user_col = ? AND status IN ('pending', 'processing', 'completed')
", [$admin_id]);

$pending_earnings = (float)($pending_release_data['raw_pending'] ?? 0) * $admin_multiplier;
$total_earned = (float)($available_data['raw_available'] ?? 0) * $admin_multiplier;
$available_balance = $total_earned - (float)($withdrawals_data['withdrawn'] ?? 0);
$total_net_earnings = $pending_earnings + $total_earned;

// 3. Transactions Flow (Global)
$transactions = get_multiple_results("
    SELECT 
        'Platform Fee' as type,
        r.reservation_id,
        (p.amount * ?) as net_amount,
        p.payment_date as date,
        CASE 
            WHEN r.status IN ('completed','checked_out') AND r.check_out_date < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'Available'
            ELSE 'Pending Release'
        END as flow_status
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.reservation_id
    WHERE p.payment_status IN ('paid','completed','verified')
    
    UNION ALL
    
    SELECT 
        'Admin Withdrawal' as type,
        payout_id as reservation_id,
        -(amount) as net_amount,
        created_at as date,
        'Recorded' as flow_status
    FROM payouts
    WHERE $p_user_col = ?
    
    ORDER BY date DESC LIMIT 100
", [$admin_multiplier, $admin_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Revenue - BookIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 280px; padding: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 600; color: #2c3e50; }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .stat-label { font-size: 14px; color: #6c757d; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #2c3e50; }
        
        .section { background: white; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .section-header h2 { font-size: 20px; margin: 0; font-weight: 600; }
        
        .badge-status { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .bg-available { background: #d4edda; color: #155724; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-paidout { background: #cce5ff; color: #004085; }
        .text-green { color: #28a745; }
        .text-red { color: #dc3545; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content flex-grow-1">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-chart-line me-2 text-primary"></i> Platform Revenue</h1>
                <div>
                    <span class="badge bg-primary p-2 fs-6">Admin Cut: <?php echo $fee_percent; ?>%</span>
                    <a href="settings.php?tab=payment" class="btn btn-sm btn-outline-secondary ms-2"><i class="fas fa-cog"></i> Config</a>
                </div>
            </div>
            
            <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($action_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="stats-container">
                <div class="stat-card" style="border-left: 5px solid #28a745;">
                    <div class="stat-label">Available Balance</div>
                    <div class="stat-value">₱<?php echo number_format($available_balance, 2); ?></div>
                    <button class="btn btn-success btn-sm mt-3 w-100 fw-bold" onclick="new bootstrap.Modal(document.getElementById('withdrawModal')).show()">
                        <i class="fas fa-hand-holding-usd"></i> Record Withdrawal
                    </button>
                </div>
                
                <div class="stat-card" style="border-left: 5px solid #ffc107;">
                    <div class="stat-label">Pending Release</div>
                    <div class="stat-value">₱<?php echo number_format($pending_earnings, 2); ?></div>
                </div>
                
                <div class="stat-card" style="border-left: 5px solid #17a2b8;">
                    <div class="stat-label">Lifetime Revenue</div>
                    <div class="stat-value">₱<?php echo number_format($total_net_earnings, 2); ?></div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Transactions Flow -->
                    <div class="section">
                        <div class="section-header">
                            <h2><i class="fas fa-list"></i> Revenue Transactions</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $txn): 
                                        $is_withdrawal = $txn['type'] === 'Admin Withdrawal';
                                        $amount_class = $is_withdrawal ? 'text-red' : 'text-green';
                                        $prefix = $is_withdrawal ? '' : '+';
                                        
                                        $status_class = '';
                                        switch($txn['flow_status']) {
                                            case 'Available': $status_class = 'bg-available'; break;
                                            case 'Pending Release': $status_class = 'bg-pending'; break;
                                            default: $status_class = 'bg-paidout'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($txn['date'])); ?></td>
                                        <td>
                                            <strong><?php echo $txn['type']; ?></strong><br>
                                            <span class="text-muted small">Ref #<?php echo $txn['reservation_id']; ?></span>
                                        </td>
                                        <td class="<?php echo $amount_class; ?> fw-bold">
                                            <?php echo $prefix; ?>₱<?php echo number_format(abs($txn['net_amount']), 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo $status_class; ?>"><?php echo $txn['flow_status']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Admin Payout Accounts -->
                    <div class="section">
                        <div class="section-header">
                            <h2><i class="fas fa-wallet"></i> Linked Accounts</h2>
                            <button class="btn btn-outline-primary btn-sm" onclick="new bootstrap.Modal(document.getElementById('addAccountModal')).show()">Add</button>
                        </div>
                        <?php foreach ($payout_accounts as $acc): ?>
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-bold"><?php echo htmlspecialchars($acc['method']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($acc['account_name']); ?></div>
                            <div><?php echo htmlspecialchars($acc['account_details'] ?? ''); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($payout_accounts)) echo "<p class='text-muted text-center'>No linked accounts.</p>"; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="addAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_payout_account">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Admin Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Method</label>
                            <select name="method" class="form-select" required>
                                <option value="GCash">GCash</option>
                                <option value="PayMaya">PayMaya</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Name</label>
                            <input type="text" name="account_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Details (Number/Voucher/IBAN)</label>
                            <input type="text" name="account_details" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="withdrawModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="request_withdrawal">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Admin Withdrawal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info border-0 shadow-sm">
                            <strong>Platform Available Balance (10% Cut):</strong> <br>
                            <span class="fs-4">₱<?php echo number_format($available_balance, 2); ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (₱)</label>
                            <input type="number" name="amount" class="form-control" max="<?php echo floor($available_balance); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Transfer To</label>
                            <select name="account_id" class="form-select" required>
                                <?php foreach ($payout_accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['method'] . ' - ' . ($acc['account_details'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-muted small"><i class="fas fa-info-circle"></i> This will immediately securely deduct the platform's float balance and log the transaction.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" <?php echo empty($payout_accounts) || $available_balance <= 0 ? 'disabled' : ''; ?>>Withdraw Funds</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
