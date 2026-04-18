<?php
// renter/messages.php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/auth.php';
include '../includes/encryption.php';

// Only renters can access
if (!isLoggedIn() || $_SESSION['role'] !== 'renter') {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all hosts the renter has reservations with OR has message history
$hosts_sql = "
    SELECT DISTINCT u.user_id as host_id, u.full_name as host_name
    FROM (
        SELECT sender_id as contact_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id as contact_id FROM messages WHERE sender_id = ?
        UNION
        SELECT un.host_id as contact_id FROM reservations r JOIN units un ON r.unit_id = un.unit_id WHERE r.user_id = ?
    ) as contacts
    JOIN users u ON contacts.contact_id = u.user_id
    WHERE u.user_id != ?";
$hosts = get_multiple_results($hosts_sql, [$user_id, $user_id, $user_id, $user_id]);

// Selected host
$selected_host_id = isset($_GET['host_id']) ? (int)$_GET['host_id'] : (count($hosts) > 0 ? $hosts[0]['host_id'] : 0);

// Get current host details
$current_host = null;
if ($selected_host_id) {
    $current_host = get_single_result("SELECT * FROM users WHERE user_id = ?", [$selected_host_id]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Messages - BookIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .chat-container { height: calc(100vh - 180px); }
        .messages-box { height: calc(100% - 70px); overflow-y: auto; }
        
        .msg-bubble { max-width: 75%; border-radius: 20px; padding: 12px 18px; position: relative; }
        .msg-mine { background: #3498db; color: white; border-bottom-right-radius: 4px; margin-left: auto; }
        .msg-other { background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 4px; border: 1px solid #e2e8f0; }
        
        /* Custom Scrollbar for chat */
        .messages-box::-webkit-scrollbar { width: 6px; }
        .messages-box::-webkit-scrollbar-track { background: transparent; }
        .messages-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        /* Auto resize textarea */
        textarea { overflow-y: auto !important; max-height: 120px; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">

    <!-- Premium Navigation -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-100 shadow-sm" style="margin-bottom: 20px;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="../public/index.php" class="flex items-center gap-3 text-2xl font-bold text-gray-900 hover:opacity-80 transition-opacity">
                    <img src="../assets/images/logo/bookit.png" alt="BookIT Logo" class="w-14 h-14 rounded-xl object-cover">
                    BookIT
                </a>

                <div class="hidden md:flex items-center gap-10">
                    <a href="../public/index.php" class="text-gray-600 hover:text-gray-900 font-medium">Home</a>
                    <a href="../public/browse_units.php" class="text-gray-600 hover:text-gray-900 font-medium">Browse</a>
                    <a href="my_bookings.php" class="text-gray-600 hover:text-gray-900 font-medium">My Bookings</a>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <button class="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-user-circle text-2xl"></i>
                            <span class="hidden sm:inline text-sm font-medium"><?php echo htmlspecialchars(substr($_SESSION['fullname'], 0, 15)); ?></span>
                        </button>
                        <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
                            <a href="../modules/notifications.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 border-b">
                                <i class="fas fa-bell mr-2 text-blue-500"></i> Notifications
                            </a>
                            <a href="my_bookings.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 border-b">
                                <i class="fas fa-calendar-check mr-2 text-orange-500"></i> My Bookings
                            </a>
                            <a href="messages.php" class="block px-4 py-3 text-gray-700 bg-gray-50 font-bold border-b text-blue-600">
                                <i class="fas fa-envelope mr-2"></i> Messages
                            </a>
                            <a href="profile.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 border-b">
                                <i class="fas fa-cog mr-2 text-gray-500"></i> Settings
                            </a>
                            <?php if (in_array($_SESSION['role'], ['host','manager','admin'])): ?>
                                <a href="../host/host_dashboard.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-50 border-b">
                                    <i class="fas fa-tachometer-alt mr-2 text-purple-500"></i> Host Dashboard
                                </a>
                            <?php endif; ?>
                            <a href="../public/logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex-grow max-w-7xl w-full mx-auto px-4 py-8">
        
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800"><i class="fas fa-lock text-blue-500 mr-2"></i>Secure Messages</h1>
            <span class="bg-green-100 text-green-800 text-sm font-semibold px-3 py-1 rounded-full border border-green-200">
                <i class="fas fa-shield-alt mr-1"></i> End-to-End Encrypted
            </span>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex chat-container">
            
            <!-- Sidebar: Hosts List -->
            <div class="w-1/3 border-r border-gray-200 bg-gray-50 flex flex-col">
                <div class="p-4 border-b border-gray-200 bg-white">
                    <h3 class="font-bold text-gray-700">Your Hosts</h3>
                </div>
                <div class="overflow-y-auto flex-grow">
                    <?php if (empty($hosts)): ?>
                        <div class="p-6 text-center text-gray-500 text-sm">
                            You don't have any bookings yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($hosts as $host): 
                            $isActive = $host['host_id'] == $selected_host_id;
                        ?>
                            <a href="?host_id=<?php echo $host['host_id']; ?>" 
                               class="flex items-center p-4 border-b border-gray-100 hover:bg-white transition-colors <?php echo $isActive ? 'bg-white border-l-4 border-l-blue-500' : ''; ?>">
                                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-lg mr-4">
                                    <?php echo strtoupper(substr($host['host_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <h4 class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($host['host_name']); ?></h4>
                                    <p class="text-sm text-gray-500 truncate">Host</p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="w-2/3 flex flex-col bg-white">
                <?php if ($current_host): ?>
                    <!-- Chat Header -->
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-white shadow-sm z-10">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold mr-3">
                                <?php echo strtoupper(substr($current_host['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($current_host['full_name']); ?></h3>
                                <p class="text-xs text-green-500 font-medium">Verified Host</p>
                            </div>
                        </div>
                    </div>

                    <!-- Messages View -->
                    <div class="p-6 messages-box flex flex-col gap-4 bg-gray-50/50" id="chatBox">
                        <!-- Messages injected via JS -->
                        <div class="text-center text-gray-400 py-10" id="loadingMessages">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i><br>Decrypting messages...
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="p-4 bg-white border-t border-gray-200">
                        <form id="sendMessageForm" class="flex items-end gap-2">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_host_id; ?>">
                            <div class="flex-grow relative">
                                <textarea name="message" id="messageInput" rows="1" class="w-full pl-4 pr-10 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none bg-gray-50" placeholder="Type a secure message..."></textarea>
                                <div class="absolute right-3 bottom-3 text-gray-400">
                                    <i class="fas fa-lock text-sm" title="E2E Encrypted"></i>
                                </div>
                            </div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-6 py-3 font-medium transition-colors flex items-center gap-2 h-[48px]">
                                <span>Send</span>
                                <i class="fas fa-paper-plane text-sm"></i>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-full text-gray-400">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-comments text-4xl text-gray-300"></i>
                        </div>
                        <h2 class="text-xl font-medium text-gray-600 mb-2">Your Messages</h2>
                        <p class="text-gray-500">Select a host from the list to start chatting.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- JavaScript to handle chat -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const chatBox = $('#chatBox');
            const hostId = <?php echo $selected_host_id; ?>;
            let pollingInterval;

            if (hostId > 0) {
                fetchMessages();
                pollingInterval = setInterval(fetchMessages, 5000); // Poll every 5s
            }

            function fetchMessages() {
                $.ajax({
                    url: '../ajax/messages_api.php',
                    type: 'GET',
                    data: { action: 'fetch_messages', chat_with: hostId },
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
                        <div class="text-center text-gray-400 py-10 flex flex-col items-center">
                            <i class="fas fa-hand-sparkles text-4xl mb-3 text-yellow-400"></i>
                            <p>No messages yet. Say hello!</p>
                        </div>
                    `);
                    return;
                }

                let html = '';
                messages.forEach(msg => {
                    const isMine = msg.is_mine;
                    const bubbleClass = isMine ? 'msg-mine' : 'msg-other';
                    
                    html += `
                        <div class="flex flex-col w-full mb-1">
                            <div class="msg-bubble ${bubbleClass} shadow-sm">
                                <p class="text-sm break-words">${escapeHtml(msg.message)}</p>
                            </div>
                            <span class="text-[11px] text-gray-400 mt-1 ${isMine ? 'text-right' : 'text-left'}">${msg.sent_at}</span>
                        </div>
                    `;
                });

                const isAtBottom = chatBox.prop("scrollHeight") - chatBox.scrollTop() <= chatBox.outerHeight() + 50;

                if (chatBox.data('last-html') !== html) {
                    chatBox.html(html);
                    chatBox.data('last-html', html);
                    
                    if (isAtBottom) {
                        scrollToBottom();
                    }
                }
            }

            function scrollToBottom() {
                chatBox.scrollTop(chatBox.prop("scrollHeight"));
            }

            $('#sendMessageForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
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
                            setTimeout(scrollToBottom, 300);
                        } else {
                            alert('Failed to send: ' + res.message);
                        }
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<span>Send</span><i class="fas fa-paper-plane text-sm"></i>');
                        input.focus();
                    }
                });
            });

            // Auto-resize textarea
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
