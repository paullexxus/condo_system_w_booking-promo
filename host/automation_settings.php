<?php
// host/automation_settings.php
include '../includes/session.php';
include '../includes/functions.php';

checkRole(['host', 'manager']);

$host_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Templates & Automations - Host Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 230px; padding: 30px; transition: margin 0.3s; }
        @media (max-width: 1200px) { .main-content { margin-left: 210px; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content flex-grow-1">
        <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-robot text-primary me-2"></i>Automation Settings</h2>
                <p class="text-muted mb-0">Manage reusable message templates and automated triggers.</p>
            </div>
            <div>
                <a href="messages.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Inbox</a>
                <button class="btn btn-primary" onclick="openTemplateModal(0)"><i class="fas fa-plus me-1"></i> New Template</button>
            </div>
        </div>

        <div class="row" id="templatesContainer">
            <!-- Templates loaded here via JS -->
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
            </div>
    </main>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title" id="modalTitle">Add Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="templateForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="template_id" id="templateId" value="0">
                    <input type="hidden" name="action" value="save_template">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Template Title</label>
                            <input type="text" class="form-control" name="title" id="templateTitle" required placeholder="e.g. Welcome to my Condo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category</label>
                            <select class="form-select" name="category" id="templateCategory" required>
                                <option value="welcome_message">Welcome Message</option>
                                <option value="house_rules">House Rules</option>
                                <option value="checkin_reminder">Check-in Reminder</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Trigger Event <span class="badge bg-secondary">Automation</span></label>
                        <div class="form-text text-muted mb-2">When should the system automatically send this message?</div>
                        <select class="form-select" name="trigger_event" id="templateTrigger" required>
                            <option value="manual_send">Manual Send Only (No Automation)</option>
                            <option value="on_booking_confirmed">Immediately after booking is confirmed</option>
                            <option value="24_hours_before_checkin">24 Hours before Check-in</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Content</label>
                        <div class="mb-2">
                            <span class="badge bg-light text-dark border me-1 cursor-pointer" onclick="insertTag('{guest_name}')">{guest_name}</span>
                            <span class="badge bg-light text-dark border me-1 cursor-pointer" onclick="insertTag('{unit_name}')">{unit_name}</span>
                            <span class="badge bg-light text-dark border me-1 cursor-pointer" onclick="insertTag('{check_in_date}')">{check_in_date}</span>
                        </div>
                        <textarea class="form-control" name="message" id="templateMessage" rows="6" required placeholder="Write your message here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let templatesData = [];

    $(document).ready(function() {
        loadTemplates();

        $('#templateForm').on('submit', function(e) {
            e.preventDefault();
            const btn = $('#saveBtn');
            btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: '../ajax/automation_api.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $('#templateModal').modal('hide');
                        loadTemplates();
                    } else {
                        alert(res.message);
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).text('Save Template');
                }
            });
        });
    });

    function loadTemplates() {
        $.getJSON('../ajax/automation_api.php?action=get_templates', function(res) {
            if (res.success) {
                templatesData = res.templates;
                renderTemplates();
            }
        });
    }

    function renderTemplates() {
        if (templatesData.length === 0) {
            $('#templatesContainer').html(`
                <div class="col-12 text-center py-5">
                    <i class="fas fa-folder-open mb-3 text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted">No Templates Found</h5>
                    <p class="text-muted">Create a template to save time messaging your guests.</p>
                </div>
            `);
            return;
        }

        let html = '';
        templatesData.forEach(t => {
            const badgeColor = t.trigger_event === 'manual_send' ? 'secondary' : 'success';
            
            html += `
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title fw-bold mb-1">${escapeHtml(t.title)}</h5>
                                    <span class="badge bg-light text-dark border me-2">${t.category.replace('_', ' ').toUpperCase()}</span>
                                    <span class="badge bg-${badgeColor}"><i class="fas ${t.trigger_event === 'manual_send' ? 'fa-hand-pointer' : 'fa-bolt'}"></i> ${t.trigger_event.replace(/_/g, ' ')}</span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <li><a class="dropdown-item" href="#" onclick="openTemplateModal(${t.template_id})"><i class="fas fa-edit me-2 text-primary"></i> Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteTemplate(${t.template_id})"><i class="fas fa-trash me-2"></i> Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                            <p class="card-text text-muted small" style="white-space: pre-wrap; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;">${escapeHtml(t.message)}</p>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#templatesContainer').html(html);
    }

    function openTemplateModal(id) {
        if (id === 0) {
            $('#modalTitle').text('Add New Template');
            $('#templateId').val(0);
            $('#templateForm')[0].reset();
        } else {
            const t = templatesData.find(x => x.template_id == id);
            if (t) {
                $('#modalTitle').text('Edit Template');
                $('#templateId').val(t.template_id);
                $('#templateTitle').val(t.title);
                $('#templateCategory').val(t.category);
                $('#templateTrigger').val(t.trigger_event);
                $('#templateMessage').val(t.message);
            }
        }
        $('#templateModal').modal('show');
    }

    function deleteTemplate(id) {
        if (confirm('Are you sure you want to delete this template?')) {
            $.post('../ajax/automation_api.php', { action: 'delete_template', template_id: id }, function(res) {
                if (res.success) {
                    loadTemplates();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }
    }

    function insertTag(tag) {
        const textarea = document.getElementById('templateMessage');
        const startPos = textarea.selectionStart;
        const endPos = textarea.selectionEnd;
        textarea.value = textarea.value.substring(0, startPos) + tag + textarea.value.substring(endPos, textarea.value.length);
        textarea.focus();
        textarea.selectionStart = startPos + tag.length;
        textarea.selectionEnd = startPos + tag.length;
    }

    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
</script>
</body>
</html>
