<?php
// update_unit.php
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unit_id'])) {
    $unit_id = (int)$_POST['unit_id'];
    $unit_name = sanitize_input($_POST['unit_name'] ?? '');
    $unit_number = sanitize_input($_POST['unit_number']);
    $unit_type = sanitize_input($_POST['unit_type']);
    $branch_id = (int)$_POST['branch_id'];
    $pricing_type = sanitize_input($_POST['pricing_type'] ?? 'nightly');
    $price_per_night = (float)($_POST['price_per_night'] ?? 0);
    $price_per_month = (float)($_POST['price_per_month'] ?? 0);
    $floor_number = !empty($_POST['floor_number']) ? (int)$_POST['floor_number'] : null;
    $max_occupancy = (int)$_POST['max_occupancy'];
    $security_deposit = (float)$_POST['security_deposit'];
    $description = sanitize_input($_POST['description']);
    $sqm = isset($_POST['sqm']) && $_POST['sqm'] !== '' ? (float)$_POST['sqm'] : null;
    $bed_type = sanitize_input($_POST['bed_type'] ?? '');
    $num_beds = isset($_POST['num_beds']) && $_POST['num_beds'] !== '' ? (int)$_POST['num_beds'] : 1;
    $num_bathrooms = isset($_POST['num_bathrooms']) && $_POST['num_bathrooms'] !== '' ? (int)$_POST['num_bathrooms'] : 1;
    
    // Check if unit number already exists in the same branch (excluding current unit)
    $check_sql = "SELECT unit_id FROM units WHERE unit_number = ? AND branch_id = ? AND unit_id != ?";
    $check_result = get_single_result($check_sql, [$unit_number, $branch_id, $unit_id]);
    
    if ($check_result) {
        echo json_encode([
            'success' => false,
            'message' => 'Unit number already exists in this branch!',
            'unit_id' => $unit_id
        ]);
        exit;
    }
    
    // Validate inputs
    if ($pricing_type === 'nightly' && $price_per_night <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Price per night must be greater than 0!',
            'unit_id' => $unit_id
        ]);
        exit;
    }
    if ($pricing_type === 'monthly' && $price_per_month <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Price per month must be greater than 0!',
            'unit_id' => $unit_id
        ]);
        exit;
    }
    
    if ($max_occupancy <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Max occupancy must be at least 1!',
            'unit_id' => $unit_id
        ]);
        exit;
    }
    
    if (empty($_POST['unit_number']) || empty($_POST['unit_type'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Fill all required fields.',
            'unit_id' => $unit_id
        ]);
        exit;
    }
    
    // Fetch original unit data to compare
    $old_unit = get_single_result("SELECT * FROM units WHERE unit_id = ?", [$unit_id]);
    
    // Do not touch host_id here — writing 0 or a branch default can unlink the listing from the real host
    // (host dashboard uses WHERE host_id = session). Ownership changes should be a separate admin action.
    $notify_host_id = (int) ($old_unit['host_id'] ?? 0);
    if ($notify_host_id <= 0) {
        $br_host = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$branch_id]);
        $notify_host_id = (int) ($br_host['host_id'] ?? 0);
    }

    $sql = "UPDATE units SET 
            unit_name = ?,
            unit_number = ?, 
            unit_type = ?, 
            branch_id = ?, 
            pricing_type = ?, 
            price_per_night = ?, 
            price_per_month = ?, 
            floor_number = ?, 
            max_occupancy = ?, 
            security_deposit = ?, 
            description = ?,
            sqm = ?,
            bed_type = ?,
            num_beds = ?,
            num_bathrooms = ?
            WHERE unit_id = ?";
    
    if (execute_query($sql, [$unit_name, $unit_number, $unit_type, $branch_id, $pricing_type, $price_per_night, $price_per_month, $floor_number, $max_occupancy, $security_deposit, $description, $sqm, $bed_type, $num_beds, $num_bathrooms, $unit_id])) {
        
        // Track changes
        $changes = [];
        if ($old_unit) {
            if ($old_unit['pricing_type'] !== $pricing_type) $changes[] = "Pricing type changed from '{$old_unit['pricing_type']}' to '{$pricing_type}'";
            if ((float)$old_unit['price_per_night'] !== $price_per_night) $changes[] = "Price per night changed from ₱" . (float)$old_unit['price_per_night'] . " to ₱{$price_per_night}";
            if ((float)$old_unit['price_per_month'] !== $price_per_month) $changes[] = "Price per month changed from ₱" . (float)$old_unit['price_per_month'] . " to ₱{$price_per_month}";
            if ((int)$old_unit['max_occupancy'] !== $max_occupancy) $changes[] = "Max occupancy changed from {$old_unit['max_occupancy']} to {$max_occupancy}";
            if ($old_unit['unit_name'] !== $unit_name) $changes[] = "Unit name changed from '{$old_unit['unit_name']}' to '{$unit_name}'";
            if ($old_unit['unit_number'] !== $unit_number) $changes[] = "Unit number changed from '{$old_unit['unit_number']}' to '{$unit_number}'";
            if ($old_unit['unit_type'] !== $unit_type) $changes[] = "Unit type changed from '{$old_unit['unit_type']}' to '{$unit_type}'";
        }
        
        $changes_str = !empty($changes) ? implode(', ', $changes) . '.' : 'General information was updated.';
        $host_note = sanitize_input($_POST['host_message'] ?? '');
        $admin_detail = trim($changes_str . ($host_note !== '' ? "\n\nMessage from admin:\n" . $host_note : ''));

        if ($notify_host_id > 0) {
            sendNotification(
                $notify_host_id,
                'Unit updated by admin',
                'An administrator updated your listing "' . $unit_name . '" (unit #' . $unit_number . '). Open Host → Unit Management to review.',
                'system',
                'system',
                $admin_detail !== '' ? $admin_detail : null
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Unit updated successfully!',
            'unit_id' => $unit_id
        ]);
    } else {
        global $conn;
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update unit! ' . ($conn ? $conn->error : 'No DB connection.'),
            'unit_id' => $unit_id
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request!'
    ]);
}
?>