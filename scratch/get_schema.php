<?php
require '../config/db.php';
$res = $conn->query("DESCRIBE units");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
