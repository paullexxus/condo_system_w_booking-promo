// BookIT Checkout JavaScript
// Handles payment method selection and form validation

document.addEventListener('DOMContentLoaded', function() {
    // Handle payment method selection with visual feedback
    const radioButtons = document.querySelectorAll('.radio-input');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove selected class from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to the checked card's parent
            if (this.checked) {
                this.closest('.payment-method-card').classList.add('selected');
            }
        });
    });
    
    // Form validation
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                e.preventDefault();
                showAlert('Please select a payment method.', 'warning');
            }
        });
    }
    
    // Initialize tooltips for payment methods
    initializePaymentMethodTooltips();
});

/**
 * Initialize tooltips for payment methods
 */
function initializePaymentMethodTooltips() {
    const paymentCards = document.querySelectorAll('.payment-method-card');
    paymentCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            const icon = this.querySelector('i');
            if (icon) {
                icon.style.transform = 'scale(1.2) rotate(5deg)';
                icon.style.transition = 'transform 0.3s ease';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            const icon = this.querySelector('i');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0)';
            }
        });
    });
}

/**
 * Show alert message to user
 * @param {string} message - Message to display
 * @param {string} type - Alert type: success, warning, danger, info
 */
function showAlert(message, type = 'info') {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insert at top of page
    const container = document.querySelector('.checkout-body') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/**
 * Format currency amount
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
 * Update pricing display when payment method changes
 */
function updatePricingDisplay() {
    const priceBreakdown = document.querySelector('.pricing-breakdown');
    if (!priceBreakdown) return;
    
    // Get all pricing rows
    const rows = priceBreakdown.querySelectorAll('.pricing-row');
    rows.forEach(row => {
        const value = row.querySelector('[data-amount]');
        if (value) {
            const amount = parseFloat(value.dataset.amount);
            value.textContent = formatCurrency(amount);
        }
    });
}

/**
 * Validate form before submission
 * @returns {boolean} True if form is valid
 */
function validateCheckoutForm() {
    const form = document.getElementById('checkoutForm');
    if (!form) return true;
    
    // Check if payment method selected
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethod) {
        showAlert('Please select a payment method', 'warning');
        return false;
    }
    
    // Check if terms accepted (if applicable)
    const termsCheckbox = document.querySelector('input[name="accept_terms"]');
    if (termsCheckbox && !termsCheckbox.checked) {
        showAlert('Please accept the terms and conditions', 'warning');
        return false;
    }
    
    return true;
}

/**
 * Submit checkout form
 */
function submitCheckoutForm() {
    if (validateCheckoutForm()) {
        document.getElementById('checkoutForm').submit();
    }
}
