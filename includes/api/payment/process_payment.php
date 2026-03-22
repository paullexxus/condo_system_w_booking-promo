<?php
/**
 * Payment API Endpoint - Real PayMongo Processing
 */

session_start();
require_once '../../../config/db.php';
require_once '../../../config/paymongo.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/email_integration.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create_source':
            createSource();
            break;
        case 'process_payment':
            processPaymentWithPayMongo();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Lumikha ng payment source
 */
function createSource() {
    $amount = (int)$_POST['amount'];
    $type = $_POST['payment_method'];
    $reservation_id = $_POST['reservation_id'] ?? null;
    
    $success_url = SITE_URL . '/includes/api/payment/callback.php?status=success&res=' . $reservation_id;
    $failed_url = SITE_URL . '/includes/api/payment/callback.php?status=failed&res=' . $reservation_id;
    
    $response = createPaymongoSource($type, [
        'amount' => $amount,
        'success_url' => $success_url,
        'failed_url' => $failed_url
    ]);
    
    if (!isset($response['data']['attributes']['checkout_url'])) {
        throw new Exception('Failed to create payment source');
    }
    
    $source_id = $response['data']['id'];
    $checkout_url = $response['data']['attributes']['checkout_url'];
    
    $sql = "INSERT INTO payment_sources (user_id, reservation_id, source_id_paymongo, payment_method, amount, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')";
    execute_query($sql, [$_SESSION['user_id'], $reservation_id, $source_id, $type, $amount]);
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkout_url,
        'source_id' => $source_id
    ]);
}

/**
 * I-process ang payment
 */
function processPaymentWithPayMongo() {
    global $conn;
    
    $source_id = $_POST['source_id'];
    $amount = (int)$_POST['amount'];
    $reservation_id = $_POST['reservation_id'] ?? null;
    
    $response = createPaymongoPayment($source_id, [
        'amount' => $amount,
        'description' => 'BookIT Reservation #' . $reservation_id
    ]);
    
    if (!isset($response['data']['id'])) {
        throw new Exception('Payment processing failed');
    }
    
    $payment_id = $response['data']['id'];
    $payment_status = $response['data']['attributes']['status'];
    
    $sql = "INSERT INTO payments (reservation_id, user_id, amount, payment_method, payment_status, transaction_reference) 
            VALUES (?, ?, ?, 'paymongo', ?, ?)";
    
    execute_query($sql, [$reservation_id, $_SESSION['user_id'], $amount, $payment_status, $payment_id]);
    
    // If payment successful
    if ($payment_status === 'paid') {
        $res_sql = "UPDATE reservations SET payment_status = 'paid', status = 'confirmed' WHERE reservation_id = ? AND user_id = ?";
        execute_query($res_sql, [$reservation_id, $_SESSION['user_id']]);
        
        // Send confirmation email
        $reservation = get_single_result(
            "SELECT r.*, u.unit_number, b.branch_name FROM reservations r 
             JOIN units u ON r.unit_id = u.unit_id 
             JOIN branches b ON r.branch_id = b.branch_id 
             WHERE r.reservation_id = ?",
            [$reservation_id]
        );
        
        if ($reservation) {
            $user = get_single_result("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
            sendReservationConfirmationEmail($user['email'], $user['full_name'], $reservation);
            
            sendNotification($_SESSION['user_id'], 'Payment Confirmed', 
                'Your payment for Unit ' . $reservation['unit_number'] . ' has been received!', 
                'payment', 'email');
        }
    }
    
    echo json_encode([
        'success' => $payment_status === 'paid',
        'payment_id' => $payment_id,
        'status' => $payment_status
    ]);
}

?>


header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create_source':
            // Create payment source (GCash, Grab Pay, etc.)
            createSource();
            break;
            
        case 'process_payment':
            // Process the actual payment
            processPaymentWithPayMongo();
            break;
            
        case 'verify_payment':
            // Verify payment status from PayMongo
            verifyPaymentStatus();
            break;
            
        case 'handle_webhook':
            // Handle PayMongo webhook for payment confirmation
            handlePaymongoWebhook();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Lumikha ng payment source
 */
function createSource() {
    $amount = (int)$_POST['amount'];
    $type = $_POST['payment_method']; // 'gcash', 'grab_pay', 'card'
    $reservation_id = $_POST['reservation_id'] ?? null;
    $amenity_booking_id = $_POST['amenity_booking_id'] ?? null;
    
    // Build redirect URLs
    $success_url = SITE_URL . '/includes/api/payment/handle_payment.php?status=success&reservation=' . $reservation_id . '&amenity=' . $amenity_booking_id;
    $failed_url = SITE_URL . '/includes/api/payment/handle_payment.php?status=failed&reservation=' . $reservation_id . '&amenity=' . $amenity_booking_id;
    
    // Create source sa PayMongo
    $response = createPaymongoSource($type, [
        'amount' => $amount,
        'success_url' => $success_url,
        'failed_url' => $failed_url
    ]);
    
    if (!isset($response['data']['attributes']['checkout_url'])) {
        throw new Exception('Failed to create payment source');
    }
    
    // Save source sa database para sa tracking
    $source_id = $response['data']['id'];
    $checkout_url = $response['data']['attributes']['checkout_url'];
    
    $sql = "INSERT INTO payment_sources (user_id, reservation_id, amenity_booking_id, source_id, payment_method, amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')";
    execute_query($sql, [$_SESSION['user_id'], $reservation_id, $amenity_booking_id, $source_id, $type, $amount]);
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkout_url,
        'source_id' => $source_id
    ]);
}

