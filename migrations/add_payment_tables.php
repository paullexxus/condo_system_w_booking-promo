<?php
/**
 * Migration: Create payment-related tables
 */

$root_dir = dirname(dirname(__FILE__));
include $root_dir . '/config/db.php';

echo "<h2>Creating Payment Tables</h2>";

$tables = [
    "host_payment_methods" => "CREATE TABLE IF NOT EXISTS host_payment_methods (
        payment_method_id INT PRIMARY KEY AUTO_INCREMENT,
        host_id INT NOT NULL,
        method_type ENUM('paymongo', 'paypal') NOT NULL,
        method_name VARCHAR(100) NOT NULL,
        account_id VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(user_id),
        UNIQUE KEY unique_method (host_id, method_type, account_id, email)
    )",
    
    "refunds" => "CREATE TABLE IF NOT EXISTS refunds (
        refund_id INT PRIMARY KEY AUTO_INCREMENT,
        reservation_id INT NOT NULL,
        payment_id VARCHAR(100),
        refund_id_paymongo VARCHAR(100),
        amount DECIMAL(10,2) NOT NULL,
        reason TEXT,
        status VARCHAR(50),
        processed_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id),
        FOREIGN KEY (processed_by) REFERENCES users(user_id)
    )"
];

foreach ($tables as $table_name => $sql) {
    try {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ Table '$table_name' created/verified</p>";
        } else {
            echo "<p style='color: red;'>✗ Error creating table '$table_name': " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Table '$table_name' may already exist: " . $e->getMessage() . "</p>";
    }
}

echo "<p><strong>Migration completed!</strong></p>";
$conn->close();
?>
