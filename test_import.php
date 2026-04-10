<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("DROP DATABASE IF EXISTS test_db_bookit");
    $pdo->exec("CREATE DATABASE test_db_bookit");
    $pdo->exec("USE test_db_bookit");

    $sql = file_get_contents('c:/wamp64/www/BookIT/condo_rental_reservation_db.sql');
    
    // We try to execute the whole thing
    if ($sql === false) {
        die("Could not read file\n");
    }

    $pdo->exec($sql);
    echo "Import Successful!\n";

} catch (PDOException $e) {
    echo "Import Failed!\n";
    echo $e->getMessage() . "\n";
}
