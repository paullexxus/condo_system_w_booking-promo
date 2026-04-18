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

                    <!-- Search & Filter Bar -->
                    <div class="p-2 border-bottom bg-light d-flex justify-content-between align-items-center z-10">
                        <div class="btn-group btn-group-sm" role="group" id="filterGroup">
                            <button type="button" class="btn btn-primary active filter-btn" data-filter="all">All</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="image"><i class="fas fa-image"></i></button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="file"><i class="fas fa-file-alt"></i></button>
                        </div>
                        <div class="input-group input-group-sm ms-2" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="chatSearch" placeholder="Search...">
                            <div id="searchNav" class="d-none bg-white border d-flex align-items-center px-2">
                                <span id="searchCount" class="small text-muted me-2">0 of 0</span>
                                <button type="button" id="searchPrev" class="btn btn-sm btn-light p-0 px-1 border-0"><i class="fas fa-chevron-up"></i></button>
                                <button type="button" id="searchNext" class="btn btn-sm btn-light p-0 px-1 border-0"><i class="fas fa-chevron-down"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatBox">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
                            <br>Decrypting messages...
                        </div>
                    </div>

                    <div class="chat-input-area d-flex flex-column">
                        <div id="profanityWarning" class="text-danger small fw-bold mb-2 d-none"><i class="fas fa-exclamation-triangle"></i> This message contains inappropriate language and cannot be sent.</div>
                        <form id="sendMessageForm" enctype="multipart/form-data">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_renter_id; ?>">
                            <div class="input-group">
                                <label class="btn btn-light border d-flex align-items-center m-0" for="attachmentInput" title="Attach Image or Document" style="cursor: pointer;">
                                    <i class="fas fa-paperclip"></i>
                                </label>
                                <input type="file" name="attachment" id="attachmentInput" class="d-none" accept=".jpg,.jpeg,.png,.gif,.docx">

                                <textarea name="message" id="messageInput" class="form-control" rows="1" placeholder="Type your secure message..."></textarea>
                                <button class="btn btn-primary px-4" type="submit" id="sendBtn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                        <div id="attachmentPreview" class="mt-2 text-primary small d-none fw-bold"></div>
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

