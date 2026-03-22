document.addEventListener('DOMContentLoaded', function() {
const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
const bookingForm = document.getElementById('bookingForm');
const checkInDate = document.getElementById('checkInDate');
const checkOutDate = document.getElementById('checkOutDate');
const numberOfGuests = document.getElementById('numberOfGuests');
const pricePerNightText = document.getElementById('pricePerNightText');
const basePrice = document.getElementById('basePrice');
const totalPrice = document.getElementById('totalPrice');
let currentPricePerNight = 350; // Store the current price
        
// Set minimum date to today
const today = new Date().toISOString().split('T')[0];
        checkInDate.min = today;
        checkOutDate.min = today;

        // Book Now buttons
        document.querySelectorAll('.book-now-btn').forEach(button => {
            button.addEventListener('click', function() {
                const branchId = this.getAttribute('data-branch-id');
                const branchName = this.getAttribute('data-branch-name');
                const branchPrice = this.getAttribute('data-branch-price');
                const branchLocation = this.getAttribute('data-branch-location');
                
                // Update modal content
                document.getElementById('modalBranchName').textContent = branchName;
                document.getElementById('modalBranchLocation').innerHTML = 
                    `<i class="fas fa-map-marker-alt"></i> ${branchLocation}`;
                document.getElementById('selectedBranchId').value = branchId;
                
                // Store and update prices - ensure it's a valid number
                let price = parseFloat(branchPrice);
                if (!isNaN(price) && price > 0) {
                    currentPricePerNight = price;
                } else {
                    // Fallback to 2500 if price is invalid
                    currentPricePerNight = 2500;
                }
                console.log('Selected price:', currentPricePerNight); // Debug
                updatePrices(currentPricePerNight);
                
                // Show modal
                bookingModal.show();
            });
        });

        // Update check-out date minimum when check-in date changes
        checkInDate.addEventListener('change', function() {
            checkOutDate.min = this.value;
            if (checkOutDate.value && checkOutDate.value < this.value) {
                checkOutDate.value = '';
            }
            updatePrices(currentPricePerNight);
        });

        // Update prices when dates or guests change
        checkOutDate.addEventListener('change', function() {
            updatePrices(currentPricePerNight);
        });
        numberOfGuests.addEventListener('change', function() {
            updatePrices(currentPricePerNight);
        });

        // Check availability button
        document.getElementById('checkAvailabilityBtn').addEventListener('click', function() {
            if (!checkInDate.value || !checkOutDate.value) {
                alert('Please select both check-in and check-out dates.');
                return;
            }
            
            // Simulate availability check
            const available = Math.random() > 0.3; // 70% chance of availability
            if (available) {
                alert('✅ This unit is available for your selected dates!');
            } else {
                alert('❌ Sorry, this unit is not available for the selected dates. Please try different dates.');
            }
        });

        // Booking form submission
        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!checkInDate.value || !checkOutDate.value) {
                alert('Please select both check-in and check-out dates.');
                return;
            }
            
            // Here you would typically send the data to your server
            const formData = {
                branch_id: document.getElementById('selectedBranchId').value,
                check_in_date: checkInDate.value,
                check_out_date: checkOutDate.value,
                number_of_guests: numberOfGuests.value
            };
            
            console.log('Booking data:', formData);
            alert('Booking request submitted! You will be redirected to the reservation page.');
            
            // Redirect to reservation page or process payment
            bookingModal.hide();
        });

        // Price calculation function
        function updatePrices(pricePerNight = 350) {
            // Validate and convert price to number
            let price = parseFloat(pricePerNight);
            if (isNaN(price) || price <= 0) {
                price = 350;
            }
            
            const checkIn = new Date(checkInDate.value);
            const checkOut = new Date(checkOutDate.value);
            
            if (checkInDate.value && checkOutDate.value && checkOut > checkIn) {
                const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                const baseAmount = price * nights;
                const cleaningFee = 150;
                const serviceFee = 180;
                const totalAmount = baseAmount + cleaningFee + serviceFee;
                
                // Format numbers safely - ensure they're numbers, not objects
                const formattedPrice = Number(price).toFixed(2);
                const formattedBase = Math.round(baseAmount);
                const formattedTotal = Math.round(totalAmount);
                
                pricePerNightText.textContent = `₱${formattedPrice} x ${nights} nights`;
                basePrice.textContent = `₱${formattedBase.toLocaleString()}`;
                totalPrice.textContent = `₱${formattedTotal.toLocaleString()}`;
            } else {
                const formattedPrice = Number(price).toFixed(2);
                pricePerNightText.textContent = `₱${formattedPrice} x 0 nights`;
                basePrice.textContent = '₱0';
                totalPrice.textContent = '₱330';
            }
        }

        // Initialize prices
        updatePrices();
    });