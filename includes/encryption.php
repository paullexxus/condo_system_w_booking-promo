<?php
// includes/encryption.php
// Handles Application-Level Symmetric Encryption for Messages using AES-256-CBC

// Helper to get environment variable if not already loaded by a library
function get_env_var($key, $default = '') {
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    
    // Fallback manual parse if dotenv not loaded
    $env_file = dirname(__DIR__) . '/.env';
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === $key) return $value;
        }
    }
    return $default;
}

$encryption_key = get_env_var('APP_MASTER_KEY', 'BkIT_Fallback_Key_Do_Not_Use_Prod');

// Ensure key size is good for AES-256
$master_key = hash('sha256', $encryption_key, true);

/**
 * Encrypts a message using AES-256-CBC
 * @param string $message Plain text message
 * @return string Base64 encoded IV + Ciphertext
 */
function encrypt_message($message) {
    global $master_key;
    if (empty($message)) return $message;
    
    // Generate a secure random Initialization Vector (IV), 16 bytes for AES
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    
    // Encrypt the message
    $encrypted = openssl_encrypt($message, 'aes-256-cbc', $master_key, 0, $iv);
    
    // Combine IV and Encrypted data, then base64 encode it so it can be stored easily
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypts a message using AES-256-CBC
 * @param string $payload Base64 encoded IV + Ciphertext
 * @return string Plain text message
 */
function decrypt_message($payload) {
    global $master_key;
    if (empty($payload)) return $payload;

    // Decode base64
    $data = base64_decode($payload);
    if ($data === false) return $payload; // Return original if not encoded properly
    
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    
    // Ensure the payload is long enough to contain the IV
    if (strlen($data) < $iv_length) {
        return $payload; // Error decrypting
    }
    
    // Extract IV and Ciphertext
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    // Decrypt
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $master_key, 0, $iv);
    
    // If decryption fails, it returns false. Return original payload as fallback
    if ($decrypted === false) {
        return $payload;
    }
    
    return $decrypted;
}
?>
