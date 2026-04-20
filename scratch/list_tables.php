<?php
require_once __DIR__ . '/../config/db.php';
$res = $conn->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        echo $row[0] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
?>
