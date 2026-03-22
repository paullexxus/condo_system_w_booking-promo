// Toggle password visibility
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = passwordField.parentNode.querySelector('.password-toggle i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Password validation and requirements checking
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Password requirements elements
            const reqLength = document.getElementById('req-length');
            const reqUppercase = document.getElementById('req-uppercase');
            const reqLowercase = document.getElementById('req-lowercase');
            const reqNumber = document.getElementById('req-number');
            const reqSpecial = document.getElementById('req-special');
            
            // Check password requirements
            function checkPasswordRequirements(password) {
                // Check length
                if (password.length >= 8) {
                    reqLength.classList.add('valid');
                    reqLength.classList.remove('invalid');
                    reqLength.querySelector('i').className = 'fas fa-check';
                } else {
                    reqLength.classList.add('invalid');
                    reqLength.classList.remove('valid');
                    reqLength.querySelector('i').className = 'fas fa-times';
                }
                
                // Check uppercase
                if (/[A-Z]/.test(password)) {
                    reqUppercase.classList.add('valid');
                    reqUppercase.classList.remove('invalid');
                    reqUppercase.querySelector('i').className = 'fas fa-check';
                } else {
                    reqUppercase.classList.add('invalid');
                    reqUppercase.classList.remove('valid');
                    reqUppercase.querySelector('i').className = 'fas fa-times';
                }
                
                // Check lowercase
                if (/[a-z]/.test(password)) {
                    reqLowercase.classList.add('valid');
                    reqLowercase.classList.remove('invalid');
                    reqLowercase.querySelector('i').className = 'fas fa-check';
                } else {
                    reqLowercase.classList.add('invalid');
                    reqLowercase.classList.remove('valid');
                    reqLowercase.querySelector('i').className = 'fas fa-times';
                }
                
                // Check number
                if (/\d/.test(password)) {
                    reqNumber.classList.add('valid');
                    reqNumber.classList.remove('invalid');
                    reqNumber.querySelector('i').className = 'fas fa-check';
                } else {
                    reqNumber.classList.add('invalid');
                    reqNumber.classList.remove('valid');
                    reqNumber.querySelector('i').className = 'fas fa-times';
                }
                
                // Check special character
                if (/[\W_]/.test(password)) {
                    reqSpecial.classList.add('valid');
                    reqSpecial.classList.remove('invalid');
                    reqSpecial.querySelector('i').className = 'fas fa-check';
                } else {
                    reqSpecial.classList.add('invalid');
                    reqSpecial.classList.remove('valid');
                    reqSpecial.querySelector('i').className = 'fas fa-times';
                }
            }
            
            // Check password match
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword === '') {
                    confirmPasswordInput.classList.remove('is-invalid', 'is-valid');
                } else if (password === confirmPassword) {
                    confirmPasswordInput.classList.remove('is-invalid');
                    confirmPasswordInput.classList.add('is-valid');
                } else {
                    confirmPasswordInput.classList.remove('is-valid');
                    confirmPasswordInput.classList.add('is-invalid');
                }
            }
            
            // Event listeners
            passwordInput.addEventListener('input', function() {
                checkPasswordRequirements(this.value);
                checkPasswordMatch();
            });
            
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            // Form validation before submission
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Check if passwords match
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                // Check password strength
                const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/;
                if (!passwordRegex.test(password)) {
                    e.preventDefault();
                    alert('Password does not meet the requirements!');
                    return false;
                }
                
                // Check file types for valid IDs
                const validId1 = document.getElementById('valid_id1');
                const validId2 = document.getElementById('valid_id2');
                
                if (validId1.files.length > 0) {
                    const file1 = validId1.files[0];
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    if (!allowedTypes.includes(file1.type)) {
                        e.preventDefault();
                        alert('Invalid file type for Valid ID 1. Please upload JPG, PNG, or PDF files only.');
                        return false;
                    }
                }
                
                if (validId2.files.length > 0) {
                    const file2 = validId2.files[0];
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    if (!allowedTypes.includes(file2.type)) {
                        e.preventDefault();
                        alert('Invalid file type for Valid ID 2. Please upload JPG, PNG, or PDF files only.');
                        return false;
                    }
                }
            });
        });