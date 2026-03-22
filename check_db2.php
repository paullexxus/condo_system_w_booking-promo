<?php
require_once __DIR__ . '/config/db.php';
$res = $conn->query("SELECT * FROM branches");
while($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
?>
