<?php
require_once '../config/db.php';

$cols = [
    "is_active TINYINT(1) DEFAULT 1",
    "valid_from DATE",
    "valid_until DATE",
    "scope ENUM('global', 'host', 'branch') DEFAULT 'global'",
    "host_id INT DEFAULT NULL",
    "branch_id INT DEFAULT NULL",
    "min_booking_amount DECIMAL(10,2) DEFAULT 0.00",
    "max_discount DECIMAL(10,2) DEFAULT NULL",
    "per_user_limit INT DEFAULT 1"
];

foreach ($cols as $c) {
    try {
        $conn->query("ALTER TABLE promo_codes ADD COLUMN " . $c);
    } catch (Exception $e) {}
}
echo "Columns processed";
?>
