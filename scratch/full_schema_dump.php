<?php
require_once 'config/db.php';

$tables_res = $conn->query("SHOW TABLES");
$tables = [];
while($row = $tables_res->fetch_array()) {
    $tables[] = $row[0];
}

$schema = [];

foreach ($tables as $table) {
    $result = $conn->query("DESCRIBE `$table`");
    if ($result) {
        $cols = [];
        while($row = $result->fetch_assoc()) {
            $cols[] = $row;
        }
        $schema[$table] = $cols;
    }
}

header('Content-Type: application/json');
echo json_encode($schema, JSON_PRETTY_PRINT);
?>
