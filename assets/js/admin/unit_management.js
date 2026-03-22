// Unit Management JavaScript
let currentUnitId = null;
let addUnitMapInitialized = false;
let addUnitMarker = null;
let addUnitMap = null;
let duplicateBlocked = false;

$(document).ready(function() {
    // Initialize DataTable with left buttons and right search
    $('#unitsTable').DataTable({
        dom: '<"row"<"col-sm-6"B><"col-sm-6"f>>rt<"row"<"col-sm-6"l><"col-sm-6"p>><"clear">',
        buttons: [
            {
                extend: 'copy',
                className: 'btn-copy',
                text: '<i class="fas fa-copy"></i> Copy'
            },
            {
                extend: 'csv', 
                className: 'btn-csv',
                text: '<i class="fas fa-file-csv"></i> CSV'
            },
            {
                extend: 'excel',
                className: 'btn-excel',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            {
                extend: 'pdf',
                className: 'btn-pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF'
            },
            {
                extend: 'print',
                className: 'btn-print',
                text: '<i class="fas fa-print"></i> Print'
            }
        ],
        pageLength: 25,
        responsive: true,
        order: [[0, 'desc']],
        language: {
            search: "Search units:",
            searchPlaceholder: "Type to search...",
            lengthMenu: "Show _MENU_ units",
            info: "Showing _START_ to _END_ of _TOTAL_ units",
            infoEmpty: "No units found",
            infoFiltered: "(filtered from _MAX_ total units)"
        }
    });
});

// ==================== UNIT FUNCTIONS ====================
// View Unit Details in Modal - AJAX VERSION
function viewUnit(unitId) {
    currentUnitId = unitId;
    
    // Show loading state
    $('#viewUnitModalBody').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading unit details...</p>
        </div>
    `);
    
    // Show modal first
    $('#viewUnitModal').modal('show');
    
    // Load unit details via AJAX
    $.ajax({
        url: 'get_unit_details.php', // Create this file
        type: 'GET',
        data: { unit_id: unitId },
        success: function(response) {
            $('#viewUnitModalBody').html(response);
        },
        error: function() {
            $('#viewUnitModalBody').html(`
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load unit details. Please try again.
                </div>
            `);
        }
    });
}

// Edit Unit in Modal
function editUnit(unitId) {
    currentUnitId = unitId;
    
    // Show loading state
    $('#editUnitModalBody').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading unit details...</p>
        </div>
    `);
    
    // Show modal first
    $('#editUnitModal').modal('show');
    
    // Load unit edit form via AJAX
    $.ajax({
        url: 'get_edit_unit_form.php',
        type: 'GET',
        cache: false,
        data: { unit_id: unitId },
        success: function(response) {
            $('#editUnitModalBody').html(response);
        },
        error: function() {
            $('#editUnitModalBody').html(`
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load unit details. Please try again.
                </div>
            `);
        }
    });
}

// Update the editSelectedUnit function to use the modal
function editSelectedUnit() {
    if (currentUnitId) {
        editUnit(currentUnitId);
        $('#viewUnitModal').modal('hide');
    }
}

function changeStatus(unitId, unitName) {
    $('#status_unit_id').val(unitId);
    $('#status_unit_name').text(unitName);
    $('#statusModal').modal('show');
}

function confirmDelete(unitId, unitName) {
    $('#delete_unit_id').val(unitId);
    $('#delete_unit_name').text(unitName);
    $('#deleteModal').modal('show');
}

// Export functions
window.exportUnits = function(format) {
    const table = $('#unitsTable').DataTable();
    if (format === 'pdf') {
        table.button('.buttons-pdf').trigger();
    } else if (format === 'csv') {
        table.button('.buttons-csv').trigger();
    }
};

// ==================== MODAL FUNCTIONS ====================
function editSelectedUnit() {
    if (currentUnitId) {
        editUnit(currentUnitId);
        $('#viewUnitModal').modal('hide');
    }
}

function createBookingForUnit() {
    if (currentUnitId) {
        window.location.href = 'reservations.php?unit=' + currentUnitId;
    }
}

// ==================== HELPER FUNCTIONS ====================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function getStatusColor(status) {
    const colors = {
        'confirmed': 'success',
        'checked_in': 'primary', 
        'completed': 'secondary',
        'pending': 'warning',
        'cancelled': 'danger'
    };
    return colors[status] || 'warning';
}

function capitalizeFirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// ==================== FORM VALIDATION ====================
function validateUnitForm(form) {
    // Only apply global JS validation to the Add Unit form
    // Edit unit form has its own specific validation logic in get_edit_unit_form.php
    if (!form.querySelector('button[name="add_unit"]')) {
        return true; 
    }

    const price = form.querySelector('input[name="price"]');
    const maxOccupancy = form.querySelector('input[name="max_occupancy"]');
    let isValid = true;

    if (price && parseFloat(price.value) <= 0) {
        showAlert('Price must be greater than 0', 'danger');
        price.focus();
        isValid = false;
    }

    if (maxOccupancy && parseInt(maxOccupancy.value) <= 0) {
        showAlert('Max occupancy must be at least 1', 'danger');
        maxOccupancy.focus();
        isValid = false;
    }

    return isValid;
}

// Show Alert
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.querySelector('.content').insertBefore(alertDiv, document.querySelector('.page-header'));
    
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// ==================== EVENT LISTENERS ====================
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateUnitForm(this)) {
                e.preventDefault();
                return;
            }

            // If this is the Add Unit form, enforce duplicate block
            if (this.querySelector('button[name="add_unit"]')) {
                if (duplicateBlocked) {
                    e.preventDefault();
                    showAlert('This listing has been flagged as a possible duplicate and must be reviewed by an administrator before saving.', 'danger');
                    return;
                }
            }
        });
    });

    // Real-time search
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
});

// ==================== KEYBOARD SHORTCUTS ====================
document.addEventListener('keydown', function(e) {
    // Ctrl + N - Add new unit
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        document.querySelector('[data-bs-target="#addUnitModal"]').click();
    }
    
    // Ctrl + F - Focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) searchInput.focus();
    }
});

// ==================== MAP + DUPLICATE CHECK FUNCTIONS ====================
function initAddUnitMap() {
    if (addUnitMapInitialized) return;
    addUnitMapInitialized = true;

    // Default center (Manila) — override by provider or user
    const defaultLat = 14.5995;
    const defaultLng = 120.9842;

    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        // Google Maps implementation
        addUnitMap = new google.maps.Map(document.getElementById('addUnitMap'), {
            center: { lat: defaultLat, lng: defaultLng },
            zoom: 13
        });

        addUnitMap.addListener('click', function(ev) {
            placeAddUnitMarker(ev.latLng.lat(), ev.latLng.lng());
        });
        // After map ready, fetch host location and draw trace
        fetchHostLocationAndDraw();
    } else if (typeof L !== 'undefined') {
        // Leaflet implementation
        addUnitMap = L.map('addUnitMap').setView([defaultLat, defaultLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(addUnitMap);

        addUnitMap.on('click', function(e) {
            placeAddUnitMarker(e.latlng.lat, e.latlng.lng);
        });
        // After map ready, fetch host location and draw trace
        fetchHostLocationAndDraw();
    } else {
        console.warn('No map provider available. Please configure MAP_PROVIDER or include map scripts.');
    }

    // Wire up input events to run duplicate checks and map geocoding
    const fields = ['building_name', 'street_address', 'city', 'unit_number'];
    
    function geocodeAdminAddress() {
        const street = document.querySelector('#addUnitModal [name="street_address"]')?.value || '';
        const city = document.querySelector('#addUnitModal [name="city"]')?.value || '';
        const addr = `${street}, ${city}, Philippines`.trim();
        
        if (addr.length > 5 && addr !== ', , Philippines') {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr)}&limit=1`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lon = parseFloat(data[0].lon);
                        if (addUnitMap) {
                            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                                addUnitMap.setCenter({ lat, lng: lon });
                                addUnitMap.setZoom(16);
                            } else if (typeof L !== 'undefined') {
                                addUnitMap.setView([lat, lon], 16);
                            }
                        }
                        placeAddUnitMarker(lat, lon);
                    }
                }).catch(e => console.log('Geocoding error', e));
        }
    }

    let geocodeTimeout;
    fields.forEach(name => {
        const el = document.querySelector(`#addUnitModal [name="${name}"]`);
        if (el) {
            el.addEventListener('blur', function() {
                runAddUnitDuplicateCheck();
            });
            
            if (name === 'street_address' || name === 'city') {
                el.addEventListener('input', function() {
                    clearTimeout(geocodeTimeout);
                    geocodeTimeout = setTimeout(geocodeAdminAddress, 800);
                });
            }
        }
    });
}

let hostMarker = null;
let hostLine = null;

function fetchHostLocationAndDraw() {
    $.ajax({
        url: 'ajax/get_host_location.php',
        type: 'GET',
        success: function(res) {
            if (!res || res.status !== 'ok') return;
            const lat = res.latitude;
            const lng = res.longitude;
            const name = res.host_name || 'Host';
            if (lat && lng) {
                placeHostMarker(parseFloat(lat), parseFloat(lng), name);
            }
        }
    });
}

