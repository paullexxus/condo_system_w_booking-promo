<?php
// Host Amenities Management
// View renter amenity bookings and manage requests

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle amenity requests via form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['booking_id'])) {
        $booking_id = (int)$_POST['booking_id'];
        $action = sanitize_input($_POST['action']);
        
        // Verify the amenity booking belongs to this host's unit
        $booking = get_single_result(
            "SELECT ab.* FROM amenity_bookings ab 
             INNER JOIN units u ON ab.branch_id = u.branch_id 
             WHERE ab.booking_id = ? AND u.host_id = ?",
            [$booking_id, $host_id]
        );
        
        if ($booking) {
            if ($action === 'approve') {
                execute_query("UPDATE amenity_bookings SET status = 'approved' WHERE booking_id = ?", [$booking_id]);
                $action_message = "Amenity request approved!";
                $action_success = true;
            } else if ($action === 'reject') {
                execute_query("UPDATE amenity_bookings SET status = 'rejected' WHERE booking_id = ?", [$booking_id]);
                $action_message = "Amenity request rejected!";
                $action_success = true;
            } else if ($action === 'complete') {
                execute_query("UPDATE amenity_bookings SET status = 'completed' WHERE booking_id = ?", [$booking_id]);
                $action_message = "Amenity booking marked as completed!";
                $action_success = true;
            }
        }
    }
}

// Get Data
$amenity_bookings = get_multiple_results("
    SELECT ab.*, a.name AS amenity_name, u.unit_name, u.unit_number, us.full_name as renter_name, us.email as renter_email
    FROM amenity_bookings ab
    INNER JOIN amenities a ON ab.amenity_id = a.id
    INNER JOIN units u ON ab.branch_id = u.branch_id
    INNER JOIN users us ON ab.user_id = us.user_id
    WHERE u.host_id = ?
    ORDER BY ab.booking_date DESC
", [$host_id]);

$status_counts = [];
$statuses = ['pending', 'approved', 'completed', 'rejected'];
foreach ($statuses as $s) {
    $row = get_single_result("SELECT COUNT(*) as cnt FROM amenity_bookings ab INNER JOIN units u ON ab.branch_id = u.branch_id WHERE u.host_id = ? AND ab.status = ?", [$host_id, $s]);
    $status_counts[$s] = (int)($row['cnt'] ?? 0);
}

$page_title = 'Amenities & Requests';

ob_start();
?>
<style>
    .amenity-card { transition: var(--transition); border-radius: 12px; border: 1px solid #eee; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .amenity-card:hover { transform: translateY(-3px); box-shadow: var(--card-shadow); }
    .status-pill { font-size: 0.7rem; font-weight: bold; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; }
    .st-pending { background: #fff3cd; color: #856404; }
    .st-approved { background: #cfe2ff; color: #084298; }
    .st-completed { background: #d4edda; color: #155724; }
    .st-rejected { background: #f8d7da; color: #721c24; }
</style>
<?php
$extra_css = ob_get_clean();

include '../templates/host_layout_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-star me-2"></i>Amenities Management</h1>
        <p class="text-muted">Manage guest requests for facility use</p>
    </div>
</div>

<?php if ($action_message): ?>
    <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo $action_message; ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Status Pills -->
<div class="d-flex gap-2 mb-4 overflow-auto pb-2">
    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 active" onclick="filterAmenity('all', this)">All (<?php echo array_sum($status_counts); ?>)</button>
    <?php foreach ($status_counts as $st => $count): ?>
        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="filterAmenity('<?php echo $st; ?>', this)">
            <?php echo ucfirst($st); ?> (<?php echo $count; ?>)
        </button>
    <?php endforeach; ?>
</div>

<!-- Requests Grid -->
<div class="row g-4" id="amenity-container">
    <?php foreach ($amenity_bookings as $booking): ?>
    <div class="col-xl-4 col-md-6 amenity-item" data-status="<?php echo $booking['status']; ?>">
        <div class="amenity-card h-100 d-flex flex-column">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                <span class="small fw-bold text-muted"><i class="fas fa-hashtag"></i> <?php echo $booking['booking_id']; ?></span>
                <span class="status-pill st-<?php echo $booking['status']; ?>"><?php echo $booking['status']; ?></span>
            </div>
            
            <div class="p-4 flex-grow-1">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 p-2 rounded me-3 text-primary"><i class="fas fa-swimmer fa-lg"></i></div>
                    <div>
                        <h5 class="mb-0"><?php echo htmlspecialchars($booking['amenity_name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($booking['unit_name']); ?> (#<?php echo $booking['unit_number']; ?>)</small>
                    </div>
                </div>

                <div class="bg-light p-3 rounded mb-3 small">
                    <div class="d-flex justify-content-between mb-2"><span>Guest:</span><span class="fw-bold"><?php echo htmlspecialchars($booking['renter_name']); ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Scheduled:</span><span><?php echo date('M d, g:i A', strtotime($booking['start_time'])); ?></span></div>
                    <div class="d-flex justify-content-between"><span>End Time:</span><span><?php echo date('M d, g:i A', strtotime($booking['end_time'])); ?></span></div>
                </div>

                <div class="d-flex justify-content-between fw-bold text-primary">
                    <span>Total Charge:</span>
                    <span>₱<?php echo number_format($booking['total_amount'] ?? 0, 2); ?></span>
                </div>
            </div>

            <div class="p-4 border-top mt-auto">
                <div class="d-flex gap-2">
                    <?php if ($booking['status'] === 'pending'): ?>
                        <form method="POST" class="flex-grow-1"><input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-success btn-sm w-100">Approve</button></form>
                        <form method="POST" class="flex-grow-1"><input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-outline-danger btn-sm w-100">Reject</button></form>
                    <?php elseif ($booking['status'] === 'approved'): ?>
                        <form method="POST" class="w-100"><input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-primary btn-sm w-100">Mark Completed</button></form>
                    <?php else: ?>
                        <button class="btn btn-light btn-sm w-100" disabled><?php echo ucfirst($booking['status']); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; if (empty($amenity_bookings)) echo '<div class="col-12 text-center py-5"><p class="text-muted">No requests found</p></div>'; ?>
</div>

<script>
    function filterAmenity(status, btn) {
        $('.btn-outline-secondary').removeClass('active');
        $(btn).addClass('active');
        if (status === 'all') {
            $('.amenity-item').fadeIn();
        } else {
            $('.amenity-item').hide();
            $(`.amenity-item[data-status="${status}"]`).fadeIn();
        }
    }
</script>

<?php include '../templates/host_layout_footer.php'; ?>
