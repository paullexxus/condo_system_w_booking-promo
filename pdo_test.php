<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=condo_rental_reservation_db', 'root', '');
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
catch (Exception $e) {
    echo $e->getMessage();
}
