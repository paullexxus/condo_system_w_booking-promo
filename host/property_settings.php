<?php
// Host Property Settings
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['host', 'manager']);

$host_id = (int)($_SESSION['user_id'] ?? 0);
$unit_id = (int)($_GET['unit_id'] ?? 0);
$active_tab = $_GET['tab'] ?? 'pricing';

if ($unit_id <= 0) {
    header('Location: unit_management.php');
    exit;
}

$unit = get_single_result("SELECT u.*, b.branch_name FROM units u LEFT JOIN branches b ON u.branch_id = b.branch_id WHERE u.unit_id = ? AND u.host_id = ?", [$unit_id, $host_id]);
if (!$unit) {
    header('Location: unit_management.php');
    exit;
}

$message = '';
$error = '';

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');
    try {
        if ($action === 'save_base_rate') {
            $rate = (float)$_POST['base_nightly_rate'];
            $col = ($unit['pricing_type'] === 'monthly') ? 'price_per_month' : 'price_per_night';
            execute_query("UPDATE units SET $col = ? WHERE unit_id = ?", [$rate, $unit_id]);
            $message = 'Base rate updated.';
        } else if ($action === 'add_blackout') {
            execute_query("INSERT INTO unit_blackouts (unit_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)", [$unit_id, $_POST['start_date'], $_POST['end_date'], $_POST['reason']]);
            $message = 'Dates blocked.';
        } else if ($action === 'remove_blackout') {
            execute_query("DELETE FROM unit_blackouts WHERE blackout_id = ? AND unit_id = ?", [(int)$_POST['blackout_id'], $unit_id]);
            $message = 'Block removed.';
        } else if ($action === 'add_addon') {
            execute_query("INSERT INTO unit_addons (unit_id, name, price, is_active) VALUES (?, ?, ?, 1)", [$unit_id, $_POST['name'], $_POST['price']]);
            $message = 'Add-on added.';
        } else if ($action === 'delete_addon') {
            execute_query("DELETE FROM unit_addons WHERE addon_id = ? AND unit_id = ?", [(int)$_POST['addon_id'], $unit_id]);
            $message = 'Add-on removed.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$is_monthly = ($unit['pricing_type'] === 'monthly');
$base_rate = $is_monthly ? (float)$unit['price_per_month'] : (float)$unit['price_per_night'];
$blackouts = get_multiple_results("SELECT * FROM unit_blackouts WHERE unit_id = ? ORDER BY start_date DESC", [$unit_id]);
$rules = get_multiple_results("SELECT * FROM unit_pricing_rules WHERE unit_id = ? ORDER BY created_at DESC", [$unit_id]);
$addons = get_multiple_results("SELECT * FROM unit_addons WHERE unit_id = ? ORDER BY created_at DESC", [$unit_id]);

$page_title = 'Property Configuration';

ob_start();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .settings-card { border: 0; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 24px; }
    .nav-tabs-custom { border-bottom: 2px solid #eee; margin-bottom: 30px; }
    .nav-tabs-custom .nav-link { border: 0; color: #64748b; font-weight: 500; border-bottom: 3px solid transparent; padding: 12px 20px; transition: var(--transition); }
    .nav-tabs-custom .nav-link:hover { color: var(--primary-color); }
    .nav-tabs-custom .nav-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); background: transparent; }
    .calendar-wrapper { background: #fff; padding: 15px; border-radius: 12px; border: 1px solid #eee; }
    .flatpickr-day.blackout-day { background: #fee2e2 !important; color: #dc2626 !important; border-radius: 4px; }
</style>
<?php
$extra_css = ob_get_clean();

include '../templates/host_layout_header.php';
?>

<div class="page-header mb-4">
    <div>
        <h1 class="page-title"><i class="fas fa-cog me-2"></i>Unit Settings</h1>
        <p class="text-muted"><?php echo htmlspecialchars($unit['unit_name'] ?? $unit['unit_number']); ?> Management</p>
    </div>
    <div class="page-actions">
        <a href="unit_management.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<ul class="nav nav-tabs nav-tabs-custom">
    <li class="nav-item"><a class="nav-link <?php echo $active_tab==='pricing'?'active':'' ?>" href="?unit_id=<?php echo $unit_id ?>&tab=pricing">Pricing & Availability</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $active_tab==='amenities'?'active':'' ?>" href="?unit_id=<?php echo $unit_id ?>&tab=amenities">Featured Amenities</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $active_tab==='photos'?'active':'' ?>" href="?unit_id=<?php echo $unit_id ?>&tab=photos">Gallery</a></li>
</ul>

<?php if ($active_tab === 'pricing'): ?>
<div class="row g-4">
    <div class="col-lg-6">
        <!-- Base Rate -->
        <div class="card settings-card">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Base Revenue Rate</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="save_base_rate">
                    <div class="input-group">
                        <span class="input-group-text bg-white">₱</span>
                        <input type="number" step="0.01" name="base_nightly_rate" class="form-control" value="<?php echo $base_rate; ?>" required>
                        <button class="btn btn-primary">Save Rate</button>
                    </div>
                    <small class="text-muted d-block mt-2">Standard <?php echo $is_monthly ? 'monthly' : 'nightly'; ?> rate before seasonal adjustments.</small>
                </form>
            </div>
        </div>

        <!-- Blackouts -->
        <div class="card settings-card">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Blackout Calendar</h6>
                <div class="calendar-wrapper mb-3">
                    <div id="settingsCalendar"></div>
                </div>
                <form method="POST" class="row g-2">
                    <input type="hidden" name="action" value="add_blackout">
                    <div class="col-6"><input type="date" name="start_date" class="form-control" required></div>
                    <div class="col-6"><input type="date" name="end_date" class="form-control" required></div>
                    <div class="col-12 mt-2"><input type="text" name="reason" class="form-control" placeholder="Reason (e.g. Maintenance)"></div>
                    <div class="col-12 mt-2"><button class="btn btn-outline-danger w-100">Block Dates</button></div>
                </form>
                
                <div class="mt-4">
                    <p class="small fw-bold text-muted text-uppercase">Active Blocks</p>
                    <?php if(empty($blackouts)) echo '<p class="text-muted small">No blocked dates.</p>'; ?>
                    <?php foreach ($blackouts as $b): ?>
                        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded mb-1 small">
                            <span><?php echo date('M d', strtotime($b['start_date'])); ?> - <?php echo date('M d, Y', strtotime($b['end_date'])); ?></span>
                            <form method="POST"><input type="hidden" name="action" value="remove_blackout"><input type="hidden" name="blackout_id" value="<?php echo $b['blackout_id']; ?>"><button class="btn btn-link text-danger p-0"><i class="fas fa-times"></i></button></form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <!-- Addons -->
        <div class="card settings-card">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Upsells & Add-ons</h6>
                <form method="POST" class="row g-2 mb-4">
                    <input type="hidden" name="action" value="add_addon">
                    <div class="col-7"><input type="text" name="name" class="form-control" placeholder="Extra Guest, Pool Access..." required></div>
                    <div class="col-3 text-center"><input type="number" step="0.01" name="price" class="form-control" placeholder="₱" required></div>
                    <div class="col-2"><button class="btn btn-success w-100"><i class="fas fa-plus"></i></button></div>
                </form>
                
                <?php foreach ($addons as $a): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars($a['name']); ?></div>
                            <div class="text-muted smaller">₱<?php echo number_format($a['price'], 2); ?></div>
                        </div>
                        <form method="POST"><input type="hidden" name="action" value="delete_addon"><input type="hidden" name="addon_id" value="<?php echo $a['addon_id']; ?>"><button class="btn btn-outline-danger btn-sm p-1 px-2 border-0"><i class="fas fa-trash-alt"></i></button></form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="text-center py-5"><h5 class="text-muted">Module Under Construction</h5><p>This tab is coming soon in the next update.</p></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const blackouts = <?php echo json_encode($blackouts); ?>;
    const ranges = blackouts.map(b => ({from: b.start_date, to: b.end_date}));
    
    flatpickr('#settingsCalendar', {
        inline: true,
        dateFormat: 'Y-m-d',
        disable: ranges,
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            const dateStr = dayElem.dateObj.toISOString().split('T')[0];
            if(blackouts.some(b => dateStr >= b.start_date && dateStr <= b.end_date)) {
                dayElem.classList.add('blackout-day');
            }
        }
    });
</script>

<?php include '../templates/host_layout_footer.php'; ?>