/**
 * I-process ang payment gamit ang source
 */
function processPaymentWithPayMongo() {
    $source_id = $_POST['source_id'];
    $amount = (int)$_POST['amount'];
    $reservation_id = $_POST['reservation_id'] ?? null;
    $amenity_booking_id = $_POST['amenity_booking_id'] ?? null;
    
    // Create payment sa PayMongo
    $response = createPaymongoPayment($source_id, [
        'amount' => $amount,
        'description' => 'BookIT ' . ($reservation_id ? 'Reservation #' . $reservation_id : 'Amenity Booking #' . $amenity_booking_id)
    ]);
    
    if (!isset($response['data']['id'])) {
        throw new Exception('Payment processing failed');
    }
    
    $payment_id = $response['data']['id'];
    $payment_status = $response['data']['attributes']['status'];
    
    // Save payment sa database
    $sql = "INSERT INTO payments (reservation_id, amenity_booking_id, user_id, amount, payment_method, payment_status, transaction_reference) 
            VALUES (?, ?, ?, ?, 'paymongo', ?, ?)";
    
    $db_payment_id = null;
    if (execute_query($sql, [$reservation_id, $amenity_booking_id, $_SESSION['user_id'], $amount, $payment_status, $payment_id])) {
        global $conn;
        $db_payment_id = $conn->insert_id;
    }
    
    // If payment successful, update reservation
    if ($payment_status === 'paid') {
        if ($reservation_id) {
            $res_sql = "UPDATE reservations SET payment_status = 'paid', status = 'confirmed' WHERE reservation_id = ? AND user_id = ?";
            execute_query($res_sql, [$reservation_id, $_SESSION['user_id']]);
            
            // Send confirmation email
            $reservation = get_single_result("SELECT r.*, u.unit_number, b.branch_name FROM reservations r 
                                            JOIN units u ON r.unit_id = u.unit_id 
                                            JOIN branches b ON r.branch_id = b.branch_id 
                                            WHERE r.reservation_id = ?", [$reservation_id]);
            
            if ($reservation) {
                $user = get_single_result("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
                sendReservationConfirmationEmail($user['email'], $user['full_name'], $reservation);
                
                sendNotification($_SESSION['user_id'], 'Payment Confirmed', 
                    'Your payment for Unit ' . $reservation['unit_number'] . ' has been received. Reservation confirmed!', 
                    'payment', 'email');
            }
        } elseif ($amenity_booking_id) {
            $amen_sql = "UPDATE amenity_bookings SET status = 'confirmed' WHERE booking_id = ? AND user_id = ?";
            execute_query($amen_sql, [$amenity_booking_id, $_SESSION['user_id']]);
            
            sendNotification($_SESSION['user_id'], 'Amenity Booking Confirmed', 
                'Your payment for amenity booking has been received. Booking confirmed!', 
                'payment', 'email');
        }
    }
    
    echo json_encode([
        'success' => $payment_status === 'paid',
        'payment_id' => $payment_id,
        'status' => $payment_status,
        'db_payment_id' => $db_payment_id,
        'message' => $payment_status === 'paid' ? 'Payment successful!' : 'Payment pending'
    ]);
}

/**
 * I-verify ang payment status
 */
function verifyPaymentStatus() {
    $payment_id = $_GET['payment_id'];
    
    $response = getPaymongoPayment($payment_id);
    
    if (!isset($response['data'])) {
        throw new Exception('Payment not found');
    }
    
    $status = $response['data']['attributes']['status'];
    
    // Update local database
    $sql = "UPDATE payments SET payment_status = ? WHERE transaction_reference = ?";
    execute_query($sql, [$status, $payment_id]);
    
    echo json_encode([
        'success' => true,
        'status' => $status,
        'data' => $response['data']['attributes']
    ]);
}

/**
 * Handle PayMongo webhook
 * This is called by PayMongo when payment status changes
 */
function handlePaymongoWebhook() {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($payload['data']['attributes']['data']['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false]);
        return;
    }
    
    $payment_id = $payload['data']['attributes']['data']['id'];
    $payment_status = $payload['data']['attributes']['data']['attributes']['status'];
    
    // Update payment in database
    $sql = "UPDATE payments SET payment_status = ? WHERE transaction_reference = ?";
    execute_query($sql, [$payment_status, $payment_id]);
    
    // Get payment details
    $payment = get_single_result("SELECT * FROM payments WHERE transaction_reference = ?", [$payment_id]);
    
    if ($payment && $payment_status === 'paid') {
        // Update reservation if it's a reservation payment
        if ($payment['reservation_id']) {
            $res_sql = "UPDATE reservations SET payment_status = 'paid', status = 'confirmed' WHERE reservation_id = ?";
            execute_query($res_sql, [$payment['reservation_id']]);
            
            sendNotification($payment['user_id'], 'Payment Confirmed via Webhook', 
                'Your payment has been confirmed.', 'payment', 'system');
        }
    }
    
    http_response_code(200);
    echo json_encode(['success' => true]);
}

?>
