<?php
// BookIT Modern Professional Homepage
// Multi-branch Condo Rental Reservation System

include '../includes/public_session.php';
include '../includes/functions.php';
include '../includes/auth.php';

// Fetch featured branches with comprehensive real data
$featuredBranches = mysqli_query($conn, "
    SELECT b.*, 
           COUNT(DISTINCT u.unit_id) as unit_count, 
           COUNT(DISTINCT r.reservation_id) as booking_count,
           MIN(u.monthly_rate) as min_price,
           MAX(u.monthly_rate) as max_price,
           AVG(u.monthly_rate) as avg_price
    FROM branches b 
    LEFT JOIN units u ON b.branch_id = u.branch_id AND u.is_available = 1 AND (u.approval_status = 'approved' OR u.approval_status IS NULL)
    LEFT JOIN reservations r ON b.branch_id = r.branch_id AND r.status = 'confirmed'
    WHERE b.is_active = 1 
    GROUP BY b.branch_id 
    ORDER BY booking_count DESC, b.created_at DESC
    LIMIT 6
");

// Fetch system statistics with proper data
$totalBranches = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM branches WHERE is_active = 1"));
$totalUnits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM units WHERE is_available = 1 AND (approval_status = 'approved' OR approval_status IS NULL)"));
$totalReservations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM reservations WHERE status = 'confirmed'"));
$totalClients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'renter' AND is_active = 1"));
$totalHosts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role IN ('host', 'manager') AND is_active = 1"));

// Calculate engagement metrics
$satisfactionRate = $totalClients['total'] > 0 ? round(($totalReservations['total'] / $totalClients['total']) * 100) : 85;
$hostEngagementRate = ($totalHosts['total'] > 0 && $totalUnits['total'] > 0) ? round(($totalUnits['total'] / ($totalHosts['total'] * 5)) * 100) : 72;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookIT - Condo Rental & Reservation System</title>
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

        /* Enhanced Color Palette - Professional BookIT Branding */
        :root {
            --primary: #2c3e50;      /* Deep Navy Blue */
            --secondary: #e74c3c;    /* Vibrant Red/Coral */
            --accent: #3498db;       /* Bright Sky Blue */
            --accent-light: #5dade2; /* Lighter Accent */
            --gold: #f39c12;         /* Premium Gold */
            --success: #27ae60;      /* Forest Green */
            --light: #ecf0f1;        /* Off-white */
            --dark: #2c3e50;         /* Dark Text */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
        }

        /* Hero Section with Styling */
        .hero-section {
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.75), rgba(52, 152, 219, 0.65)), 
                        url('../assets/images/hero-luxury-condo.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Smooth transitions for all interactive elements */
        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* shadow effects */
        .shadow-soft {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .shadow-lg {
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12);
        }

        .shadow-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .shadow-hover:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            transform: translateY(-8px);
        }

        /* Modern Card Styling - Soft UI Approach */
        .card-modern {
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: white;
            backdrop-filter: blur(10px);
        }

        /* Modern Button Styling */
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
            text-decoration: none;
        }

        .btn-luxury-primary:active {
            transform: translateY(-1px);
        }

        .btn-luxury-secondary {
            border: 2.5px solid var(--primary);
            color: var(--primary);
            background: white;
        }

        .btn-luxury-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(44, 62, 80, 0.2);
        }

        /* Booking Overlay Bar - Design */
        .booking-overlay {
            position: absolute;
            bottom: -70px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 20px;
            padding: 35px 45px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 950px;
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .booking-input {
            border: 1.5px solid var(--gray-200);
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--gray-50);
        }

        .booking-input:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }

        .booking-input::placeholder {
            color: #a0aec0;
        }

        /* Statistics Counter Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-item {
            animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .stat-item:nth-child(1) { animation-delay: 0.1s; }
        .stat-item:nth-child(2) { animation-delay: 0.2s; }
        .stat-item:nth-child(3) { animation-delay: 0.3s; }
        .stat-item:nth-child(4) { animation-delay: 0.4s; }

        /* Section spacing and styling */
        section {
            scroll-margin-top: 100px;
        }

        /* Gradient text effect */
        .gradient-text {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Image container with overlay */
        .image-container {
            position: relative;
            overflow: hidden;
            border-radius: 18px;
            aspect-ratio: 4/3;
        }

        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .image-container:hover img {
            transform: scale(1.08);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.4);
            opacity: 0;
            transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-container:hover .image-overlay {
            opacity: 1;
        }

        /* Badge Styling */
        .badge-luxury {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-featured {
            background: linear-gradient(135deg, var(--gold) 0%, #e67e22 100%);
        }

        .badge-popular {
            background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
        }

        /* Feature Icon Styling */
        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.15), rgba(243, 156, 18, 0.1));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--accent);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .shadow-hover:hover .feature-icon {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.25), rgba(243, 156, 18, 0.2));
            transform: translateY(-5px);
        }

        /* Price Styling - Typography */
        .price-tag {
            font-family: 'Poppins', sans-serif;
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--secondary) 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .price-period {
            color: var(--gray-600);
            font-size: 14px;
            font-weight: 500;
            text-transform: lowercase;
        }

        /* Rating Stars */
        .rating-stars {
            color: var(--gold);
            font-size: 14px;
            font-weight: 600;
        }

        /* Text ellipsis for descriptions */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Section background gradients */
        .bg-gradient-dark {
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%);
        }

        .bg-gradient-light {
            background: linear-gradient(to bottom, var(--gray-50) 0%, white 100%);
        }

        /* Enhanced responsive design */
        @media (max-width: 768px) {
            .booking-overlay {
                flex-direction: column;
                gap: 15px;
                bottom: -50px;
                padding: 25px;
                max-width: 95%;
            }

            .hero-section {
                min-height: 70vh;
            }

            .btn-modern {
                padding: 11px 22px;
                font-size: 14px;
            }

            .price-tag {
                font-size: 28px;
            }

            .feature-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
        }

        @media (max-width: 640px) {
            .hero-section {
                min-height: 60vh;
            }

            .booking-overlay {
                padding: 20px;
            }

            .stat-item {
                text-align: center;
            }
        }

        /* Smooth scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Loading animation */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Property card enhancement */
        .property-card {
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        .property-card-image {
            position: relative;
            height: 280px;
            overflow: hidden;
            border-radius: 18px 18px 0 0;
        }

        .property-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .property-card:hover .property-card-image img {
            transform: scale(1.1);
        }

        /* Section titles styling */
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 2px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--gold) 0%, transparent 100%);
            border-radius: 2px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-soft">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-3 text-2xl font-bold gradient-text hover:opacity-80 smooth-transition">
                    <img src="../assets/images/logo/bookit.png" alt="BookIT Logo" class="w-16 h-16 rounded-xl object-cover hover:shadow-lg smooth-transition">
                    BookIT
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center gap-10">
                    <a href="#features" class="text-gray-600 hover:text-gray-900 smooth-transition font-500 relative group">
                        Features
                        <span class="absolute bottom-0 left-0 w-0 h-1 bg-gradient-to-r from-blue-500 to-orange-500 group-hover:w-full smooth-transition"></span>
                    </a>
                    <a href="#properties" class="text-gray-600 hover:text-gray-900 smooth-transition font-500 relative group">
                        Properties
                        <span class="absolute bottom-0 left-0 w-0 h-1 bg-gradient-to-r from-blue-500 to-orange-500 group-hover:w-full smooth-transition"></span>
                    </a>
                    <a href="#about" class="text-gray-600 hover:text-gray-900 smooth-transition font-500 relative group">
                        About
                        <span class="absolute bottom-0 left-0 w-0 h-1 bg-gradient-to-r from-blue-500 to-orange-500 group-hover:w-full smooth-transition"></span>
                    </a>
                </div>

                <!-- Right Actions -->
                <div class="flex items-center gap-4">
                    <?php if (isLoggedIn() && in_array($_SESSION['role'], ['host','manager','admin'])): ?>
                        <a href="manager_register.php" class="hidden md:block btn-modern btn-luxury-secondary">
                            <i class="fas fa-handshake text-lg"></i> Be a Host
                        </a>
                    <?php else: ?>
                        <a href="be_host.php" class="hidden md:block btn-modern btn-luxury-secondary">
                            <i class="fas fa-handshake text-lg"></i> Be a Host
                        </a>
                    <?php endif; ?>

                    <?php if (isLoggedIn()): ?>
                        <div class="relative group">
                            <button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 smooth-transition">
                                <i class="fas fa-user-circle text-2xl"></i>
                                <span class="hidden sm:inline text-sm font-500"><?php echo htmlspecialchars(substr($_SESSION['fullname'], 0, 15)); ?></span>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                                <a href="../modules/notifications.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 first:rounded-t-lg smooth-transition">
                                    <i class="fas fa-bell mr-2 text-blue-500"></i> Notifications
                                </a>
                                <a href="../renter/my_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 smooth-transition">
                                    <i class="fas fa-calendar-check mr-2 text-orange-500"></i> My Bookings
                                </a>
                                <a href="../renter/profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 smooth-transition">
                                    <i class="fas fa-cog mr-2 text-gray-500"></i> Settings
                                </a>
                                <hr class="my-2">
                                <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 last:rounded-b-lg smooth-transition font-500">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn-modern btn-luxury-primary">
                            <i class="fas fa-sign-in-alt text-lg"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Premium Hero Section -->
    <section class="hero-section relative">
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-gray-50"></div>
        
        <div class="relative z-10 max-w-6xl mx-auto px-4 text-center text-white">

            <h1 class="text-5xl md:text-7xl font-bold mb-6 leading-tight" style="animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.1s both;">
                Find Your Perfect <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 via-orange-300 to-red-300">Residence</span>
            </h1>
            
            <p class="text-xl md:text-2xl mb-8 text-gray-100 max-w-3xl mx-auto leading-relaxed" style="animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.2s both;">
                Experience properties with seamless booking. Browse, reserve, and manage your stays all in one place.
            </p>

            <div class="flex flex-wrap gap-4 justify-center mb-32" style="animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.3s both;">
                <?php if (!isLoggedIn()): ?>
                    <a href="browse_units.php" class="btn-modern btn-luxury-primary text-lg px-8 py-4 hover:shadow-xl">
                        <i class="fas fa-search text-xl"></i> Explore Now
                    </a>
                    <a href="register.php" class="btn-modern btn-luxury-secondary text-lg px-8 py-4 hover:shadow-lg">
                        <i class="fas fa-user-plus text-xl"></i> Create Account
                    </a>
                <?php else: ?>
                    <?php if ($_SESSION['role'] == 'renter'): ?>
                        <a href="../renter/reserve_unit.php" class="btn-modern btn-luxury-primary text-lg px-8 py-4 hover:shadow-xl">
                            <i class="fas fa-home text-xl"></i> Reserve Unit
                        </a>
                        <a href="../renter/my_bookings.php" class="btn-modern btn-luxury-secondary text-lg px-8 py-4 hover:shadow-lg">
                            <i class="fas fa-calendar-check text-xl"></i> My Bookings
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php" class="btn-modern btn-luxury-primary text-lg px-8 py-4 hover:shadow-xl">
                            <i class="fas fa-tachometer-alt text-xl"></i> Dashboard
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Premium Booking Overlay Bar -->
            <div class="booking-overlay">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                    <div class="text-left">
                        <label class="block text-gray-700 font-600 mb-3 text-sm uppercase tracking-wide">Check-in Date</label>
                        <input type="date" class="booking-input w-full" id="checkInBooking" placeholder="Select date">
                    </div>
                    <div class="text-left">
                        <label class="block text-gray-700 font-600 mb-3 text-sm uppercase tracking-wide">Check-out Date</label>
                        <input type="date" class="booking-input w-full" id="checkOutBooking" placeholder="Select date">
                    </div>
                    <div class="text-left">
                        <label class="block text-gray-700 font-600 mb-3 text-sm uppercase tracking-wide">Number of Guests</label>
                        <select class="booking-input w-full">
                            <option selected>1 Guest</option>
                            <option>2 Guests</option>
                            <option>3 Guests</option>
                            <option>4 Guests</option>
                            <option>5+ Guests</option>
                        </select>
                    </div>
                    <button class="btn-modern btn-luxury-primary w-full justify-center text-base py-4" onclick="handleSearch()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </section>

    <style>
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <!-- About Section - Modern Design -->
    <section id="about" class="py-24 bg-white relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-50 rounded-full -mr-48 -mt-48 opacity-50"></div>
        <div class="max-w-6xl mx-auto px-4 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
                <div>
                    <div class="section-title mb-12">
                        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                           Condo Rentals Made Simple
                        </h2>
                    </div>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                        BookIT is your trusted platform for finding and booking condominiums across the Philippines. 
                        We connect you with properties, ensuring a seamless experience from search to check-in.
                    </p>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        Whether you're looking for a weekend getaway, a business stay, or a long-term rental, 
                        our curated selection of high-quality properties meets every need.
                    </p>
                    
                    <!-- Quick Features List -->
                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check text-blue-600 font-bold"></i>
                            </div>
                            <span class="text-gray-700 font-500">Verified Properties</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check text-orange-600 font-bold"></i>
                            </div>
                            <span class="text-gray-700 font-500">Secure Payments</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check text-green-600 font-bold"></i>
                            </div>
                            <span class="text-gray-700 font-500">24/7 Support</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check text-red-600 font-bold"></i>
                            </div>
                            <span class="text-gray-700 font-500">Best Rates</span>
                        </div>
                    </div>

                    <button class="btn-modern btn-luxury-primary text-lg">
                        Learn More <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                <div class="relative">
                    <div class="image-container shadow-lg">
                        <img src="../assets/images/branches/condoBGC.jpg" alt="Luxury Condo" class="rounded-2xl">
                        <div class="image-overlay">
                            <button class="w-20 h-20 bg-white rounded-full flex items-center justify-center hover:bg-orange-500 hover:text-white smooth-transition shadow-lg">
                                <i class="fas fa-play text-2xl ml-1"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Floating Card -->
                    <div class="absolute -bottom-10 -left-10 bg-white rounded-2xl p-6 shadow-lg max-w-xs border-t-4 border-orange-500">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-orange-500 rounded-xl flex items-center justify-center">
                                <i class="fas fa-star text-white text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-3xl font-bold text-gray-900">4.9★</p>
                                <p class="text-gray-600 text-sm">2,500+ Reviews</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-24 bg-gradient-dark text-white relative overflow-hidden">
        <!-- Decorative elements -->
        <div class="absolute top-0 left-0 w-96 h-96 bg-blue-500 rounded-full opacity-10 -ml-48 -mt-48 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-orange-500 rounded-full opacity-10 -mr-48 -mb-48 blur-3xl"></div>

        <div class="max-w-6xl mx-auto px-4 relative z-10">
            <div class="text-center mb-20">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">Our Impact & Achievements</h2>
                <p class="text-xl text-gray-300 max-w-2xl mx-auto">
                    Trusted by thousands of guests and hosts across the Philippines
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Locations Stat -->
                <div class="stat-item text-center p-8 bg-white bg-opacity-5 backdrop-blur-sm rounded-2xl border border-white border-opacity-10 hover:bg-opacity-10 smooth-transition">
                    <div class="feature-icon mx-auto mb-6" style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.3), rgba(52, 152, 219, 0.1)); color: #60a5fa;">
                        <i class="fas fa-building text-3xl"></i>
                    </div>
                    <div class="text-5xl font-bold mb-3"><?php echo $totalBranches['total']; ?>+</div>
                    <p class="text-gray-300 text-lg font-500">Locations</p>
                    <p class="text-gray-400 text-sm mt-2">Across the Philippines</p>
                </div>

                <!-- Available Properties Stat -->
                <div class="stat-item text-center p-8 bg-white bg-opacity-5 backdrop-blur-sm rounded-2xl border border-white border-opacity-10 hover:bg-opacity-10 smooth-transition">
                    <div class="feature-icon mx-auto mb-6" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.3), rgba(34, 197, 94, 0.1)); color: #4ade80;">
                        <i class="fas fa-home text-3xl"></i>
                    </div>
                    <div class="text-5xl font-bold mb-3"><?php echo $totalUnits['total']; ?>+</div>
                    <p class="text-gray-300 text-lg font-500">Available Properties</p>
                    <p class="text-gray-400 text-sm mt-2">Ready to book</p>
                </div>

                <!-- Successful Bookings Stat -->
                <div class="stat-item text-center p-8 bg-white bg-opacity-5 backdrop-blur-sm rounded-2xl border border-white border-opacity-10 hover:bg-opacity-10 smooth-transition">
                    <div class="feature-icon mx-auto mb-6" style="background: linear-gradient(135deg, rgba(251, 146, 60, 0.3), rgba(251, 146, 60, 0.1)); color: #fb923c;">
                        <i class="fas fa-calendar-check text-3xl"></i>
                    </div>
                    <div class="text-5xl font-bold mb-3"><?php echo $totalReservations['total']; ?>+</div>
                    <p class="text-gray-300 text-lg font-500">Successful Bookings</p>
                    <p class="text-gray-400 text-sm mt-2">This year</p>
                </div>

                <!-- Guest Satisfaction Stat -->
                <div class="stat-item text-center p-8 bg-white bg-opacity-5 backdrop-blur-sm rounded-2xl border border-white border-opacity-10 hover:bg-opacity-10 smooth-transition">
                    <div class="feature-icon mx-auto mb-6" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.3), rgba(239, 68, 68, 0.1)); color: #f87171;">
                        <i class="fas fa-smile text-3xl"></i>
                    </div>
                    <div class="text-5xl font-bold mb-3"><?php echo $satisfactionRate; ?>%</div>
                    <p class="text-gray-300 text-lg font-500">Guest Satisfaction</p>
                    <p class="text-gray-400 text-sm mt-2">Highly rated</p>
                </div>
            </div>

            <!-- Additional Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                <!-- Active Hosts -->
                <div class="stat-item p-8 bg-white bg-opacity-5 backdrop-blur-sm rounded-2xl border border-white border-opacity-10 hover:bg-opacity-10 smooth-transition flex items-center gap-6">
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user-tie text-white text-2xl"></i>
                    </div>
                    <div>
                        <div class="text-4xl font-bold"><?php echo $totalHosts['total']; ?>+</div>
                        <p class="text-gray-300 text-lg font-500">Active Hosts</p>
                        <p class="text-gray-400 text-sm">Trusted partners</p>
                    </div>
                </div>

                <!-- Happy Renters -->
                <div class="stat-item p-8 bg-white bg-opacity-5 backdrop-blur-sm rounded-2xl border border-white border-opacity-10 hover:bg-opacity-10 smooth-transition flex items-center gap-6">
                    <div class="w-20 h-20 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                    <div>
                        <div class="text-4xl font-bold"><?php echo $totalClients['total']; ?>+</div>
                        <p class="text-gray-300 text-lg font-500">Happy Renters</p>
                        <p class="text-gray-400 text-sm">Growing community</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Premium Features Section -->
    <section id="features" class="py-24 bg-gray-50 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-80 h-80 bg-blue-100 rounded-full -ml-40 -mt-40 opacity-40 blur-2xl"></div>
        
        <div class="max-w-6xl mx-auto px-4 relative z-10">
            <div class="text-center mb-20">
                <h2 class="text-4xl md:text-5xl font-bold mb-4 text-gray-900">Why Choose BookIT?</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Everything you need for a perfect stay with our platform</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="card-modern shadow-soft shadow-hover p-8 group">
                    <div class="feature-icon mb-6 group-hover:scale-110 smooth-transition">
                        <i class="fas fa-search text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Advanced Search</h3>
                    <p class="text-gray-600 leading-relaxed">Find your perfect condo with our intelligent filtering system and real-time availability tracking.</p>
                    <div class="mt-6 flex items-center text-blue-600 font-600 opacity-0 group-hover:opacity-100 smooth-transition">
                        Learn more <i class="fas fa-arrow-right ml-2"></i>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="card-modern shadow-soft shadow-hover p-8 group">
                    <div class="feature-icon mb-6 group-hover:scale-110 smooth-transition" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(34, 197, 94, 0.1)); color: #10b981;">
                        <i class="fas fa-lock text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Secure Booking</h3>
                    <p class="text-gray-600 leading-relaxed">Your bookings are protected with encrypted transactions and industry-leading secure payment processing.</p>
                    <div class="mt-6 flex items-center text-green-600 font-600 opacity-0 group-hover:opacity-100 smooth-transition">
                        Learn more <i class="fas fa-arrow-right ml-2"></i>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="card-modern shadow-soft shadow-hover p-8 group">
                    <div class="feature-icon mb-6 group-hover:scale-110 smooth-transition" style="background: linear-gradient(135deg, rgba(249, 115, 22, 0.15), rgba(249, 115, 22, 0.1)); color: #f97316;">
                        <i class="fas fa-headset text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">24/7 Support</h3>
                    <p class="text-gray-600 leading-relaxed">Our dedicated support team is here to help you anytime, anywhere during your stay.</p>
                    <div class="mt-6 flex items-center text-orange-600 font-600 opacity-0 group-hover:opacity-100 smooth-transition">
                        Learn more <i class="fas fa-arrow-right ml-2"></i>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="card-modern shadow-soft shadow-hover p-8 group">
                    <div class="feature-icon mb-6 group-hover:scale-110 smooth-transition" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.1)); color: #ef4444;">
                        <i class="fas fa-map-marker-alt text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Great Locations</h3>
                    <p class="text-gray-600 leading-relaxed">Browse properties in the best neighborhoods with excellent access to amenities and transport.</p>
                    <div class="mt-6 flex items-center text-red-600 font-600 opacity-0 group-hover:opacity-100 smooth-transition">
                        Learn more <i class="fas fa-arrow-right ml-2"></i>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="card-modern shadow-soft shadow-hover p-8 group">
                    <div class="feature-icon mb-6 group-hover:scale-110 smooth-transition" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(168, 85, 247, 0.1)); color: #a855f7;">
                        <i class="fas fa-star text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Verified Reviews</h3>
                    <p class="text-gray-600 leading-relaxed">Read authentic reviews from verified guests to make informed booking decisions with confidence.</p>
                    <div class="mt-6 flex items-center text-purple-600 font-600 opacity-0 group-hover:opacity-100 smooth-transition">
                        Learn more <i class="fas fa-arrow-right ml-2"></i>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="card-modern shadow-soft shadow-hover p-8 group">
                    <div class="feature-icon mb-6 group-hover:scale-110 smooth-transition" style="background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(14, 165, 233, 0.1)); color: #0ea5e9;">
                        <i class="fas fa-percent text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Best Rates</h3>
                    <p class="text-gray-600 leading-relaxed">Get competitive pricing with flexible booking options and exclusive deals for returning customers.</p>
                    <div class="mt-6 flex items-center text-sky-600 font-600 opacity-0 group-hover:opacity-100 smooth-transition">
                        Learn more <i class="fas fa-arrow-right ml-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Premium Properties Section -->
    <section id="properties" class="py-24 bg-white relative overflow-hidden">
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-orange-50 rounded-full -mr-48 -mb-48 opacity-50 blur-3xl"></div>
        
        <div class="max-w-7xl mx-auto px-4 relative z-10">
            <div class="text-center mb-20">
                <h2 class="text-4xl md:text-5xl font-bold mb-4 text-gray-900">Featured Branch</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Handpicked condos for your perfect getaway</p>
            </div>

            <?php if (mysqli_num_rows($featuredBranches) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php while ($branch = mysqli_fetch_assoc($featuredBranches)): 
                        // Select branch image
                        $branch_images = [
                            'BGC' => 'condoBGC.jpg',
                            'Makati' => 'makati-condo.jpg',
                            'Ortigas' => 'ortigas-condo.jpg',
                            'Mandaluyong' => 'mandaluyong-condo.jpg',
                            'Quezon City' => 'qc-condo.jpg',
                            'Pasig' => 'pasig-condo.jpg'
                        ];
                        
                        $branch_city = $branch['city'] ?? 'Metro Manila';
                        $image_name = 'condoBGC.jpg';
                        
                        foreach ($branch_images as $city => $img) {
                            if (stripos($branch_city, $city) !== false) {
                                $image_name = $img;
                                break;
                            }
                        }
                        
                        $image_path = "../assets/images/branches/" . $image_name;
                        $default_image = "../assets/images/branches/condoBGC.jpg";

                        // Get minimum price
                        $minPrice = $branch['min_price'] ?? null;
                        if (empty($minPrice) || $minPrice == 0) {
                            $displayPrice = 'Contact';
                        } else {
                            $displayPrice = '₱' . number_format($minPrice, 0);
                        }

                        // Get ratings
                        if (function_exists('column_exists') && column_exists('reviews', 'branch_id')) {
                            $ratingData = get_single_result("SELECT AVG(rating) AS avg_rating, COUNT(*) AS reviews_count FROM reviews WHERE branch_id = ? AND is_approved = 1", [$branch['branch_id']]);
                            $avgRating = $ratingData && $ratingData['avg_rating'] ? round($ratingData['avg_rating'],1) : null;
                            $reviewsCount = $ratingData ? (int)$ratingData['reviews_count'] : 0;
                        } else {
                            $avgRating = 4.8;
                            $reviewsCount = $branch['booking_count'] > 0 ? intval($branch['booking_count'] * 0.6) : 0;
                        }
                    ?>
                        <div class="card-modern shadow-soft shadow-hover overflow-hidden h-full flex flex-col group">
                            <!-- Premium Image Container -->
                            <div class="relative h-80 overflow-hidden bg-gray-200">
                                <img src="<?php echo file_exists($image_path) ? $image_path : $default_image; ?>" 
                                     alt="<?php echo htmlspecialchars($branch['branch_name']); ?>"
                                     class="w-full h-full object-cover group-hover:scale-110 smooth-transition"
                                     onerror="this.src='<?php echo $default_image; ?>'">
                                
                                <!-- Premium Badges -->
                                <div class="absolute top-4 left-4 flex gap-2 z-20">
                                    <span class="badge-luxury badge-featured animate-pulse">
                                        <i class="fas fa-crown mr-1"></i> Featured
                                    </span>
                                    <?php if ($branch['booking_count'] > 50): ?>
                                        <span class="badge-luxury badge-popular">
                                            <i class="fas fa-fire mr-1"></i> Popular
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Premium Rating Badge -->
                                <div class="absolute top-4 right-4 bg-white rounded-xl px-4 py-3 shadow-lg backdrop-blur-sm border border-white border-opacity-20 z-20">
                                    <div class="rating-stars flex items-center gap-2 mb-1">
                                        <i class="fas fa-star"></i>
                                        <?php 
                                        if ($avgRating): 
                                            echo '<span class="font-bold text-gray-900">' . $avgRating . '</span>';
                                        else:
                                            echo '<span class="font-bold text-gray-900">New</span>';
                                        endif;
                                        ?>
                                    </div>
                                    <p class="text-xs text-gray-600 font-500"><?php echo $reviewsCount; ?> reviews</p>
                                </div>

                                <!-- Overlay Gradient -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-0 group-hover:opacity-60 smooth-transition"></div>
                            </div>

                            <!-- Premium Content -->
                            <div class="p-6 flex flex-col flex-grow">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2 group-hover:text-blue-600 smooth-transition">
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </h3>
                                
                                <p class="text-sm text-gray-500 mb-4 flex items-center gap-2">
                                    <i class="fas fa-map-marker-alt text-orange-500"></i> 
                                    <?php echo htmlspecialchars($branch_city); ?>
                                </p>

                                <!-- Description -->
                                <p class="text-gray-600 text-sm mb-6 line-clamp-2 leading-relaxed">
                                    <?php echo !empty($branch['description']) ? htmlspecialchars($branch['description']) : 'Condo property with world-class amenities and exceptional service.'; ?>
                                </p>

                                <!-- Pricing Section -->
                                <div class="mb-6 pb-6 border-b border-gray-100">
                                    <p class="text-gray-600 text-sm font-500 mb-2">Starting from</p>
                                    <div class="flex items-baseline gap-2">
                                        <span class="price-tag"><?php echo $displayPrice; ?></span>
                                        <span class="price-period">/night</span>
                                    </div>
                                </div>

                                <!-- Premium Features Grid -->
                                <div class="grid grid-cols-2 gap-3 mb-6">
                                    <div class="flex items-center gap-2 text-sm text-gray-600 bg-blue-50 px-3 py-2 rounded-lg">
                                        <i class="fas fa-home text-blue-500 font-bold"></i>
                                        <span class="font-500"><?php echo $branch['unit_count']; ?> units</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 bg-green-50 px-3 py-2 rounded-lg">
                                        <i class="fas fa-swimming-pool text-green-500 font-bold"></i>
                                        <span class="font-500">Pool</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 bg-purple-50 px-3 py-2 rounded-lg">
                                        <i class="fas fa-wifi text-purple-500 font-bold"></i>
                                        <span class="font-500">WiFi</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 bg-red-50 px-3 py-2 rounded-lg">
                                        <i class="fas fa-shield-alt text-red-500 font-bold"></i>
                                        <span class="font-500">Security</span>
                                    </div>
                                </div>

                                <!-- Amenities Preview -->
                                <div class="mb-6">
                                    <?php
                                    $amenities = getBranchAmenities($branch['branch_id']);
                                    if (!empty($amenities)) {
                                        $count = 0;
                                        foreach ($amenities as $am) {
                                            if ($count++ >= 3) break;
                                            echo '<span class="inline-block bg-blue-50 text-blue-700 text-xs font-600 px-3 py-2 rounded-lg mr-2 mb-2 border border-blue-200">' . htmlspecialchars($am['amenity_name']) . '</span>';
                                        }
                                        if (count($amenities) > 3) {
                                            echo '<span class="text-xs text-gray-500 font-600">+' . (count($amenities)-3) . ' more amenities</span>';
                                        }
                                    }
                                    ?>
                                </div>

                                <!-- Premium Action Buttons -->
                                <div class="flex gap-3 mt-auto pt-4 border-t border-gray-100">
                                    <button class="flex-1 btn-modern btn-luxury-primary justify-center text-base py-3 font-600 book-now-btn"
                                            data-branch-id="<?php echo $branch['branch_id']; ?>"
                                            data-branch-name="<?php echo htmlspecialchars($branch['branch_name']); ?>"
                                            data-branch-price="<?php echo $branch['min_price'] ?? 2500; ?>"
                                            data-branch-location="<?php echo htmlspecialchars($branch_city); ?>">
                                        <i class="fas fa-calendar-check"></i> Book Now
                                    </button>
                                    <a href="branch_details.php?id=<?php echo $branch['branch_id']; ?>" 
                                       class="flex-1 btn-modern btn-luxury-secondary justify-center text-base py-3 font-600">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- View All Properties Button -->
                <div class="text-center mt-16">
                    <a href="browse_units.php" class="btn-modern btn-luxury-primary text-lg px-12 py-4 shadow-lg hover:shadow-xl">
                        <i class="fas fa-th-large"></i> View All <?php echo $totalUnits['total']; ?>+ Properties
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-24 bg-gray-50 rounded-2xl">
                    <i class="fas fa-building text-7xl text-gray-300 mb-6"></i>
                    <h3 class="text-3xl font-bold text-gray-600 mb-3">No Properties Available</h3>
                    <p class="text-gray-500 text-lg mb-8">Check back soon for exciting new listings!</p>
                    <a href="browse_units.php" class="btn-modern btn-luxury-primary text-lg px-8 py-4">
                        <i class="fas fa-search"></i> Browse All Properties
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Premium CTA Section -->
    <section class="py-24 bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800 text-white relative overflow-hidden">
        <!-- Decorative elements -->
        <div class="absolute top-0 left-0 w-96 h-96 bg-blue-400 rounded-full opacity-10 -ml-48 -mt-48 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-orange-400 rounded-full opacity-10 -mr-48 -mb-48 blur-3xl"></div>

        <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
            <h2 class="text-5xl md:text-6xl font-bold mb-6 leading-tight">Ready to Book Your Dream Condo?</h2>
            <p class="text-xl md:text-2xl mb-12 text-blue-100 leading-relaxed">
                Join thousands of satisfied guests who trust BookIT for condo rentals. 
                Experience seamless booking, secure payments, and exceptional service today.
            </p>
            
            <div class="flex flex-wrap gap-4 justify-center">
                <?php if (!isLoggedIn()): ?>
                    <a href="browse_units.php" class="btn-modern bg-white text-blue-600 hover:bg-gray-50 text-lg px-10 py-4 font-bold shadow-lg hover:shadow-xl">
                        <i class="fas fa-search"></i> Browse Now
                    </a>
                    <a href="register.php" class="btn-modern border-2 border-white text-white hover:bg-white hover:text-blue-600 text-lg px-10 py-4 font-bold">
                        <i class="fas fa-user-plus"></i> Sign Up Free
                    </a>
                <?php else: ?>
                    <?php if ($_SESSION['role'] == 'renter'): ?>
                        <a href="../renter/reserve_unit.php" class="btn-modern bg-white text-blue-600 hover:bg-gray-50 text-lg px-10 py-4 font-bold shadow-lg hover:shadow-xl">
                            <i class="fas fa-home"></i> Reserve Now
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php" class="btn-modern bg-white text-blue-600 hover:bg-gray-50 text-lg px-10 py-4 font-bold shadow-lg hover:shadow-xl">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Premium Footer -->
    <footer class="bg-gray-900 text-gray-300 py-20 border-t border-gray-800">
        <div class="max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center gap-3 mb-6">
                        <img src="../uploads/logo/bookit.png" alt="BookIT Logo" class="w-14 h-14 rounded-xl object-cover">
                        <h3 class="text-white text-2xl font-bold">BookIT</h3>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Condo rental platform connecting guests with properties across the Philippines. Seamless booking, secure payments, exceptional service.
                    </p>
                    <div class="flex gap-4 mt-6">
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-blue-600 rounded-lg flex items-center justify-center smooth-transition">
                            <i class="fab fa-facebook-f text-sm"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-sky-400 rounded-lg flex items-center justify-center smooth-transition">
                            <i class="fab fa-twitter text-sm"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-pink-600 rounded-lg flex items-center justify-center smooth-transition">
                            <i class="fab fa-instagram text-sm"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 hover:bg-red-600 rounded-lg flex items-center justify-center smooth-transition">
                            <i class="fab fa-youtube text-sm"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-6">Quick Links</h4>
                    <ul class="space-y-3">
                        <li><a href="browse_units.php" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> Browse Properties</a></li>
                        <li><a href="#features" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> Features</a></li>
                        <li><a href="be_host.php" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> Become a Host</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> About Us</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-6">Support & Info</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> Contact Us</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> Safety Tips</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white smooth-transition flex items-center gap-2"><i class="fas fa-arrow-right text-orange-500 text-xs"></i> FAQ</a></li>
                    </ul>
                </div>

                <!-- Newsletter -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-6">Newsletter</h4>
                    <p class="text-gray-400 text-sm mb-4">Subscribe to get special offers and updates.</p>
                    <div class="flex flex-col gap-3">
                        <input type="email" placeholder="Your email" class="px-4 py-3 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-500 focus:border-orange-500 focus:outline-none smooth-transition">
                        <button class="btn-modern btn-luxury-primary justify-center font-bold">
                            <i class="fas fa-paper-plane"></i> Subscribe
                        </button>
                    </div>
                </div>
            </div>

            <hr class="border-gray-800 my-8">

            <!-- Bottom Footer -->
            <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-400">
                <p>&copy; 2025 BookIT. All rights reserved. <span class="text-orange-500 ml-2">✨ Service Since 2025</span></p>
                <div class="flex gap-6 mt-6 md:mt-0">
                    <a href="#" class="hover:text-white smooth-transition">Privacy Policy</a>
                    <a href="#" class="hover:text-white smooth-transition">Terms of Service</a>
                    <a href="#" class="hover:text-white smooth-transition">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Booking Modal -->
    <div id="bookingModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-96 overflow-y-auto shadow-2xl">
            <!-- Modal content will be populated via JavaScript -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle search button click
        function handleSearch() {
            const checkIn = document.getElementById('checkInBooking').value;
            const checkOut = document.getElementById('checkOutBooking').value;
            
            if (checkIn && checkOut) {
                window.location.href = `browse_units.php?check_in=${checkIn}&check_out=${checkOut}`;
            } else {
                alert('Please select both check-in and check-out dates');
            }
        }

        // Book Now Button Handler
        document.querySelectorAll('.book-now-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const branchId = this.dataset.branchId;
                window.location.href = `branch_details.php?id=${branchId}#booking`;
            });
        });

        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== '#' && document.querySelector(href)) {
                    e.preventDefault();
                    document.querySelector(href).scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Set minimum dates for booking inputs
        const today = new Date();
        today.setDate(today.getDate() + 1); // Minimum is tomorrow
        const minDateString = today.toISOString().split('T')[0];
        
        const checkInInput = document.getElementById('checkInBooking');
        const checkOutInput = document.getElementById('checkOutBooking');
        
        if (checkInInput) checkInInput.setAttribute('min', minDateString);
        if (checkOutInput) checkOutInput.setAttribute('min', minDateString);

        // Update checkout minimum date when checkin is selected
        if (checkInInput) {
            checkInInput.addEventListener('change', function() {
                const checkInDate = new Date(this.value);
                checkInDate.setDate(checkInDate.getDate() + 1);
                const minCheckoutDate = checkInDate.toISOString().split('T')[0];
                if (checkOutInput) checkOutInput.setAttribute('min', minCheckoutDate);
            });
        }

        // Scroll to top on page load
        window.addEventListener('load', () => {
            window.scrollTo(0, 0);
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe all stat items
        document.querySelectorAll('.stat-item').forEach(el => {
            el.style.opacity = '0';
            observer.observe(el);
        });
    </script>
</body>
</html>
