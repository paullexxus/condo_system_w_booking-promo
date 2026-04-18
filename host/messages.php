<?php
// host/messages.php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/encryption.php';

checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];

// Get all contacts (renters) who have reservations with the host OR have message history
$renters_sql = "
    SELECT DISTINCT u.user_id as renter_id, u.full_name as renter_name, u.profile_picture
    FROM (
        SELECT sender_id as contact_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id as contact_id FROM messages WHERE sender_id = ?
        UNION
        SELECT r.user_id as contact_id FROM reservations r JOIN units un ON r.unit_id = un.unit_id WHERE un.host_id = ?
    ) as contacts
    JOIN users u ON contacts.contact_id = u.user_id
    WHERE u.user_id != ?";
$renters = get_multiple_results($renters_sql, [$host_id, $host_id, $host_id, $host_id]);

// Selected Renter
$selected_renter_id = isset($_GET['renter_id']) ? (int)$_GET['renter_id'] : (count($renters) > 0 ? $renters[0]['renter_id'] : 0);

// Get current renter details
$current_renter = null;
if ($selected_renter_id) {
    $current_renter = get_single_result("SELECT * FROM users WHERE user_id = ?", [$selected_renter_id]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Inbox - Host Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <style>
        .inbox-container { height: calc(100vh - 140px); background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; overflow: hidden; margin-top: 20px;}
        .inbox-sidebar { width: 320px; border-right: 1px solid #e9ecef; display: flex; flex-direction: column; }
        .inbox-list { overflow-y: auto; flex-grow: 1; }
        .inbox-item { padding: 15px; border-bottom: 1px solid #f8f9fa; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; text-decoration: none; color: inherit; }
        .inbox-item:hover { background: #f8f9fa; color: inherit; }
        .inbox-item.active { background: #eef2ff; border-left: 4px solid #4f46e5; }
        
        @media (min-width: 769px) {
            .main-content { margin-left: 230px; width: calc(100% - 230px); }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .inbox-sidebar { display: none; } /* On small screens, hide sidebar or make toggleable */
        }
        
        .chat-area { flex-grow: 1; display: flex; flex-direction: column; background: #fafafa; }
        .chat-header { padding: 15px 20px; border-bottom: 1px solid #e9ecef; background: #fff; display: flex; align-items: center; justify-content: space-between; }
        .chat-messages { flex-grow: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; }
        .chat-input-area { padding: 15px 20px; background: #fff; border-top: 1px solid #e9ecef; }
        
        .msg-bubble { max-width: 70%; padding: 12px 16px; border-radius: 16px; position: relative; font-size: 0.95rem; }
        .msg-mine { background: #4f46e5; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-other { background: #fff; color: #333; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #e9ecef; }
        .msg-time { display: block; font-size: 0.75rem; opacity: 0.7; margin-top: 4px; text-align: right; }
        
        .avatar-circle { width: 45px; height: 45px; background: #e0e7ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; flex-shrink: 0; }
        
        /* Auto resize textarea */
        textarea { resize: none; overflow-y: auto; max-height: 120px; }
        textarea::-webkit-scrollbar { width: 6px; }
        textarea::-webkit-scrollbar-track { background: transparent; }
        textarea::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="fas fa-inbox text-primary me-2"></i>Guest Inbox</h2>
                <p class="text-muted mb-0">Secure E2E Encrypted messaging with your renters.</p>
            </div>
            <div>
                <a href="automation_settings.php" class="btn btn-outline-primary"><i class="fas fa-robot me-1"></i> Automation & Templates</a>
            </div>
        </div>

        <div class="inbox-container">
            <!-- Sidebar -->
            <div class="inbox-sidebar">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="mb-0 fw-bold">Active Conversations</h6>
                </div>
                <div class="inbox-list">
                    <?php if (empty($renters)): ?>
                        <div class="p-4 text-center text-muted">
                            <p>No active reservations yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($renters as $renter): ?>
                            <a href="?renter_id=<?php echo $renter['renter_id']; ?>" class="inbox-item <?php echo $renter['renter_id'] == $selected_renter_id ? 'active' : ''; ?>">
                                <div class="avatar-circle">
                                    <?php echo strtoupper(substr($renter['renter_name'], 0, 1)); ?>
                                </div>
                                <div class="overflow-hidden">
                                    <h6 class="mb-1 text-truncate fw-bold"><?php echo htmlspecialchars($renter['renter_name']); ?></h6>
                                    <small class="text-muted d-block">Renter</small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="chat-area">
                <?php if ($current_renter): ?>
                    <div class="chat-header">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle mb-0" style="width:40px; height:40px;">
                                <?php echo strtoupper(substr($current_renter['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($current_renter['full_name']); ?></h6>
                                <small class="text-success"><i class="fas fa-circle" style="font-size: 8px;"></i> Guest</small>
                            </div>
                        </div>
                        <span class="badge bg-light text-dark border"><i class="fas fa-lock text-success me-1"></i> Encrypted</span>
                    </div>

                    <div class="chat-messages" id="chatBox">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
                            <br>Decrypting messages...
                        </div>
                    </div>

                    <div class="chat-input-area">
                        <form id="sendMessageForm">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_renter_id; ?>">
                            <div class="input-group">
                                <textarea name="message" id="messageInput" class="form-control" rows="1" placeholder="Type your secure message..."></textarea>
                                <button class="btn btn-primary px-4" type="submit" id="sendBtn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                        <i class="fas fa-comments text-light" style="font-size: 5rem; mb-3"></i>
                        <h5>No conversation selected</h5>
                        <p>Select a guest from the sidebar to view messages.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        const chatBox = $('#chatBox');
        const renterId = <?php echo $selected_renter_id; ?>;
        
        if (renterId > 0) {
            fetchMessages();
            setInterval(fetchMessages, 5000);
        }

        function fetchMessages() {
            $.ajax({
                url: '../ajax/messages_api.php',
                type: 'GET',
                data: { action: 'fetch_messages', chat_with: renterId },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        renderMessages(res.messages);
                    }
                }
            });
        }

        function renderMessages(messages) {
            if (messages.length === 0) {
                chatBox.html(`
                    <div class="text-center text-muted py-5">
                        <p>No messages yet. Send a welcome message!</p>
                    </div>
                `);
                return;
            }

            let html = '';
            messages.forEach(msg => {
                const isMine = msg.is_mine;
                const bubbleClass = isMine ? 'msg-mine' : 'msg-other';
                
                html += `
                    <div class="msg-bubble ${bubbleClass} shadow-sm">
                        ${escapeHtml(msg.message)}
                        <span class="msg-time ${isMine ? 'text-light' : 'text-muted'}">${msg.sent_at}</span>
                    </div>
                `;
            });

            const isAtBottom = chatBox.prop("scrollHeight") - chatBox.scrollTop() <= chatBox.outerHeight() + 50;
            
            if (chatBox.data('last-html') !== html) {
                chatBox.html(html);
                chatBox.data('last-html', html);
                
                if (isAtBottom) {
                    chatBox.scrollTop(chatBox.prop("scrollHeight"));
                }
            }
        }

        $('#sendMessageForm').on('submit', function(e) {
            e.preventDefault();
            const btn = $('#sendBtn');
            const input = $('#messageInput');
            const msg = input.val().trim();

            if (!msg) return;

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: '../ajax/messages_api.php',
                type: 'POST',
                data: $(this).serialize() + '&action=send_message',
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        input.val('');
                        fetchMessages();
                        setTimeout(() => chatBox.scrollTop(chatBox.prop("scrollHeight")), 300);
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
                    input.focus();
                }
            });
        });

        // Auto-resize
        $('#messageInput').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight < 100 ? this.scrollHeight : 100) + 'px';
        });

        function escapeHtml(unsafe) {
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;")
                 .replace(/\n/g, "<br>");
        }
    });
</script>
</body>
</html>
