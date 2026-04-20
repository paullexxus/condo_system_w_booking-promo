<?php
// ajax/messages_api.php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/auth.php';
include '../includes/encryption.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Bulletproof #5: Global Throttling
if (!throttleRequest('messaging_api', 60, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please slow down.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'fetch_prohibited_words') {
    $words = get_multiple_results("SELECT word, severity FROM prohibited_words");
    echo json_encode(['success' => true, 'words' => $words]);
    exit;
}

if ($action === 'search_messages') {
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    
    // Bulletproof #10: Search Hardening
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query too short (min 2 chars)']);
        exit;
    }
    
    if (!throttleRequest('search_messages', 10, 60)) {
        echo json_encode(['success' => false, 'message' => 'Search rate limit exceeded.']);
        exit;
    }

    // Escape wildcards to prevent % abuse
    $escaped_query = str_replace(['%', '_'], ['\%', '\_'], $query);
    $like_pattern = "%" . $escaped_query . "%";

    // Fallback search logic: Standard LIKE
    $sql = "SELECT m.*, u.full_name as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.user_id 
            WHERE (m.sender_id = ? OR m.receiver_id = ?) 
            AND m.message LIKE ? 
            AND m.is_deleted_everyone = 0 
            ORDER BY m.sent_at DESC LIMIT 50";
            
    $results = get_multiple_results($sql, [$user_id, $user_id, $like_pattern]);
    
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

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

            $is_sender = ($msg['sender_id'] == $user_id);
            $deleted_by_sender = isset($msg['deleted_by_sender']) ? $msg['deleted_by_sender'] : 0;
            $deleted_by_receiver = isset($msg['deleted_by_receiver']) ? $msg['deleted_by_receiver'] : 0;
            $deleted_admin = isset($msg['deleted_by_admin']) ? $msg['deleted_by_admin'] : 0;
            $deleted_everyone = isset($msg['is_deleted_everyone']) ? $msg['is_deleted_everyone'] : 0;

            if ($deleted_admin) {
                $deleted_everyone = 1;
            }

            // Hide from specific user if they deleted it for themselves
            if ($is_sender && $deleted_by_sender) continue;
            if (!$is_sender && $deleted_by_receiver) continue;

            $message_content = $plaintext;
            $file_path = isset($msg['file_path']) ? $msg['file_path'] : null;
            $file_type = isset($msg['message_type']) ? $msg['message_type'] : null;
            $original_file_name = isset($msg['file_name']) ? $msg['file_name'] : null;
            $is_deleted = false;

            if ($deleted_everyone) {
                $message_content = '';
                $file_path = null;
                $file_type = null;
                $original_file_name = null;
                $is_deleted = true;
            }

            $formatted_messages[] = [
                'message_id' => $msg['message_id'],
                'sender_id' => $msg['sender_id'],
                'receiver_id' => $msg['receiver_id'],
                'message' => $message_content,
                'file_path' => $file_path,
                'file_type' => $file_type,
                'original_file_name' => $original_file_name,
                'is_deleted' => $is_deleted,
                'sent_at' => date('M d, g:i A', strtotime($msg['sent_at'])),
                'is_mine' => $is_sender,
                'can_delete_everyone' => $is_sender && ((time() - strtotime($msg['sent_at'])) <= (15 * 60))
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

    $has_attachment = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;

    if (!$receiver_id || (empty($message) && !$has_attachment)) {
        echo json_encode(['success' => false, 'message' => 'Invalid message data.']);
        exit;
    }

    // Bulletproof #4: Idempotency
    $idem_key = $_POST['idempotency_key'] ?? '';
    if (!isIdempotent($idem_key, 'send_message')) {
        echo json_encode(['success' => true, 'message' => 'Message already sent (Idempotent)']);
        exit;
    }

    // Messaging Abuse Protection (Phase 4.3 Hardening)
    $abuse_check = checkAbuse('messaging');
    if (!$abuse_check['allowed']) {
        echo json_encode(['success' => false, 'message' => $abuse_check['message']]);
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

    // 3. Strict Profanity Filter (Database-driven) & Length Validation
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long. Limit is 2000 characters.']);
        exit;
    }

    if (!empty($message)) {
        $prohibited_words = get_multiple_results("SELECT word, severity FROM prohibited_words");
        $lower_msg = strtolower($message);
        
        // Normalize against bypassing (e.g., $ -> s, @ -> a)
        $normalized_msg = str_replace(['@', '$', '1', '!', '0', '3', '4', '+'], ['a', 's', 'i', 'i', 'o', 'e', 'a', 't'], $lower_msg);
        $no_space_msg = str_replace([' ', '.', '-', '_'], '', $normalized_msg);

        if ($prohibited_words && count($prohibited_words) > 0) {
            foreach ($prohibited_words as $p_word) {
                $word = strtolower($p_word['word']);
                $severity = $p_word['severity'];
                
                // Match exact word boundaries for mild ones, and substrings for severe ones potentially
                $matched = false;
                
                if (preg_match("/\b" . preg_quote($word, "/") . "\b/i", $message)) {
                    $matched = true;
                } elseif (strpos($normalized_msg, $word) !== false || strpos($no_space_msg, $word) !== false) {
                    $matched = true;
                }

                if ($matched) {
                    if ($severity === 'severe') {
                        echo json_encode(['success' => false, 'message' => 'Your message contains highly inappropriate language and cannot be sent.']);
                        exit;
                    } else if ($severity === 'mild') {
                        // Mask the mild word instead of breaking
                        $message = str_ireplace($word, str_repeat('*', strlen($word)), $message);
                    }
                }
            }
        }
    }

    // 4. Handle Attachments
    $file_path = null;
    $message_type = 'text';
    $original_name = null;

    if ($has_attachment) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'docx'];
        $filename = $_FILES['attachment']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filesize = $_FILES['attachment']['size'];
        
        if ($filesize > 25 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 25MB limit.']);
            exit;
        }
        
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed: images (jpg, jpeg, png, gif) and docx.']);
            exit;
        }

        // Bulletproof #2: Magic Byte Validation
        if (!validateFileSignature($_FILES['attachment']['tmp_name'], $ext)) {
            logSystemError("File Signature Mismatch Attempted", ["filename" => $filename, "ext" => $ext, "ip" => $_SERVER['REMOTE_ADDR']]);
            echo json_encode(['success' => false, 'message' => 'Security Error: File signature does not match extension.']);
            exit;
        }
        
        $upload_dir = '../uploads/chat_attachments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $new_filename = uniqid('msg_') . '.' . $ext;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_filename)) {
            $file_path = 'uploads/chat_attachments/' . $new_filename;
            $message_type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file';
            $original_name = $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload attachment.']);
            exit;
        }
    }

    // Encrypt the message before saving
    $encrypted = empty($message) ? '' : encrypt_message($message);

    $sql = "INSERT INTO messages (sender_id, receiver_id, booking_id, message, is_encrypted, file_path, message_type, file_name) 
            VALUES (?, ?, ?, ?, 1, ?, ?, ?)";
    
    if (execute_query($sql, [$user_id, $receiver_id, $booking_id, $encrypted, $file_path, $message_type, $original_name])) {
        // Automatically create a message_log entry
        global $conn;
        $message_id = $conn->insert_id;
        execute_query("INSERT INTO message_logs (message_id, action) VALUES (?, 'sent')", [$message_id]);

        // Send a notification to the receiver
        if (function_exists('sendNotification')) {
            $sender_name = $_SESSION['fullname'] ?? 'Someone';
            $notif_title = $has_attachment ? "New File from " . $sender_name : "New Message from " . $sender_name;
            $notif_msg = "You have received a secure message. Check your inbox.";
            sendNotification($receiver_id, $notif_title, $notif_msg, 'system', 'message', null);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

if ($action === 'delete_message') {
    $message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $delete_type = isset($_POST['delete_type']) ? $_POST['delete_type'] : '';

    if (!$message_id || !in_array($delete_type, ['me', 'everyone'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    $sql = "SELECT sender_id, receiver_id, sent_at FROM messages WHERE message_id = ?";
    $msg = get_single_result($sql, [$message_id]);

    if (!$msg) {
        echo json_encode(['success' => false, 'message' => 'Message not found.']);
        exit;
    }

    $is_sender = ($msg['sender_id'] == $user_id);
    $is_receiver = ($msg['receiver_id'] == $user_id);

    if (!$is_sender && !$is_receiver) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if ($delete_type === 'me') {
        if ($is_sender) {
            execute_query("UPDATE messages SET deleted_by_sender = 1 WHERE message_id = ?", [$message_id]);
        } else {
            execute_query("UPDATE messages SET deleted_by_receiver = 1 WHERE message_id = ?", [$message_id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($delete_type === 'everyone') {
        if (!$is_sender) {
            echo json_encode(['success' => false, 'message' => 'Only the sender can delete for everyone.']);
            exit;
        }

        $sent_time = strtotime($msg['sent_at']);
        if ((time() - $sent_time) > (15 * 60)) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete for everyone after 15 minutes.']);
            exit;
        }

        execute_query("UPDATE messages SET is_deleted_everyone = 1 WHERE message_id = ?", [$message_id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
