// BookIT Payment Gateway JavaScript
// Handles payment processing and auto-submission

document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit payment after 3 seconds if no errors
    const errorElement = document.querySelector('.error-message');
    const pendingPaymentForm = document.querySelector('form[id="paymentForm"]');
    
    if (!errorElement && pendingPaymentForm) {
        initializePaymentTimer();
    }
    
    // Initialize payment progress steps
    initializePaymentSteps();
    
    // Handle manual submission buttons
    const submitButton = document.querySelector('.btn-success-payment');
    if (submitButton) {
        submitButton.addEventListener('click', function() {
            submitPayment();
        });
    }
});

/**
 * Initialize payment timer for auto-submission
 */
function initializePaymentTimer() {
    let timeRemaining = 3;
    const timerElement = document.getElementById('paymentTimer');
    
    if (timerElement) {
        timerElement.textContent = timeRemaining;
        
        const countdownInterval = setInterval(() => {
            timeRemaining--;
            timerElement.textContent = timeRemaining;
            
            if (timeRemaining <= 0) {
                clearInterval(countdownInterval);
                // Submit payment form automatically
                const form = document.querySelector('form[id="paymentForm"]');
                if (form) {
                    form.submit();
                }
            }
        }, 1000);
    }
}

/**
 * Initialize payment progress steps animation
 */
function initializePaymentSteps() {
    const stepsList = document.querySelector('.processing-steps');
    if (!stepsList) return;
    
    const steps = stepsList.querySelectorAll('li');
    let currentStep = 0;
    
    // Mark first step as current
    if (steps.length > 0) {
        steps[0].classList.add('current');
    }
    
    // Animate steps based on processing
    const animateNextStep = () => {
        if (currentStep < steps.length) {
            // Mark current step as done
            if (currentStep > 0) {
                steps[currentStep - 1].classList.remove('current');
                steps[currentStep - 1].classList.add('done');
            }
            
            // Mark next step as current
            if (currentStep < steps.length) {
                steps[currentStep].classList.add('current');
            }
            
            currentStep++;
            
            // Schedule next animation (every 1 second)
            if (currentStep < steps.length) {
                setTimeout(animateNextStep, 1000);
            }
        }
    };
    
    // Start animation if there are steps
    if (steps.length > 0) {
        setTimeout(animateNextStep, 1000);
    }
}

/**
 * Submit payment form
 */
function submitPayment() {
    const form = document.querySelector('form[id="paymentForm"]');
    if (form) {
        // Add loading state to button
        const submitButton = form.querySelector('.btn-success-payment');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        }
        
        // Submit form
        form.submit();
    }
}

/**
 * Cancel payment and go back to checkout
 */
function cancelPayment() {
    const backUrl = document.querySelector('.btn-cancel-payment');
    if (backUrl) {
        window.location.href = backUrl.href;
    } else {
        window.history.back();
    }
}

/**
 * Display payment status with animation
 * @param {string} status - Status: success, failure, processing
 * @param {string} message - Status message
 */
function displayPaymentStatus(status, message) {
    const container = document.querySelector('.payment-body');
    if (!container) return;
    
    let className = 'alert-info';
    let icon = 'fas fa-info-circle';
    
    if (status === 'success') {
        className = 'alert-success';
        icon = 'fas fa-check-circle';
    } else if (status === 'failure') {
        className = 'alert-danger';
        icon = 'fas fa-exclamation-circle';
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${className} fade show`;
    alertDiv.innerHTML = `
        <i class="${icon} me-2"></i>
        ${message}
    `;
    
    container.insertBefore(alertDiv, container.firstChild);
}

/**
 * Format currency for display
 * @param {number} amount - Amount to format
 * @returns {string} Formatted currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

/**
 * Get payment method display name
 * @param {string} method - Payment method code
 * @returns {string} Display name
 */
function getPaymentMethodName(method) {
    const methods = {
        'gcash': 'GCash',
        'paymaya': 'PayMaya',
        'bank_transfer': 'Bank Transfer',
        'credit_card': 'Credit Card'
    };
    
    return methods[method] || method;
}

/**
 * Update payment amount display
 * @param {number} amount - New amount
 */
function updatePaymentAmount(amount) {
    const amountElement = document.querySelector('.payment-amount .amount');
    if (amountElement) {
        amountElement.textContent = formatCurrency(amount);
    }
}

/**
 * Handle payment error
 * @param {string} error - Error message
 */
function handlePaymentError(error) {
    displayPaymentStatus('failure', error);
    
    // Re-enable submit button
    const submitButton = document.querySelector('.btn-success-payment');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Complete Payment';
    }
}
