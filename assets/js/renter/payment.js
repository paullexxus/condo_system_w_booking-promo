function selectPaymentMethod(method) {
    // I-remove ang selected class sa lahat ng cards
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // I-add ang selected class sa clicked card
    const currentTarget = event.currentTarget || event.target.closest('.payment-method-card');
    if (currentTarget) currentTarget.classList.add('selected');
    
    // I-set ang selected payment method
    document.getElementById('selectedPaymentMethod').value = method;
    
    // I-show ang transaction reference field at payment proof field
    document.getElementById('transactionReferenceDiv').style.display = 'block';
    
    var proofDiv = document.getElementById('paymentProofDiv');
    if (proofDiv) proofDiv.style.display = 'block';
    
    // I-enable ang pay button
    document.getElementById('payButton').disabled = false;
}

function updatePaymentAmount(amount) {
    document.getElementById('paymentAmountInput').value = amount;
    
    // Update display in summary if it exists
    const summaryTotalEl = document.getElementById('summaryTotalDisplay');
    if (summaryTotalEl) {
        summaryTotalEl.textContent = '₱' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Update is_partial indicator
    const planPartial = document.getElementById('planPartial');
    document.getElementById('isPartialInput').value = (planPartial && planPartial.checked) ? "1" : "0";
}

// I-validate ang form before submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const paymentMethod = document.getElementById('selectedPaymentMethod').value;
    const transactionRef = document.querySelector('input[name="transaction_reference"]').value;
    
    if (!paymentMethod) {
        e.preventDefault();
        alert('Please select a payment method.');
        return;
    }
    
    if (!transactionRef.trim()) {
        e.preventDefault();
        alert('Please enter your transaction reference number.');
        return;
    }
    
    const paymentProof = document.getElementById('paymentProofInput');
    if (paymentProof && paymentProof.files.length === 0) {
        e.preventDefault();
        alert('Please upload your payment proof/receipt.');
        return;
    }
    
    // I-show ang loading state
    const payButton = document.getElementById('payButton');
    payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    payButton.disabled = true;
});

// Timer Logic for Hold expiration
document.addEventListener('DOMContentLoaded', function() {
    const timerEl = document.getElementById('payment-timer');
    if (timerEl) {
        const expiresAtStr = timerEl.dataset.expires;
        if (!expiresAtStr) return;
        
        // Convert SQL timestamp string to Date object
        // Assuming format is YYYY-MM-DD HH:MM:SS
        const t = expiresAtStr.split(/[- :]/);
        const expiresAt = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]).getTime();
        
        const updateTimer = () => {
            const now = new Date().getTime();
            const distance = expiresAt - now;
            
            if (distance < 0) {
                timerEl.innerHTML = "EXPIRED";
                timerEl.classList.remove('text-warning');
                timerEl.classList.add('text-primary'); // Changed to primary to match theme but stand out
                timerEl.style.color = "#dc3545"; // Bootstrap danger color
                
                // Disable payment
                const payBtn = document.getElementById('payButton');
                if (payBtn) {
                    payBtn.disabled = true;
                    payBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Hold Expired';
                }
                
                // Refresh page after a delay to show expiration error
                setTimeout(() => location.reload(), 2000);
                return;
            }
            
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            timerEl.innerHTML = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
            setTimeout(updateTimer, 1000);
        };
        
        updateTimer();
    }
});