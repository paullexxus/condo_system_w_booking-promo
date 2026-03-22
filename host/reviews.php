<?php
/**
 * Reviews Management - Host Dashboard
 * View and manage guest reviews for your listings
 */

include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';

// Check role
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
$action_message = '';
$action_success = false;

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        // Approve Review
        if ($action === 'approve_review') {
            $review_id = sanitize_input($_POST['review_id']);
            
            // Verify review belongs to host's unit
            $review = get_single_result(
                "SELECT r.* FROM reviews r
                 INNER JOIN units u ON r.unit_id = u.unit_id
                 WHERE r.review_id = ? AND u.host_id = ?",
                [$review_id, $host_id]
            );
            
            if ($review) {
                $stmt = $conn->prepare("UPDATE reviews SET is_approved = 1 WHERE review_id = ?");
                $stmt->bind_param("i", $review_id);
                
                if ($stmt->execute()) {
                    $action_message = "Review approved successfully!";
                    $action_success = true;
                } else {
                    $action_message = "Failed to approve review: " . $stmt->error;
                    $action_success = false;
                }
                $stmt->close();
            } else {
                $action_message = "Review not found!";
                $action_success = false;
            }
        }
        
        // Reject Review
        else if ($action === 'reject_review') {
            $review_id = sanitize_input($_POST['review_id']);
            
            // Verify review belongs to host's unit
            $review = get_single_result(
                "SELECT r.* FROM reviews r
                 INNER JOIN units u ON r.unit_id = u.unit_id
                 WHERE r.review_id = ? AND u.host_id = ?",
                [$review_id, $host_id]
            );
            
            if ($review) {
                $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
                $stmt->bind_param("i", $review_id);
                
                if ($stmt->execute()) {
                    $action_message = "Review rejected and removed!";
                    $action_success = true;
                } else {
                    $action_message = "Failed to reject review: " . $stmt->error;
                    $action_success = false;
                }
                $stmt->close();
            } else {
                $action_message = "Review not found!";
                $action_success = false;
            }
        }
    }
}

// Fetch all reviews for host's units
$all_reviews = get_multiple_results("
    SELECT r.*, u.unit_name, u.unit_number, usr.full_name as guest_name
    FROM reviews r
    INNER JOIN units u ON r.unit_id = u.unit_id
    INNER JOIN users usr ON r.user_id = usr.user_id
    WHERE u.host_id = ?
    ORDER BY r.created_at DESC
", [$host_id]);

// Calculate review statistics
$stats = get_single_result("
    SELECT 
        COUNT(*) as total_reviews,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_reviews,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending_reviews,
        ROUND(AVG(CASE WHEN is_approved = 1 THEN rating ELSE NULL END), 1) as average_rating
    FROM reviews r
    INNER JOIN units u ON r.unit_id = u.unit_id
    WHERE u.host_id = ?
", [$host_id]);

// Separate reviews by status
$pending_reviews = array_filter($all_reviews ?? [], function($r) { return $r['is_approved'] == 0; });
$approved_reviews = array_filter($all_reviews ?? [], function($r) { return $r['is_approved'] == 1; });

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews Management - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/sidebar-common.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
        }
        
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        .content {
            flex: 1;
            padding: 30px;
            max-width: 100%;
            width: 100%;
            margin-left: 280px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .stat-card .stat-icon {
            font-size: 32px;
            color: #f39c12;
            margin-bottom: 10px;
        }
        
        .section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .section-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .review-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #fafafa;
        }
        
        .review-card.pending {
            border-left: 4px solid #f39c12;
            background: #fffbf0;
        }
        
        .review-card.approved {
            border-left: 4px solid #27ae60;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        
        .review-guest-info {
            flex: 1;
        }
        
        .review-guest-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
        }
        
        .review-unit-info {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }
        
        .review-rating {
            font-size: 18px;
            color: #f39c12;
        }
        
        .review-rating .star {
            margin-right: 2px;
        }
        
        .review-comment {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin: 12px 0;
            font-size: 14px;
            line-height: 1.5;
            color: #444;
        }
        
        .review-meta {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }
        
        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        
        .btn {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
            display: block;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1><i class="fas fa-star"></i> Reviews Management</h1>
            </div>
            
            <!-- Success/Error Alert -->
            <?php if ($action_message): ?>
            <div class="alert alert-<?php echo $action_success ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $action_success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-comments"></i></div>
                    <div class="stat-label">Total Reviews</div>
                    <div class="stat-value"><?php echo $stats['total_reviews'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">Approved</div>
                    <div class="stat-value"><?php echo $stats['approved_reviews'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $stats['pending_reviews'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-label">Average Rating</div>
                    <div class="stat-value"><?php echo $stats['average_rating'] ?? 'N/A'; ?></div>
                </div>
            </div>
            
            <!-- Pending Reviews Section -->
            <?php if (!empty($pending_reviews)): ?>
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-hourglass-half"></i> Pending Reviews (<?php echo count($pending_reviews); ?>)</h2>
                </div>
                
                <?php foreach ($pending_reviews as $review): ?>
                <div class="review-card pending">
                    <div class="review-header">
                        <div class="review-guest-info">
                            <div class="review-guest-name"><?php echo htmlspecialchars($review['guest_name']); ?></div>
                            <div class="review-unit-info">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($review['unit_name'] ?? $review['unit_number']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="review-rating">
                                <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                <span class="star"><i class="fas fa-star"></i></span>
                                <?php endfor; ?>
                            </div>
                            <span class="badge badge-pending">Pending</span>
                        </div>
                    </div>
                    
                    <?php if ($review['comment']): ?>
                    <div class="review-comment">
                        <?php echo htmlspecialchars($review['comment']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="review-meta">
                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                    </div>
                    
                    <div class="review-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="approve_review">
                            <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reject_review">
                            <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this review?');">
                                <i class="fas fa-trash"></i> Reject
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Approved Reviews Section -->
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-check-circle"></i> Approved Reviews (<?php echo count($approved_reviews); ?>)</h2>
                </div>
                
                <?php if (!empty($approved_reviews)): ?>
                    <?php foreach ($approved_reviews as $review): ?>
                    <div class="review-card approved">
                        <div class="review-header">
                            <div class="review-guest-info">
                                <div class="review-guest-name"><?php echo htmlspecialchars($review['guest_name']); ?></div>
                                <div class="review-unit-info">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($review['unit_name'] ?? $review['unit_number']); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="review-rating">
                                    <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                    <span class="star"><i class="fas fa-star"></i></span>
                                    <?php endfor; ?>
                                </div>
                                <span class="badge badge-approved">Approved</span>
                            </div>
                        </div>
                        
                        <?php if ($review['comment']): ?>
                        <div class="review-comment">
                            <?php echo htmlspecialchars($review['comment']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="review-meta">
                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                        </div>
                        
                        <div class="review-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="reject_review">
                                <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove this review?');">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Approved Reviews Yet</h3>
                        <p>Approved reviews from guests will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
