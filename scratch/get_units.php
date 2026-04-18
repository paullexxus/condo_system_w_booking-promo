<?php
include_once __DIR__ . '/../config/db.php';
$units = get_multiple_results("SELECT unit_id, unit_name, unit_number, unit_type, price_per_night, price_per_month FROM units LIMIT 5");
echo json_encode($units, JSON_PRETTY_PRINT);
