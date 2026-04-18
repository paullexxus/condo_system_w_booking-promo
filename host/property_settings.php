<?php
// Host → My Properties → Property Management (Pricing & Availability)

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
if (isset($_SESSION['success_message'])) { $message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message'])) { $error = $_SESSION['error_message']; unset($_SESSION['error_message']); }

// Handle POST actions (Pricing & Availability)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    try {
        if ($action === 'save_base_rate') {
            $rate = (float)($_POST['base_nightly_rate'] ?? 0);
            if ($rate <= 0) throw new Exception('Base rate must be greater than 0.');
            $is_monthly = ($unit['pricing_type'] ?? 'nightly') === 'monthly';
            if ($is_monthly) {
                execute_query("UPDATE units SET price_per_month = ? WHERE unit_id = ?", [$rate, $unit_id]);
            } else {
                execute_query("UPDATE units SET price_per_night = ? WHERE unit_id = ?", [$rate, $unit_id]);
            }
            $_SESSION['success_message'] = 'Base rate saved successfully.';
        } elseif ($action === 'add_blackout') {
            $start = sanitize_input($_POST['start_date'] ?? '');
            $end = sanitize_input($_POST['end_date'] ?? '');
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $start) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $end)) {
                throw new Exception('Invalid blackout date format.');
            }
            if (strtotime($end) < strtotime($start)) throw new Exception('End date must be after start date.');
            execute_query("INSERT INTO unit_blackouts (unit_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)", [$unit_id, $start, $end, sanitize_input($_POST['reason'] ?? '')]);
            $_SESSION['success_message'] = 'Blackout dates added.';
        } elseif ($action === 'remove_blackout') {
            $blackout_id = (int)($_POST['blackout_id'] ?? 0);
            execute_query("DELETE FROM unit_blackouts WHERE blackout_id = ? AND unit_id = ?", [$blackout_id, $unit_id]);
            $_SESSION['success_message'] = 'Blackout removed.';
        } elseif ($action === 'save_weekend_rule') {
            $adj_type = sanitize_input($_POST['adjustment_type'] ?? 'fixed');
            $adj_val = (float)($_POST['adjustment_value'] ?? 0);
            if (!in_array($adj_type, ['fixed','percentage'], true)) throw new Exception('Invalid adjustment type.');
            execute_query(
                "INSERT INTO unit_pricing_rules (unit_id, rule_type, adjustment_type, adjustment_value, is_active)
                 VALUES (?, 'weekend', ?, ?, 1)",
                [$unit_id, $adj_type, $adj_val]
            );
            $_SESSION['success_message'] = 'Weekend pricing rule saved.';
        } elseif ($action === 'add_date_rule') {
            $start = sanitize_input($_POST['start_date'] ?? '');
            $end = sanitize_input($_POST['end_date'] ?? '');
            $adj_type = sanitize_input($_POST['adjustment_type'] ?? 'fixed');
            $adj_val = (float)($_POST['adjustment_value'] ?? 0);
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $start) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $end)) {
                throw new Exception('Invalid rule date format.');
            }
            if (strtotime($end) < strtotime($start)) throw new Exception('End date must be after start date.');
            if (!in_array($adj_type, ['fixed','percentage'], true)) throw new Exception('Invalid adjustment type.');
            execute_query(
                "INSERT INTO unit_pricing_rules (unit_id, rule_type, adjustment_type, adjustment_value, start_date, end_date, is_active)
                 VALUES (?, 'date_range', ?, ?, ?, ?, 1)",
                [$unit_id, $adj_type, $adj_val, $start, $end]
            );
            $_SESSION['success_message'] = 'Date-range pricing rule added.';
        } elseif ($action === 'delete_rule') {
            $rule_id = (int)($_POST['rule_id'] ?? 0);
            execute_query("DELETE FROM unit_pricing_rules WHERE rule_id = ? AND unit_id = ?", [$rule_id, $unit_id]);
            $_SESSION['success_message'] = 'Pricing rule deleted.';
        } elseif ($action === 'add_addon') {
            $name = sanitize_input($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            if ($name === '' || $price < 0) throw new Exception('Invalid add-on.');
            execute_query("INSERT INTO unit_addons (unit_id, name, price, is_active) VALUES (?, ?, ?, 1)", [$unit_id, $name, $price]);
            $_SESSION['success_message'] = 'Add-on added.';
        } elseif ($action === 'delete_addon') {
            $addon_id = (int)($_POST['addon_id'] ?? 0);
            execute_query("DELETE FROM unit_addons WHERE addon_id = ? AND unit_id = ?", [$addon_id, $unit_id]);
            $_SESSION['success_message'] = 'Add-on deleted.';
        }
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header('Location: property_settings.php?unit_id=' . $unit_id . '&tab=' . urlencode($active_tab));
    exit;
}

