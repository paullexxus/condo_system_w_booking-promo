// Initialize tabs
function initializeProfileTabs() {
    var triggerTabList = [].slice.call(document.querySelectorAll('#profileTabs button'));
    triggerTabList.forEach(function (triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
}

// Profile picture preview
function initializeProfilePictureUpload() {
    const profilePictureInput = document.getElementById('profilePictureInput');
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImage').src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeProfileTabs();
    initializeProfilePictureUpload();
});