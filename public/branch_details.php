<?php
include '../includes/public_session.php';
include '../includes/functions.php';
include '../includes/auth.php';

$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$branch = $branch_id ? getBranchById($branch_id) : false;
if (!$branch) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $branch ? esc($branch['branch_name']) : 'Branch Not Found'; ?> - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public/index.css">
    <link rel="stylesheet" href="../assets/css/components/cta-light.css">
</head>
<body>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $branch ? esc($branch['branch_name']) : 'Branch Not Found'; ?> - BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .shadow-soft {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card-modern {
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: white;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-luxury-primary {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 10px 20px rgba(243, 156, 18, 0.2);
        }

        .btn-luxury-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(243, 156, 18, 0.35);
            color: white;
        }

        .badge-luxury {
            display: inline-block;
            background: linear-gradient(135deg, #3498db 0%, #5dade2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="bg-gray-50">
<!-- Premium Navigation -->
<nav class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-soft">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <a href="index.php" class="flex items-center gap-3 text-2xl font-bold text-gray-900 hover:opacity-80 smooth-transition">
                <img src="../assets/images/logo/bookit.png" alt="BookIT Logo" class="w-14 h-14 rounded-xl object-cover">
                BookIT
            </a>

            <div class="hidden md:flex items-center gap-10">
                <a href="index.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Home</a>
                <a href="browse_units.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">Browse</a>
            </div>

            <div class="flex items-center gap-4">
                <?php if (isLoggedIn()): ?>
                    <a href="../renter/my_bookings.php" class="text-gray-600 hover:text-gray-900 smooth-transition font-500">
                        <i class="fas fa-calendar-check"></i> My Bookings
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-modern btn-luxury-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<div class="max-w-6xl mx-auto px-4 py-12">
    <?php if (!$branch): ?>
        <div class="card-modern p-12 text-center">
            <i class="fas fa-exclamation-circle text-6xl text-gray-300 mb-6"></i>
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Branch Not Found</h2>
            <p class="text-gray-600 mb-8 text-lg">The branch you are looking for does not exist.</p>
            <a href="index.php" class="btn-modern btn-luxury-primary">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    <?php else: ?>
        <!-- Header Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
            <div class="md:col-span-2">
                <h1 class="text-5xl font-bold text-gray-900 mb-4"><?php echo esc($branch['branch_name']); ?></h1>
                <div class="space-y-2 text-gray-600 text-lg">
                    <p><i class="fas fa-map-marker-alt text-orange-500 mr-3"></i><?php echo esc($branch['address'] . ', ' . $branch['city']); ?></p>
                    <?php if (!empty($branch['contact_number'])): ?>
                        <p><i class="fas fa-phone text-blue-500 mr-3"></i><?php echo esc($branch['contact_number']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($branch['email'])): ?>
                        <p><i class="fas fa-envelope text-green-500 mr-3"></i><?php echo esc($branch['email']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <a href="browse_units.php?branch_id=<?php echo $branch['branch_id']; ?>" class="btn-modern btn-luxury-primary justify-center text-base py-3">
                    <i class="fas fa-home"></i> View Available Units
                </a>
                <a href="mailto:<?php echo esc($branch['email'] ?: 'info@bookit.com'); ?>" class="btn-modern border-2 border-gray-900 text-gray-900 justify-center text-base py-3 hover:bg-gray-100 smooth-transition">
                    <i class="fas fa-envelope"></i> Contact Host
                </a>
            </div>
        </div>

        <!-- Description Section -->
        <div class="card-modern shadow-soft p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">About this Location</h2>
            <p class="text-gray-600 text-lg leading-relaxed">
                <?php echo !empty($branch['description']) ? esc($branch['description']) : 'Premium location with excellent amenities and services.'; ?>
            </p>
        </div>

        <!-- Amenities Section -->
        <div class="card-modern shadow-soft p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Amenities & Features</h2>
            <div class="flex flex-wrap gap-3">
                <?php 
                $amenities = getBranchAmenities($branch['branch_id']);
                if (!empty($amenities)):
                    foreach ($amenities as $a): ?>
                        <span class="badge-luxury"><i class="fas fa-check mr-2"></i><?php echo esc($a['amenity_name']); ?></span>
                    <?php endforeach; 
                else: ?>
                    <p class="text-gray-600">No amenities listed.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Section -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-2xl p-12 mb-12">
            <h2 class="text-2xl font-bold mb-8">Location Statistics</h2>
            <?php $stats = getBranchStatistics($branch['branch_id']); ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="text-5xl font-bold mb-2"><?php echo number_format($stats['active_units'] ?? 0); ?></div>
                    <p class="text-blue-100 text-lg">Active Units</p>
                </div>
                <div class="text-center">
                    <div class="text-5xl font-bold mb-2"><?php echo number_format($stats['total_reservations'] ?? 0); ?></div>
                    <p class="text-blue-100 text-lg">Total Bookings</p>
                </div>
                <div class="text-center">
                    <div class="text-5xl font-bold mb-2">
                        <?php 
                        $units = get_multiple_results("SELECT monthly_rate FROM units WHERE branch_id = ? AND is_available = 1 AND (approval_status = 'approved' OR approval_status IS NULL)", [$branch['branch_id']]);
                        $avgPrice = !empty($units) ? number_format(array_sum(array_column($units, 'monthly_rate')) / count($units), 0) : 0;
                        echo '₱' . $avgPrice;
                        ?>
                    </div>
                    <p class="text-blue-100 text-lg">Average Price/Month</p>
                </div>
                <div class="text-center">
                    <div class="text-5xl font-bold mb-2"><?php echo count($amenities ?? []); ?></div>
                    <p class="text-blue-100 text-lg">Amenities</p>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="card-modern shadow-soft bg-gradient-to-r from-gray-900 to-gray-800 text-white p-12 text-center rounded-2xl">
            <h2 class="text-3xl font-bold mb-4">Ready to Reserve?</h2>
            <p class="text-gray-300 text-lg mb-8">Check out our available units at this premium location.</p>
            <a href="browse_units.php?branch_id=<?php echo $branch['branch_id']; ?>" class="btn-modern bg-white text-gray-900 justify-center text-base py-3 px-10 hover:bg-gray-100 smooth-transition font-bold">
                <i class="fas fa-search"></i> View All Units
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
