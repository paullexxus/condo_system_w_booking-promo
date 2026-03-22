<?php
/**
 * Migration: Add Duplicate Listing Detection Tables
 * 
 * This migration adds tables and columns for comprehensive duplicate listing detection
 * following Airbnb's model with 6 verification layers.
 */

include_once __DIR__ . '/../config/db.php';

echo "<h2>Duplicate Listing Detection - Database Migration</h2>";

// ==================== ALTER EXISTING TABLES ====================

// 1. Extend units table with address and location data
$alter_columns = [
    "building_name VARCHAR(100)",
    "street_address VARCHAR(255)",
    "unit_number_formal VARCHAR(50)",
    "city VARCHAR(100)",
    "postal_code VARCHAR(20)",
    "latitude DECIMAL(10, 8)",
    "longitude DECIMAL(11, 8)",
    "address_hash VARCHAR(255)",
    "location_hash VARCHAR(255)",
    "is_flagged BOOLEAN DEFAULT FALSE",
    "flag_reason VARCHAR(500)",
    "verified_at TIMESTAMP NULL"
];

foreach ($alter_columns as $column_def) {
    $alter_units_sql = "ALTER TABLE units ADD COLUMN IF NOT EXISTS " . $column_def;
    try {
        $conn->query($alter_units_sql);
    } catch (Exception $e) {
        // Column may already exist
    }
}
echo "<p style='color: green;'>✓ Extended units table with address/location fields</p>";

// 2. Extend users table with verification data
$user_columns = [
    "phone_hash VARCHAR(255)",
    "email_hash VARCHAR(255)",
    "id_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'",
    "id_verification_date TIMESTAMP NULL",
    "face_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'",
    "face_verification_date TIMESTAMP NULL",
    "profile_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'",
    "profile_verification_date TIMESTAMP NULL",
    "payout_account_id INT NULL",
    "is_verified_host BOOLEAN DEFAULT FALSE"
];

foreach ($user_columns as $column_def) {
    $alter_users_sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS " . $column_def;
    try {
        $conn->query($alter_users_sql);
    } catch (Exception $e) {
        // Column may already exist
    }
}
echo "<p style='color: green;'>✓ Extended users table with verification fields</p>";

// ==================== CREATE NEW TABLES ====================

