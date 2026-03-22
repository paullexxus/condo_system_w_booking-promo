<?php
// Test branch addition

include 'includes/session.php';
include 'includes/functions.php';
include_once 'config/db.php';

// Don't require admin check for testing
// checkRole(['admin']);

echo "=== Testing Branch Addition ===\n\n";

// Check if branches table has data
$result = $conn->query("SELECT COUNT(*) as count FROM branches");
$row = $result->fetch_assoc();
echo "Current branch count: " . $row['count'] . "\n";

// Add a test branch
echo "\nAdding test branch...\n";
$branch_name = "Test Branch - " . date('Y-m-d H:i:s');
$address = "123 Test Street";
$city = "Test City";
$contact_number = "555-1234";
$email = "test@branch.com";

$sql = "INSERT INTO branches (branch_name, address, city, contact_number, email, host_id, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, 1)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Prepare failed: " . $conn->error . "\n";
} else {
    $stmt->bind_param("sssssi", $branch_name, $address, $city, $contact_number, $email, $null_host);
    $null_host = null;
    
    if ($stmt->execute()) {
        echo "✓ Branch added successfully!\n";
        echo "New Branch ID: " . $conn->insert_id . "\n";
    } else {
        echo "✗ Failed to add branch: " . $stmt->error . "\n";
    }
    $stmt->close();
}

// Verify the branch was added
echo "\nVerifying branch addition...\n";
$result = $conn->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_id DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    echo "Latest branch:\n";
    echo "  ID: " . $row['branch_id'] . "\n";
    echo "  Name: " . $row['branch_name'] . "\n";
    echo "  City: " . $row['city'] . "\n";
    echo "  Status: " . ($row['is_active'] ? "Active" : "Inactive") . "\n";
} else {
    echo "No active branches found!\n";
}

echo "\n=== Test Complete ===\n";
?>
