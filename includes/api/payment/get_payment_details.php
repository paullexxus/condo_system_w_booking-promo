<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || (!$_SESSION['user_role'] === 'admin' && !$_SESSION['user_role'] === 'host')) {
    http_response_code(403);
    exit('Access Denied');
}

if (!isset($_GET['payment_id']) || empty($_GET['payment_id'])) {
    http_response_code(400);
    exit('Payment ID is required');
}

$payment_id = intval($_GET['payment_id']);

try {
    $query = "SELECT p.*, r.reservation_id, r.check_in, r.check_out, r.total_amount,
                     u.username as renter_name, u.email as renter_email, u.phone as renter_phone,
                     prop.property_name, prop.property_type, prop.address as property_address,
                     h.username as host_name, h.email as host_email, h.phone as host_phone,
                     b.branch_name, b.branch_address, b.branch_phone,
                     pproof.filename as proof_filename, pproof.file_path as proof_filepath
              FROM payments p
              INNER JOIN reservations r ON p.reservation_id = r.reservation_id
              INNER JOIN users u ON r.renter_id = u.user_id
              INNER JOIN properties prop ON r.property_id = prop.property_id
              INNER JOIN users h ON prop.host_id = h.user_id
              INNER JOIN branches b ON prop.branch_id = b.branch_id
              LEFT JOIN payment_proofs pproof ON p.payment_id = pproof.payment_id
              WHERE p.payment_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo '<div class="alert alert-warning">Payment not found.</div>';
        exit;
    }
    
    // Check if host can view this payment (if host user)
    if ($_SESSION['user_role'] === 'host' && $payment['host_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied to this payment.</div>';
        exit;
    }
    
    ?>
    <div class="row">
        <div class="col-md-6">
            <div class="payment-detail-item">
                <div class="payment-detail-label">Payment ID</div>
                <div class="payment-detail-value">#<?php echo $payment['payment_id']; ?></div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Reservation ID</div>
                <div class="payment-detail-value">#<?php echo $payment['reservation_id']; ?></div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Amount</div>
                <div class="payment-detail-value"><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Payment Method</div>
                <div class="payment-detail-value">
                    <span class="badge bg-info">
                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Status</div>
                <div class="payment-detail-value">
                    <span class="badge payment-status status-<?php echo $payment['payment_status']; ?>">
                        <?php echo ucfirst($payment['payment_status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Payment Date</div>
                <div class="payment-detail-value"><?php echo date('F j, Y g:i A', strtotime($payment['payment_date'])); ?></div>
            </div>
            
            <?php if ($payment['transaction_reference']): ?>
            <div class="payment-detail-item">
                <div class="payment-detail-label">Transaction Reference</div>
                <div class="payment-detail-value"><?php echo htmlspecialchars($payment['transaction_reference']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <div class="payment-detail-item">
                <div class="payment-detail-label">Renter Information</div>
                <div class="payment-detail-value">
                    <strong><?php echo htmlspecialchars($payment['renter_name']); ?></strong><br>
                    <?php echo htmlspecialchars($payment['renter_email']); ?><br>
                    <?php echo htmlspecialchars($payment['renter_phone']); ?>
                </div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Property Information</div>
                <div class="payment-detail-value">
                    <strong><?php echo htmlspecialchars($payment['property_name']); ?></strong><br>
                    <?php echo ucfirst($payment['property_type']); ?><br>
                    <?php echo htmlspecialchars($payment['property_address']); ?>
                </div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Booking Period</div>
                <div class="payment-detail-value">
                    <?php echo date('M j, Y', strtotime($payment['check_in'])); ?> 
                    to 
                    <?php echo date('M j, Y', strtotime($payment['check_out'])); ?>
                </div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Total Booking Amount</div>
                <div class="payment-detail-value">₱<?php echo number_format($payment['total_amount'], 2); ?></div>
            </div>
            
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <div class="payment-detail-item">
                <div class="payment-detail-label">Host Information</div>
                <div class="payment-detail-value">
                    <strong><?php echo htmlspecialchars($payment['host_name']); ?></strong><br>
                    <?php echo htmlspecialchars($payment['host_email']); ?><br>
                    <?php echo htmlspecialchars($payment['host_phone']); ?>
                </div>
            </div>
            
            <div class="payment-detail-item">
                <div class="payment-detail-label">Branch</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment['branch_name']); ?><br>
                    <?php echo htmlspecialchars($payment['branch_address']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($payment['refund_reason']): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="payment-detail-item">
                <div class="payment-detail-label">Refund Reason</div>
                <div class="payment-detail-value text-danger">
                    <?php echo nl2br(htmlspecialchars($payment['refund_reason'])); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($payment['proof_filename']): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="payment-detail-item">
                <div class="payment-detail-label">Proof of Payment</div>
                <div class="payment-detail-value">
                    <?php
                    $proofPath = $payment['proof_filepath'] ?: 'uploads/payments/' . $payment['proof_filename'];
                    $fileExtension = strtolower(pathinfo($payment['proof_filename'], PATHINFO_EXTENSION));
                    
                    if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): 
                    ?>
                        <img src="<?php echo $proofPath; ?>" alt="Proof of Payment" class="proof-of-payment img-fluid" style="max-height: 300px;">
                        <div class="mt-2">
                            <a href="<?php echo $proofPath; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i> View Full Size
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-file me-2"></i>
                            <a href="<?php echo $proofPath; ?>" target="_blank" class="text-decoration-none">
                                Download Proof File: <?php echo htmlspecialchars($payment['proof_filename']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="border-top pt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Last updated: <?php echo $payment['updated_at'] ? date('M j, Y g:i A', strtotime($payment['updated_at'])) : 'Never'; ?>
                </small>
            </div>
        </div>
    </div>
    <?php
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading payment details. Please try again.</div>';
}
?>