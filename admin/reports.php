<?php
// Admin Printable Reports
// Include your shared database connection file
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/session.php';
include_once __DIR__ . '/../includes/functions.php';
checkRole(['admin']);

// -----------------------------
// Variables
// -----------------------------
$useDb = isset($conn) && $conn instanceof mysqli;
$mysqli = $useDb ? $conn : null;
$generatedDate = date("F d, Y h:i A");
$superadminName = $_SESSION['fullname'] ?? "System Superadmin";
// Print mode flag (used for body class and to skip sidebar)
$isPrint = isset($_GET['print']) && $_GET['print'] === '1';

// -----------------------------
// Load Data (with fallbacks)
// -----------------------------
if ($useDb) {
    try {
        // Users: fetch from users table with proper columns
        $users = fetch_all($mysqli, "SELECT 
            user_id as id, 
            full_name as name, 
            email, 
            role, 
            is_active,
            (SELECT branch_name FROM branches WHERE branch_id = users.branch_id) AS branch
            FROM users ORDER BY full_name");

        // Branches: include computed units_count
        $branches = fetch_all($mysqli, "SELECT 
            branch_id as id, 
            branch_name as name, 
            is_active,
            city as location,
            (SELECT COUNT(*) FROM units WHERE branch_id = branches.branch_id) AS units_count
            FROM branches ORDER BY branch_name");

        // Units - using the actual structure from your functions.php
        // Since there's no price column in units table, we'll use 0 as default
        $units = fetch_all($mysqli, "SELECT 
            unit_id as id, 
            unit_number as title, 
            unit_type as type,
            unit_number as property, 
            (SELECT branch_name FROM branches WHERE branch_id = units.branch_id) AS location,
            0 as price, -- Default price since no price column exists
            is_available,
            created_at
            FROM units ORDER BY unit_id DESC");

        // Reservations - kasama na ang created_at timestamp
        $reservations = fetch_all($mysqli, "SELECT 
            r.reservation_id as id,
            u.full_name as customer,
            un.unit_number,
            b.branch_name as location,
            r.check_in_date,
            r.check_out_date,
            r.total_amount as price,
            r.status,
            r.created_at
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            JOIN units un ON r.unit_id = un.unit_id
            JOIN branches b ON r.branch_id = b.branch_id
            ORDER BY r.created_at DESC");

        // System Revenue
        $monthly_revenue = fetch_all($mysqli, "
            SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
            FROM payments
            WHERE payment_status = 'paid' AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");

    } catch (Exception $e) {
        // If any database error occurs, use fallback data
        error_log("Database error in reports: " . $e->getMessage());
        $useDb = false;
    }
}

// If database failed, use fallback data
if (!$useDb) {
    // Fallback data
    $users = [
        ["id"=>"U001","name"=>"John Doe","email"=>"john@example.com","role"=>"Admin","branch"=>"Main","is_active"=>"1"],
    ];
    $branches = [
        ["id"=>"B001","name"=>"Central Branch","is_active"=>"1","location"=>"Makati","units_count"=>5],
    ];
    $units = [
        ["id"=>"U001","title"=>"Unit 101","type"=>"Studio","property"=>"Unit 101","location"=>"Central Branch","price"=>"₱5,000","is_available"=>"1","created_at"=>"2025-11-01"],
    ];
    $reservations = [
        ["id"=>"R001","customer"=>"John Doe","unit_number"=>"Unit 101","location"=>"Central Branch","check_in_date"=>"2025-12-01","check_out_date"=>"2025-12-05","price"=>"₱20,000","status"=>"confirmed","created_at"=>"2025-11-15 14:30:25"],
    ];
    $monthly_revenue = [
        ["month"=>"2025-01", "total"=>15000],
        ["month"=>"2025-02", "total"=>25000],
    ];
}
?>
<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>Branch Management</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/admin/reports.css">
        <!-- DataTables -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    </head>
    <body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

<main class="content">
        <div id="printableArea">
            <!-- Report Header -->
            <div class="report-header">
                <div class="report-title">
                    <h1><i class="fas fa-chart-line"></i> Admin Reports</h1>
                    <div class="report-meta">
                        Generated: <?= esc($generatedDate) ?> | 
                        Admin: <?= esc($superadminName) ?>
                    </div>
                </div>
                <div class="report-actions no-print">
                    <button class="btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="btn-print" onclick="downloadPDF()">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-number"><?= count($users) ?></div>
                    <div class="summary-label">Total Users</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?= count($branches) ?></div>
                    <div class="summary-label">Total Branches</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?= count($units) ?></div>
                    <div class="summary-label">Total Units</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?= count($reservations) ?></div>
                    <div class="summary-label">Total Reservations</div>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-box no-print">
                <input type="search" id="globalSearch" placeholder="Search across all reports..." class="form-control">
            </div>

            <!-- Revenue Chart (Admin) -->
            <div class="section-module">
                <h2><i class="fas fa-chart-line"></i> Total Platform Revenue (Last 12 Months)</h2>
                <div style="height: 350px;">
                    <canvas id="adminRevenueChart"></canvas>
                </div>
            </div>

            <!-- Users Section -->
            <div class="section-module">
                <h2><i class="fas fa-users"></i> Users Management</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $r): ?>
                            <tr>
                                <td>#U<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= esc($r['name']) ?></td>
                                <td><?= esc($r['email']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $r['role'] === 'admin' ? 'active' : ($r['role'] === 'manager' ? 'pending' : 'confirmed') ?>">
                                        <?= esc($r['role']) ?>
                                    </span>
                                </td>
                                <td><?= esc($r['branch'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $r['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $r['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Branches Section -->
            <div class="section-module">
                <h2><i class="fas fa-code-branch"></i> Branches Management</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Units Count</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($branches as $r): ?>
                            <tr>
                                <td>#B<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= esc($r['name']) ?></td>
                                <td><?= esc($r['location']) ?></td>
                                <td><?= esc($r['units_count']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $r['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $r['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Units Section -->
            <div class="section-module">
                <h2><i class="fas fa-home"></i> Units Management</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Unit Number</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($units as $r): ?>
                            <tr>
                                <td>#UN<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= esc($r['title']) ?></td>
                                <td><?= esc($r['type']) ?></td>
                                <td><?= esc($r['location']) ?></td>
                                <td>₱<?= number_format($r['price'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="badge badge-<?= $r['is_available'] ? 'active' : 'inactive' ?>">
                                        <?= $r['is_available'] ? 'Available' : 'Occupied' ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reservations Section -->
            <div class="section-module">
                <h2><i class="fas fa-calendar-check"></i> Reservations Management</h2>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Unit</th>
                                <th>Location</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Reserved At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reservations as $r): ?>
                            <tr>
                                <td>#R<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= esc($r['customer']) ?></td>
                                <td><?= esc($r['unit_number']) ?></td>
                                <td><?= esc($r['location']) ?></td>
                                <td><?= date('M d, Y', strtotime($r['check_in_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($r['check_out_date'])) ?></td>
                                <td>₱<?= number_format($r['price'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="badge badge-<?= $r['status'] === 'confirmed' ? 'confirmed' : 'pending' ?>">
                                        <?= ucfirst($r['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                    <div class="reservation-time"><?= date('h:i A', strtotime($r['created_at'])) ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Report Footer -->
            <div class="report-footer">
                "The Admin has full system-wide access and control over all users, branches, units, and reservations for monitoring and decision-making."
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin/reports.js"></script>
    <script>
        // Chart Initialization
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('adminRevenueChart');
            if(ctx) {
                var monthlyData = <?php echo json_encode($monthly_revenue); ?>;
                var labels = monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleString('default', { month: 'short', year: 'numeric' });
                });
                var dataPoints = monthlyData.map(item => item.total);
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'System Revenue (₱)',
                            data: dataPoints,
                            borderColor: '#e67e22',
                            backgroundColor: 'rgba(230, 126, 34, 0.2)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) { return '₱' + value.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
            }
        });

        // Auto-print if in print mode
        <?php if ($isPrint): ?>
        window.onload = function() {
            window.print();
            // Redirect back after print
            setTimeout(function() {
                window.location.href = 'reports.php';
            }, 500);
        }
        <?php endif; ?>

        function downloadPDF() {
            const element = document.getElementById('printableArea');
            html2pdf().set({
                margin: 0.5,
                filename: 'Admin_Report_<?php echo date("Y_m_d_H_i"); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            }).from(element).save();
        }
    </script>
</body>
</html>