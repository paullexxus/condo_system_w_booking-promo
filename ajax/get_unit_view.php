<?php
// Get unit view details
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

if (!$unit_id) {
    echo '<div class="alert alert-danger">Invalid unit ID</div>';
    exit;
}

// Verify unit belongs to host
$unit = get_single_result("
    SELECT u.*, b.branch_name 
    FROM units u
    INNER JOIN branches b ON u.branch_id = b.branch_id
    WHERE u.unit_id = ? AND u.host_id = ?
", [$unit_id, $host_id]);

if (!$unit) {
    echo '<div class="alert alert-danger">Unit not found</div>';
    exit;
}

// Get unit images
$images = get_multiple_results("SELECT * FROM unit_images WHERE unit_id = ?", [$unit_id]);

// Get total bookings (reservations)
$bookings = get_single_result("SELECT COUNT(*) as total FROM reservations WHERE unit_id = ?", [$unit_id]);
?>

<div class="view-modal-content">
    <div style="margin-bottom: 20px;">
        <?php if ($images): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-bottom: 15px;">
            <?php foreach ($images as $img): ?>
            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Unit photo" style="width: 100%; height: 100px; object-fit: cover; border-radius: 6px;">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-6">
            <strong>Unit Name:</strong> <?php echo htmlspecialchars($unit['unit_name']); ?><br>
            <strong>Branch:</strong> <?php echo htmlspecialchars($unit['branch_name']); ?><br>
            <strong>Rate per Night:</strong> ₱<?php echo number_format($unit['monthly_rate'] ?? 0); ?><br>
            <strong>Capacity:</strong> <?php echo $unit['max_occupancy']; ?> guests<br>
        </div>
        <div class="col-6">
            <strong>City:</strong> <?php echo htmlspecialchars($unit['city'] ?? 'N/A'); ?><br>
            <strong>Unit Number:</strong> <?php echo htmlspecialchars($unit['unit_number'] ?? 'N/A'); ?><br>
            <strong>Status:</strong> <span class="badge <?php echo $unit['is_available'] ? 'bg-success' : 'bg-warning'; ?>">
                <?php echo $unit['is_available'] ? 'Available' : 'Maintenance'; ?>
            </span><br>
            <strong>Total Bookings:</strong> <?php echo $bookings['total'] ?? 0; ?><br>
        </div>
    </div>

    <?php if ($unit['description']): ?>
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
        <strong>Description:</strong><br>
        <?php echo nl2br(htmlspecialchars($unit['description'])); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($unit['latitude']) && !empty($unit['longitude'])): ?>
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
        <strong>Location Coordinates:</strong><br>
        Latitude: <?php echo htmlspecialchars((string)($unit['latitude'] ?? 'N/A')); ?><br>
        Longitude: <?php echo htmlspecialchars((string)($unit['longitude'] ?? 'N/A')); ?>
    </div>
    <?php endif; ?>
</div>
