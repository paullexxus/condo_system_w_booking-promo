<?php
// includes/email_functions.php

function sendReservationApprovalEmail($user_email, $user_name, $reservation_details, $manager_notes = "") {
    $subject = "🎉 Reservation Approved - BookIT";
    
    $message = "
    <h3>Hello " . htmlspecialchars($user_name) . "!</h3>
    <p>We're excited to inform you that your reservation has been <strong>approved</strong>!</p>
    
    <div style='background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin: 15px 0;'>
        <h4>Reservation Details:</h4>
        <p><strong>Reservation ID:</strong> #" . htmlspecialchars($reservation_details['reservation_id']) . "</p>
        <p><strong>Unit:</strong> " . htmlspecialchars($reservation_details['unit_number']) . "</p>
        <p><strong>Branch:</strong> " . htmlspecialchars($reservation_details['branch_name']) . "</p>
        <p><strong>Check-in:</strong> " . htmlspecialchars($reservation_details['check_in_date']) . "</p>
        <p><strong>Check-out:</strong> " . htmlspecialchars($reservation_details['check_out_date']) . "</p>
        <p><strong>Total Amount:</strong> ₱" . number_format($reservation_details['total_amount'], 2) . "</p>
    </div>
    
    " . (!empty($manager_notes) ? "
    <div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;'>
        <h5>📝 Manager's Note:</h5>
        <p>" . htmlspecialchars($manager_notes) . "</p>
    </div>
    " : "") . "
    
    <p><strong>Next Steps:</strong></p>
    <ol>
        <li>Proceed with payment according to our payment terms</li>
        <li>Prepare valid ID for check-in</li>
        <li>Contact us if you have any questions</li>
    </ol>
    
    <p>You can view your reservation details in your BookIT account.</p>
    
    <div style='text-align: center; margin: 20px 0;'>
        <a href='http://localhost/BookIT/renter/my_bookings.php' 
           style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
            📋 View My Bookings
        </a>
    </div>
    
    <p>Thank you for choosing BookIT!</p>
    <p><em>The BookIT Team</em></p>
    ";
    
    return sendEmailNotification($user_email, $user_name, $subject, $message);
}

function sendReservationRejectionEmail($user_email, $user_name, $reservation_details, $reason = "") {
    $subject = "❌ Reservation Declined - BookIT";
    
    $message = "
    <h3>Hello " . htmlspecialchars($user_name) . "!</h3>
    <p>We regret to inform you that your reservation has been <strong>declined</strong>.</p>
    
    <div style='background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin: 15px 0;'>
        <h4>Reservation Details:</h4>
        <p><strong>Reservation ID:</strong> #" . htmlspecialchars($reservation_details['reservation_id']) . "</p>
        <p><strong>Unit:</strong> " . htmlspecialchars($reservation_details['unit_number']) . "</p>
        <p><strong>Branch:</strong> " . htmlspecialchars($reservation_details['branch_name']) . "</p>
        <p><strong>Check-in:</strong> " . htmlspecialchars($reservation_details['check_in_date']) . "</p>
        <p><strong>Check-out:</strong> " . htmlspecialchars($reservation_details['check_out_date']) . "</p>
        <p><strong>Total Amount:</strong> ₱" . number_format($reservation_details['total_amount'], 2) . "</p>
    </div>
    
    " . (!empty($reason) ? "
    <div style='background: #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;'>
        <h5>📋 Reason for Cancellation:</h5>
        <p>" . htmlspecialchars($reason) . "</p>
    </div>
    " : "") . "
    
    <p>You may:</p>
    <ul>
        <li>Contact us for more information</li>
        <li>Make a new reservation with different dates</li>
        <li>Choose a different unit or branch</li>
    </ul>
    
    <div style='text-align: center; margin: 20px 0;'>
        <a href='http://localhost/BookIT/renter/reserve_unit.php' 
           style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
            🏠 Make New Reservation
        </a>
    </div>
    
    <p>We hope to serve you better next time!</p>
    <p><em>The BookIT Team</em></p>
    ";
    
    return sendEmailNotification($user_email, $user_name, $subject, $message);
}

function sendEmailNotification($to_email, $to_name, $subject, $message, $is_html = true) {
    // Simple implementation using PHP mail() function
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: BookIT <noreply@bookit.com>" . "\r\n";
    $headers .= "Reply-To: noreply@bookit.com" . "\r\n";
    
    $full_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            }
            .container { 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px; 
            }
            .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; padding: 30px; 
            text-align: center; 
            border-radius: 10px 10px 0 0; 
            }
            .content { 
            padding: 30px; 
            background: #f9f9f9; 
            }
            .footer { 
            padding: 20px; 
            text-align: center; 
            color: #666; 
            font-size: 0.9em; 
            }
            .button { 
            background: #007bff; 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 5px; 
            display: inline-block; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>BookIT Notification</h2>
                <p>Multi-Branch Condo Rental Booking and Reservations</p>
            </div>
            <div class='content'>
                " . $message . "
            </div>
            <div class='footer'>
                <p>This is an automated message from BookIT System. Please do not reply to this email.</p>
                <p>© 2025 BookIT. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Try centralized PHPMailer helper if available
    error_log("EMAIL TO: " . $to_email . " - SUBJECT: " . $subject);
    if (function_exists('sendEmailViaPhpMail')) {
        return sendEmailViaPhpMail($to_email, $to_name, $subject, $full_message);
    }

    // Fallback to PHP mail()
    $result = @mail($to_email, $subject, $full_message, $headers);
    if ($result) {
        error_log("mail(): Email sent to: " . $to_email);
    } else {
        error_log("mail() FAILED to: " . $to_email);
    }
    return $result;
}
?>