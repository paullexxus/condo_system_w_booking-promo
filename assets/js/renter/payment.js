function selectPaymentMethod(method) {
    // I-remove ang selected class sa lahat ng cards
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // I-add ang selected class sa clicked card
    event.currentTarget.classList.add('selected');
    
    // I-set ang selected payment method
    document.getElementById('selectedPaymentMethod').value = method;
    
    // I-show ang transaction reference field
    document.getElementById('transactionReferenceDiv').style.display = 'block';
    
    // I-enable ang pay button
    document.getElementById('payButton').disabled = false;
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
    
    // I-show ang loading state
    const payButton = document.getElementById('payButton');
    payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    payButton.disabled = true;
});