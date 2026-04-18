<?php
require_once '../config/db.php';

$res = $conn->query("SHOW COLUMNS FROM promo_codes");
echo "promo_codes columns:\n";
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}

$res2 = $conn->query("SHOW COLUMNS FROM reservations");
echo "\nreservations columns:\n";
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
