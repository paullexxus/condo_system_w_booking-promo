<?php
// Reviews Management - Host Dashboard
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$message = '';
$success = false;

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize_input($_POST['action']);
    $review_id = (int)$_POST['review_id'];
    
    // Safety check: ensure review belongs to host's unit
    $check = get_single_result("SELECT r.review_id FROM reviews r JOIN units u ON r.unit_id = u.unit_id WHERE r.review_id = ? AND u.host_id = ?", [$review_id, $host_id]);
    
    if ($check) {
        if ($action === 'approve_review') {
            if (execute_query("UPDATE reviews SET is_approved = 1 WHERE review_id = ?", [$review_id])) {
                $message = "Review approved and is now visible publically.";
                $success = true;
            }
        } else if ($action === 'reject_review') {
            if (execute_query("DELETE FROM reviews WHERE review_id = ?", [$review_id])) {
                $message = "Review has been removed.";
                $success = true;
            }
        }
    }
}

// Data Fetching
$stats = get_single_result("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
        ROUND(AVG(CASE WHEN is_approved = 1 THEN rating ELSE NULL END), 1) as avg
    FROM reviews r JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = ?
", [$host_id]);

$reviews = get_multiple_results("
    SELECT r.*, u.unit_number, us.full_name as guest_name
    FROM reviews r
    JOIN units u ON r.unit_id = u.unit_id
    JOIN users us ON r.user_id = us.user_id
    WHERE u.host_id = ?
    ORDER BY r.created_at DESC
", [$host_id]);

$page_title = 'Guest Reviews & Ratings';
include '../templates/host_layout_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-star me-2 text-warning"></i>Reviews & Feedback</h1>
        <p class="text-muted">Manage property ratings and moderation from your guests</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show"><?php echo $message; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="stats-grid mb-4">
    <div class="stat-card border-top border-warning border-4">
        <div class="small fw-bold text-muted text-uppercase mb-1">Average Rating</div>
        <div class="d-flex align-items-center gap-2">
            <span class="h2 fw-bold mb-0"><?php echo $stats['avg'] ?? '0.0'; ?></span>
            <div class="text-warning">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="fas fa-star <?php echo $i <= round($stats['avg'] ?? 0) ? '' : 'text-muted opacity-25'; ?>"></i>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="small fw-bold text-muted text-uppercase mb-1">Total Received</div>
        <div class="h2 fw-bold mb-0"><?php echo $stats['total']; ?></div>
    </div>
    <div class="stat-card">
        <div class="small fw-bold text-muted text-uppercase mb-1">Pending Approval</div>
        <div class="h2 fw-bold mb-0 text-warning"><?php echo $stats['pending']; ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Feedback</h5>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary btn-sm active" onclick="filterReviews('all')">All</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="filterReviews('pending')">Pending</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Guest</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Unit</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                        <tr class="review-row" data-status="<?php echo $r['is_approved'] ? 'approved' : 'pending'; ?>">
                            <td><b><?php echo htmlspecialchars($r['guest_name']); ?></b><br><small class="text-muted"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></small></td>
                            <td>
                                <div class="text-warning small">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $r['rating'] ? '' : 'text-muted opacity-25'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td class="small" style="max-width: 300px;"><?php echo nl2br(htmlspecialchars($r['comment'])); ?></td>
                            <td><span class="badge bg-light text-dark border">#<?php echo $r['unit_number']; ?></span></td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <?php if (!$r['is_approved']): ?>
                                        <form method="POST"><input type="hidden" name="action" value="approve_review"><input type="hidden" name="review_id" value="<?php echo $r['review_id']; ?>"><button class="btn btn-success btn-sm px-3">Approve</button></form>
                                    <?php endif; ?>
                                    <form method="POST"><input type="hidden" name="action" value="reject_review"><input type="hidden" name="review_id" value="<?php echo $r['review_id']; ?>"><button class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this review?')"><i class="fas fa-trash"></i></button></form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($reviews)) echo '<tr><td colspan="5" class="text-center py-5 text-muted">No reviews found.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function filterReviews(status) {
        $('.btn-outline-secondary').removeClass('active');
        $(event.target).addClass('active');
        if (status === 'all') {
            $('.review-row').show();
        } else {
            $('.review-row').hide();
            $(`.review-row[data-status="${status}"]`).show();
        }
    }
</script>

<?php include '../templates/host_layout_footer.php'; ?>
