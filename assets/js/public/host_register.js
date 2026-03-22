// Password validation and UI interactions
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', validatePasswordRequirements);
    }
});

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = event.target.closest('.password-toggle').querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function validatePasswordRequirements() {
    const password = document.getElementById('password').value;
    
    // Check requirements
    const hasLength = password.length >= 8;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSpecial = /[\W_]/.test(password);
    
    // Update UI
    updateRequirement('req-length', hasLength);
    updateRequirement('req-uppercase', hasUppercase);
    updateRequirement('req-lowercase', hasLowercase);
    updateRequirement('req-number', hasNumber);
    updateRequirement('req-special', hasSpecial);
}

function updateRequirement(id, isMet) {
    const element = document.getElementById(id);
    if (!element) return;
    
    const icon = element.querySelector('i');
    
    if (isMet) {
        element.classList.add('met');
        element.classList.remove('unmet');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-check');
        icon.style.color = '#28a745';
    } else {
        element.classList.remove('met');
        element.classList.add('unmet');
        icon.classList.remove('fa-check');
        icon.classList.add('fa-times');
        icon.style.color = '#dc3545';
    }
}
