// Client-side validation for forgot/reset/otp forms
document.addEventListener('DOMContentLoaded', function(){
    const forgotForm = document.getElementById('forgotForm');
    if (forgotForm){
        forgotForm.addEventListener('submit', function(e){
            const email = document.getElementById('email');
            if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)){
                e.preventDefault();
                alert('Please enter a valid email address.');
                email.focus();
                return false;
            }
        });
    }

    const otpForm = document.getElementById('otpForm');
    if (otpForm){
        otpForm.addEventListener('submit', function(e){
            const otp = document.getElementById('otp');
            if (!otp || !/^[0-9]{6}$/.test(otp.value)){
                e.preventDefault();
                alert('Please enter the 6-digit OTP.');
                otp.focus();
                return false;
            }
        });
    }

    const resetForm = document.getElementById('resetForm');
    if (resetForm){
        resetForm.addEventListener('submit', function(e){
            const p1 = document.getElementById('password');
            const p2 = document.getElementById('confirm_password');
            if (!p1 || p1.value.length < 8){
                e.preventDefault();
                alert('Password must be at least 8 characters.');
                p1.focus();
                return false;
            }
            if (p1.value !== p2.value){
                e.preventDefault();
                alert('Passwords do not match.');
                p2.focus();
                return false;
            }
        });
    }
});
