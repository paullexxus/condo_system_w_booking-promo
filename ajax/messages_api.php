<?php
// ajax/messages_api.php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/auth.php';
include '../includes/encryption.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'fetch_messages') {
    $chat_with_id = isset($_GET['chat_with']) ? (int)$_GET['chat_with'] : 0;
    $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

    if (!$chat_with_id) {
        echo json_encode(['success' => false, 'message' => 'Missing chat partner ID']);
        exit;
    }

    $booking_condition = $booking_id ? " AND booking_id = " . $booking_id : "";

    $sql = "SELECT * FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)) 
               $booking_condition 
            ORDER BY sent_at ASC";

    $messages = get_multiple_results($sql, [$user_id, $chat_with_id, $chat_with_id, $user_id]);

    // Format & Decrypt messages for JSON
    $formatted_messages = [];
    if ($messages && count($messages) > 0) {
        foreach ($messages as $msg) {
            
            // Decrypt the message
            $plaintext = $msg['is_encrypted'] ? decrypt_message($msg['message']) : $msg['message'];
            
            // Mark as read if user is receiver and read_at is null
            if ($msg['receiver_id'] == $user_id && $msg['read_at'] === null) {
                execute_query("UPDATE messages SET read_at = CURRENT_TIMESTAMP WHERE message_id = ?", [$msg['message_id']]);
            }

            $formatted_messages[] = [
                'message_id' => $msg['message_id'],
                'sender_id' => $msg['sender_id'],
                'receiver_id' => $msg['receiver_id'],
                'message' => $plaintext,
                'sent_at' => date('M d, g:i A', strtotime($msg['sent_at'])),
                'is_mine' => $msg['sender_id'] == $user_id
            ];
        }
    }

    echo json_encode(['success' => true, 'messages' => $formatted_messages]);
    exit;
}

if ($action === 'send_message') {
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $booking_id = !empty($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;

    if (!$receiver_id || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Invalid message data.']);
        exit;
    }

    // 1. Check if user is blocked from messaging
    $sender_status = get_single_result("SELECT can_message, role FROM users WHERE user_id = ?", [$user_id]);
    if (!$sender_status || $sender_status['can_message'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Your account is currently restricted from sending messages.']);
        exit;
    }

    // 2. Daily Rate Limiter (Spam Protection) - Bypass for admins
    if ($sender_status['role'] !== 'admin') {
        $today_count = get_single_result("SELECT COUNT(*) as count FROM messages WHERE sender_id = ? AND DATE(sent_at) = CURDATE()", [$user_id])['count'];
        if ($today_count >= 50) {
            echo json_encode(['success' => false, 'message' => 'Daily message limit reached (50/day). Please try again tomorrow.']);
            exit;
        }
    }

    // 3. Profanity Filter
    $bad_words = ['fuck', 'shit', 'bitch', 'asshole', 'cunt', 'dick', 'pussy', 'bastard'];
    $lower_msg = strtolower($message);
    foreach ($bad_words as $word) {
        if (strpos($lower_msg, $word) !== false) {
            echo json_encode(['success' => false, 'message' => 'Your message contains inappropriate language and cannot be sent.']);
            exit;
        }
    }

    // Encrypt the message before saving
    $encrypted = encrypt_message($message);

    $sql = "INSERT INTO messages (sender_id, receiver_id, booking_id, message, is_encrypted) 
            VALUES (?, ?, ?, ?, 1)";
    
    if (execute_query($sql, [$user_id, $receiver_id, $booking_id, $encrypted])) {
        // Automatically create a message_log entry
        global $conn;
        $message_id = $conn->insert_id;
        execute_query("INSERT INTO message_logs (message_id, action) VALUES (?, 'sent')", [$message_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
