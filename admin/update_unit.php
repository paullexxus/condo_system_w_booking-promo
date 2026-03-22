<?php
// update_unit.php
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';
checkRole(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_unit'])) {
    $unit_id = (int)$_POST['unit_id'];
    $unit_number = sanitize_input($_POST['unit_number']);
    $unit_type = sanitize_input($_POST['unit_type']);
    $branch_id = (int)$_POST['branch_id'];
    $price = (float)$_POST['price'];
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
    if ($price <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Price must be greater than 0!',
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
        $_SESSION['error'] = "Fill all required fields.";
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    $sql = "UPDATE units SET 
            unit_number = ?, 
            unit_type = ?, 
            branch_id = ?, 
            monthly_rate = ?, 
            floor_number = ?, 
            max_occupancy = ?, 
            security_deposit = ?, 
            description = ?,
            sqm = ?,
            bed_type = ?,
            num_beds = ?,
            num_bathrooms = ?,
            updated_at = NOW()
            WHERE unit_id = ?";
    
    if (execute_query($sql, [$unit_number, $unit_type, $branch_id, $price, $floor_number, $max_occupancy, $security_deposit, $description, $sqm, $bed_type, $num_beds, $num_bathrooms, $unit_id])) {
        echo json_encode([
            'success' => true,
            'message' => 'Unit updated successfully!',
            'unit_id' => $unit_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update unit!',
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