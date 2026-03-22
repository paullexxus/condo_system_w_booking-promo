<?php
/**
 * Payment Management - Host Dashboard
 * Manage payment methods and process payments using PayMongo and PayPal
 */

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
include_once '../config/paymongo.php';

checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle payment method submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        // Add Payment Method
        if ($action === 'add_payment_method') {
            $method_type = sanitize_input($_POST['method_type']); // paymongo or paypal
            $method_name = sanitize_input($_POST['method_name']);
            
            if ($method_type === 'paymongo') {
                $account_id = sanitize_input($_POST['account_id']);
                $phone = sanitize_input($_POST['phone'] ?? '');
                
                $stmt = $conn->prepare("
                    INSERT INTO host_payment_methods 
                    (host_id, method_type, method_name, account_id, phone, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->bind_param("issss", $host_id, $method_type, $method_name, $account_id, $phone);
                
                if ($stmt->execute()) {
                    $action_message = "PayMongo payment method added successfully!";
                    $action_success = true;
                } else {
                    $action_message = "Failed to add payment method: " . $stmt->error;
                    $action_success = false;
                }
                $stmt->close();
                
            } else if ($method_type === 'paypal') {
                $email = sanitize_input($_POST['email']);
                
                $stmt = $conn->prepare("
                    INSERT INTO host_payment_methods 
                    (host_id, method_type, method_name, email, is_active, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->bind_param("isss", $host_id, $method_type, $method_name, $email);
                
                if ($stmt->execute()) {
                    $action_message = "PayPal payment method added successfully!";
                    $action_success = true;
                } else {
                    $action_message = "Failed to add payment method: " . $stmt->error;
                    $action_success = false;
                }
                $stmt->close();
            }
        }
        
        // Update Payment Method
        else if ($action === 'edit_payment_method') {
            $payment_method_id = sanitize_input($_POST['payment_method_id']);
            $method_name = sanitize_input($_POST['method_name']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("
                UPDATE host_payment_methods 
                SET method_name = ?, is_active = ?
                WHERE payment_method_id = ? AND host_id = ?
            ");
            $stmt->bind_param("siii", $method_name, $is_active, $payment_method_id, $host_id);
            
            if ($stmt->execute()) {
                $action_message = "Payment method updated successfully!";
                $action_success = true;
            } else {
                $action_message = "Failed to update payment method: " . $stmt->error;
                $action_success = false;
            }
            $stmt->close();
        }
        
        // Delete Payment Method
        else if ($action === 'delete_payment_method') {
            $payment_method_id = sanitize_input($_POST['payment_method_id']);
            
            // Check if method exists and belongs to host
            $method = get_single_result(
                "SELECT * FROM host_payment_methods WHERE payment_method_id = ? AND host_id = ?",
                [$payment_method_id, $host_id]
            );
            
            if ($method) {
                $stmt = $conn->prepare("
                    DELETE FROM host_payment_methods 
                    WHERE payment_method_id = ? AND host_id = ?
                ");
                $stmt->bind_param("ii", $payment_method_id, $host_id);
                
                if ($stmt->execute()) {
                    $action_message = "Payment method deleted successfully!";
                    $action_success = true;
                } else {
                    $action_message = "Failed to delete payment method: " . $stmt->error;
                    $action_success = false;
                }
                $stmt->close();
            } else {
                $action_message = "Payment method not found!";
                $action_success = false;
            }
        }
        
        // Process Refund
        else if ($action === 'process_refund') {
            $reservation_id = sanitize_input($_POST['reservation_id']);
            $refund_reason = sanitize_input($_POST['refund_reason']);
            
            // Get reservation and payment info
            $reservation = get_single_result("
                SELECT r.*, p.payment_id, p.amount FROM reservations r
                LEFT JOIN payments p ON r.reservation_id = p.reservation_id
                WHERE r.reservation_id = ?
            ", [$reservation_id]);
            
            if ($reservation && $reservation['payment_id']) {
                // Get host's PayMongo method
                $paymongo_method = get_single_result(
                    "SELECT * FROM host_payment_methods WHERE host_id = ? AND method_type = 'paymongo' AND is_active = 1 LIMIT 1",
                    [$host_id]
                );
                
                if ($paymongo_method) {
                    // Process refund via PayMongo
                    $refund_response = createPaymongoRefund(
                        $reservation['payment_id'],
                        $reservation['amount'] * 100 // Convert to cents
                    );
                    
                    if (isset($refund_response['data'])) {
                        $refund_id = $refund_response['data']['id'];
                        $refund_status = $refund_response['data']['attributes']['status'];
                        
                        // Log refund in database
                        $stmt = $conn->prepare("
                            INSERT INTO refunds 
                            (reservation_id, payment_id, refund_id, amount, reason, status, processed_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->bind_param("issisii", $reservation_id, $reservation['payment_id'], $refund_id, $reservation['amount'], $refund_reason, $refund_status, $host_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $action_message = "Refund processed successfully! Status: " . ucfirst($refund_status);
                        $action_success = true;
                    } else {
                        $action_message = "Failed to process refund: " . ($refund_response['error'] ?? 'Unknown error');
                        $action_success = false;
                    }
                } else {
                    $action_message = "No active PayMongo payment method configured!";
                    $action_success = false;
                }
            } else {
                $action_message = "Reservation or payment not found!";
                $action_success = false;
            }
        }
    }
}

// Fetch all payment methods for this host
$payment_methods = get_multiple_results("
    SELECT * FROM host_payment_methods 
    WHERE host_id = ?
    ORDER BY created_at DESC
", [$host_id]);

// Fetch recent transactions
$recent_payments = get_multiple_results("
    SELECT p.*, r.reservation_id, r.check_in_date, r.check_out_date, u.full_name
    FROM payments p
    LEFT JOIN reservations r ON p.reservation_id = r.reservation_id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY p.payment_date DESC
    LIMIT 20
", []);

// Calculate payment statistics
$stats = get_single_result("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE 0 END) as total_received,
        COUNT(CASE WHEN p.payment_status = 'pending' THEN 1 END) as pending_payments,
        SUM(CASE WHEN p.payment_status = 'pending' THEN p.amount ELSE 0 END) as pending_amount
    FROM payments p
    LEFT JOIN reservations r ON p.reservation_id = r.reservation_id
    WHERE r.unit_id IN (SELECT unit_id FROM units WHERE host_id = ?)
", [$host_id]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
        }
        
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        .content {
            flex: 1;
            padding: 30px;
            max-width: 100%;
            width: 100%;
            margin-left: 280px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .stat-card .stat-icon {
            font-size: 32px;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .section-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .payment-method-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .payment-method-card.paymongo {
            border-left: 4px solid #0066cc;
        }
        
        .payment-method-card.paypal {
            border-left: 4px solid #003087;
        }
        
        .payment-method-info {
            flex: 1;
        }
        
        .payment-method-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .payment-method-details {
            font-size: 13px;
            color: #666;
        }
        
        .payment-method-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 10px;
        }
        
        .badge-paymongo {
            background: #0066cc;
            color: white;
        }
        
        .badge-paypal {
            background: #003087;
            color: white;
        }
        
        .badge-active {
            background: #27ae60;
            color: white;
        }
        
        .badge-inactive {
            background: #95a5a6;
            color: white;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 1px solid #e0e0e0;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .btn {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            color: white;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            color: white;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1><i class="fas fa-credit-card"></i> Payment Management</h1>
            </div>
            
            <!-- Success/Error Alert -->
            <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-label">Total Received</div>
                    <div class="stat-value">₱<?php echo number_format($stats['total_received'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-value">₱<?php echo number_format($stats['pending_amount'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="stat-label">Total Transactions</div>
                    <div class="stat-value"><?php echo $stats['total_payments'] ?? 0; ?></div>
                </div>
            </div>
            
            <!-- Payment Methods Section -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-credit-card"></i> Payment Methods</h2>
                    <button class="btn btn-primary" onclick="openAddPaymentMethodModal()">
                        <i class="fas fa-plus"></i> Add Payment Method
                    </button>
                </div>
                
                <?php if (!empty($payment_methods)): ?>
                    <?php foreach ($payment_methods as $method): ?>
                    <div class="payment-method-card <?php echo $method['method_type']; ?>">
                        <div class="payment-method-info">
                            <div class="payment-method-name">
                                <span class="payment-method-badge badge-<?php echo $method['method_type']; ?>">
                                    <?php echo strtoupper($method['method_type']); ?>
                                </span>
                                <?php echo htmlspecialchars($method['method_name']); ?>
                            </div>
                            <div class="payment-method-details">
                                <?php if ($method['method_type'] === 'paymongo'): ?>
                                    Account: <?php echo htmlspecialchars($method['account_id']); ?>
                                    <?php if ($method['phone']): ?> • Phone: <?php echo htmlspecialchars($method['phone']); ?><?php endif; ?>
                                <?php else: ?>
                                    Email: <?php echo htmlspecialchars($method['email']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="payment-method-badge badge-<?php echo $method['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $method['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <button class="btn btn-secondary" onclick="openEditPaymentMethodModal(<?php echo htmlspecialchars(json_encode($method)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger" onclick="openDeleteConfirm(<?php echo $method['payment_method_id']; ?>, '<?php echo htmlspecialchars($method['method_name']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-credit-card"></i>
                        <h3>No Payment Methods</h3>
                        <p>Add a payment method to receive payments from customers.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Transactions -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent Transactions</h2>
                </div>
                
                <?php if (!empty($recent_payments)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Guest</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['full_name'] ?? 'N/A'); ?></td>
                                <td>₱<?php echo number_format($payment['amount']); ?></td>
                                <td>
                                    <span class="payment-method-badge badge-<?php echo $payment['payment_method']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-method-badge badge-<?php echo strtolower($payment['payment_status']); ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['payment_status'] === 'paid'): ?>
                                    <button class="btn btn-secondary" onclick="openRefundModal(<?php echo $payment['reservation_id']; ?>)">
                                        <i class="fas fa-undo"></i> Refund
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Transactions</h3>
                    <p>Your payment transactions will appear here.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Payment Method Modal -->
    <div id="addPaymentMethodModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payment Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addPaymentMethodForm">
                        <input type="hidden" name="action" value="add_payment_method">
                        
                        <div class="form-group">
                            <label>Payment Provider</label>
                            <select name="method_type" id="methodType" onchange="updatePaymentMethodFields()" required>
                                <option value="">Select Provider</option>
                                <option value="paymongo">PayMongo (GCash, Grab Pay, Cards)</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Method Name</label>
                            <input type="text" name="method_name" placeholder="e.g., My GCash Account" required>
                        </div>
                        
                        <!-- PayMongo Fields -->
                        <div id="paymongoFields" style="display: none;">
                            <div class="form-group">
                                <label>Account ID / Phone Number</label>
                                <input type="text" name="account_id" placeholder="e.g., 09171234567">
                            </div>
                            <div class="form-group">
                                <label>Phone (Optional)</label>
                                <input type="tel" name="phone" placeholder="e.g., +63 917 123 4567">
                            </div>
                        </div>
                        
                        <!-- PayPal Fields -->
                        <div id="paypalFields" style="display: none;">
                            <div class="form-group">
                                <label>PayPal Email</label>
                                <input type="email" name="email" placeholder="your@paypal.email">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addPaymentMethodForm" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Method
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Payment Method Modal -->
    <div id="editPaymentMethodModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payment Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editPaymentMethodForm">
                        <input type="hidden" name="action" value="edit_payment_method">
                        <input type="hidden" name="payment_method_id" id="editMethodId">
                        
                        <div class="form-group">
                            <label>Method Name</label>
                            <input type="text" name="method_name" id="editMethodName" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" id="editMethodActive">
                                Active
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editPaymentMethodForm" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Refund Modal -->
    <div id="refundModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="refundForm">
                        <input type="hidden" name="action" value="process_refund">
                        <input type="hidden" name="reservation_id" id="refundReservationId">
                        
                        <div class="form-group">
                            <label>Refund Reason</label>
                            <textarea name="refund_reason" placeholder="Explain why you're issuing this refund..." rows="4" required></textarea>
                        </div>
                        
                        <p style="color: #666; font-size: 13px; margin-top: 15px;">
                            <i class="fas fa-info-circle"></i> Full amount will be refunded to the guest's PayPal or payment method.
                        </p>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="refundForm" class="btn btn-danger">
                        <i class="fas fa-check"></i> Process Refund
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteMethodName"></strong>?</p>
                    <p style="color: #666; font-size: 13px;">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete_payment_method">
                        <input type="hidden" name="payment_method_id" id="deleteMethodId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updatePaymentMethodFields() {
            const methodType = document.getElementById('methodType').value;
            document.getElementById('paymongoFields').style.display = methodType === 'paymongo' ? 'block' : 'none';
            document.getElementById('paypalFields').style.display = methodType === 'paypal' ? 'block' : 'none';
        }
        
        function openAddPaymentMethodModal() {
            document.getElementById('addPaymentMethodForm').reset();
            document.getElementById('methodType').value = '';
            updatePaymentMethodFields();
            const modal = new bootstrap.Modal(document.getElementById('addPaymentMethodModal'));
            modal.show();
        }
        
        function openEditPaymentMethodModal(method) {
            document.getElementById('editMethodId').value = method.payment_method_id;
            document.getElementById('editMethodName').value = method.method_name;
            document.getElementById('editMethodActive').checked = method.is_active === 1;
            const modal = new bootstrap.Modal(document.getElementById('editPaymentMethodModal'));
            modal.show();
        }
        
        function openRefundModal(reservationId) {
            document.getElementById('refundReservationId').value = reservationId;
            const modal = new bootstrap.Modal(document.getElementById('refundModal'));
            modal.show();
        }
        
        function openDeleteConfirm(methodId, methodName) {
            document.getElementById('deleteMethodId').value = methodId;
            document.getElementById('deleteMethodName').textContent = methodName;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
