include_once __DIR__ . '/../includes/session.php';
include_once __DIR__ . '/../includes/auth.php';

checkRole(['host', 'manager', 'admin']);

$affected_units = [];
$cleanup_performed = false;
$error_message = '';

// 1. Identify affected units
$sql = "SELECT unit_id, unit_name, street_address, unit_number, city FROM units 
        WHERE street_address = '0' 
           OR unit_number = '0' 
           OR city = '0'";

$affected_units = get_multiple_results($sql);

// 2. Handle Repair Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_action'])) {
    $update_sql = "UPDATE units SET 
                   street_address = CASE WHEN street_address = '0' THEN NULL ELSE street_address END,
                   unit_number = CASE WHEN unit_number = '0' THEN NULL ELSE unit_number END,
                   city = CASE WHEN city = '0' THEN NULL ELSE city END
                   WHERE street_address = '0' OR unit_number = '0' OR city = '0'";
    
    if ($conn->query($update_sql)) {
        $cleanup_performed = true;
        // Refresh the list
        $affected_units = get_multiple_results($sql);
    } else {
        $error_message = $conn->error;
    }
}

$page_title = 'Data Restoration Utility';
include_once __DIR__ . '/../templates/host_layout_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1"><i class="fas fa-tools me-2 text-warning"></i>Data Integrity Portal</h4>
                            <p class="mb-0 opacity-75 small">Identify and repair numeric data corruption in property records.</p>
                        </div>
                        <?php if (count($affected_units) > 0): ?>
                        <form method="POST">
                            <button type="submit" name="repair_action" class="btn btn-warning rounded-pill px-4 fw-bold shadow">
                                <i class="fas fa-magic me-2"></i>Repair All Corrupted Units
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4 bg-light">
                    <?php if ($cleanup_performed): ?>
                        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4">
                            <i class="fas fa-check-circle me-2"></i> <strong>Success!</strong> Data normalization complete. Affected fields have been reset.
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i> <strong>Error:</strong> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-3 p-4 border">
                        <h6 class="text-uppercase text-muted fw-bold small mb-4 tracking-wider">Affected Property Records (<?php echo count($affected_units); ?>)</h6>
                        
                        <?php if (empty($affected_units)): ?>
                            <div class="text-center py-5">
                                <div class="display-1 text-muted opacity-25 mb-3"><i class="fas fa-shield-check"></i></div>
                                <h5 class="text-muted">Your database is clean.</h5>
                                <p class="text-secondary small">No corrupted text-fields detected in the unit records.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Unit ID</th>
                                            <th>Name</th>
                                            <th>Street Address</th>
                                            <th>Unit Number</th>
                                            <th>City</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($affected_units as $unit): ?>
                                        <tr>
                                            <td class="fw-bold">#<?php echo $unit['unit_id']; ?></td>
                                            <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                            <td><span class="badge <?php echo $unit['street_address'] === '0' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> p-2 px-3"><?php echo htmlspecialchars($unit['street_address']); ?></span></td>
                                            <td><span class="badge <?php echo $unit['unit_number'] === '0' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> p-2 px-3"><?php echo htmlspecialchars($unit['unit_number']); ?></span></td>
                                            <td><span class="badge <?php echo $unit['city'] === '0' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> p-2 px-3"><?php echo htmlspecialchars($unit['city']); ?></span></td>
                                            <td><i class="fas fa-exclamation-circle text-danger"></i> Corrupted</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../templates/host_layout_footer.php'; ?>
<?php exit; // End the script here to prevent the old echo logic from running ?>


