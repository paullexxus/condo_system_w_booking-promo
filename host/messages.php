<?php
// host/messages.php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/encryption.php';
checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];

// Fetch Contacts
$renters = get_multiple_results("
    SELECT DISTINCT u.user_id as renter_id, u.full_name as renter_name, u.profile_picture
    FROM (
        SELECT sender_id as contact_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id as contact_id FROM messages WHERE sender_id = ?
        UNION
        SELECT r.user_id as contact_id FROM reservations r JOIN units un ON r.unit_id = un.unit_id WHERE un.host_id = ?
    ) as contacts
    JOIN users u ON contacts.contact_id = u.user_id
    WHERE u.user_id != ?", [$host_id, $host_id, $host_id, $host_id]);

// Selected Renter
$selected_renter_id = isset($_GET['renter_id']) ? (int)$_GET['renter_id'] : (count($renters) > 0 ? $renters[0]['renter_id'] : 0);
$current_renter = $selected_renter_id ? get_single_result("SELECT * FROM users WHERE user_id = ?", [$selected_renter_id]) : null;

$page_title = 'Guest Messaging';

ob_start();
?>
<style>
    .inbox-container { height: calc(100vh - 180px); background: #fff; border-radius: 16px; box-shadow: var(--card-shadow); display: flex; overflow: hidden; }
    .inbox-sidebar { width: 300px; border-right: 1px solid #eee; display: flex; flex-direction: column; background: #fcfcfc; }
    .inbox-item { padding: 16px; border-bottom: 1px solid #f5f5f5; cursor: pointer; transition: var(--transition); display: flex; align-items: center; text-decoration: none; color: inherit; }
    .inbox-item:hover { background: #f0f4ff; }
    .inbox-item.active { background: #eef2ff; border-left: 4px solid var(--primary-color); }
    .avatar-sm { width: 40px; height: 40px; background: #e0e7ff; color: var(--primary-color); border-radius: 50%; display: grid; place-items: center; font-weight: bold; margin-right: 12px; flex-shrink: 0; }
    
    .chat-area { flex-grow: 1; display: flex; flex-direction: column; }
    .chat-header { padding: 15px 20px; border-bottom: 1px solid #eee; background: #fff; display: flex; align-items: center; justify-content: space-between; }
    .chat-messages { flex-grow: 1; overflow-y: auto; padding: 20px; background: #f8fafc; display: flex; flex-direction: column; gap: 12px; }
    .chat-input-area { padding: 15px 20px; background: #fff; border-top: 1px solid #eee; }
    
    .msg-group { display: flex; flex-direction: column; max-width: 80%; }
    .msg-mine { align-self: flex-end; }
    .msg-other { align-self: flex-start; }
    
    .bubble { padding: 10px 16px; border-radius: 18px; font-size: 0.95rem; }
    .bubble-mine { background: var(--primary-color); color: white; border-bottom-right-radius: 4px; }
    .bubble-other { background: #fff; color: #1e293b; border-bottom-left-radius: 4px; border: 1px solid #e2e8f0; }
    
    .search-bar { background: #fcfcfc; border-bottom: 1px solid #eee; padding: 10px 15px; }
    .search-match { background: #fde047 !important; color: #000 !important; border-radius: 2px; }
    
    textarea.msg-input { border: 1px solid #e2e8f0; border-radius: 20px; padding: 8px 16px; resize: none; overflow: hidden; }
</style>
<?php
$extra_css = ob_get_clean();

include '../templates/host_layout_header.php';
?>

<div class="page-header mb-4">
    <div>
        <h1 class="page-title"><i class="fas fa-comments me-2 text-primary"></i>Guest Concierge</h1>
        <p class="text-muted">Encrypted communication channel with your renters</p>
    </div>
    <div class="page-actions">
        <a href="automation_settings.php" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="fas fa-robot me-1"></i> Auto-Replies</a>
    </div>
</div>

<div class="inbox-container">
    <!-- Chats Sidebar -->
    <div class="inbox-sidebar">
        <div class="p-3 border-bottom bg-light small fw-bold text-uppercase text-muted letter-spacing-1">Conversations</div>
        <div class="flex-grow-1 overflow-auto">
            <?php foreach($renters as $r): ?>
                <a href="?renter_id=<?php echo $r['renter_id']; ?>" class="inbox-item <?php echo $r['renter_id'] == $selected_renter_id ? 'active' : ''; ?>">
                    <div class="avatar-sm"><?php echo strtoupper(substr($r['renter_name'], 0, 1)); ?></div>
                    <div class="overflow-hidden">
                        <div class="fw-bold text-truncate" style="font-size: 0.9rem;"><?php echo htmlspecialchars($r['renter_name']); ?></div>
                        <small class="text-muted">Renter</small>
                    </div>
                </a>
            <?php endforeach; if(empty($renters)) echo '<div class="p-4 text-center text-muted small">No active chats.</div>'; ?>
        </div>
    </div>

    <!-- Active Chat -->
    <div class="chat-area">
        <?php if ($current_renter): ?>
            <div class="chat-header">
                <div class="d-flex align-items-center">
                    <div class="avatar-sm mb-0" style="width:36px; height:36px;"><?php echo strtoupper(substr($current_renter['full_name'], 0, 1)); ?></div>
                    <div>
                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($current_renter['full_name']); ?></h6>
                        <small class="text-success"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> Active Now</small>
                    </div>
                </div>
                <div class="small text-muted"><i class="fas fa-shield-alt me-1 text-success"></i> E2E Encrypted</div>
            </div>

            <!-- Features Bar -->
            <div class="search-bar d-flex justify-content-between align-items-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-primary active filter-btn" data-filter="all">All</button>
                    <button class="btn btn-outline-secondary filter-btn" data-filter="image"><i class="fas fa-image"></i></button>
                    <button class="btn btn-outline-secondary filter-btn" data-filter="file"><i class="fas fa-file-alt"></i></button>
                </div>
                <div class="input-group input-group-sm" style="max-width: 200px;">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                    <input type="text" id="chatSearch" class="form-control border-start-0" placeholder="Find...">
                </div>
            </div>

            <div class="chat-messages" id="chatBox">
                <div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div> Decrypting...</div>
            </div>

            <div class="chat-input-area">
                <div id="profanityWarning" class="alert alert-danger py-2 small d-none"><i class="fas fa-exclamation-triangle me-2"></i> Inappropriate content detected.</div>
                <form id="sendMessageForm">
                    <input type="hidden" name="receiver_id" value="<?php echo $selected_renter_id; ?>">
                    <div class="d-flex gap-2">
                        <label class="btn btn-light rounded-circle shadow-sm" for="attachmentInput" style="width:40px; height:40px; padding:0; display:grid; place-items:center;">
                            <i class="fas fa-paperclip"></i>
                        </label>
                        <input type="file" name="attachment" id="attachmentInput" class="d-none">
                        
                        <textarea name="message" id="messageInput" class="form-control msg-input" placeholder="Type a message..." rows="1"></textarea>
                        
                        <button class="btn btn-primary rounded-circle shadow" id="sendBtn" style="width:40px; height:40px; padding:0;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div id="attachmentPreview" class="small text-primary mt-2 d-none"></div>
                </form>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted opacity-50">
                <i class="fas fa-comments fa-4x mb-3"></i>
                <h5>Select a guest to start chatting</h5>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts (Maintaining original functionality) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        const renterId = <?php echo $selected_renter_id; ?>;
        const chatBox = $('#chatBox');
        let prohibitedWords = [];
        let allMessages = [];
        let curFilter = 'all';

        if (renterId > 0) {
            loadWords();
            loadMessages();
            setInterval(loadMessages, 5000);
        }

        function loadWords() { $.getJSON('../ajax/messages_api.php', {action:'fetch_prohibited_words'}, res => { if(res.success) prohibitedWords = res.words; }); }
        
        function loadMessages() {
            $.getJSON('../ajax/messages_api.php', {action:'fetch_messages', chat_with:renterId}, res => {
                if(res.success) {
                    allMessages = res.messages;
                    render();
                }
            });
        }

        function render() {
            let filtered = allMessages;
            if(curFilter === 'image') filtered = allMessages.filter(m => m.file_type === 'image');
            if(curFilter === 'file') filtered = allMessages.filter(m => m.file_type === 'file');

            let html = '';
            filtered.forEach(m => {
                const mine = m.is_mine;
                html += `
                    <div class="msg-group ${mine ? 'msg-mine' : 'msg-other'}">
                        <div class="bubble ${mine ? 'bubble-mine' : 'bubble-other'} shadow-sm">
                            ${m.is_deleted ? '<i>Deleted</i>' : (m.message || '')}
                            ${m.file_path ? `<div class="mt-2"><a href="../public/access_file.php?file=${encodeURIComponent(m.file_path.replace('uploads/',''))}" target="_blank" class="${mine ? 'text-white' : 'text-primary'} small">📎 Attachment</a></div>` : ''}
                        </div>
                        <small class="text-muted mt-1 ${mine ? 'text-end' : ''}" style="font-size:0.7rem;">${m.sent_at}</small>
                    </div>
                `;
            });
            
            const atBottom = chatBox.prop("scrollHeight") - chatBox.scrollTop() <= chatBox.outerHeight() + 50;
            chatBox.html(html || '<div class="text-center py-5 text-muted small">No messages here.</div>');
            if(atBottom) chatBox.scrollTop(chatBox.prop("scrollHeight"));
        }

        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('btn-primary active').addClass('btn-outline-secondary');
            $(this).addClass('btn-primary active').removeClass('btn-outline-secondary');
            curFilter = $(this).data('filter');
            render();
        });

        $('#sendMessageForm').on('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'send_message');
            $.ajax({
                url: '../ajax/messages_api.php', type: 'POST', data: fd, contentType: false, processData: false,
                success: function() { $('#messageInput').val(''); loadMessages(); }
            });
        });

        $('#attachmentInput').on('change', function() {
            if(this.files[0]) $('#attachmentPreview').text(`📎 ${this.files[0].name}`).removeClass('d-none');
            else $('#attachmentPreview').addClass('d-none');
        });
    });
</script>

<?php include '../templates/host_layout_footer.php'; ?>
