<?php
// Test script para sa database at validation
require_once 'includes/components/database.php';
require_once 'includes/components/validation.php';

echo "<h2>Database Connection Test</h2>";

try {
    $db = DatabaseHelper::getInstance();
    
    // Test simple query
    $result = $db->getOne("SELECT NOW() as current_time");
    if ($result) {
        echo "<div style='color: green; padding: 10px; background: #e8f5e9; margin: 10px 0; border-radius: 4px;'>";
        echo "✓ Database connection successful!<br>";
        echo "Current time from DB: <strong>" . $result['current_time'] . "</strong>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: white; padding: 10px; background: #ffebee; margin: 10px 0; border-radius: 4px;'>";
    echo "✗ Database connection failed: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<h2>Validation Functions Test</h2>";

// Test unit number validation
$test_cases = [
    'A-101' => true,    // Valid
    'B101' => true,     // Valid
    'ABC-123' => false, // Invalid (too long)
    '123' => true,      // Valid
    'A/101' => false    // Invalid (special character)
];

echo "<h3>Unit Number Validation Test:</h3>";
foreach ($test_cases as $unit_number => $expected) {
    $result = validate_unit_number($unit_number);
    $status = $result === $expected ? '✓' : '✗';
    $color = $result === $expected ? 'green' : 'red';
    echo "<div style='color: $color'>$status Testing '$unit_number': " . 
         ($result ? 'Valid' : 'Invalid') . 
         " (Expected: " . ($expected ? 'Valid' : 'Invalid') . ")</div>";
}

// Test price validation
echo "<h3>Price Validation Test:</h3>";
$test_prices = [
    '1000' => true,
    '0' => false,
    '-100' => false,
    '1500.50' => true,
    'abc' => false
];

foreach ($test_prices as $price => $expected) {
    $result = validate_price($price);
    $status = $result === $expected ? '✓' : '✗';
    $color = $result === $expected ? 'green' : 'red';
    echo "<div style='color: $color'>$status Testing '$price': " . 
         ($result ? 'Valid' : 'Invalid') . 
         " (Expected: " . ($expected ? 'Valid' : 'Invalid') . ")</div>";
}

// Test form validation
echo "<h3>Form Validation Test:</h3>";
$test_form_data = [
    'unit_number' => 'A-101',
    'unit_type' => 'Studio',
    'branch_id' => '1',
    'price' => '1500.50',
    'max_occupancy' => '2'
];

$errors = validate_unit_form($test_form_data);
if (empty($errors)) {
    echo "<div style='color: green'>✓ Form validation passed</div>";
} else {
    echo "<div style='color: red'>✗ Form validation failed:</div>";
    foreach ($errors as $error) {
        echo "<div style='color: red'>- $error</div>";
    }
}

// Test sanitization
echo "<h3>Input Sanitization Test:</h3>";
$test_input = [
    'unit_number' => ' A-101 ',  // with spaces
    'description' => '<script>alert("xss")</script>Nice unit', // with XSS
    'price' => '1,500.50',  // with comma
];

$cleaned = sanitize_unit_input($test_input);
echo "<pre>";
echo "Original input:\n";
print_r($test_input);
echo "\nCleaned input:\n";
print_r($cleaned);
echo "</pre>";
?>