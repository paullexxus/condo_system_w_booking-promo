<?php
require 'config/db.php';
$res = $conn->query("SHOW CREATE TABLE users");
while($r = $res->fetch_assoc()) {
    echo $r['Create Table'];
}
$conn->close();
