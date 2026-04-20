<?php
/**
 * BookIT Paid Amenities Dispatch & Approval Dashboard
 * 
 * Features:
 * 1. Global visibility of pending add-on requests.
 * 2. Administrative Control: Approve, Reject, or Modify Pricing.
 * 3. Authoritative Sync: Updates the booking price in real-time upon approval.
 */

include_once '../includes/session.php';
include_once '../includes/functions.php';
include_once '../config/db.php';

checkRole(['admin', 'manager']);

$admin_id = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$error = '';

// POST Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');
    $id = (int)($_POST['booking_addon_id'] ?? 0);
    
    try {
        if ($action === 'approve') {
            $new_price = (float)($_POST['approved_price'] ?? 0);
            
            // 1. Update status and approved price
            execute_query(
                "UPDATE booking_addons SET status = 'approved', approved_price = ?, admin_id = ?, updated_at = NOW() WHERE id = ?",
                [$new_price, $admin_id, $id]
            );
            
            // 2. Fetch reservation context for notification
            $ctx = get_single_result("SELECT ba.booking_id, r.user_id, ua.name FROM booking_addons ba JOIN reservations r ON ba.booking_id = r.reservation_id JOIN unit_addons ua ON ba.addon_id = ua.addon_id WHERE ba.id = ?", [$id]);
            
            if ($ctx) {
                sendNotification($ctx['user_id'], 'Amenity Approved: ' . $ctx['name'], "Your request for {$ctx['name']} has been approved. Your booking summary has been updated.", 'booking', 'system');
                // Optional: Audit Log
                // logAudit($admin_id, 'approve_amenity', "Approved amenity {$ctx['name']} for booking #{$ctx['booking_id']}");
            }
            
            $message = 'Amenity request approved successfully.';
            
        } elseif ($action === 'reject') {
            $note = sanitize_input($_POST['admin_note'] ?? '');
            
            execute_query(
                "UPDATE booking_addons SET status = 'rejected', admin_note = ?, admin_id = ?, updated_at = NOW() WHERE id = ?",
                [$note, $admin_id, $id]
            );
            
            $ctx = get_single_result("SELECT ba.booking_id, r.user_id, ua.name FROM booking_addons ba JOIN reservations r ON ba.booking_id = r.reservation_id JOIN unit_addons ua ON ba.addon_id = ua.addon_id WHERE ba.id = ?", [$id]);
            if ($ctx) {
                sendNotification($ctx['user_id'], 'Amenity Declined: ' . $ctx['name'], "Your request for {$ctx['name']} was declined: $note", 'booking', 'system');
            }
            
            $message = 'Amenity request rejected.';
        }
    } catch (Exception $e) {
        $error = "Critical Error: " . $e->getMessage();
    }
}

// Fetch Pending Requests
$sql = "SELECT ba.*, r.check_in_date, r.check_out_date, u.unit_number, u.unit_type, renter.full_name as renter_name, ua.name as addon_name, ua.price as catalog_price
        FROM booking_addons ba
        JOIN reservations r ON ba.booking_id = r.reservation_id
        JOIN units u ON r.unit_id = u.unit_id
        JOIN users renter ON r.user_id = renter.user_id
        JOIN unit_addons ua ON ba.addon_id = ua.addon_id
        WHERE ba.status = 'pending'
        ORDER BY ba.updated_at ASC";
$requests = get_multiple_results($sql);

$page_title = 'Amenity Requests';
include '../templates/admin_layout_header.php';
?>

<style>
    .request-card { border: 0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: all 0.3s ease; }
    .request-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .status-badge-pending { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .price-tag { font-family: 'JetBrains Mono', 'Courier New', monospace; font-weight: 700; color: #059669; }
</style>

<div class="page-header mb-4">
    <div>
        <h1 class="page-title text-dark">Amenity Approval Pipeline</h1>
        <p class="text-muted">Review and authorize premium service requests for unit bookings.</p>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success border-0 shadow-sm mb-4"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm mb-4"><?php echo $error; ?></div><?php endif; ?>

<div class="row g-4">
    <?php if (empty($requests)): ?>
        <div class="col-12 text-center py-5">
            <div class="mb-3 text-muted" style="font-size: 3rem;"><i class="fas fa-check-double"></i></div>
            <h5 class="text-muted">Queue is Clear</h5>
            <p>No pending amenity requests requiring approval.</p>
        </div>
    <?php else: ?>
        <?php foreach ($requests as $r): ?>
        <div class="col-xl-4 col-md-6">
            <div class="card request-card h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <span class="status-badge-pending">Pending</span>
                    <span class="text-muted small">ID #<?php echo $r['id']; ?></span>
                </div>
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($r['addon_name']); ?></h5>
                    <p class="text-muted small mb-3">Requested for Unit <?php echo $r['unit_number']; ?> (<?php echo $r['unit_type']; ?>)</p>
                    
                    <div class="bg-light p-3 rounded-lg mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Renter</span>
                            <span class="small fw-bold"><?php echo htmlspecialchars($r['renter_name']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Dates</span>
                            <span class="small"><?php echo date('M d', strtotime($r['check_in_date'])); ?> - <?php echo date('M d', strtotime($r['check_out_date'])); ?></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="text-muted small">Requested Price</span>
                        <span class="price-tag text-lg">₱<?php echo number_format($r['price'], 2); ?></span>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <button class="btn btn-outline-danger w-100 br-8" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $r['id']; ?>">Decline</button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-primary w-100 br-8" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $r['id']; ?>">Approve</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Modal -->
        <div class="modal fade" id="approveModal<?php echo $r['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content border-0 shadow-lg">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="booking_addon_id" value="<?php echo $r['id']; ?>">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Approve Amenity</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-4">
                        <p class="text-muted small mb-4">Confirming this request will activate the service for the guest. You may adjust the finalized price below.</p>
                        <div class="form-group mb-0">
                            <label class="form-label small fw-bold">Finalized Price (₱)</label>
                            <input type="number" step="0.01" name="approved_price" class="form-control form-control-lg" value="<?php echo $r['price']; ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Confirm Approval</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="rejectModal<?php echo $r['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content border-0 shadow-lg">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="booking_addon_id" value="<?php echo $r['id']; ?>">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Decline Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="form-group mb-0">
                            <label class="form-label small fw-bold">Reason for Rejection</label>
                            <textarea name="admin_note" class="form-control" rows="3" placeholder="e.g. Service not available on selected dates" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">Decline Request</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../templates/admin_layout_footer.php'; ?>
