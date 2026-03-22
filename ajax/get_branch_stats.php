<?php
include_once '../config/db.php';
include_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['branch_id'])) {
    $branch_id = (int)$_POST['branch_id'];
    
    $stats = getBranchStatistics($branch_id);
    
    if ($stats) {
        echo json_encode(['success' => true, 'data' => $stats]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Branch not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>