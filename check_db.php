<?php
require_once __DIR__ . '/config/db.php';
$res = $conn->query("SELECT unit_id, unit_name, host_id, branch_id FROM units WHERE host_id IN (10, 11)");
while($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
?>
