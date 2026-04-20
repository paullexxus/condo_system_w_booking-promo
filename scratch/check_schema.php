<?php
include 'config/db.php';
$tables = ['units', 'unit_images', 'unit_amenities', 'amenities', 'audit_logs'];
foreach($tables as $t) {
    $res = $conn->query("DESCRIBE $t");
    if($res) {
        echo "\n--- Table: $t ---\n";
        while($row = $res->fetch_assoc()) echo $row['Field'] . ' (' . $row['Type'] . ")\n";
    } else {
        echo "\n--- Table $t does not exist ---\n";
    }
}
?>
