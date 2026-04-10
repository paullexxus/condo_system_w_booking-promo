<?php
// Admin: Bookings + Promo Codes

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']);

$message = '';
$error = '';
if (isset($_SESSION['success_message'])) { $message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message'])) { $error = $_SESSION['error_message']; unset($_SESSION['error_message']); }

// Create promo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_promo'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = sanitize_input($_POST['type'] ?? 'percentage');
    $value = (float)($_POST['value'] ?? 0);
    $expires = trim($_POST['expires_at'] ?? '');
    $usage_limit = trim($_POST['usage_limit'] ?? '');

    try {
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) throw new Exception('Invalid promo code format.');
        if (!in_array($type, ['percentage','fixed'], true)) throw new Exception('Invalid promo type.');
        if ($value <= 0) throw new Exception('Promo value must be greater than 0.');

        $expires_at = null;
        if ($expires !== '') {
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $expires)) throw new Exception('Expiration must be YYYY-MM-DD.');
            $expires_at = $expires . ' 23:59:59';
        }

        $limit = null;
        if ($usage_limit !== '') {
            $limit = (int)$usage_limit;
            if ($limit < 1) throw new Exception('Usage limit must be empty or >= 1.');
        }

        $ok = execute_query(
            "INSERT INTO promos (code, type, value, expires_at, usage_limit, created_by) VALUES (?, ?, ?, ?, ?, ?)",
            [$code, $type, $value, $expires_at, $limit, (int)($_SESSION['user_id'] ?? 0)]
        );
        if (!$ok) throw new Exception('Failed to create promo (maybe duplicate code).');
        $_SESSION['success_message'] = 'Promo created.';
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header('Location: bookings_promos.php');
    exit;
}

// Delete promo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_promo'])) {
    $promo_id = (int)($_POST['promo_id'] ?? 0);
    execute_query("DELETE FROM promos WHERE promo_id = ?", [$promo_id]);
    $_SESSION['success_message'] = 'Promo deleted.';
    header('Location: bookings_promos.php');
    exit;
}

// Host approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_host'])) {
    $host_id = (int)($_POST['host_id'] ?? 0);
    execute_query("UPDATE users SET is_active = 1 WHERE user_id = ?", [$host_id]);
    $_SESSION['success_message'] = 'Host approved.';
    header('Location: bookings_promos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_host'])) {
    $host_id = (int)($_POST['host_id'] ?? 0);
    // Alternatively, you can drop the record or flag it as rejected
    execute_query("DELETE FROM users WHERE user_id = ?", [$host_id]);
    $_SESSION['success_message'] = 'Host rejected.';
    header('Location: bookings_promos.php');
    exit;
}

$pending_hosts = get_multiple_results("SELECT user_id, full_name, email FROM users WHERE role = 'host' AND is_active = 0 ORDER BY created_at DESC");


$promos = get_multiple_results("SELECT * FROM promos ORDER BY created_at DESC");

$bookings = [];
if (function_exists('column_exists') && column_exists('bookings', 'booking_id')) {
    $bookings = get_multiple_results(
        "SELECT bk.*, u.full_name as renter_name, un.unit_number, un.unit_name
         FROM bookings bk
         LEFT JOIN users u ON bk.user_id = u.user_id
         LEFT JOIN units un ON bk.unit_id = un.unit_id
         ORDER BY bk.created_at DESC
         LIMIT 200"
    );
}

