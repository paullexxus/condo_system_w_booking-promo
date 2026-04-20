<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'condo_rental_reservation_db');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "Starting Moderation Hardening Migration...\n";

// 1. Add columns to units table
$cols = [
    'review_locked_by' => 'INT NULL',
    'review_locked_at' => 'DATETIME NULL',
    'reviewed_at' => 'DATETIME NULL'
];

foreach ($cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM units LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE units ADD $col $def");
        echo "Added $col to units table.\n";
    } else {
        echo "$col already exists in units table.\n";
    }
}

// 2. Create unit_flags table
$conn->query("CREATE TABLE IF NOT EXISTS unit_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    flag_type VARCHAR(50) NOT NULL,
    details TEXT,
    flagged_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (unit_id)
) ENGINE=InnoDB;");
echo "unit_flags table ensured.\n";

echo "Migration Successful!\n";
?>
