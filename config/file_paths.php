<?php
/**
 * File Upload Configuration
 * Centralized file path management for all document uploads
 */

// Base upload directory
define('UPLOADS_DIR', __DIR__ . '/../uploads');
define('UPLOADS_URL', SITE_URL . '/uploads');

// Valid IDs Upload Paths
define('VALID_IDS_DIR', UPLOADS_DIR . '/valid_ids');
define('VALID_IDS_URL', UPLOADS_URL . '/valid_ids');

define('VALID_IDS_ADMIN_DIR', VALID_IDS_DIR . '/admin');
define('VALID_IDS_ADMIN_URL', VALID_IDS_URL . '/admin');

define('VALID_IDS_HOST_DIR', VALID_IDS_DIR . '/host');
define('VALID_IDS_HOST_URL', VALID_IDS_URL . '/host');

define('VALID_IDS_RENTER_DIR', VALID_IDS_DIR . '/renter');
define('VALID_IDS_RENTER_URL', VALID_IDS_URL . '/renter');

// Unit Images Upload Paths
define('UNIT_IMAGES_DIR', UPLOADS_DIR . '/unit_images');
define('UNIT_IMAGES_URL', UPLOADS_URL . '/unit_images');

define('UNIT_OVERVIEW_DIR', UNIT_IMAGES_DIR . '/overview');
define('UNIT_OVERVIEW_URL', UNIT_IMAGES_URL . '/overview');

define('UNIT_GALLERY_DIR', UNIT_IMAGES_DIR . '/gallery');
define('UNIT_GALLERY_URL', UNIT_IMAGES_URL . '/gallery');

// Payment Proofs Upload Paths
define('PAYMENT_PROOFS_DIR', UPLOADS_DIR . '/payment_proofs');
define('PAYMENT_PROOFS_URL', UPLOADS_URL . '/payment_proofs');

define('PAYMENT_GCASH_DIR', PAYMENT_PROOFS_DIR . '/gcash');
define('PAYMENT_GCASH_URL', PAYMENT_PROOFS_URL . '/gcash');

define('PAYMENT_BANK_DIR', PAYMENT_PROOFS_DIR . '/bank_transfer');
define('PAYMENT_BANK_URL', PAYMENT_PROOFS_URL . '/bank_transfer');

define('PAYMENT_PAYPAL_DIR', PAYMENT_PROOFS_DIR . '/paypal');
define('PAYMENT_PAYPAL_URL', PAYMENT_PROOFS_URL . '/paypal');

define('PAYMENT_OTHER_DIR', PAYMENT_PROOFS_DIR . '/other');
define('PAYMENT_OTHER_URL', PAYMENT_PROOFS_URL . '/other');

// Profile Pictures
define('PROFILE_PICTURES_DIR', UPLOADS_DIR . '/profile_pictures');
define('PROFILE_PICTURES_URL', UPLOADS_URL . '/profile_pictures');

// Documents
define('DOCUMENTS_DIR', UPLOADS_DIR . '/documents');
define('DOCUMENTS_URL', UPLOADS_URL . '/documents');

