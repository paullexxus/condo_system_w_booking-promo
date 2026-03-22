<?php
/**
 * BookIT Renter Functions
 * Consolidated payment, validation, and utility functions for renter module
 * Consolidates duplicate logic from checkout.php, payment_gateway.php, payment.php, and payment_success.php
 */

/**
 * Get all available payment methods with descriptions
 * 
 * @return array Payment methods with metadata
 */
function getPaymentMethods() {
    return [
        'gcash' => [
            'name' => 'GCash',
            'icon' => 'fab fa-mobile',
            'description' => 'Send money via GCash app instantly',
            'processing_time' => '0-5 minutes',
            'fee' => 0
        ],
        'paymaya' => [
            'name' => 'PayMaya',
            'icon' => 'fas fa-credit-card',
            'description' => 'Digital wallet and payment solution',
            'processing_time' => '0-10 minutes',
            'fee' => 0
        ],
        'bank_transfer' => [
            'name' => 'Bank Transfer',
            'icon' => 'fas fa-university',
            'description' => 'Direct transfer to our business account',
            'processing_time' => '1-2 hours',
            'fee' => 0
        ],
        'credit_card' => [
            'name' => 'Credit Card',
            'icon' => 'fas fa-credit-card',
            'description' => 'Visa, Mastercard, American Express',
            'processing_time' => '5-15 minutes',
            'fee' => 0
        ]
    ];
}

/**
 * Validate payment request from POST/GET
 * 
 * @param string $type - Type of booking: 'reservation' or 'amenity'
 * @param int $id - Booking ID
 * @param int $userId - User ID
 * @return array|false Booking details or false if invalid
 */
function getBookingForPayment($type, $id, $userId) {
    global $conn;
    
    // Validate input
    if ($id <= 0 || !in_array($type, ['reservation', 'amenity'])) {
        return false;
    }
    
    $type = sanitize_input($type);
    $id = (int)$id;
    $userId = (int)$userId;
    
    if ($type == 'reservation') {
        return get_single_result(
            "SELECT r.*, u.unit_number, u.unit_type, u.monthly_rate, u.security_deposit, 
                    b.branch_name, b.address, b.city
            FROM reservations r 
            JOIN units u ON r.unit_id = u.unit_id 
            JOIN branches b ON r.branch_id = b.branch_id 
            WHERE r.reservation_id = ? AND r.user_id = ? AND r.status IN ('pending', 'approved')",
            [$id, $userId]
        );
    } else if ($type == 'amenity') {
        return get_single_result(
            "SELECT ab.*, a.amenity_name, a.description, a.hourly_rate, b.branch_name 
            FROM amenity_bookings ab 
            JOIN amenities a ON ab.amenity_id = a.amenity_id 
            JOIN branches b ON ab.branch_id = b.branch_id 
            WHERE ab.booking_id = ? AND ab.user_id = ? AND ab.status = 'pending'",
            [$id, $userId]
        );
    }
    
    return false;
}

/**
 * Calculate total amount for booking (including tax and deposits)
 * 
 * @param array $booking - Booking details
 * @param string $type - Type: 'reservation' or 'amenity'
 * @return array Amount breakdown
 */
function calculateBookingTotal($booking, $type) {
    $subtotal = 0;
    $tax = 0;
    $deposit = 0;
    
    if ($type == 'reservation') {
        // Calculate rental amount
        if (isset($booking['check_in_date']) && isset($booking['check_out_date'])) {
            $checkIn = new DateTime($booking['check_in_date']);
            $checkOut = new DateTime($booking['check_out_date']);
            $days = $checkOut->diff($checkIn)->days;
            $days = ($days == 0) ? 1 : $days;  // Minimum 1 day
            
            $daily_rate = $booking['monthly_rate'] / 30;  // Convert monthly to daily rate
            $subtotal = $daily_rate * $days;
        }
        
        // Add security deposit
        $deposit = floatval($booking['security_deposit'] ?? 0);
        $subtotal += $deposit;
        
        // Calculate tax (5%)
        $tax = $subtotal * 0.05;
    } else if ($type == 'amenity') {
        // Calculate amenity rental time
        if (isset($booking['start_time']) && isset($booking['end_time'])) {
            $startTime = new DateTime($booking['start_time']);
            $endTime = new DateTime($booking['end_time']);
            $hours = ceil($endTime->diff($startTime)->h + ($endTime->diff($startTime)->i / 60));
            $hours = ($hours == 0) ? 1 : $hours;  // Minimum 1 hour
            
            $subtotal = floatval($booking['hourly_rate']) * $hours;
        }
        
        // Calculate tax (5%)
        $tax = $subtotal * 0.05;
    }
    
    $total = $subtotal + $tax;
    
    return [
        'subtotal' => round($subtotal, 2),
        'tax' => round($tax, 2),
        'deposit' => round($deposit, 2),
        'total' => round($total, 2),
        'currency' => '₱'
    ];
}

