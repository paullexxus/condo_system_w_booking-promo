<?php
require_once '../includes/session.php'; // Includes constants and starts session correctly
require_once '../config/db.php';

// Check if user is logged in and has appropriate role
checkRole(['admin', 'host']);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$branch_id = $_SESSION['branch_id'] ?? null;

// Global amenities catalog (id + name). Legacy UI expects extra columns — aliased below.
$amenities_query = "SELECT a.id AS amenity_id,
       a.name AS amenity_name,
       '' AS description,
       1 AS is_available,
       0 AS hourly_rate,
       NULL AS branch_id,
       'Global catalog' AS branch_name,
       NULL AS created_at
    FROM amenities a
    WHERE 1=1
    ORDER BY a.name ASC";

$stmt = $conn->prepare($amenities_query);
$stmt->execute();
$amenities_result = $stmt->get_result();

// Get branches for filter
$branches_query = "SELECT branch_id, branch_name FROM branches WHERE is_active = 1";
$branches_result = $conn->query($branches_query);

// Statistics (catalog has no branch / availability columns; booking counts optional)
$stats_query = "SELECT
    (SELECT COUNT(*) FROM amenities) AS total_amenities,
    (SELECT COUNT(*) FROM amenities) AS available_amenities,
    0 AS unavailable_amenities,
    (SELECT COUNT(*) FROM amenity_bookings WHERE status = 'confirmed') AS total_bookings,
    (SELECT COUNT(*) FROM amenity_bookings WHERE status = 'pending') AS pending_bookings";

$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_amenities' => 0,
    'available_amenities' => 0,
    'unavailable_amenities' => 0,
    'total_bookings' => 0,
    'pending_bookings' => 0,
];
if (!$stats) {
    $stats = [
        'total_amenities' => 0,
        'available_amenities' => 0,
        'unavailable_amenities' => 0,
        'total_bookings' => 0,
        'pending_bookings' => 0,
    ];
}

// Most used amenity (join catalog id to amenity_bookings.amenity_id)
$most_used_query = "SELECT a.name AS amenity_name,
       SUM(CASE WHEN ab.status = 'confirmed' THEN 1 ELSE 0 END) AS booking_count
    FROM amenities a
    LEFT JOIN amenity_bookings ab ON a.id = ab.amenity_id
    GROUP BY a.id, a.name
    ORDER BY booking_count DESC
    LIMIT 1";

