<?php
$c = mysqli_connect('localhost', 'root', '', 'condo_rental_reservation_db');
$tables = ['host_applications', 'users'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    $r = mysqli_query($c, "DESCRIBE $t");
    while($row = mysqli_fetch_assoc($r)) {
        print_r($row);
    }
}
$r = mysqli_query($c, "SHOW TABLES LIKE '%audit%'");
echo "--- AUDIT TABLES ---\n";
while($row = mysqli_fetch_assoc($r)) {
    print_r($row);
}
