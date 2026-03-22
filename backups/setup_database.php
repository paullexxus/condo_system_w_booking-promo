<?php
// BookIT Database Setup Script
// Run this file to create the database and tables

include 'config/db.php';

echo "<h2>BookIT Database Setup</h2>";

// Create tables
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        role ENUM('admin', 'host', 'renter') NOT NULL,
        branch_id INT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "branches" => "CREATE TABLE IF NOT EXISTS branches (
        branch_id INT PRIMARY KEY AUTO_INCREMENT,
        branch_name VARCHAR(100) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(50) NOT NULL,
        contact_number VARCHAR(20),
        email VARCHAR(100),
        host_id INT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(user_id)
    )",
    
    "units" => "CREATE TABLE IF NOT EXISTS units (
        unit_id INT PRIMARY KEY AUTO_INCREMENT,
        branch_id INT NOT NULL,
        unit_number VARCHAR(20) NOT NULL,
        unit_type VARCHAR(50) NOT NULL,
        floor_number INT,
        host_id INT,
        monthly_rate DECIMAL(10,2) NOT NULL,
        security_deposit DECIMAL(10,2) DEFAULT 0,
        is_available BOOLEAN DEFAULT TRUE,
        description TEXT,
        max_occupancy INT DEFAULT 2,
        building_name VARCHAR(255),
        street_address VARCHAR(255),
        city VARCHAR(100),
        address_hash VARCHAR(64),
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
        FOREIGN KEY (host_id) REFERENCES users(user_id)
    )",
    
    "amenities" => "CREATE TABLE IF NOT EXISTS amenities (
        amenity_id INT PRIMARY KEY AUTO_INCREMENT,
        branch_id INT NOT NULL,
        amenity_name VARCHAR(100) NOT NULL,
        description TEXT,
        hourly_rate DECIMAL(8,2) DEFAULT 0,
        is_available BOOLEAN DEFAULT TRUE,
        max_capacity INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
    )",
    
    "reservations" => "CREATE TABLE IF NOT EXISTS reservations (
        reservation_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        unit_id INT NOT NULL,
        branch_id INT NOT NULL,
        check_in_date DATE NOT NULL,
        check_out_date DATE NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        security_deposit DECIMAL(10,2) DEFAULT 0,
        status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
        special_requests TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (unit_id) REFERENCES units(unit_id),
        FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
    )",
    
    "amenity_bookings" => "CREATE TABLE IF NOT EXISTS amenity_bookings (
        booking_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        amenity_id INT NOT NULL,
        branch_id INT NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        total_amount DECIMAL(8,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (amenity_id) REFERENCES amenities(amenity_id),
        FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
    )",
    
    "payments" => "CREATE TABLE IF NOT EXISTS payments (
        payment_id INT PRIMARY KEY AUTO_INCREMENT,
        reservation_id INT NULL,
        amenity_booking_id INT NULL,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('cash', 'bank_transfer', 'gcash', 'paymaya', 'credit_card') NOT NULL,
        payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        transaction_reference VARCHAR(100),
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id),
        FOREIGN KEY (amenity_booking_id) REFERENCES amenity_bookings(booking_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )",
    
    "notifications" => "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('booking', 'payment', 'reminder', 'system') NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        sent_via ENUM('email', 'sms', 'system') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )",
    
    "reviews" => "CREATE TABLE IF NOT EXISTS reviews (
        review_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        unit_id INT NOT NULL,
        branch_id INT NOT NULL,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        is_approved BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (unit_id) REFERENCES units(unit_id),
        FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
    )",
    
    "payment_sources" => "CREATE TABLE IF NOT EXISTS payment_sources (
        source_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        reservation_id INT NULL,
        amenity_booking_id INT NULL,
        source_id_paymongo VARCHAR(100) NOT NULL UNIQUE,
        payment_method VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id),
        FOREIGN KEY (amenity_booking_id) REFERENCES amenity_bookings(booking_id)
    )",
    
    "user_bank_accounts" => "CREATE TABLE IF NOT EXISTS user_bank_accounts (
        account_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        account_name VARCHAR(100) NOT NULL,
        is_verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        UNIQUE KEY (user_id)
    )",
    
    "user_payment_methods" => "CREATE TABLE IF NOT EXISTS user_payment_methods (
        method_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        method VARCHAR(50) NOT NULL,
        account_details VARCHAR(255) NOT NULL,
        is_verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        UNIQUE KEY (user_id, method)
    )"
];