$most_used_result = $conn->query($most_used_query);
$most_used = $most_used_result ? $most_used_result->fetch_assoc() : null;

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/BookIT');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amenity Management - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <link href="../assets/css/modules/amenity_management.css" rel="stylesheet">
    <link href="../assets/css/modals.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main content -->
        <main class="main-content flex-grow-1">
                <!-- Page Header -->
                <div class="page-header mb-4">
                    <div>
                        <h1 class="page-title mb-1">
                            <i class="fas fa-swimming-pool me-2"></i>Amenity Management
                        </h1>
                        <p class="text-muted">
                            <?php echo $user_role == 'admin' ? 'Manage available amenities across all branches' : 'Manage and monitor amenities available in your branch'; ?>
                        </p>
                    </div>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addAmenityModal">
                        <i class="fas fa-plus me-2"></i>Add New Amenity
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid mb-4">
                    <div class="stat-card total-amenities">
                        <div class="stat-icon">
                            <i class="fas fa-swimming-pool"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Amenities</div>
                            <div class="stat-value"><?php echo $stats['total_amenities']; ?></div>
                        </div>
                    </div>
                    <div class="stat-card available-amenities">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Available</div>
                            <div class="stat-value"><?php echo $stats['available_amenities']; ?></div>
                        </div>
                    </div>
                    <div class="stat-card unavailable-amenities">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Unavailable</div>
                            <div class="stat-value"><?php echo $stats['unavailable_amenities']; ?></div>
                        </div>
                    </div>
                    <div class="stat-card total-bookings">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Bookings</div>
                            <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Top Controls - Search & Filter Bar -->
                <div class="filter-card mb-4">
                    <div class="filter-content">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search amenity name...">
                        </div>
                        <?php if ($user_role == 'admin'): ?>
                        <select class="form-control filter-select" id="branchFilter">
                            <option value="">All Branches</option>
                            <?php 
                            $branches_result->data_seek(0);
                            while ($branch = $branches_result->fetch_assoc()): ?>
                                <option value="<?php echo $branch['branch_id']; ?>">
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php endif; ?>
                        <select class="form-control filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Under Maintenance</option>
                        </select>
                        <select class="form-control filter-select" id="sortSelect">
                            <option value="name">Sort by Name</option>
                            <option value="date">Recently Added</option>
                            <option value="bookings">Most Booked</option>
                            <option value="fee">Sort by Fee</option>
                        </select>
                        <button class="btn btn-outline-secondary" id="resetFilters">
                            <i class="fas fa-refresh"></i>Reset
                        </button>
                    </div>
                </div>

                <!-- Analytics Summary -->
                <div class="analytics-card mb-4">
                    <div class="analytics-header">
                        <h6 class="analytics-title">
                            <i class="fas fa-chart-bar me-2"></i>Analytics Summary
                        </h6>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-item">
                            <span class="analytics-label">Most Used Amenity</span>
                            <span class="analytics-value">
                                <?php echo $most_used ? htmlspecialchars($most_used['amenity_name']) . ' (' . $most_used['booking_count'] . ' bookings)' : 'No data'; ?>
                            </span>
                        </div>
                        <div class="analytics-item">
                            <span class="analytics-label">Pending Bookings</span>
                            <span class="analytics-value"><?php echo $stats['pending_bookings']; ?></span>
                        </div>
                        <div class="analytics-item">
                            <span class="analytics-label">Availability Rate</span>
                            <span class="analytics-value">
                                <?php echo $stats['total_amenities'] > 0 ? round(($stats['available_amenities'] / $stats['total_amenities']) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                        <div class="analytics-item">
                            <span class="analytics-label">Last Updated</span>
                            <span class="analytics-value"><?php echo date('M j, Y g:i A'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Amenities Table -->
                <div class="amenities-table-card">
                    <div class="table-header">
                        <h6 class="table-title">
                            <i class="fas fa-list me-2"></i>Amenities List
                        </h6>
                    </div>
                    <div class="table-wrapper">
                        <table class="table amenities-table" id="amenitiesTable">
                            <thead>
                                <tr>
                                    <th>Amenity Name</th>
                                    <th>Description</th>
                                    <?php if ($user_role == 'admin'): ?><th>Branch</th><?php endif; ?>
                                    <th>Availability</th>
                                    <th>Fee</th>
                                    <th>Bookings</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($amenity = $amenities_result->fetch_assoc()): ?>
                                <tr class="amenity-row" 
                                    data-amenity-id="<?php echo (int) $amenity['amenity_id']; ?>"
                                    data-branch-id="<?php echo (int) ($amenity['branch_id'] ?? 0); ?>"
                                    data-status="<?php echo !empty($amenity['is_available']) ? 'available' : 'unavailable'; ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower($amenity['amenity_name'])); ?>"
                                    data-date="<?php echo !empty($amenity['created_at']) ? (int) strtotime($amenity['created_at']) : 0; ?>"
                                    data-fee="<?php echo htmlspecialchars((string) ($amenity['hourly_rate'] ?? 0)); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($amenity['amenity_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="amenity-desc">
                                            <?php echo htmlspecialchars(substr($amenity['description'], 0, 50)) . (strlen($amenity['description']) > 50 ? '...' : ''); ?>
                                        </span>
                                    </td>
                                    <?php if ($user_role == 'admin'): ?>
                                    <td>
                                        <span class="branch-badge">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo htmlspecialchars($amenity['branch_name']); ?>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="status-badge badge bg-<?php echo $amenity['is_available'] ? 'success' : 'warning'; ?>">
                                            <i class="fas fa-<?php echo $amenity['is_available'] ? 'check-circle' : 'clock'; ?> me-1"></i>
                                            <?php echo $amenity['is_available'] ? 'Available' : 'Maintenance'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fee-amount">₱<?php echo number_format($amenity['hourly_rate'], 2); ?>/hr</span>
                                    </td>
                                    <td>
                                        <span class="booking-count">0</span>
                                    </td>
                                    <td>
                                        <span class="date-added">
                                            <?php echo !empty($amenity['created_at']) ? date('M j, Y', strtotime($amenity['created_at'])) : '—'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action edit-amenity" 
                                                    data-amenity-id="<?php echo $amenity['amenity_id']; ?>"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action toggle-availability" 
                                                    data-amenity-id="<?php echo $amenity['amenity_id']; ?>"
                                                    data-action="<?php echo $amenity['is_available'] ? 'disable' : 'enable'; ?>"
                                                    title="<?php echo $amenity['is_available'] ? 'Disable' : 'Enable'; ?>">
                                                <i class="fas fa-<?php echo $amenity['is_available'] ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                            <button class="btn-action delete-amenity" 
                                                    data-amenity-id="<?php echo $amenity['amenity_id']; ?>"
                                                    data-amenity-name="<?php echo htmlspecialchars($amenity['amenity_name']); ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Empty State -->
                    <div class="empty-state" id="emptyState" style="display: none;">
                        <i class="fas fa-swimming-pool"></i>
                        <h5>No amenities found</h5>
                        <p>Try adjusting your search or filters</p>
                        <button class="btn btn-primary mt-3" id="resetEmptyState">Reset Filters</button>
                    </div>
            </main>
        </div>
    </div>

    <!-- Add Amenity Modal -->
    <?php include '../includes/api/amenity/amenity_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="../assets/js/modules/amenity_management.js"></script>
</body>
</html>
<?php $conn->close(); ?>