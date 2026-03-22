<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

// This would be implemented based on your export library (PhpSpreadsheet for Excel, TCPDF for PDF)
// For now, here's a basic CSV export example:

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=payments_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Headers
fputcsv($output, [
    'Payment ID',
    'Reservation ID', 
    'Renter Name',
    'Renter Email',
    'Property Name',
    'Host Name',
    'Branch',
    'Amount',
    'Payment Method',
    'Status',
    'Payment Date',
    'Transaction Reference'
]);

// Get data with same filters
$filters = [
    'start_date' => $_POST['export_start_date'] ?? '',
    'end_date' => $_POST['export_end_date'] ?? ''
];

try {
    $query = "SELECT p.*, r.reservation_id, u.username as renter_name, u.email as renter_email,
                     prop.property_name, h.username as host_name, b.branch_name
              FROM payments p
              INNER JOIN reservations r ON p.reservation_id = r.reservation_id
              INNER JOIN users u ON r.renter_id = u.user_id
              INNER JOIN properties prop ON r.property_id = prop.property_id
              INNER JOIN users h ON prop.host_id = h.user_id
              INNER JOIN branches b ON prop.branch_id = b.branch_id
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(p.payment_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(p.payment_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $query .= " ORDER BY p.payment_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['payment_id'],
            $row['reservation_id'],
            $row['renter_name'],
            $row['renter_email'],
            $row['property_name'],
            $row['host_name'],
            $row['branch_name'],
            $row['amount'],
            $row['payment_method'],
            $row['payment_status'],
            $row['payment_date'],
            $row['transaction_reference'] ?? ''
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    fputcsv($output, ['Error', 'Failed to generate export']);
}

fclose($output);
exit;