// Create tables
foreach ($tables as $tableName => $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color: green;'>✓ Table '$tableName' created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating table '$tableName': " . mysqli_error($conn) . "</p>";
    }
}

// Insert default admin user
$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$adminSql = "INSERT IGNORE INTO users (full_name, email, password, role) VALUES ('System Administrator', 'admin@bookit.com', '$adminPassword', 'admin')";

if (mysqli_query($conn, $adminSql)) {
    echo "<p style='color: green;'>✓ Default admin user created</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating admin user: " . mysqli_error($conn) . "</p>";
}

// Insert sample branches
$branches = [
    "INSERT IGNORE INTO branches (branch_name, address, city, contact_number, email) VALUES ('BookIT Makati', '123 Ayala Avenue, Makati City', 'Makati', '02-8123-4567', 'makati@bookit.com')",
    "INSERT IGNORE INTO branches (branch_name, address, city, contact_number, email) VALUES ('BookIT BGC', '456 BGC High Street, Taguig City', 'Taguig', '02-8123-4568', 'bgc@bookit.com')",
    "INSERT IGNORE INTO branches (branch_name, address, city, contact_number, email) VALUES ('BookIT Ortigas', '789 Ortigas Center, Pasig City', 'Pasig', '02-8123-4569', 'ortigas@bookit.com')"
];

foreach ($branches as $branchSql) {
    if (mysqli_query($conn, $branchSql)) {
        echo "<p style='color: green;'>✓ Sample branch added</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding branch: " . mysqli_error($conn) . "</p>";
    }
}

// Insert sample units
$units = [
    "INSERT IGNORE INTO units (branch_id, unit_number, unit_type, floor_number, monthly_rate, security_deposit, max_occupancy, description) VALUES (1, 'A101', 'Studio', 1, 15000.00, 5000.00, 2, 'Cozy studio unit with city view')",
    "INSERT IGNORE INTO units (branch_id, unit_number, unit_type, floor_number, monthly_rate, security_deposit, max_occupancy, description) VALUES (1, 'A102', '1BR', 1, 20000.00, 7000.00, 3, '1 bedroom unit with balcony')",
    "INSERT IGNORE INTO units (branch_id, unit_number, unit_type, floor_number, monthly_rate, security_deposit, max_occupancy, description) VALUES (2, 'B101', 'Studio', 1, 18000.00, 6000.00, 2, 'Modern studio in BGC')",
    "INSERT IGNORE INTO units (branch_id, unit_number, unit_type, floor_number, monthly_rate, security_deposit, max_occupancy, description) VALUES (3, 'C101', 'Studio', 1, 16000.00, 5500.00, 2, 'Affordable studio in Ortigas')"
];

foreach ($units as $unitSql) {
    if (mysqli_query($conn, $unitSql)) {
        echo "<p style='color: green;'>✓ Sample unit added</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding unit: " . mysqli_error($conn) . "</p>";
    }
}

// Insert sample amenities
$amenities = [
    "INSERT IGNORE INTO amenities (branch_id, amenity_name, description, hourly_rate, max_capacity) VALUES (1, 'Swimming Pool', 'Olympic-size swimming pool', 200.00, 20)",
    "INSERT IGNORE INTO amenities (branch_id, amenity_name, description, hourly_rate, max_capacity) VALUES (1, 'Gym', 'Fully equipped fitness center', 150.00, 15)",
    "INSERT IGNORE INTO amenities (branch_id, amenity_name, description, hourly_rate, max_capacity) VALUES (2, 'Swimming Pool', 'Rooftop swimming pool', 250.00, 25)",
    "INSERT IGNORE INTO amenities (branch_id, amenity_name, description, hourly_rate, max_capacity) VALUES (3, 'Swimming Pool', 'Indoor swimming pool', 180.00, 15)"
];

foreach ($amenities as $amenitySql) {
    if (mysqli_query($conn, $amenitySql)) {
        echo "<p style='color: green;'>✓ Sample amenity added</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding amenity: " . mysqli_error($conn) . "</p>";
    }
}

echo "<hr>";
echo "<h3>Setup Complete!</h3>";
echo "<p><strong>Admin Login Credentials:</strong></p>";
echo "<p>Email: admin@bookit.com</p>";
echo "<p>Password: admin123</p>";
echo "<p><a href='public/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
echo "<p><a href='admin/admin_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Go to Admin Dashboard</a></p>";
?>


