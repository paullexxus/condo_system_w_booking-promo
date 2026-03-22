<?php
// Admin: Pending Unit Approvals
// Approve or reject units uploaded by hosts

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']);

$message = '';
$error = '';

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_unit'])) {
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    if ($unit_id > 0) {
        if (execute_query("UPDATE units SET approval_status = 'approved', rejection_reason = NULL WHERE unit_id = ?", [$unit_id])) {
            $_SESSION['success_message'] = "Unit approved successfully. It is now visible to renters.";
        } else {
            $_SESSION['error_message'] = "Failed to approve unit.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid unit ID.";
    }
    header("Location: pending_units.php");
    exit;
}

// Handle reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_unit'])) {
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($unit_id <= 0) {
        $_SESSION['error_message'] = "Invalid unit ID.";
    } elseif (empty($rejection_reason)) {
        $_SESSION['error_message'] = "Rejection reason is required.";
    } else {
        if (execute_query("UPDATE units SET approval_status = 'rejected', rejection_reason = ? WHERE unit_id = ?", [sanitize_input($rejection_reason), $unit_id])) {
            $_SESSION['success_message'] = "Unit rejected. Host has been notified of the reason.";
        } else {
            $_SESSION['error_message'] = "Failed to reject unit.";
        }
    }
    header("Location: pending_units.php");
    exit;
}

// Fetch pending units (check if approval_status column exists)
$pending_units = [];
try {
    if (function_exists('column_exists') && column_exists('units', 'approval_status')) {
        $pending_units = get_multiple_results(
            "SELECT u.*, b.branch_name, b.city,
                    host.full_name as host_name, host.email as host_email
             FROM units u
             LEFT JOIN branches b ON u.branch_id = b.branch_id
             LEFT JOIN users host ON u.host_id = host.user_id
             WHERE u.approval_status = 'pending'
             ORDER BY u.created_at ASC"
        );
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Unit Approvals - BookIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <link href="../assets/css/admin/manage_branch.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content flex-grow-1">
        <div class="page-header">
            <div>
                <h1 class="page-title mb-1">
                    <i class="fas fa-clipboard-check me-2"></i>Pending Unit Approvals
                </h1>
                <p class="text-muted">Review and approve or reject units uploaded by hosts</p>
            </div>
            <div class="page-actions">
                <a href="unit_management.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Unit Management
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!function_exists('column_exists') || !column_exists('units', 'approval_status')): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> The approval workflow is not installed. Run migration <code>006_add_unit_approval_workflow.sql</code> first.
            </div>
        <?php elseif (empty($pending_units)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                    <h5>No Pending Units</h5>
                    <p class="text-muted">All units have been reviewed. New units uploaded by hosts will appear here.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Branch</th>
                            <th>Host</th>
                            <th>Price</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_units as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['unit_name'] ?? $u['unit_number'] ?? 'Unit #' . $u['unit_id']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($u['unit_type'] ?? 'N/A') ?> • <?= (int)($u['max_occupancy'] ?? 0) ?> persons</small>
                            </td>
                            <td><?= htmlspecialchars($u['branch_name'] ?? 'N/A') ?><br><small class="text-muted"><?= htmlspecialchars($u['city'] ?? '') ?></small></td>
                            <td><?= htmlspecialchars($u['host_name'] ?? 'N/A') ?><br><small class="text-muted"><?= htmlspecialchars($u['host_email'] ?? '') ?></small></td>
                            <td>₱<?= number_format($u['monthly_rate'] ?? $u['price'] ?? 0, 2) ?>/mo</td>
                            <td><?= date('M d, Y', strtotime($u['created_at'] ?? 'now')) ?></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Approve this unit? It will become visible to renters.');">
                                    <input type="hidden" name="unit_id" value="<?= (int)$u['unit_id'] ?>">
                                    <button type="submit" name="approve_unit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$u['unit_id'] ?>">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </td>
                        </tr>
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?= (int)$u['unit_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="unit_id" value="<?= (int)$u['unit_id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Unit</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Rejecting: <strong><?= htmlspecialchars($u['unit_name'] ?? $u['unit_number'] ?? 'Unit #' . $u['unit_id']) ?></strong></p>
                                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                                            <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Required: Explain why this unit was rejected."></textarea>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reject_unit" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Reject Unit
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