// Summary metrics
$total_promos = is_array($promos) ? count($promos) : 0;
$total_bookings = is_array($bookings) ? count($bookings) : 0;
$total_booking_revenue = 0.0;
if (is_array($bookings) && !empty($bookings)) {
    foreach ($bookings as $b) {
        $total_booking_revenue += (float)($b['total_amount'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bookings & Promos - BookIT Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/sidebar-common.css" rel="stylesheet">
  <link href="../assets/css/admin/admin-common.css" rel="stylesheet">
  <link href="../assets/css/modals.css" rel="stylesheet">
  <link href="../assets/css/admin/manage_branch.css" rel="stylesheet">
  <link href="../assets/css/admin/bookings_promos.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>
<div class="d-flex">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content flex-grow-1">
    <div class="page-header">
      <div>
        <h1 class="page-title mb-1">
          <i class="fas fa-ticket-alt me-2"></i>Bookings & Promo Codes
        </h1>
        <p class="text-muted">Manage discounts and monitor usage</p>
      </div>
      <div class="page-actions">
        <button type="button" class="btn-refresh" onclick="location.reload()">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
      </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="card">
        <div class="card-icon"><i class="fas fa-receipt"></i></div>
        <div class="card-info">
          <h3><?= (int)$total_bookings ?></h3>
          <p>Total Bookings</p>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="card-info">
          <h3>₱<?= number_format($total_booking_revenue, 2) ?></h3>
          <p>Booking Revenue</p>
        </div>
      </div>
      <div class="card">
        <div class="card-icon"><i class="fas fa-tags"></i></div>
        <div class="card-info">
          <h3><?= (int)$total_promos ?></h3>
          <p>Promo Codes</p>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="saas-card h-100 mb-0">
          <div class="saas-card-header">
            <h2 class="saas-card-title">Create Promo Code</h2>
            <p class="saas-card-subtitle">Generate a new discount voucher</p>
          </div>
          <form method="POST" class="row g-3">
            <input type="hidden" name="create_promo" value="1">
            <div class="col-12">
              <label class="saas-label">Promo Code</label>
              <input class="saas-input" name="code" placeholder="e.g. SUMMER10" required>
            </div>
            <div class="col-12 d-flex gap-3">
              <div class="flex-grow-1">
                <label class="saas-label">Discount Type</label>
                <select class="saas-select" name="type" id="promoType" onchange="updatePromoPrefix()">
                  <option value="percentage">Percentage (%)</option>
                  <option value="fixed">Fixed Amount</option>
                </select>
              </div>
              <div class="flex-grow-1">
                <label class="saas-label">Value</label>
                <div class="input-group">
                  <span class="input-group-text saas-input-prefix" id="promoPrefix">%</span>
                  <input class="saas-input has-prefix" type="number" step="0.01" name="value" required>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <label class="saas-label">Expiration Date</label>
              <input class="saas-input" type="date" name="expires_at">
            </div>
            <div class="col-md-6">
              <label class="saas-label">Usage Limit</label>
              <input class="saas-input" type="number" name="usage_limit" min="1" placeholder="Unlimited">
            </div>
            <div class="col-12 mt-4">
              <button class="btn-saas-primary" type="submit"><i class="fas fa-magic me-2"></i> Create Promo</button>
            </div>
          </form>
          <script>
            function updatePromoPrefix() {
              const type = document.getElementById('promoType').value;
              document.getElementById('promoPrefix').innerText = type === 'percentage' ? '%' : '₱';
            }
          </script>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="saas-card h-100 mb-0">
          <div class="saas-card-header">
            <h2 class="saas-card-title">Host Approvals</h2>
            <p class="saas-card-subtitle">Review pending host accounts</p>
          </div>
          <div class="table-responsive border-0">
            <?php if (empty($pending_hosts)): ?>
              <div class="saas-empty-state py-4">
                <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                <h3 class="empty-title">All caught up!</h3>
                <p class="empty-desc">There are no pending host approvals at the moment.</p>
              </div>
            <?php else: ?>
              <table class="saas-table">
                <thead>
                  <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pending_hosts as $h): ?>
                    <tr>
                      <td class="fw-medium text-dark"><?= htmlspecialchars($h['email']) ?></td>
                      <td><?= htmlspecialchars($h['full_name']) ?></td>
                      <td class="text-end">
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="host_id" value="<?= (int)$h['user_id'] ?>">
                          <button name="approve_host" value="1" class="btn btn-sm btn-success rounded-pill px-3 me-1" type="submit">Approve</button>
                          <button name="reject_host" value="1" class="btn btn-sm btn-outline-secondary rounded-pill px-3" type="submit" onclick="return confirm('Reject this host?');">Reject</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-4">
      <div class="col-lg-12">
        <div class="saas-card">
          <div class="saas-card-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div class="text-start">
              <h2 class="saas-card-title m-0">Existing Promos</h2>
              <p class="saas-card-subtitle m-0 mt-1">Manage and monitor your active promo codes</p>
            </div>
            <div class="saas-toolbar mb-0 d-flex justify-content-end align-items-center flex-nowrap" style="gap: 12px; width: max-content;">
              <div class="toolbar-search" style="width: 320px; flex: none;">
                <i class="fas fa-search"></i>
                <input type="text" class="saas-input" id="promoSearch" placeholder="Search by code..." onkeyup="filterPromos()">
              </div>
              <select class="saas-select toolbar-filter" style="width: 160px; min-width: 160px; flex: none;" id="promoFilter" onchange="filterPromos()">
                <option value="all">All Types</option>
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed</option>
              </select>
            </div>
          </div>
          <div class="table-responsive border-0">
            <?php if (empty($promos)): ?>
              <div class="saas-empty-state py-5">
                <div class="empty-icon"><i class="fas fa-tags"></i></div>
                <h3 class="empty-title">No promo codes yet</h3>
                <p class="empty-desc">Create your first promo code above to start offering discounts.</p>
              </div>
            <?php else: ?>
              <table class="saas-table" id="promoTable">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Expires</th>
                    <th>Usage Limit</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($promos as $p): 
                  $is_expired = ($p['expires_at'] !== null && strtotime($p['expires_at']) < time());
                  $type_badge = $p['type'] === 'percentage' ? 'badge-blue' : 'badge-green';
                  $used = (int)$p['used_count'];
                  $limit = $p['usage_limit'] !== null ? (int)$p['usage_limit'] : INF;
                  $pct = $limit === INF ? 0 : min(100, ($used / $limit) * 100);
                  $progress_class = $pct >= 100 ? 'full' : '';
                ?>
                  <tr class="promo-row" data-code="<?= htmlspecialchars(strtolower($p['code'])) ?>" data-type="<?= htmlspecialchars($p['type']) ?>">
                    <td class="fw-bold text-dark">
                      <?= htmlspecialchars($p['code']) ?>
                      <?php if ($is_expired): ?> <span class="saas-badge badge-red ms-2">Expired</span> <?php endif; ?>
                    </td>
                    <td><span class="saas-badge <?= $type_badge ?>"><?= ucfirst(htmlspecialchars($p['type'])) ?></span></td>
                    <td class="fw-medium text-dark">
                      <?= $p['type'] === 'percentage' ? number_format((float)$p['value'], 0) . '%' : '₱' . number_format((float)$p['value'], 2) ?>
                    </td>
                    <td><?= $p['expires_at'] ? date('M j, Y', strtotime($p['expires_at'])) : '<span class="text-muted">No expiry</span>' ?></td>
                    <td>
                      <?php if ($limit === INF): ?>
                        <div class="usage-pill"><?= $used ?> used (Unlimited)</div>
                      <?php else: ?>
                        <div class="usage-pill mb-1"><?= $used ?> of <?= $limit ?> used</div>
                        <div class="usage-progress-wrapper">
                          <div class="usage-progress-fill <?= $progress_class ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <button class="btn-saas-danger" onclick="openDeleteModal(<?= (int)$p['promo_id'] ?>, '<?= htmlspecialchars($p['code'], ENT_QUOTES) ?>')">
                        <i class="fas fa-trash-alt me-1"></i> Delete
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-12">
        <div class="saas-card">
          <div class="saas-card-header">
            <h2 class="saas-card-title">Recent Bookings</h2>
            <p class="saas-card-subtitle">Overview of orders and applied prompts</p>
          </div>
          <div class="table-responsive border-0">
            <?php if (!function_exists('column_exists') || !column_exists('bookings', 'booking_id')): ?>
              <div class="alert alert-warning mb-0 m-3 rounded-3 border-0">Run migration <code>007_create_bookings_promos_pricing.sql</code> to create the <code>bookings</code> table.</div>
            <?php elseif (empty($bookings)): ?>
              <div class="saas-empty-state py-5">
                <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                <h3 class="empty-title">No bookings found</h3>
                <p class="empty-desc">When renters make bookings, they will appear here.</p>
              </div>
            <?php else: ?>
              <table class="saas-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Unit</th>
                    <th>User</th>
                    <th>Stay</th>
                    <th>Total</th>
                    <th>Promo</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($bookings as $b): 
                    // Add basic statuses mimicking SaaS pills
                    $st = strtolower($b['status']);
                    $stClass = 'badge-gray';
                    if ($st === 'confirmed' || $st === 'completed') $stClass = 'badge-green';
                    if ($st === 'pending') $stClass = 'badge-orange';
                    if ($st === 'cancelled') $stClass = 'badge-red';
                  ?>
                    <tr>
                      <td class="fw-medium text-dark">#<?= (int)$b['booking_id'] ?></td>
                      <td><?= htmlspecialchars($b['unit_name'] ?? $b['unit_number'] ?? ('Unit #' . $b['unit_id'])) ?></td>
                      <td><?= htmlspecialchars($b['renter_name'] ?? ('User #' . $b['user_id'])) ?></td>
                      <td><?= date('M j', strtotime($b['check_in_date'])) ?> &rarr; <?= date('M j', strtotime($b['check_out_date'])) ?></td>
                      <td class="fw-bold">₱<?= number_format((float)$b['total_amount'], 2) ?></td>
                      <td>
                        <?php if(!empty($b['promo_code'])): ?>
                          <span class="saas-badge badge-blue"><i class="fas fa-tag me-1"></i> <?= htmlspecialchars($b['promo_code']) ?></span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td><span class="saas-badge <?= $stClass ?>"><?= ucfirst($st) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div class="modal fade" id="deletePromoModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content saas-modal-content">
          <div class="modal-header saas-modal-header border-0">
            <h5 class="modal-title fw-bold text-dark"><i class="fas fa-exclamation-triangle text-danger me-2"></i> Delete Promo Code</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body saas-modal-body">
            Are you sure you want to delete promo code <strong id="deletePromoCodeDisplay" class="text-danger"></strong>? <br>
            <span class="text-muted small">This action cannot be undone and renters will no longer be able to use it.</span>
          </div>
          <div class="modal-footer saas-modal-footer">
            <form method="POST" id="deletePromoForm" class="w-100 d-flex gap-2">
              <input type="hidden" name="delete_promo" value="1">
              <input type="hidden" name="promo_id" id="deletePromoId" value="">
              <button type="button" class="btn btn-light rounded-3 px-4 py-2" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger rounded-3 px-4 py-2">Yes, delete promo</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      function openDeleteModal(id, code) {
        document.getElementById('deletePromoId').value = id;
        document.getElementById('deletePromoCodeDisplay').innerText = code;
        new bootstrap.Modal(document.getElementById('deletePromoModal')).show();
      }

      function filterPromos() {
        const searchInput = document.getElementById('promoSearch').value.toLowerCase();
        const typeFilter = document.getElementById('promoFilter').value;
        const rows = document.querySelectorAll('.promo-row');

        rows.forEach(row => {
          const code = row.getAttribute('data-code');
          const type = row.getAttribute('data-type');
          
          const matchesSearch = code.includes(searchInput);
          const matchesType = (typeFilter === 'all') || (type === typeFilter);

          if (matchesSearch && matchesType) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      }
    </script>
  </main>
</div>
</body>
</html>