$tables = [
    // Table 1: Unit Images - stores metadata for all unit photos
    "unit_images" => "CREATE TABLE IF NOT EXISTS unit_images (
        image_id INT PRIMARY KEY AUTO_INCREMENT,
        unit_id INT NOT NULL,
        image_path VARCHAR(500) NOT NULL,
        image_hash VARCHAR(128),
        room_type VARCHAR(100),
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_flagged BOOLEAN DEFAULT FALSE,
        flag_reason VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
        INDEX (unit_id),
        INDEX (image_hash)
    )",
    
    // Table 2: Image Fingerprints - perceptual hashing for similarity detection
    "image_fingerprints" => "CREATE TABLE IF NOT EXISTS image_fingerprints (
        fingerprint_id INT PRIMARY KEY AUTO_INCREMENT,
        image_id INT NOT NULL,
        ahash VARCHAR(255),
        phash VARCHAR(255),
        dhash VARCHAR(255),
        similarity_score DECIMAL(5, 2),
        matched_images JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (image_id) REFERENCES unit_images(image_id) ON DELETE CASCADE,
        INDEX (image_id),
        UNIQUE KEY unique_image_fingerprint (image_id)
    )",
    
    // Table 3: Geolocation Data - precise location coordinates
    "unit_geolocation" => "CREATE TABLE IF NOT EXISTS unit_geolocation (
        geolocation_id INT PRIMARY KEY AUTO_INCREMENT,
        unit_id INT NOT NULL,
        latitude DECIMAL(10, 8) NOT NULL,
        longitude DECIMAL(11, 8) NOT NULL,
        address_latitude DECIMAL(10, 8),
        address_longitude DECIMAL(11, 8),
        coordinate_hash VARCHAR(128),
        proximity_matches JSON,
        verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
        UNIQUE KEY unique_unit_geolocation (unit_id),
        INDEX (unit_id),
        INDEX (coordinate_hash),
        INDEX (latitude),
        INDEX (longitude)
    )",
    
    // Table 4: Host Verification Checks
    "host_verification" => "CREATE TABLE IF NOT EXISTS host_verification (
        verification_id INT PRIMARY KEY AUTO_INCREMENT,
        host_id INT NOT NULL UNIQUE,
        id_document_path VARCHAR(500),
        id_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        id_verified_date TIMESTAMP NULL,
        face_photo_path VARCHAR(500),
        face_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        face_verified_date TIMESTAMP NULL,
        profile_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        profile_verified_date TIMESTAMP NULL,
        phone_number_verified BOOLEAN DEFAULT FALSE,
        email_verified BOOLEAN DEFAULT FALSE,
        payout_account_id INT NULL,
        payout_verified BOOLEAN DEFAULT FALSE,
        verification_score INT DEFAULT 0,
        is_verified_host BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX (host_id)
    )",
    
    // Table 5: Duplicate Detection Logs - main audit trail
    "duplicate_detection_logs" => "CREATE TABLE IF NOT EXISTS duplicate_detection_logs (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        unit_id INT NOT NULL,
        duplicate_unit_id INT,
        detection_type ENUM('address', 'geolocation', 'image', 'phone_cross', 'host_identity', 'manual') NOT NULL,
        severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
        confidence_score DECIMAL(5, 2),
        details JSON,
        action_taken ENUM('flagged', 'warning', 'suspended', 'approved', 'pending_review') DEFAULT 'pending_review',
        admin_notes TEXT,
        reviewed_by INT NULL,
        review_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
        FOREIGN KEY (duplicate_unit_id) REFERENCES units(unit_id) ON DELETE SET NULL,
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX (unit_id),
        INDEX (detection_type),
        INDEX (severity),
        INDEX (action_taken),
        INDEX (created_at)
    )",
    
    // Table 6: Address Verification Records - detailed address comparisons
    "address_verification" => "CREATE TABLE IF NOT EXISTS address_verification (
        address_id INT PRIMARY KEY AUTO_INCREMENT,
        unit_id INT NOT NULL,
        building_name VARCHAR(255),
        street_address VARCHAR(500),
        unit_number VARCHAR(50),
        city VARCHAR(100),
        postal_code VARCHAR(20),
        full_address VARCHAR(1000),
        address_hash VARCHAR(128),
        normalized_address VARCHAR(1000),
        duplicate_count INT DEFAULT 0,
        matching_units JSON,
        verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
        INDEX (unit_id),
        INDEX (address_hash),
        INDEX (normalized_address)
    )",
    
    // Table 7: Phone/Email Cross-Check Records
    "host_contact_verification" => "CREATE TABLE IF NOT EXISTS host_contact_verification (
        contact_id INT PRIMARY KEY AUTO_INCREMENT,
        host_id INT NOT NULL,
        phone_number VARCHAR(20),
        phone_hash VARCHAR(255),
        email VARCHAR(100),
        email_hash VARCHAR(255),
        payout_account_id INT,
        linked_hosts JSON,
        duplicate_listings JSON,
        verification_level INT DEFAULT 0,
        flagged_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX (host_id),
        INDEX (phone_hash),
        INDEX (email_hash),
        UNIQUE KEY unique_host_contact (host_id)
    )",
    
    // Table 8: Suspicious Listings Queue - for admin manual review
    "suspicious_listings_queue" => "CREATE TABLE IF NOT EXISTS suspicious_listings_queue (
        queue_id INT PRIMARY KEY AUTO_INCREMENT,
        unit_id INT NOT NULL,
        host_id INT NOT NULL,
        reason TEXT,
        overall_risk_score DECIMAL(5, 2),
        address_risk DECIMAL(5, 2) DEFAULT 0,
        location_risk DECIMAL(5, 2) DEFAULT 0,
        image_risk DECIMAL(5, 2) DEFAULT 0,
        contact_risk DECIMAL(5, 2) DEFAULT 0,
        identity_risk DECIMAL(5, 2) DEFAULT 0,
        status ENUM('pending', 'under_review', 'approved', 'rejected', 'resolved') DEFAULT 'pending',
        assigned_to INT NULL,
        notes TEXT,
        comparison_data JSON,
        verification_attempts INT DEFAULT 0,
        manual_review_requested BOOLEAN DEFAULT FALSE,
        video_call_scheduled TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
        FOREIGN KEY (host_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX (unit_id),
        INDEX (host_id),
        INDEX (status),
        INDEX (overall_risk_score),
        INDEX (created_at)
    )"
];

// Execute table creation
foreach ($tables as $tableName => $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color: green;'>✓ Table '$tableName' created successfully</p>";
    } else {
        if (strpos($conn->error, 'already exists') !== false) {
            echo "<p style='color: blue;'>ℹ Table '$tableName' already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error creating table '$tableName': " . htmlspecialchars($conn->error) . "</p>";
        }
    }
}

echo "<hr>";
echo "<h3>✓ Duplicate Detection System Database Migration Complete!</h3>";
echo "<p>The following features are now available:</p>";
echo "<ul>";
echo "<li>✅ Address Verification Check (exact address matching)</li>";
echo "<li>✅ Map Pin + Geolocation Validation (GPS coordinates)</li>";
echo "<li>✅ Image Fingerprinting / Similarity Check (perceptual hashing)</li>";
echo "<li>✅ Phone Number + Host Account Cross-Check</li>";
echo "<li>✅ Host Identity Verification (ID, Face, Profile)</li>";
echo "<li>✅ Manual Review System (Suspicious Listings Queue)</li>";
echo "</ul>";
echo "<p><a href='../admin/admin_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Dashboard</a></p>";
?>
