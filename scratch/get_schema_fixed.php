<?php
require 'c:\wamp64\www\BookIT\config\db.php';
$stmt = $conn->query("SHOW TABLES");
while ($row = $stmt->fetch_array()) {
    $table = $row[0];
    echo "TABLE: $table\n";
    $cols = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($c = $cols->fetch_assoc()) {
        echo " - {$c['Field']} ({$c['Type']})\n";
    }
}