$is_monthly = ($unit['pricing_type'] ?? 'nightly') === 'monthly';
$base_rate = $is_monthly ? (float)$unit['price_per_month'] : (float)$unit['price_per_night'];
$base_rate = max(0, $base_rate);
$blackouts = get_multiple_results("SELECT * FROM unit_blackouts WHERE unit_id = ? ORDER BY start_date DESC", [$unit_id]);
$rules = get_multiple_results("SELECT * FROM unit_pricing_rules WHERE unit_id = ? ORDER BY created_at DESC", [$unit_id]);
$addons = get_multiple_results("SELECT * FROM unit_addons WHERE unit_id = ? ORDER BY created_at DESC", [$unit_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Property Settings - BookIT Host</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/sidebar-common.css" rel="stylesheet">
  <link href="../assets/css/admin/admin-common.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    .mini-calendar { border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px; background: #fff; }
    .flatpickr-day.blackout-day { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
    .flatpickr-day.has-pricing-rule { background: #dbeafe; border-color: #bfdbfe; color: #1e3a8a; }
    .calendar-note { font-size: .85rem; margin-top: .5rem; color: #6b7280; min-height: 20px; }
  </style>
</head>
<body>
<div class="d-flex">
  <?php include '../includes/sidebar.php'; ?>
  <main class="content flex-grow-1 p-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <h2 class="mb-1"><i class="fas fa-sliders-h me-2"></i>Property Management</h2>
        <div class="text-muted">
          <?= htmlspecialchars($unit['unit_name'] ?? $unit['unit_number'] ?? ('Unit #' . $unit_id)) ?> • <?= htmlspecialchars($unit['branch_name'] ?? '') ?>
        </div>
      </div>
      <a class="btn btn-outline-secondary" href="unit_management.php"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link <?= $active_tab==='overview'?'active':'' ?>" href="?unit_id=<?= $unit_id ?>&tab=overview">Overview</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='pricing'?'active':'' ?>" href="?unit_id=<?= $unit_id ?>&tab=pricing">Pricing & Availability</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='photos'?'active':'' ?>" href="?unit_id=<?= $unit_id ?>&tab=photos">Photos</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='amenities'?'active':'' ?>" href="?unit_id=<?= $unit_id ?>&tab=amenities">Amenities</a></li>
      <li class="nav-item"><a class="nav-link <?= $active_tab==='bookings'?'active':'' ?>" href="?unit_id=<?= $unit_id ?>&tab=bookings">Bookings</a></li>
    </ul>

    <?php if ($active_tab !== 'pricing'): ?>
      <div class="card"><div class="card-body text-muted">This tab is a placeholder. The Pricing & Availability tab is implemented.</div></div>
    <?php else: ?>
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header fw-bold">Base rate (<?= $is_monthly ? 'Monthly' : 'Nightly' ?>)</div>
            <div class="card-body">
              <form method="POST">
                <input type="hidden" name="action" value="save_base_rate">
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input class="form-control" type="number" step="0.01" name="base_nightly_rate" value="<?= htmlspecialchars((string)$base_rate) ?>" required>
                  <button class="btn btn-primary" type="submit">Save</button>
                </div>
              </form>
              <div class="small text-muted mt-2">This rate is used in renter dynamic pricing calculations.</div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header fw-bold">Calendar — block dates</div>
            <div class="card-body">
              <div class="mini-calendar mb-3">
                <div id="hostBlackoutCalendar"></div>
                <div id="hostBlackoutNote" class="calendar-note">Select a date to see blackout reason.</div>
              </div>
              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="add_blackout">
                <div class="col-md-6">
                  <label class="form-label">From</label>
                  <input class="form-control" type="date" name="start_date" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">To</label>
                  <input class="form-control" type="date" name="end_date" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Reason (optional)</label>
                  <input class="form-control" name="reason" placeholder="Maintenance, personal use, etc.">
                </div>
                <div class="col-12">
                  <button class="btn btn-outline-danger" type="submit"><i class="fas fa-ban me-1"></i> Block dates</button>
                </div>
              </form>

              <hr>
              <div class="fw-bold mb-2">Active blackouts</div>
              <?php if (empty($blackouts)): ?>
                <div class="text-muted">No blocked dates.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead><tr><th>Range</th><th>Reason</th><th></th></tr></thead>
                    <tbody>
                      <?php foreach ($blackouts as $b): ?>
                        <tr>
                          <td><?= htmlspecialchars($b['start_date']) ?> → <?= htmlspecialchars($b['end_date']) ?></td>
                          <td class="text-muted"><?= htmlspecialchars($b['reason'] ?? '') ?></td>
                          <td class="text-end">
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="action" value="remove_blackout">
                              <input type="hidden" name="blackout_id" value="<?= (int)$b['blackout_id'] ?>">
                              <button class="btn btn-sm btn-outline-secondary" type="submit">Remove</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card">
            <div class="card-header fw-bold">Weekend pricing rules</div>
            <div class="card-body">
              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="save_weekend_rule">
                <div class="col-md-6">
                  <label class="form-label">Adjustment type</label>
                  <select class="form-select" name="adjustment_type">
                    <option value="fixed">Fixed (₱)</option>
                    <option value="percentage">Percentage (%)</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Value</label>
                  <input class="form-control" type="number" step="0.01" name="adjustment_value" value="0">
                </div>
                <div class="col-12">
                  <button class="btn btn-outline-primary" type="submit">Save weekend rule</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header fw-bold">Holiday / date-range pricing rules</div>
            <div class="card-body">
              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="add_date_rule">
                <div class="col-md-6">
                  <label class="form-label">Start date</label>
                  <input class="form-control" type="date" name="start_date" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">End date</label>
                  <input class="form-control" type="date" name="end_date" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Type</label>
                  <select class="form-select" name="adjustment_type">
                    <option value="fixed">Fixed (₱)</option>
                    <option value="percentage">Percentage (%)</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Value</label>
                  <input class="form-control" type="number" step="0.01" name="adjustment_value" value="0">
                </div>
                <div class="col-12">
                  <button class="btn btn-outline-primary" type="submit">Add rule</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header fw-bold">Pricing rules</div>
            <div class="card-body">
              <?php if (empty($rules)): ?>
                <div class="text-muted">No rules yet.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead><tr><th>Type</th><th>Details</th><th></th></tr></thead>
                    <tbody>
                      <?php foreach ($rules as $r): ?>
                        <tr>
                          <td><?= htmlspecialchars($r['rule_type']) ?></td>
                          <td class="text-muted">
                            <?= htmlspecialchars($r['adjustment_type']) ?> <?= htmlspecialchars($r['adjustment_value']) ?>
                            <?php if ($r['rule_type'] === 'date_range'): ?>
                              • <?= htmlspecialchars($r['start_date']) ?> → <?= htmlspecialchars($r['end_date']) ?>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="action" value="delete_rule">
                              <input type="hidden" name="rule_id" value="<?= (int)$r['rule_id'] ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header fw-bold">Add-ons for renters</div>
            <div class="card-body">
              <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="add_addon">
                <div class="col-md-8">
                  <label class="form-label">Name</label>
                  <input class="form-control" name="name" placeholder="Early check-in" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Price (₱)</label>
                  <input class="form-control" type="number" step="0.01" name="price" value="0">
                </div>
                <div class="col-12">
                  <button class="btn btn-outline-success" type="submit">Add add-on</button>
                </div>
              </form>

              <hr>
              <?php if (empty($addons)): ?>
                <div class="text-muted">No add-ons yet.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead><tr><th>Name</th><th>Price</th><th></th></tr></thead>
                    <tbody>
                      <?php foreach ($addons as $a): ?>
                        <tr>
                          <td><?= htmlspecialchars($a['name']) ?></td>
                          <td class="text-muted">₱<?= number_format((float)$a['price'], 2) ?></td>
                          <td class="text-end">
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="action" value="delete_addon">
                              <input type="hidden" name="addon_id" value="<?= (int)$a['addon_id'] ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                          </td>
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
    <?php endif; ?>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  (function () {
    const blackouts = <?= json_encode($blackouts ?: [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const pricingRules = <?= json_encode($rules ?: [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const blackoutNote = document.getElementById('hostBlackoutNote');
    const blackoutRanges = blackouts.map(function (b) {
      return { from: b.start_date, to: b.end_date };
    });

    function findBlackout(dateObj) {
      const stamp = dateObj.getTime();
      return blackouts.find(function (b) {
        const s = new Date(b.start_date + 'T00:00:00').getTime();
        const e = new Date(b.end_date + 'T23:59:59').getTime();
        return stamp >= s && stamp <= e;
      }) || null;
    }

    function hasDatePricingRule(dateObj) {
      const stamp = dateObj.getTime();
      return pricingRules.some(function (r) {
        if (r.rule_type !== 'date_range' || !r.start_date || !r.end_date) return false;
        const s = new Date(r.start_date + 'T00:00:00').getTime();
        const e = new Date(r.end_date + 'T23:59:59').getTime();
        return stamp >= s && stamp <= e;
      });
    }

    flatpickr('#hostBlackoutCalendar', {
      inline: true,
      dateFormat: 'Y-m-d',
      disable: blackoutRanges,
      onDayCreate: function (_, __, ___, dayElem) {
        const d = dayElem.dateObj;
        const b = findBlackout(d);
        if (b) {
          dayElem.classList.add('blackout-day');
          dayElem.title = 'Blocked: ' + (b.reason || 'No reason provided');
        } else if (hasDatePricingRule(d)) {
          dayElem.classList.add('has-pricing-rule');
          dayElem.title = 'Date-range pricing rule is active';
        }
      },
      onChange: function (selectedDates) {
        if (!selectedDates || !selectedDates.length) return;
        const b = findBlackout(selectedDates[0]);
        if (b) {
          blackoutNote.textContent = 'Blocked: ' + (b.reason || 'No reason provided');
        } else {
          blackoutNote.textContent = 'No blackout on selected date.';
        }
      }
    });
  })();
</script>
</body>
</html>