<!-- Image Lightbox Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-transparent border-0 shadow-none">
      <div class="modal-header border-0 pb-0 justify-content-end">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center pt-0">
        <img id="fullscreenImg" src="" class="img-fluid rounded shadow-lg" style="max-height: 85vh;">
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let imageModalObj = null;
    function openFullscreenImage(src) {
        $('#fullscreenImg').attr('src', src);
        if(!imageModalObj) imageModalObj = new bootstrap.Modal(document.getElementById('imageModal'));
        imageModalObj.show();
    }

    $(document).ready(function() {
        const chatBox = $('#chatBox');
        const renterId = <?php echo $selected_renter_id; ?>;
        
        if (renterId > 0) {
            fetchProhibitedWords();
            fetchMessages();
            setInterval(fetchMessages, 5000);
        }

        let prohibitedWords = [];
        function fetchProhibitedWords() {
            $.getJSON('../ajax/messages_api.php', { action: 'fetch_prohibited_words' }, function(res) {
                if (res.success && res.words) {
                    prohibitedWords = res.words;
                }
            });
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

        window.deleteMessage = function(id, type) {
            if (!confirm(`Are you sure you want to delete this message ${type === 'everyone' ? 'for everyone' : 'for yourself'}?`)) return;
            $.post('../ajax/messages_api.php', { action: 'delete_message', message_id: id, delete_type: type }, function(res) {
                if (res.success) {
                    fetchMessages();
                } else {
                    alert(res.message || 'Failed to delete message.');
                }
            }, 'json');
        }

        let allMessages = [];
        let currentFilter = 'all';
        let searchQuery = '';

        function renderMessages(messages) {
            allMessages = messages;
            let filtered = messages;

            if (currentFilter === 'image') filtered = messages.filter(m => m.file_type === 'image');
            if (currentFilter === 'file') filtered = messages.filter(m => m.file_type === 'file');

            if (filtered.length === 0) {
                chatBox.html(`
                    <div class="text-center text-muted py-5">
                        <p>${searchQuery || currentFilter !== 'all' ? 'No matching messages found.' : 'No messages yet. Say hello!'}</p>
                    </div>
                `);
                return;
            }

            let html = '';
            filtered.forEach(msg => {
                const isMine = msg.is_mine;
                const bubbleClass = isMine ? 'msg-mine' : 'msg-other';
                
                let contentHtml = '';
                
                if (msg.is_deleted) {
                    contentHtml = `<div class="font-italic text-muted"><i class="fas fa-ban"></i> This message was deleted</div>`;
                } else {
                    let textContent = escapeHtml(msg.message || '');
                    
                    if (searchQuery && textContent.toLowerCase().includes(searchQuery.toLowerCase())) {
                        const regex = new RegExp(`(${searchQuery})`, "gi");
                        textContent = textContent.replace(regex, "<mark class='search-match bg-warning rounded px-1 text-dark'>$1</mark>");
                    }

                    if (textContent) contentHtml += `<div>${textContent}</div>`;

                    if (msg.file_path) {
                        if (msg.file_type === 'image') {
                            contentHtml += `<div class="mt-2"><img src="../${msg.file_path}" class="rounded shadow-sm img-fluid" style="max-height:150px; cursor:pointer;" onclick="openFullscreenImage('../${escapeHtml(msg.file_path)}')"></div>`;
                        } else {
                            contentHtml += `
                                <div class="mt-2 border rounded bg-white text-dark p-2 d-flex align-items-center justify-content-between shadow-sm">
                                    <div class="text-truncate" style="max-width:200px;">
                                        <i class="fas fa-file-word text-primary me-2"></i>
                                        <small>${escapeHtml(msg.original_file_name)}</small>
                                    </div>
                                    <a href="../${msg.file_path}" download class="btn btn-sm btn-light border text-primary" title="Download"><i class="fas fa-download"></i></a>
                                </div>
                            `;
                        }
                    }
                }

                let deleteMenu = '';
                if (!msg.is_deleted) {
                    let deleteEveryoneAttr = msg.can_delete_everyone ? '' : 'd-none';
                    deleteMenu = `
                        <div class="dropdown d-inline-block align-self-end ${isMine ? 'me-2 order-1' : 'ms-2 order-3'}">
                            <button class="btn btn-sm text-muted p-0 border-0" style="background:none;" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v px-2"></i></button>
                            <ul class="dropdown-menu shadow-sm border-0 py-1" style="font-size: 0.85em; min-width: 140px;">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="deleteMessage(${msg.message_id}, 'me')"><i class="fas fa-trash text-muted me-2"></i>Delete for me</a></li>
                                <li class="${deleteEveryoneAttr}"><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteMessage(${msg.message_id}, 'everyone')"><i class="fas fa-ban me-2"></i>Delete for everyone</a></li>
                            </ul>
                        </div>
                    `;
                }

                html += `
                    <div class="d-flex w-100 mb-2 ${isMine ? 'justify-content-end' : 'justify-content-start'}">
                        <div class="d-flex flex-column" style="max-width: 85%;">
                            <div class="d-flex align-items-end ${isMine ? 'justify-content-end' : 'justify-content-start'}">
                                <div class="msg-bubble ${bubbleClass} shadow-sm order-2">
                                    ${contentHtml}
                                </div>
                                ${deleteMenu}
                            </div>
                            <span class="msg-time ${isMine ? 'text-end text-muted' : 'text-start text-muted'} order-last mt-1 d-block w-100">${msg.sent_at}</span>
                        </div>
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

        let matchIndex = -1;
        let totalMatches = 0;

        function updateSearchNav() {
            let matches = $('.search-match');
            totalMatches = matches.length;
            if (totalMatches > 0) {
                $('#searchNav').removeClass('d-none');
                if (matchIndex < 0) matchIndex = 0;
                if (matchIndex >= totalMatches) matchIndex = totalMatches - 1;
                
                $('#searchCount').text(`${matchIndex + 1} of ${totalMatches}`);
                
                $('.search-match').removeClass('border border-dark').css('outline', 'none');
                let current = $(matches[matchIndex]);
                current.addClass('border border-dark').css('outline', '2px solid #000');
                
                // scroll to it
                let scrollTo = current.offset().top - chatBox.offset().top + chatBox.scrollTop() - chatBox.height() / 2;
                chatBox.scrollTop(scrollTo);
            } else {
                $('#searchNav').addClass('d-none');
            }
        }

        $('#searchNext').on('click', function() {
            if (totalMatches > 0) {
                matchIndex = (matchIndex + 1) % totalMatches;
                updateSearchNav();
            }
        });

        $('#searchPrev').on('click', function() {
            if (totalMatches > 0) {
                matchIndex = (matchIndex - 1 + totalMatches) % totalMatches;
                updateSearchNav();
            }
        });

        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $(this).removeClass('btn-outline-primary').addClass('btn-primary');
            currentFilter = $(this).data('filter');
            renderMessages(allMessages);
            setTimeout(() => chatBox.scrollTop(chatBox.prop("scrollHeight")), 50);
        });

        $('#chatSearch').on('input', function() {
            searchQuery = $(this).val().trim();
            matchIndex = -1;
            renderMessages(allMessages);
            if (searchQuery) updateSearchNav();
        });

        // File Attachment UI
        $('#attachmentInput').on('change', function() {
            const file = this.files[0];
            if (file) {
                $('#attachmentPreview').removeClass('d-none').html(`<i class="fas fa-paperclip"></i> Attached: ${escapeHtml(file.name)} <button type="button" class="btn btn-sm text-danger ms-2 p-0" onclick="$('#attachmentInput').val(''); $('#attachmentPreview').addClass('d-none');">&times; Remove</button>`);
            } else {
                $('#attachmentPreview').addClass('d-none');
            }
        });

        // Profanity Check Dynamic
        $('#messageInput').on('keyup input', function() {
            const msg = $(this).val().toLowerCase();
            let normalizedMsg = msg.replace(/[@$1!034+]/g, match => {
                const map = {'@':'a', '$':'s', '1':'i', '!':'i', '0':'o', '3':'e', '4':'a', '+':'t'};
                return map[match];
            });
            let noSpaceMsg = normalizedMsg.replace(/[\s\.\-_]/g, '');

            let isSevere = false;
            let isMild = false;

            for (let pw of prohibitedWords) {
                let word = pw.word.toLowerCase();
                let match = false;
                let regex = new RegExp("\\b" + word + "\\b", "i");
                
                if (regex.test(msg)) {
                    match = true;
                } else if (normalizedMsg.includes(word) || noSpaceMsg.includes(word)) {
                    match = true;
                }

                if (match) {
                    if (pw.severity === 'severe') isSevere = true;
                    if (pw.severity === 'mild') isMild = true;
                }
            }

            if (isSevere) {
                $('#profanityWarning').html('<i class="fas fa-exclamation-triangle"></i> This message contains highly inappropriate language and cannot be sent.').removeClass('d-none text-warning text-dark').addClass('text-danger');
                $('#sendBtn').prop('disabled', true);
            } else if (isMild) {
                $('#profanityWarning').html('<i class="fas fa-info-circle"></i> Note: Mild offensive words will be automatically masked.').removeClass('d-none text-danger').addClass('text-warning text-dark');
                $('#sendBtn').prop('disabled', false);
            } else {
                $('#profanityWarning').addClass('d-none');
                $('#sendBtn').prop('disabled', false);
            }
        });

        $('#sendMessageForm').on('submit', function(e) {
            e.preventDefault();
            if ($('#sendBtn').prop('disabled')) return;

            const btn = $('#sendBtn');
            const formData = new FormData(this);
            formData.append('action', 'send_message');

            const msg = $('#messageInput').val().trim();
            const file = $('#attachmentInput').val();

            if (!msg && !file) return;

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: '../ajax/messages_api.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $('#messageInput').val('').trigger('input');
                        $('#attachmentInput').val('');
                        $('#attachmentPreview').addClass('d-none').empty();
                        fetchMessages();
                        setTimeout(() => chatBox.scrollTop(chatBox.prop("scrollHeight")), 300);
                    } else {
                        alert('Failed to send: ' + res.message);
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
                    $('#messageInput').focus();
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
