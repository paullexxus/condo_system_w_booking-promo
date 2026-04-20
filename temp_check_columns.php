<?php
require_once 'config/db.php';
$res = $conn->query("DESCRIBE units");
echo "<pre>";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>
