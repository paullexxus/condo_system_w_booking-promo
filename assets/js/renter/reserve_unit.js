// Auto-update check-out date when check-in date changes
document.querySelector('input[name="check_in_date"]').addEventListener('change', function() {
    const checkInDate = new Date(this.value);
    const checkOutDate = new Date(checkInDate);
    checkOutDate.setDate(checkOutDate.getDate() + 1);
    
    const checkOutInput = document.querySelector('input[name="check_out_date"]');
    checkOutInput.min = checkOutDate.toISOString().split('T')[0];
    checkOutInput.value = checkOutDate.toISOString().split('T')[0];
});

// Dynamic pricing calculation for amenities
document.addEventListener('DOMContentLoaded', function() {
    const amenityCheckboxes = document.querySelectorAll('.amenity-checkbox');
    
    amenityCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const unitCard = this.closest('.unit-card');
            const unitId = unitCard.querySelector('input[name="unit_id"]').value;
            const unitRate = parseFloat(unitCard.querySelector('.price-tag').textContent.replace(/[₱,]/g, '').split('/')[0]);
            const securityDeposit = parseFloat(unitCard.querySelector('.security-deposit span:last-child').textContent.replace(/[₱,]/g, ''));
            
            // Calculate total days from date inputs
            const checkInInput = document.querySelector('input[name="check_in_date"]');
            const checkOutInput = document.querySelector('input[name="check_out_date"]');
            let totalDays = 1;
            
            if (checkInInput && checkInInput.value && checkOutInput && checkOutInput.value) {
                const checkInDate = new Date(checkInInput.value);
                const checkOutDate = new Date(checkOutInput.value);
                const timeDiff = checkOutDate.getTime() - checkInDate.getTime();
                totalDays = Math.ceil(timeDiff / (1000 * 3600 * 24));
                totalDays = totalDays > 0 ? totalDays : 1;
            }
            
            // Calculate amenity total for this unit
            let amenityTotal = 0;
            const unitAmenityCheckboxes = unitCard.querySelectorAll('input[name="amenities[]"]:checked');
            unitAmenityCheckboxes.forEach(function(amenity) {
                amenityTotal += parseFloat(amenity.dataset.rate) * totalDays;
            });
            
            // Update display
            const amenityCostDiv = document.querySelector(`#amenity-total-${unitId}`);
            const totalCostSpan = document.querySelector(`#total-cost-${unitId}`);
            const amenityCostSection = document.querySelector(`#amenity-total-${unitId}`).parentElement;
            
            if (amenityTotal > 0) {
                amenityCostDiv.textContent = '₱' + amenityTotal.toFixed(2);
                amenityCostSection.style.display = 'block';
            } else {
                amenityCostSection.style.display = 'none';
            }
            
            // Update total
            const unitTotal = unitRate * totalDays;
            const grandTotal = unitTotal + amenityTotal + securityDeposit;
            totalCostSpan.textContent = '₱' + grandTotal.toFixed(2);
        });
    });
});