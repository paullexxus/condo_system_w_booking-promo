<?php
require_once '../../includes/session.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reservation_id'])) {
    $reservation_id = (int)$_GET['reservation_id'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT status FROM reservations WHERE reservation_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => $row['status']]);
    } else {
        echo json_encode(['error' => 'Not found']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>
