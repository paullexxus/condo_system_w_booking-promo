<?php
require_once '../config/db.php';
$res = $conn->query("SHOW COLUMNS FROM reservations");
while($row = $res->fetch_assoc()) echo $row['Field'] . ' ' . $row['Type'] . PHP_EOL;
echo "--\n";
$res2 = $conn->query("SHOW COLUMNS FROM payments");
while($row = $res2->fetch_assoc()) echo $row['Field'] . ' ' . $row['Type'] . PHP_EOL;
