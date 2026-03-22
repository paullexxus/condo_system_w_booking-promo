<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

try {
    $query = "SELECT 
        r.reservation_id as id,
        CONCAT(u.unit_name, ' - ', usr.full_name) as title,
        r.check_in_date as start,
        r.check_out_date as end,
        r.status,
        CASE 
            WHEN r.status = 'confirmed' THEN '#28a745'
            WHEN r.status = 'checked-in' THEN '#ffc107'
            WHEN r.status = 'checked-out' THEN '#17a2b8'
            WHEN r.status = 'pending' THEN '#6c757d'
            ELSE '#dc3545'
        END as color
    FROM reservations r
    LEFT JOIN units u ON r.unit_id = u.unit_id
    LEFT JOIN users usr ON r.user_id = usr.user_id
    WHERE 1=1";
    
    $params = [];
    
    if ($user_role === 'host') {
        $query .= " AND u.host_id = ?";
        $params[] = $user_id;
    }
    
    $reservations = get_multiple_results($query, $params);
    
    echo json_encode($reservations);
} catch (Exception $e) {
    echo json_encode([]);
}
?>