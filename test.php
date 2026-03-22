<?php
require 'C:/wamp64/www/BookIT/config/db.php';
$res = $conn->query("DESCRIBE users");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