function placeHostMarker(lat, lng, label) {
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        const pos = { lat: lat, lng: lng };
        if (!hostMarker) {
            hostMarker = new google.maps.Marker({ position: pos, map: addUnitMap, icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' }, title: label });
        } else {
            hostMarker.setPosition(pos);
        }
    } else if (typeof L !== 'undefined') {
        if (!hostMarker) {
            hostMarker = L.marker([lat, lng], { icon: L.icon({ iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png' }) }).addTo(addUnitMap);
        } else {
            addUnitMap.removeLayer(hostMarker);
            hostMarker = L.marker([lat, lng]).addTo(addUnitMap);
        }
    }

    // If unit marker exists, draw a line and show distance
    const latField = document.getElementById('unit_latitude');
    const lngField = document.getElementById('unit_longitude');
    if (latField && lngField && latField.value && lngField.value) {
        drawHostUnitLine(parseFloat(lat), parseFloat(lng), parseFloat(latField.value), parseFloat(lngField.value));
    }
}

function drawHostUnitLine(hostLat, hostLng, unitLat, unitLng) {
    // remove previous line
    if (hostLine) {
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
            hostLine.setMap(null);
        } else if (typeof L !== 'undefined') {
            addUnitMap.removeLayer(hostLine);
        }
        hostLine = null;
    }

    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        const line = new google.maps.Polyline({
            path: [ {lat: hostLat, lng: hostLng}, {lat: unitLat, lng: unitLng} ],
            geodesic: true,
            strokeColor: '#FF0000',
            strokeOpacity: 0.7,
            strokeWeight: 2
        });
        line.setMap(addUnitMap);
        hostLine = line;
    } else if (typeof L !== 'undefined') {
        hostLine = L.polyline([[hostLat, hostLng], [unitLat, unitLng]], { color: 'red' }).addTo(addUnitMap);
    }

    // Show distance
    const distance = haversineDistance([hostLat, hostLng], [unitLat, unitLng]);
    showAlert('Distance between host and unit: ' + distance.toFixed(1) + ' m', 'info');
}

function haversineDistance(coord1, coord2) {
    const toRad = v => v * Math.PI / 180;
    const R = 6371000; // meters
    const dLat = toRad(coord2[0] - coord1[0]);
    const dLon = toRad(coord2[1] - coord1[1]);
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(toRad(coord1[0])) * Math.cos(toRad(coord2[0])) * Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function placeAddUnitMarker(lat, lng) {
    // set or move marker
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        const pos = { lat: lat, lng: lng };
        if (!addUnitMarker) {
            addUnitMarker = new google.maps.Marker({ position: pos, map: addUnitMap });
        } else {
            addUnitMarker.setPosition(pos);
        }
    } else if (typeof L !== 'undefined') {
        if (!addUnitMarker) {
            addUnitMarker = L.marker([lat, lng]).addTo(addUnitMap);
        } else {
            addUnitMap.removeLayer(addUnitMarker);
            addUnitMarker = L.marker([lat, lng]).addTo(addUnitMap);
        }
    }

    // update hidden fields
    const latField = document.getElementById('unit_latitude');
    const lngField = document.getElementById('unit_longitude');
    if (latField && lngField) {
        latField.value = lat;
        lngField.value = lng;
    }

    // Run duplicate check when marker placed
    runAddUnitDuplicateCheck();
}

function runAddUnitDuplicateCheck() {
    const modal = document.getElementById('addUnitModal');
    if (!modal) return;

    const form = modal.querySelector('form');
    if (!form) return;

    const data = {
        building_name: form.querySelector('[name="building_name"]').value || '',
        street_address: form.querySelector('[name="street_address"]').value || '',
        city: form.querySelector('[name="city"]').value || '',
        unit_number: form.querySelector('[name="unit_number"]').value || '',
        latitude: form.querySelector('#unit_latitude').value || '',
        longitude: form.querySelector('#unit_longitude').value || ''
    };

    // don't run if nothing entered yet
    if (!data.building_name && !data.street_address && !data.latitude) {
        duplicateBlocked = false;
        return;
    }

    $.ajax({
        url: 'ajax/check_duplicate.php',
        type: 'POST',
        data: data,
        success: function(res) {
            if (!res || !res.analysis) return;
            const analysis = res.analysis;
            if (analysis.overall_risk >= 70) {
                duplicateBlocked = true;
                showAlert('Possible duplicate detected — this listing is blocked and queued for admin review.', 'danger');
            } else {
                duplicateBlocked = false;
                showAlert('No high-risk duplicates detected. You can proceed to save.', 'success');
            }
        },
        error: function() {
            // silently ignore server errors for now
        }
    });
}

// Initialize map when modal opens
$(document).on('shown.bs.modal', '#addUnitModal', function () {
    initAddUnitMap();
});