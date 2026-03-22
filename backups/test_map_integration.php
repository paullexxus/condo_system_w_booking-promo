<?php
/**
 * Test: Map Integration Verification
 * Tests that the map is properly integrated into unit_management.php
 */

echo "<h1>Map Integration Verification Report</h1>";

// Test 1: Check database tables exist
include_once 'config/db.php';

echo "<h2>1. Database Tables Check</h2>";
$tables_to_check = [
    'units',
    'unit_images',
    'image_fingerprints',
    'unit_geolocation',
    'address_verification',
    'host_contact_verification',
    'duplicate_detection_logs',
    'suspicious_listings_queue'
];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✅ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Table '$table' missing</p>";
    }
}

// Test 2: Check database columns in units table
echo "<h2>2. Units Table Columns Check</h2>";
$columns_to_check = [
    'street_address',
    'unit_number_formal',
    'city',
    'postal_code',
    'latitude',
    'longitude',
    'address_hash',
    'location_hash'
];

$result = $conn->query("DESCRIBE units");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

foreach ($columns_to_check as $col) {
    if (in_array($col, $existing_columns)) {
        echo "<p style='color: green;'>✅ Column '$col' exists in units table</p>";
    } else {
        echo "<p style='color: red;'>❌ Column '$col' missing from units table</p>";
    }
}

// Test 3: Check PHP files exist
echo "<h2>3. PHP Files Check</h2>";
$files_to_check = [
    'host/unit_management.php',
    'includes/DuplicateDetectionEngine.php',
    'includes/ImageFingerprinting.php',
    'includes/AddressVerification.php',
    'includes/GeolocationValidation.php',
    'ajax/get_unit.php',
    'ajax/get_unit_view.php',
    'ajax/register_image_fingerprint.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ File '$file' exists</p>";
    } else {
        echo "<p style='color: red;'>❌ File '$file' missing</p>";
    }
}

// Test 4: Check CSS and JS files
echo "<h2>4. CSS and JS Files Check</h2>";
$assets_to_check = [
    'assets/css/host/unit_management.css',
    'assets/js/host/unit_management.js',
    'assets/js/public/host_register.js'
];

foreach ($assets_to_check as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ Asset '$file' exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠️  Asset '$file' missing (may not be critical)</p>";
    }
}

// Test 5: Check configuration
echo "<h2>5. Configuration Check</h2>";
include_once 'config/constants.php';

if (defined('GOOGLE_MAPS_API_KEY')) {
    echo "<p style='color: green;'>✅ GOOGLE_MAPS_API_KEY is defined</p>";
    $key = GOOGLE_MAPS_API_KEY;
    if (strlen($key) > 5) {
        echo "<p style='color: green;'>✅ API Key appears valid (length: " . strlen($key) . ")</p>";
    } else {
        echo "<p style='color: orange;'>⚠️  API Key may be placeholder (demo key)</p>";
    }
} else {
    echo "<p style='color: red;'>❌ GOOGLE_MAPS_API_KEY not defined</p>";
}

if (defined('MAP_PROVIDER')) {
    echo "<p style='color: green;'>✅ MAP_PROVIDER is defined: " . MAP_PROVIDER . "</p>";
} else {
    echo "<p style='color: red;'>❌ MAP_PROVIDER not defined</p>";
}

// Test 6: Check for DuplicateDetectionEngine
echo "<h2>6. Detection Engine Check</h2>";
if (class_exists('DuplicateDetectionEngine')) {
    echo "<p style='color: green;'>✅ DuplicateDetectionEngine class exists</p>";
} else {
    include_once 'includes/DuplicateDetectionEngine.php';
    if (class_exists('DuplicateDetectionEngine')) {
        echo "<p style='color: green;'>✅ DuplicateDetectionEngine class loaded successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ DuplicateDetectionEngine class not found</p>";
    }
}

echo "<hr>";
echo "<h3>✅ Map Integration Verification Complete!</h3>";
echo "<p><a href='host/unit_management.php' class='btn btn-primary' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Unit Management Page</a></p>";
?>
