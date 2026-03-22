// Unit Management JavaScript

// View Toggle
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const view = this.dataset.view;
        const container = document.getElementById('unitsContainer');
        
        if (view === 'list') {
            container.classList.add('list-view');
        } else {
            container.classList.remove('list-view');
        }
    });
});

// Search Filter
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        filterUnits();
    });
}

// Status Filter
const statusFilter = document.getElementById('statusFilter');
if (statusFilter) {
    statusFilter.addEventListener('change', function() {
        filterUnits();
    });
}

function filterUnits() {
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const statusFilterVal = document.getElementById('statusFilter')?.value || '';
    
    document.querySelectorAll('.unit-card').forEach(card => {
        const name = card.dataset.name || '';
        const status = card.dataset.status || '';
        
        const matchesSearch = name.includes(searchTerm);
        const matchesStatus = !statusFilterVal || status === statusFilterVal;
        
        card.style.display = (matchesSearch && matchesStatus) ? 'flex' : 'none';
    });
}

// Image Upload Handling
const uploadArea = document.getElementById('uploadArea');
const photoInput = document.getElementById('photoInput');
const photoPreview = document.getElementById('photoPreview');

if (uploadArea && photoInput) {
    uploadArea.addEventListener('click', () => photoInput.click());
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = '#3498db';
        uploadArea.style.background = '#e8f4f8';
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.style.borderColor = '#ddd';
        uploadArea.style.background = 'white';
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = '#ddd';
        uploadArea.style.background = 'white';
        
        const files = e.dataTransfer.files;
        photoInput.files = files;
        handlePhotoSelect(files);
    });
    
    photoInput.addEventListener('change', (e) => {
        handlePhotoSelect(e.target.files);
    });
}

function handlePhotoSelect(files) {
    if (!photoPreview) return;
    
    if (files.length > 5) {
        alert('You can only upload a maximum of 5 images per unit. Extra images will be ignored.');
        const dt = new DataTransfer();
        for (let i = 0; i < 5; i++) {
            dt.items.add(files[i]);
        }
        files = dt.files;
        if (photoInput) photoInput.files = files;
    }

    photoPreview.innerHTML = '';
    
    Array.from(files).forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.style.cssText = 'position: relative; border-radius: 6px; overflow: hidden; aspect-ratio: 1;';
                div.innerHTML = `
                    <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="position: absolute; top: 5px; right: 5px; padding: 4px 8px;">×</button>
                `;
                photoPreview.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
