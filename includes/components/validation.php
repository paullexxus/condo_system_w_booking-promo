<?php
/**
 * Data Validation Functions
 * Mga functions para sa pag-validate ng input data
 */

// Validate unit number (e.g., A-101, 1234)
function validate_unit_number($unit_number) {
    // Valid formats:
    // A-101, B101, 123, ABC-123
    $unit_number = trim(strtoupper($unit_number));
    
    // Allow: 
    // 1. Single letter/number followed by optional hyphen and numbers (A-101, B101)
    // 2. Up to 3 letters followed by optional hyphen and numbers (ABC-123)
    // 3. Just numbers (123)
    return preg_match('/^([A-Z]{1,3}-?\d{1,3}|\d{1,4})$/', $unit_number);
}

// Validate price (dapat positive number)
function validate_price($price) {
    return is_numeric($price) && $price > 0;
}

// Validate phone number (PH format)
function validate_phone($phone) {
    // Tatanggap ng +639xxxxxxxxx o 09xxxxxxxxx format
    return preg_match('/^(\+639|09)\d{9}$/', $phone);
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Main validation function para sa unit form
function validate_unit_form($data) {
    $errors = [];
    
    // Required fields
    $required_fields = ['unit_number', 'unit_type', 'branch_id', 'price'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "Kinakailangan punan ang " . str_replace('_', ' ', $field);
        }
    }
    
    // Validate unit number format
    if (!empty($data['unit_number']) && !validate_unit_number($data['unit_number'])) {
        $errors[] = "Hindi valid ang format ng unit number (e.g., A-101)";
    }
    
    // Validate price
    if (!empty($data['price']) && !validate_price($data['price'])) {
        $errors[] = "Ang price ay dapat positive number";
    }
    
    // Validate max occupancy
    if (!empty($data['max_occupancy']) && (!is_numeric($data['max_occupancy']) || $data['max_occupancy'] < 1)) {
        $errors[] = "Ang maximum occupancy ay dapat 1 o higit pa";
    }
    
    return $errors;
}

// Sanitize input
function sanitize_unit_input($data) {
    $clean = [];
    foreach ($data as $key => $value) {
        // Basic sanitization
        $value = trim($value);
        
        // Specific field sanitization
        switch ($key) {
            case 'unit_number':
                // Only allow alphanumeric and hyphen
                $value = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($value));
                break;
                
            case 'price':
            case 'security_deposit':
                // Remove any non-numeric chars except decimal point
                $value = preg_replace('/[^0-9.]/', '', $value);
                $value = filter_var($value, FILTER_VALIDATE_FLOAT) ? $value : 0;
                break;
                
            case 'description':
                // Strict sanitization - remove all HTML tags and scripts
                $value = strip_tags($value);
                // Convert special characters to HTML entities
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                break;
                
            default:
                // Default sanitization for other fields
                $value = strip_tags($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                break;
        }
        
        $clean[$key] = $value;
    }
    return $clean;
}
?>