// Allowed file types
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
define('ALLOWED_ID_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// File size limits (in bytes)
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5 MB
define('MAX_DOCUMENT_SIZE', 10 * 1024 * 1024); // 10 MB
define('MAX_ID_SIZE', 5 * 1024 * 1024); // 5 MB

/**
 * File Upload Helper Class
 */
class FileUploadHelper {
    
    /**
     * Get target directory for valid IDs based on user role
     * 
     * @param string $role User role (admin, host, renter)
     * @return string Directory path
     */
    public static function getValidIDDirectory($role) {
        switch ($role) {
            case 'admin':
                return VALID_IDS_ADMIN_DIR;
            case 'host':
            case 'manager':
                return VALID_IDS_HOST_DIR;
            case 'renter':
                return VALID_IDS_RENTER_DIR;
            default:
                return VALID_IDS_DIR;
        }
    }

    /**
     * Get URL for valid IDs based on user role
     * 
     * @param string $role User role
     * @return string URL path
     */
    public static function getValidIDURL($role) {
        switch ($role) {
            case 'admin':
                return VALID_IDS_ADMIN_URL;
            case 'host':
            case 'manager':
                return VALID_IDS_HOST_URL;
            case 'renter':
                return VALID_IDS_RENTER_URL;
            default:
                return VALID_IDS_URL;
        }
    }

    /**
     * Get payment proof directory based on payment method
     * 
     * @param string $method Payment method (gcash, bank_transfer, paypal, other)
     * @return string Directory path
     */
    public static function getPaymentProofDirectory($method) {
        switch (strtolower($method)) {
            case 'gcash':
                return PAYMENT_GCASH_DIR;
            case 'bank_transfer':
            case 'bank':
                return PAYMENT_BANK_DIR;
            case 'paypal':
                return PAYMENT_PAYPAL_DIR;
            default:
                return PAYMENT_OTHER_DIR;
        }
    }

    /**
     * Get payment proof URL based on payment method
     * 
     * @param string $method Payment method
     * @return string URL path
     */
    public static function getPaymentProofURL($method) {
        switch (strtolower($method)) {
            case 'gcash':
                return PAYMENT_GCASH_URL;
            case 'bank_transfer':
            case 'bank':
                return PAYMENT_BANK_URL;
            case 'paypal':
                return PAYMENT_PAYPAL_URL;
            default:
                return PAYMENT_OTHER_URL;
        }
    }

    /**
     * Validate file upload
     * 
     * @param array $file File from $_FILES
     * @param array $allowed_extensions Allowed file extensions
     * @param int $max_size Maximum file size in bytes
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function validateFile($file, $allowed_extensions, $max_size) {
        // Check if file was uploaded
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload error: ' . $file['error']];
        }

        // Check file size
        if ($file['size'] > $max_size) {
            return ['success' => false, 'error' => 'File size exceeds limit (' . ($max_size / 1024 / 1024) . ' MB)'];
        }

        // Check file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_extensions)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)];
        }

        return ['success' => true];
    }

    /**
     * Generate unique filename with timestamp
     * 
     * @param string $original_filename Original filename
     * @param string $prefix Optional prefix (e.g., user_id)
     * @return string Unique filename
     */
    public static function generateUniqueFilename($original_filename, $prefix = '') {
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $filename = pathinfo($original_filename, PATHINFO_FILENAME);
        
        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        
        // Add prefix and timestamp
        $timestamp = date('YmdHis');
        $unique_id = bin2hex(random_bytes(4));
        
        if ($prefix) {
            return "{$prefix}_{$filename}_{$timestamp}_{$unique_id}.{$extension}";
        } else {
            return "{$filename}_{$timestamp}_{$unique_id}.{$extension}";
        }
    }

    /**
     * Save uploaded file
     * 
     * @param array $file File from $_FILES
     * @param string $target_dir Target directory
     * @param string $prefix Optional filename prefix
     * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
     */
    public static function saveFile($file, $target_dir, $prefix = '') {
        // Generate unique filename
        $filename = self::generateUniqueFilename($file['name'], $prefix);
        $target_path = $target_dir . '/' . $filename;

        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            return ['success' => true, 'filename' => $filename];
        } else {
            return ['success' => false, 'error' => 'Failed to save file'];
        }
    }

    /**
     * Delete file
     * 
     * @param string $file_path File path to delete
     * @return bool
     */
    public static function deleteFile($file_path) {
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        return false;
    }

    /**
     * Get file URL from filename and directory
     * 
     * @param string $filename Filename
     * @param string $directory Directory URL constant
     * @return string Full URL
     */
    public static function getFileURL($filename, $directory_url) {
        return $directory_url . '/' . $filename;
    }
}
?>
