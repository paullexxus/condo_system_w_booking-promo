// BookIT Book Amenity JavaScript
// Handles amenity time selection and calculations

document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate end time when start time changes
    initializeTimeCalculation();
    
    // Update pricing when time changes
    initializePricingUpdate();
    
    // Form validation
    initializeFormValidation();
    
    // Amenity card interactions
    initializeAmenityCardInteractions();
});

/**
 * Initialize time calculation for amenity bookings
 */
function initializeTimeCalculation() {
    const startTimeInputs = document.querySelectorAll('input[name="start_time"]');
    
    startTimeInputs.forEach(function(startTimeInput) {
        startTimeInput.addEventListener('change', function() {
            const startTime = this.value;
            const endTimeInput = this.closest('.row') ? this.closest('.row').querySelector('input[name="end_time"]') : document.querySelector('input[name="end_time"]');
            
            if (startTime && endTimeInput) {
                // Calculate default end time (1 hour after start)
                const endTime = calculateEndTime(startTime, 1);
                endTimeInput.value = endTime;
                
                // Update pricing
                updateAmenityPricing();
            }
        });
    });
    
    // Handle end time changes
    const endTimeInputs = document.querySelectorAll('input[name="end_time"]');
    endTimeInputs.forEach(function(endTimeInput) {
        endTimeInput.addEventListener('change', function() {
            validateTimeRange();
            updateAmenityPricing();
        });
    });
}

/**
 * Calculate end time based on start time and duration
 * @param {string} startTime - Start time in HH:MM format
 * @param {number} hours - Number of hours to add
 * @returns {string} End time in HH:MM format
 */
function calculateEndTime(startTime, hours = 1) {
    const [hoursStr, minutesStr] = startTime.split(':');
    const startHour = parseInt(hoursStr);
    const startMinute = parseInt(minutesStr);
    
    let endHour = startHour + hours;
    let endMinute = startMinute;
    
    // Handle day overflow
    if (endHour >= 24) {
        endHour = endHour - 24;
    }
    
    return endHour.toString().padStart(2, '0') + ':' + 
           endMinute.toString().padStart(2, '0');
}

/**
 * Calculate hours between start and end time
 * @param {string} startTime - Start time in HH:MM format
 * @param {string} endTime - End time in HH:MM format
 * @returns {number} Number of hours
 */
function calculateHours(startTime, endTime) {
    const [startHours, startMinutes] = startTime.split(':').map(Number);
    const [endHours, endMinutes] = endTime.split(':').map(Number);
    
    let start = startHours + startMinutes / 60;
    let end = endHours + endMinutes / 60;
    
    // Handle overnight bookings
    if (end <= start) {
        end += 24;
    }
    
    // Round up to nearest hour
    return Math.ceil(end - start);
}

/**
 * Validate time range
 * @returns {boolean} True if valid
 */
function validateTimeRange() {
    const startTimeInput = document.querySelector('input[name="start_time"]');
    const endTimeInput = document.querySelector('input[name="end_time"]');
    
    if (!startTimeInput || !endTimeInput) return true;
    
    const startTime = startTimeInput.value;
    const endTime = endTimeInput.value;
    
    if (!startTime || !endTime) return true;
    
    const hours = calculateHours(startTime, endTime);
    
    if (hours <= 0) {
        showAlert('End time must be after start time', 'danger');
        endTimeInput.value = calculateEndTime(startTime, 1);
        return false;
    }
    
    return true;
}

/**
 * Initialize pricing update listener
 */
function initializePricingUpdate() {
    const dateInputs = document.querySelectorAll('input[name="booking_date"]');
    dateInputs.forEach(input => {
        input.addEventListener('change', updateAmenityPricing);
    });
}

/**
 * Update amenity pricing based on time selection
 */
function updateAmenityPricing() {
    const startTimeInput = document.querySelector('input[name="start_time"]');
    const endTimeInput = document.querySelector('input[name="end_time"]');
    const hourlyRateElement = document.querySelector('[data-hourly-rate]');
    const pricingElement = document.querySelector('.amenity-pricing-estimate');
    
    if (!startTimeInput || !endTimeInput || !hourlyRateElement) return;
    
    const startTime = startTimeInput.value;
    const endTime = endTimeInput.value;
    
    if (!startTime || !endTime) return;
    
    const hourlyRate = parseFloat(hourlyRateElement.dataset.hourlyRate);
    const hours = calculateHours(startTime, endTime);
    const totalAmount = hourlyRate * hours;
    
    if (pricingElement) {
        pricingElement.innerHTML = `
            <div class="pricing-breakdown">
                <div class="pricing-row">
                    <span>Duration:</span>
                    <strong>${hours} hour(s)</strong>
                </div>
                <div class="pricing-row">
                    <span>Hourly Rate:</span>
                    <strong>₱${hourlyRate.toFixed(2)}</strong>
                </div>
                <div class="pricing-row total">
                    <span>Total:</span>
                    <strong>₱${totalAmount.toFixed(2)}</strong>
                </div>
            </div>
        `;
    }
    
    // Update hidden input with total
    const totalInput = document.querySelector('input[name="total_amount"]');
    if (totalInput) {
        totalInput.value = totalAmount.toFixed(2);
    }
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const bookingForm = document.querySelector('form[method="POST"]');
    if (!bookingForm) return;
    
    bookingForm.addEventListener('submit', function(e) {
        const startTimeInput = document.querySelector('input[name="start_time"]');
        const endTimeInput = document.querySelector('input[name="end_time"]');
        
        if (startTimeInput && endTimeInput) {
            if (!validateTimeRange()) {
                e.preventDefault();
                return false;
            }
        }
    });
}

/**
 * Initialize amenity card interactions
 */
function initializeAmenityCardInteractions() {
    const amenityCards = document.querySelectorAll('.amenity-card');
    amenityCards.forEach(card => {
        card.addEventListener('click', function() {
            // Add visual feedback
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Search form enhancement
    const searchForm = document.querySelector('form[action=""]');
    if (searchForm) {
        const branchSelect = searchForm.querySelector('select[name="branch_id"]');
        const dateInput = searchForm.querySelector('input[name="booking_date"]');
        
        if (branchSelect && dateInput) {
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
            
            // Auto-submit when both fields are filled
            [branchSelect, dateInput].forEach(element => {
                element.addEventListener('change', function() {
                    if (branchSelect.value && dateInput.value) {
                        // Optional: auto-submit after a short delay
                        // setTimeout(() => searchForm.submit(), 500);
                    }
                });
            });
        }
    }
});