/**
 * Create payment record in database
 * 
 * @param array $pending - Pending payment session data
 * @param int $userId - User ID
 * @param string $transactionRef - Transaction reference
 * @return string|false Payment ID or false on error
 */
function createPaymentRecord($pending, $userId, $transactionRef) {
    global $conn;
    
    try {
        $bookingId = $pending['booking_id'];
        $type = $pending['type'];
        $paymentId = bin2hex(random_bytes(16));
        
        $reservationId = ($type == 'reservation') ? $bookingId : null;
        $amenityId = ($type == 'amenity') ? $bookingId : null;
        
        $paymentSql = "INSERT INTO payments 
                      (payment_id, user_id, reservation_id, amenity_booking_id, amount, payment_method, status, transaction_ref, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW())";
        
        $result = execute_query($paymentSql, [
            $paymentId,
            $userId,
            $reservationId,
            $amenityId,
            $pending['total'],
            $pending['method'],
            $transactionRef
        ]);
        
        if (!$result) {
            return false;
        }
        
        return $paymentId;
    } catch (Exception $e) {
        error_log("Payment creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update booking status after successful payment
 * 
 * @param string $type - Type: 'reservation' or 'amenity'
 * @param int $bookingId - Booking ID
 * @return bool Success status
 */
function updateBookingAfterPayment($type, $bookingId) {
    global $conn;
    
    try {
        if ($type == 'reservation') {
            execute_query(
                "UPDATE reservations SET status = 'confirmed', payment_status = 'paid' WHERE reservation_id = ?",
                [$bookingId]
            );
        } else if ($type == 'amenity') {
            execute_query(
                "UPDATE amenity_bookings SET status = 'confirmed', payment_status = 'paid' WHERE booking_id = ?",
                [$bookingId]
            );
        } else {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Booking update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send payment confirmation notifications
 * 
 * @param int $userId - User ID
 * @param string $type - Type: 'reservation' or 'amenity'
 * @param int $bookingId - Booking ID
 * @param array $bookingDetails - Booking details for email
 * @return bool Success status
 */
function sendPaymentNotification($userId, $type, $bookingId, $bookingDetails = []) {
    try {
        if ($type == 'reservation') {
            $title = "Payment Confirmed - Reservation #$bookingId";
            $message = "Your payment has been successfully processed. Your reservation is now confirmed.";
            if (!empty($bookingDetails)) {
                $message .= "\n\nUnit: " . $bookingDetails['unit_number'] . " (" . $bookingDetails['unit_type'] . ")";
            }
        } else {
            $title = "Payment Confirmed - Amenity Booking #$bookingId";
            $message = "Your payment has been successfully processed. Your amenity booking is now confirmed.";
            if (!empty($bookingDetails)) {
                $message .= "\n\nAmenity: " . $bookingDetails['amenity_name'];
            }
        }
        
        sendNotification($userId, $title, $message, 'payment', 'system');
        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return true;  // Don't fail payment if notification fails
    }
}

/**
 * Validate payment method
 * 
 * @param string $method - Payment method code
 * @return bool Valid or not
 */
function isValidPaymentMethod($method) {
    $validMethods = ['gcash', 'paymaya', 'bank_transfer', 'credit_card'];
    return in_array($method, $validMethods);
}

/**
 * Format currency for display
 * 
 * @param float $amount - Amount to format
 * @param string $currency - Currency symbol (default: ₱)
 * @return string Formatted amount
 */
function formatCurrency($amount, $currency = '₱') {
    return $currency . number_format($amount, 2, '.', ',');
}

/**
 * Get booking summary for display
 * 
 * @param array $booking - Booking details
 * @param string $type - Type: 'reservation' or 'amenity'
 * @return array Summary data
 */
function getBookingSummary($booking, $type) {
    $summary = [];
    
    if ($type == 'reservation') {
        $summary = [
            'title' => 'Unit Reservation',
            'unit' => $booking['unit_number'] . ' (' . $booking['unit_type'] . ')',
            'branch' => $booking['branch_name'],
            'location' => $booking['address'] . ', ' . $booking['city'],
            'checkIn' => date('M d, Y', strtotime($booking['check_in_date'])),
            'checkOut' => date('M d, Y', strtotime($booking['check_out_date'])),
            'specialty' => 'Monthly Rate: ' . formatCurrency($booking['monthly_rate']),
            'icon' => 'fas fa-building'
        ];
    } else if ($type == 'amenity') {
        $summary = [
            'title' => 'Amenity Booking',
            'amenity' => $booking['amenity_name'],
            'branch' => $booking['branch_name'],
            'description' => $booking['description'],
            'startTime' => date('M d, Y H:i', strtotime($booking['start_time'])),
            'endTime' => date('M d, Y H:i', strtotime($booking['end_time'])),
            'specialty' => 'Hourly Rate: ' . formatCurrency($booking['hourly_rate']),
            'icon' => 'fas fa-star'
        ];
    }
    
    return $summary;
}

/**
 * Check if user can checkout (has approved booking)
 * 
 * @param int $userId - User ID
 * @param int $bookingId - Booking ID
 * @param string $type - Type: 'reservation' or 'amenity'
 * @return bool Can checkout or not
 */
function canUserCheckout($userId, $bookingId, $type) {
    $booking = getBookingForPayment($type, $bookingId, $userId);
    
    if (!$booking) {
        return false;
    }
    
    // Check approval status for reservations
    if ($type == 'reservation' && $booking['status'] != 'approved') {
        return false;
    }
    
    // Check if already paid
    if (isset($booking['payment_status']) && $booking['payment_status'] == 'paid') {
        return false;
    }
    
    return true;
}

/**
 * Simulate payment gateway responses for testing
 * Note: In production, use actual PayMongo API
 * 
 * @param string $paymentMethod - Payment method code
 * @param float $amount - Amount
 * @param string $transactionReference - Transaction reference
 * @return array Simulation result
 */
function simulatePaymentGateway($paymentMethod, $amount, $transactionReference) {
    if (!isValidPaymentMethod($paymentMethod)) {
        return [
            'success' => false,
            'transaction_id' => null,
            'message' => 'Invalid payment method'
        ];
    }
    
    $gateways = [
        'gcash' => ['success_rate' => 0.95, 'processing_time' => 2],
        'paymaya' => ['success_rate' => 0.92, 'processing_time' => 3],
        'bank_transfer' => ['success_rate' => 0.88, 'processing_time' => 5],
        'credit_card' => ['success_rate' => 0.90, 'processing_time' => 4]
    ];
    
    $gateway = $gateways[$paymentMethod] ?? $gateways['gcash'];
    
    // Simulate processing
    usleep($gateway['processing_time'] * 100000);  // Use usleep instead of sleep for testing
    
    // Simulate success/failure based on rate
    $success = (rand(1, 100) / 100) <= $gateway['success_rate'];
    
    return [
        'success' => $success,
        'transaction_id' => $success ? 'TXN' . date('YmdHis') . rand(100, 999) : null,
        'message' => $success ? 'Payment successful' : 'Payment failed - insufficient funds',
        'amount' => $amount,
        'method' => $paymentMethod
    ];
}

?>
