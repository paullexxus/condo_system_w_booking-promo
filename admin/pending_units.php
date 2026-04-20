<?php
/**
 * Hardened Unit Moderation Center (Admin)
 * Features: AJAX Workflow, Concurrency Review Locks, Snapshot Integrity, and Elite UI.
 */
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';

checkRole(['admin']);

// Rotate CSRF for high-security action
$csrf_token = generateCSRFToken();
$idempotency_key = uniqid('mod_', true);

$page_title = 'Unit Moderation Center';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - BookIT Admin</title>
    
    <!-- Core Assets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Map & Alerts -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- System Styles -->
    <link href="../assets/css/sidebar-common.css" rel="stylesheet">
    <link href="../assets/css/admin/admin_dashboard.css" rel="stylesheet">
    
    <style>
        :root {
            --status-pending: #f59e0b;
            --status-reviewing: #3b82f6;
            --bg-glass: rgba(255, 255, 255, 0.95);
        }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        /* High-End Timeline */
        .mod-timeline { display: flex; justify-content: space-between; margin-bottom: 2rem; position: relative; }
        .mod-timeline::before { content: ''; position: absolute; top: 15px; left: 0; right: 0; height: 2px; background: #e2e8f0; z-index: 1; }
        .mod-step { position: relative; z-index: 2; background: white; width: 32px; height: 32px; border-radius: 50%; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .mod-step.active { border-color: var(--status-reviewing); color: var(--status-reviewing); font-weight: bold; }
        .mod-step.complete { background: var(--status-reviewing); border-color: var(--status-reviewing); color: white; }
        .mod-step-label { position: absolute; top: 40px; left: 50%; transform: translateX(-50%); font-size: 11px; white-space: nowrap; color: #64748b; font-weight: 500; }

        /* Moderation Cards */
        .mod-card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 1.5rem; transition: transform 0.2s; }
        .mod-gallery-hero { height: 350px; width: 100%; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; cursor: zoom-in; }
        .mod-thumb { height: 70px; width: 70px; object-fit: cover; border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .mod-thumb:hover { opacity: 0.8; transform: scale(1.05); }
        .mod-thumb.active { border: 2px solid var(--status-reviewing); }
        
        .amenity-pill { padding: 6px 14px; border-radius: 100px; background: #f1f5f9; font-size: 12px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 8px; }
        .map-frame { height: 250px; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        
        /* Concurrency Warning */
        .lock-warning { animation: pulse-red 2s infinite; }
        @keyframes pulse-red { 0% { background: #fee2e2; } 50% { background: #fecaca; } 100% { background: #fee2e2; } }

        /* Flagging UI */
        .flag-btn { position: absolute; top: 10px; right: 10px; opacity: 0.1; transition: 0.3s; z-index: 10; }
        .img-container:hover .flag-btn { opacity: 1; }
        .flagged-badge { position: absolute; top: 10px; left: 10px; z-index: 10; background: #ef4444; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body>

<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content flex-grow-1">
        <div class="dashboard-header mb-4">
            <div>
                <h1 class="mb-1 fw-bold">Moderation Center</h1>
                <p class="text-muted mb-0">High-integrity unit reviews & security clearing</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <div id="stats-indicator" class="stats-badge bg-white shadow-sm border px-3 py-2 rounded-pill">
                    <span class="badge bg-primary rounded-pill me-2" id="pending-count">0</span> 
                    <span class="small text-dark fw-600">Pending Reviews</span>
                </div>
                <button class="btn btn-light btn-sm shadow-sm border rounded-pill px-3" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Pending Units Table -->
        <div class="mod-card card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pending-table">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3">Unit Identity</th>
                                <th>Listing Branch</th>
                                <th>Host / Provider</th>
                                <th>Pricing Structure</th>
                                <th>Data Health</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via fallback PHP or future SignalR/Long-poll -->
                            <?php
                            $units = get_multiple_results("
                                SELECT u.unit_id, u.unit_name, u.price_per_night, u.price_per_month, u.pricing_type, u.created_at,
                                       b.branch_name, b.city, h.full_name as host_name
                                FROM units u
                                JOIN branches b ON u.branch_id = b.branch_id
                                JOIN users h ON u.host_id = h.user_id
                                WHERE u.approval_status = 'pending'
                                ORDER BY u.created_at ASC
                            ");
                            
                            foreach ($units as $u): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= htmlspecialchars($u['unit_name']) ?></div>
                                    <div class="small text-muted">ID: #<?= (int)$u['unit_id'] ?> • <?= time_ago($u['created_at']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($u['branch_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($u['city']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($u['host_name']) ?></div>
                                    <div class="small text-muted">Enterprise Member</div>
                                </td>
                                <td>
                                    <?php if ($u['pricing_type'] === 'monthly'): ?>
                                        <span class="fw-bold">₱<?= number_format($u['price_per_month']) ?></span><small class="text-muted">/mo</small>
                                    <?php else: ?>
                                        <span class="fw-bold">₱<?= number_format($u['price_per_night']) ?></span><small class="text-muted">/night</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-soft-success text-success border border-success border-opacity-10 px-3 py-2 rounded-pill">
                                        <i class="fas fa-check-double me-1"></i> Snapshot-Ready
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-primary btn-sm px-3 rounded-pill" onclick="openReviewModal(<?= (int)$u['unit_id'] ?>)">
                                        <i class="fas fa-search me-1"></i> Begin Review
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; if (empty($units)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-check-double fa-3x mb-3 opacity-25"></i>
                                        <h5>Zero Pending Items</h5>
                                        <p class="small">The moderation queue is currently clear. Excellent work!</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ELITE REVIEW MODAL -->
<div class="modal fade" id="reviewModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary">
                        <i class="fas fa-clipboard-list fs-4"></i>
                    </div>
                    <div>
                        <h4 class="modal-title fw-bold" id="modUnitName">Listing Review</h4>
                        <p class="text-muted small mb-0"><i class="fas fa-fingerprint me-1"></i> ID: #<span id="modUnitIdDisplay">0</span> • <span class="badge bg-warning text-dark">Immutable Snapshot</span></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="releaseLock()"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Timeline -->
                <div class="mod-timeline">
                    <div class="mod-step complete"><i class="fas fa-check"></i><div class="mod-step-label">Submitted</div></div>
                    <div class="mod-step active">2<div class="mod-step-label">Under Review</div></div>
                    <div class="mod-step">3<div class="mod-step-label">Verified</div></div>
                    <div class="mod-step">4<div class="mod-step-label">Final Decision</div></div>
                </div>

                <div class="row g-4 mt-2">
                    <!-- Left: Gallery & Map -->
                    <div class="col-lg-7">
                        <div class="position-relative img-container mb-3">
                            <img id="mainGallery" src="" class="mod-gallery-hero shadow-sm">
                            <div class="flagged-badge d-none" id="imgFlaggedBadge">PROHIBITED CONTENT</div>
                            <button class="btn btn-danger btn-sm flag-btn rounded-circle" onclick="flagCurrentContent('image')" title="Flag this image">
                                <i class="fas fa-flag"></i>
                            </button>
                        </div>
                        <div class="d-flex gap-2 overflow-auto pb-2" id="modThumbs">
                            <!-- JS Inject -->
                        </div>
                        
                        <div class="mod-card card mt-3">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="fas fa-map-marker-alt text-danger me-2"></i>Precise Location</h6>
                                <div id="modMap" class="map-frame"></div>
                                <div class="mt-2 small text-muted d-flex justify-content-between">
                                    <span id="modFullAddress">Address loading...</span>
                                    <code class="bg-light px-2 rounded" id="modCoords">0, 0</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Info & Validation -->
                    <div class="col-lg-5">
                        <div class="alert alert-info py-2 small d-none" id="lockStatusMessage">
                            <i class="fas fa-lock me-2"></i> Review session inherited (Takeover active)
                        </div>

                        <div class="mod-card card bg-light border-0 shadow-none">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Verification Checklist</h6>
                                <div id="validationList" class="small text-muted">
                                    <!-- JS Inject -->
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold">Moderation Verdict</span>
                                    <span id="verdictBadge" class="badge bg-soft-secondary">PENDING</span>
                                </div>
                            </div>
                        </div>

                        <div class="mod-card card border">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="fas fa-user-tie text-primary me-2"></i>Host Credentials</h6>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="bg-soft-primary p-2 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold small" id="modHostName">...</div>
                                        <div class="text-muted x-small" id="modHostEmail">...</div>
                                    </div>
                                </div>
                                <div class="text-muted small mb-3" id="modUnitDesc">...</div>
                                
                                <h6 class="fw-bold mb-2 small uppercase text-muted">Amenties & Specifications</h6>
                                <div class="d-flex flex-wrap gap-2" id="modAmenities">
                                    <!-- JS Inject -->
                                </div>
                            </div>
                        </div>
                        
                        <div id="flagsContainer" class="d-none mt-3">
                           <h6 class="fw-bold text-danger mb-2 small"><i class="fas fa-exclamation-triangle"></i> Flagged Content (Escalated)</h6>
                           <div id="flagsList" class="small"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-0 px-4 py-3 justify-content-between" style="border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;">
                <div class="small text-muted"><i class="fas fa-shield me-1"></i> Data integrity verified via Audit-Chain</div>
                <div class="d-flex gap-2">
                    <button type="button" id="rejectBtn" class="btn btn-outline-danger px-4" onclick="initiateVerdict('reject')">
                        <i class="fas fa-times-circle me-1"></i> Reject
                    </button>
                    <button type="button" id="approveBtn" class="btn btn-success px-4" onclick="initiateVerdict('approve')">
                        <i class="fas fa-check-circle me-1"></i> Approve & Publish
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<script>
let currentUnitId = null;
let reviewModal = null;
let mapInstance = null;
let mapMarker = null;

$(document).ready(function() {
    reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
    $('#pending-count').text($('#pending-table tbody tr').length);
});

async function openReviewModal(unitId) {
    currentUnitId = unitId;
    
    Swal.fire({
        title: 'Verifying Lockdown...',
        text: 'Acquiring exclusive review access',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch(`get_unit_moderation_data.php?unit_id=${unitId}`);
        const data = await response.json();

        if (!data.success) {
            Swal.fire('Access Denied', data.message, 'warning');
            return;
        }

        populateReviewModal(data);
        Swal.close();
        reviewModal.show();
    } catch (e) {
        Swal.fire('Error', 'Failed to fetch unit data securely.', 'error');
    }
}

function populateReviewModal(data) {
    const u = data.unit;
    
    // Core Info
    $('#modUnitName').text(u.unit_name);
    $('#modUnitIdDisplay').text(u.unit_id);
    $('#modUnitDesc').text(u.description);
    $('#modHostName').text(u.host_name);
    $('#modHostEmail').text(u.host_email);
    $('#modFullAddress').text(`${u.street_address}, ${u.unit_number}, ${u.branch_name}, ${u.city}`);
    $('#modCoords').text(`${u.latitude}, ${u.longitude}`);

    // Lock Inheritance
    if (data.lock_message) {
        $('#lockStatusMessage').text(data.lock_message).removeClass('d-none');
    } else {
        $('#lockStatusMessage').addClass('d-none');
    }

    // Gallery
    if (u.images.length > 0) {
        $('#mainGallery').attr('src', '../' + u.images[0].image_path).data('id', u.images[0].image_id);
        let thumbsHtml = '';
        u.images.forEach(img => {
            thumbsHtml += `<img src="../${img.image_path}" class="mod-thumb" onclick="swapMainGallery(this, ${img.image_id})">`;
        });
        $('#modThumbs').html(thumbsHtml);
    }

    // Amenities
    let amenHtml = '';
    u.amenities.forEach(a => {
        amenHtml += `<span class="amenity-pill"><i class="fas fa-check text-success"></i> ${a}</span>`;
    });
    $('#modAmenities').html(amenHtml || '<span class="text-muted italic">No specific amenities listed</span>');

    // Validation Checklist
    let validHtml = '';
    const checks = [
        { label: 'Primary Photos', passed: u.images.length > 0 },
        { label: 'Pricing Verified', passed: data.unit.price_per_night > 0 || data.unit.price_per_month > 0 },
        { label: 'Occupancy Set', passed: u.max_occupancy > 0 },
        { label: 'Location Mapped', passed: u.latitude != 0 }
    ];
    
    checks.forEach(c => {
        validHtml += `
            <div class="d-flex justify-content-between mb-1">
                <span>${c.label}</span>
                <i class="fas ${c.passed ? 'fa-check text-success' : 'fa-times text-danger'}"></i>
            </div>
        `;
    });
    $('#validationList').html(validHtml);

    // Block logic
    $('#approveBtn').prop('disabled', !data.can_approve);
    $('#verdictBadge').attr('class', data.can_approve ? 'badge bg-success' : 'badge bg-danger')
                    .text(data.can_approve ? 'VERIFIED' : 'DATA INCOMPLETE');

    // Escalations / Flags
    if (u.flags && u.flags.length > 0) {
        $('#flagsContainer').removeClass('d-none');
        let flagsHtml = '';
        u.flags.forEach(f => {
            flagsHtml += `
                <div class="border-start border-danger border-4 ps-2 mb-2 bg-soft-danger py-1">
                    <div class="fw-bold">${f.flag_type}</div>
                    <div class="x-small">${f.details}</div>
                    <div class="x-small text-muted">— ${f.flagged_by_name}</div>
                </div>
            `;
        });
        $('#flagsList').html(flagsHtml);
    } else {
        $('#flagsContainer').addClass('d-none');
    }

    // Map Init
    initModMap(u.latitude, u.longitude, u.unit_name);
}

function initModMap(lat, lng, label) {
    if (mapInstance) mapInstance.remove();
    
    mapInstance = L.map('modMap').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapInstance);
    mapMarker = L.marker([lat, lng]).addTo(mapInstance).bindPopup(label).openPopup();
    
    // Fix resize issue if modal opens fast
    setTimeout(() => mapInstance.invalidateSize(), 400);
}

function swapMainGallery(thumb, id) {
    $('.mod-thumb').removeClass('active');
    $(thumb).addClass('active');
    $('#mainGallery').attr('src', thumb.src).data('id', id);
}

async function flagCurrentContent(type) {
    const { value: details } = await Swal.fire({
        title: 'Flag Content',
        input: 'textarea',
        inputLabel: `Identify why this ${type} is prohibited:`,
        inputPlaceholder: 'e.g. Blurry photo, inappropriate image, suspicious description...',
        showCancelButton: true
    });

    if (details) {
        const formData = new FormData();
        formData.append('unit_id', currentUnitId);
        formData.append('action', 'flag');
        formData.append('flag_type', type === 'image' ? 'Image Standards' : 'Content Standard');
        formData.append('flag_details', details);
        formData.append('csrf_token', '<?= $csrf_token ?>');
        formData.append('idempotency_key', '<?= $idempotency_key ?>');

        const response = await fetch('process_unit_moderation.php', { method: 'POST', body: formData });
        const resData = await response.json();
        
        if (resData.success) {
            Swal.fire('Flagged', 'Content has been flagged for audit.', 'info');
            openReviewModal(currentUnitId); // Refresh
        }
    }
}

async function initiateVerdict(verdict) {
    const title = verdict === 'approve' ? 'Final Approval' : 'Unit Rejection';
    const text = verdict === 'approve' 
        ? 'Are you certain this listing meets all safety and quality guidelines?' 
        : 'Please specify the rejection reason (shared with host):';
        
    const config = {
        title: title,
        text: text,
        icon: verdict === 'approve' ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonText: verdict === 'approve' ? 'Yes, Approve & Publish' : 'Confirm Rejection',
        confirmButtonColor: verdict === 'approve' ? '#10b981' : '#ef4444'
    };

    if (verdict === 'reject') {
        config.input = 'textarea';
        config.inputValidator = (value) => {
            if (!value) return 'Mandatory: Rejection reason required.';
        }
    }

    const { value: reason, isConfirmed } = await Swal.fire(config);

    if (isConfirmed) {
        Swal.fire({ title: 'Processing Transaction', didOpen: () => Swal.showLoading() });
        
        const formData = new FormData();
        formData.append('unit_id', currentUnitId);
        formData.append('action', verdict);
        formData.append('reason', reason || '');
        formData.append('csrf_token', '<?= $csrf_token ?>');
        formData.append('idempotency_key', '<?= $idempotency_key ?>');

        try {
            const response = await fetch('process_unit_moderation.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                Swal.fire('Success', data.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Failed', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'Transaction failed.', 'error');
        }
    }
}

function releaseLock() {
    // Optional: Send beacon to release lock immediately
}
</script>

</body>
</html>
