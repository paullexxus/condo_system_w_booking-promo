// Simple and working show password functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Hide empty alert boxes on page load
            const errorAlert = document.querySelector('.alert-danger');
            const successAlert = document.querySelector('.alert-success');
            
            if (errorAlert && errorAlert.textContent.trim() === '') {
                errorAlert.style.display = 'none';
            }
            if (successAlert && successAlert.textContent.trim() === '') {
                successAlert.style.display = 'none';
            }
            
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            
            // Toggle main password
            if (togglePassword && passwordField) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    
                    // Toggle eye icon
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
            
            // Toggle confirm password
            if (toggleConfirmPassword && confirmPasswordField) {
                toggleConfirmPassword.addEventListener('click', function() {
                    const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordField.setAttribute('type', type);
                    
                    // Toggle eye icon
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
            
            // Real-time password validation
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    validatePassword(this.value);
                });
            }
            
            // Confirm password match
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', function() {
                    validatePasswordMatch();
                });
            }
        });
        
        function validatePassword(password) {
            // Check length
            const lengthValid = password.length >= 8;
            updateRequirement('req-length', lengthValid);
            
            // Check uppercase
            const uppercaseValid = /[A-Z]/.test(password);
            updateRequirement('req-uppercase', uppercaseValid);
            
            // Check lowercase
            const lowercaseValid = /[a-z]/.test(password);
            updateRequirement('req-lowercase', lowercaseValid);
            
            // Check number
            const numberValid = /\d/.test(password);
            updateRequirement('req-number', numberValid);
            
            // Check special character
            const specialValid = /[\W_]/.test(password);
            updateRequirement('req-special', specialValid);
        }
        
        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const icon = element.querySelector('i');
            
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-check');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                icon.classList.remove('fa-check');
                icon.classList.add('fa-times');
            }
        }
        
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmField = document.getElementById('confirm_password');
            
            if (confirmPassword === '') {
                confirmField.style.borderColor = '';
            } else if (password === confirmPassword) {
                confirmField.style.borderColor = '#198754';
            } else {
                confirmField.style.borderColor = '#dc3545';
            }
        }
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check if passwords match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            // Check password strength
            const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/;
            if (!passwordRegex.test(password)) {
                e.preventDefault();
                alert('Please ensure your password meets all the requirements.');
                return;
            }
        });