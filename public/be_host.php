<?php
include '../includes/public_session.php';
include '../includes/functions.php';
include '../includes/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Become a Host - BookIT</title>
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

    .card-modern {
        border-radius: 20px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        overflow: hidden;
        background: white;
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
                <?php if (!isLoggedIn()): ?>
                    <a href="login.php" class="btn-modern btn-luxury-primary">
                        <i class="fas fa-sign-in-alt text-lg"></i> Login
                    </a>
                <?php else: ?>
                    <div class="relative group">
                        <button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 smooth-transition">
                            <i class="fas fa-user-circle text-2xl"></i>
                            <span class="hidden sm:inline text-sm font-500"><?php echo htmlspecialchars(substr($_SESSION['fullname'], 0, 15)); ?></span>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
                            <a href="../renter/my_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 first:rounded-t-lg smooth-transition">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <div class="min-h-screen bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 flex items-center">
    <div class="max-w-6xl mx-auto px-4 w-full">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <!-- Left Content -->
        <div class="text-white">
          <h1 class="text-5xl md:text-6xl font-bold mb-6 leading-tight">
            Start Hosting Your Condo
          </h1>
          <p class="text-xl text-blue-100 mb-8 leading-relaxed">
            Turn your property into a profitable venture. Join thousands of successful hosts on BookIT and start earning today.
          </p>

          <div class="space-y-4 mb-8">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center flex-shrink-0 mt-1">
                <i class="fas fa-check text-2xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2">Manage Units Easily</h3>
                <p class="text-blue-100">List and manage multiple units with our intuitive platform</p>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center flex-shrink-0 mt-1">
                <i class="fas fa-check text-2xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2">Reach More Guests</h3>
                <p class="text-blue-100">Get visibility to thousands of users searching for condo rentals</p>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center flex-shrink-0 mt-1">
                <i class="fas fa-check text-2xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2">Real-time Tracking</h3>
                <p class="text-blue-100">Monitor bookings and payments with our advanced dashboard</p>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center flex-shrink-0 mt-1">
                <i class="fas fa-check text-2xl"></i>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2">Dedicated Support</h3>
                <p class="text-blue-100">Get help from our team every step of the way</p>
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-4">
            <?php if (!isLoggedIn()): ?>
              <a href="host_register.php" class="btn-modern btn-luxury-primary text-lg px-10 py-4 bg-white text-blue-600 hover:bg-gray-100">
                <i class="fas fa-user-plus"></i> Register as Host
              </a>
              <a href="login.php" class="btn-modern border-2 border-white text-white hover:bg-white hover:text-blue-600 text-lg px-10 py-4">
                <i class="fas fa-sign-in-alt"></i> Login
              </a>
            <?php else: ?>
              <a href="../host/host_dashboard.php" class="btn-modern btn-luxury-primary text-lg px-10 py-4 bg-white text-blue-600 hover:bg-gray-100">
                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Content - Illustration -->
        <div class="text-center">
          <div class="relative inline-block">
            <div class="w-96 h-96 bg-white bg-opacity-10 rounded-3xl backdrop-blur-lg flex items-center justify-center">
              <div class="text-center">
                <i class="fas fa-home text-9xl text-white opacity-30 block mb-4"></i>
                <p class="text-white text-xl font-semibold">Your Properties Here</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Section -->
  <div class="bg-white py-20">
    <div class="max-w-6xl mx-auto px-4">
      <div class="text-center mb-16">
        <h2 class="text-4xl font-bold text-gray-900 mb-4">Why Host with BookIT?</h2>
        <p class="text-xl text-gray-600">Everything you need to succeed</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="card-modern shadow-soft p-8 text-center hover:shadow-lg smooth-transition">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-chart-line text-3xl text-blue-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-3">Increase Revenue</h3>
          <p class="text-gray-600">Earn competitive rates and maximize your property's profitability with our platform</p>
        </div>

        <div class="card-modern shadow-soft p-8 text-center hover:shadow-lg smooth-transition">
          <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-users text-3xl text-orange-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-3">Quality Guests</h3>
          <p class="text-gray-600">Access verified renters and trusted bookings on our secure platform</p>
        </div>

        <div class="card-modern shadow-soft p-8 text-center hover:shadow-lg smooth-transition">
          <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-headset text-3xl text-green-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-3">Expert Support</h3>
          <p class="text-gray-600">Get professional assistance whenever you need help managing your property</p>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA Section -->
  <div class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-20">
    <div class="max-w-4xl mx-auto px-4 text-center">
      <h2 class="text-4xl font-bold mb-6">Ready to Get Started?</h2>
      <p class="text-xl text-gray-300 mb-10">Join our community of successful hosts today and start earning.</p>
      
      <?php if (!isLoggedIn()): ?>
        <a href="host_register.php" class="btn-modern btn-luxury-primary text-lg px-12 py-4 bg-white text-gray-900 hover:bg-gray-100">
          <i class="fas fa-user-plus"></i> Create Host Account
        </a>
      <?php else: ?>
        <a href="../host/host_dashboard.php" class="btn-modern btn-luxury-primary text-lg px-12 py-4 bg-white text-gray-900 hover:bg-gray-100">
          <i class="fas fa-arrow-right"></i> Go to Dashboard
        </a>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>