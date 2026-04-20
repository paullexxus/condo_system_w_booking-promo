<?php
include 'config/db.php';

function check_table($conn, $table) {
    echo "Checking table: $table\n";
    $result = $conn->query("SHOW COLUMNS FROM $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo " - {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo " - Table NOT FOUND or error: " . $conn->error . "\n";
    }
    echo "\n";
}

check_table($conn, 'payouts');
check_table($conn, 'payout_accounts');
check_table($conn, 'payout_requests');
