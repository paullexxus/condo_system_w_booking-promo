<?php
/**
 * PayMongo Configuration
 * Philippine payment gateway integration
 * FIXED CRITICAL #7: Use environment variables instead of hardcoded credentials
 */

// Load environment variables from .env file if exists
if (file_exists(__DIR__ . '/../.env')) {
    $env_file = file(__DIR__ . '/../.env');
    foreach ($env_file as $line) {
        $line = trim($line);
        if (!empty($line) && $line[0] !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// PayMongo API Keys from environment or fallback to empty (will error if not set)
$paymongo_secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
$paymongo_public = $_ENV['PAYMONGO_PUBLIC_KEY'] ?? getenv('PAYMONGO_PUBLIC_KEY');

if (empty($paymongo_secret) || empty($paymongo_public)) {
    // For development, provide test keys as fallback (CHANGE FOR PRODUCTION)
    if (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false) {
        $paymongo_secret = 'sk_test_...';
        $paymongo_public = 'pk_test_...';
        error_log("WARNING: Using test PayMongo keys. Set PAYMONGO_* in .env for proper configuration.");
    } else {
        die("ERROR: PayMongo API keys not configured. Set PAYMONGO_SECRET_KEY and PAYMONGO_PUBLIC_KEY in .env");
    }
}

define('PAYMONGO_SECRET_KEY', $paymongo_secret);
define('PAYMONGO_PUBLIC_KEY', $paymongo_public);

// PayMongo API Endpoints
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

/**
 * Pagsagot ng PayMongo API
 * @param string $endpoint - API endpoint (e.g., 'sources', 'payments')
 * @param string $method - HTTP method (GET, POST)
 * @param array $data - Request body data
 * @return array - Response from API
 */
function paymongoRequest($endpoint, $method = 'POST', $data = []) {
    $url = PAYMONGO_API_URL . '/' . $endpoint;
    
    // Prepare authorization header (Basic Auth with secret key)
    $auth = base64_encode(PAYMONGO_SECRET_KEY . ':');
    
    $options = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\nAuthorization: Basic " . $auth,
            'timeout' => 30
        ]
    ];
    
    if ($method === 'POST' || $method === 'PUT') {
        $options['http']['content'] = json_encode($data);
    }
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return [
            'success' => false,
            'error' => 'API connection failed'
        ];
    }
    
    return json_decode($response, true);
}

/**
 * Lumikha ng Payment Source (GCash, Grab Pay, Credit Card)
 * @param string $type - 'gcash', 'grab_pay', 'card'
 * @param array $details - Payment details
 * @return array - Source response
 */
function createPaymongoSource($type, $details = []) {
    $data = [
        'data' => [
            'attributes' => [
                'amount' => $details['amount'],
                'currency' => 'PHP',
                'type' => $type,
                'redirect' => [
                    'success' => $details['success_url'] ?? '',
                    'failed' => $details['failed_url'] ?? ''
                ]
            ]
        ]
    ];
    
    // Add specific details based on payment type
    if ($type === 'card') {
        $data['data']['attributes']['details'] = [
            'card_number' => $details['card_number'] ?? '',
            'exp_month' => $details['exp_month'] ?? '',
            'exp_year' => $details['exp_year'] ?? '',
            'cvc' => $details['cvc'] ?? ''
        ];
    }
    
    return paymongoRequest('sources', 'POST', $data);
}

/**
 * I-create ang payment mula sa source
 * @param string $source_id - Source ID
 * @param array $details - Payment details
 * @return array - Payment response
 */
function createPaymongoPayment($source_id, $details = []) {
    $data = [
        'data' => [
            'attributes' => [
                'amount' => $details['amount'],
                'currency' => 'PHP',
                'source' => [
                    'id' => $source_id,
                    'type' => 'source'
                ],
                'description' => $details['description'] ?? 'BookIT Payment',
                'statement_descriptor' => 'BookIT'
            ]
        ]
    ];
    
    return paymongoRequest('payments', 'POST', $data);
}

/**
 * I-retrieve ang payment status
 * @param string $payment_id - Payment ID
 * @return array - Payment details
 */
function getPaymongoPayment($payment_id) {
    return paymongoRequest('payments/' . $payment_id, 'GET');
}

/**
 * Mag-refund ng payment
 * @param string $payment_id - Payment ID
 * @param int $amount - Amount to refund (optional, null = full refund)
 * @return array - Refund response
 */
function createPaymongoRefund($payment_id, $amount = null) {
    $data = [
        'data' => [
            'attributes' => [
                'payment_id' => $payment_id
            ]
        ]
    ];
    
    if ($amount !== null) {
        $data['data']['attributes']['amount'] = $amount;
    }
    
    return paymongoRequest('refunds', 'POST', $data);
}

?>
