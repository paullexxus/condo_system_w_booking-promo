<?php
$output = '';
$output .= "Starting test...\n";

try {
    $output .= "Including constants...\n";
    require_once 'config/constants.php';
    $output .= "✓ Constants loaded\n";
} catch (Throwable $e) {
    $output .= "✗ Constants ERROR: " . $e->getMessage() . "\n";
    file_put_contents('c:/wamp64/www/BookIT/debug_output.txt', $output);
    die($output);
}

try {
    $output .= "Creating DB connection...\n";
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
    $output .= "✓ DB connected\n";
} catch (Throwable $e) {
    $output .= "✗ DB ERROR: " . $e->getMessage() . "\n";
    file_put_contents('c:/wamp64/www/BookIT/debug_output.txt', $output);
    die($output);
}

try {
    $output .= "Starting session...\n";
    session_start();
    $output .= "✓ Session started\n";
} catch (Throwable $e) {
    $output .= "✗ Session ERROR: " . $e->getMessage() . "\n";
    file_put_contents('c:/wamp64/www/BookIT/debug_output.txt', $output);
    die($output);
}

try {
    $output .= "Loading db.php...\n";
    require 'config/db.php';
    $output .= "✓ DB functions loaded\n";
} catch (Throwable $e) {
    $output .= "✗ DB Functions ERROR: " . $e->getMessage() . "\n";
    file_put_contents('c:/wamp64/www/BookIT/debug_output.txt', $output);
    die($output);
}

try {
    $output .= "Loading security.php...\n";
    require 'includes/security.php';
    $output .= "✓ Security loaded\n";
} catch (Throwable $e) {
    $output .= "✗ Security ERROR: " . $e->getMessage() . "\n";
    file_put_contents('c:/wamp64/www/BookIT/debug_output.txt', $output);
    die($output);
}

$output .= "\n✓ SUCCESS - All includes loaded!\n";
file_put_contents('c:/wamp64/www/BookIT/debug_output.txt', $output);
echo $output;
?>