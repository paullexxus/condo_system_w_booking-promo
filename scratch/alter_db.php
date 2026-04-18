<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(__DIR__) . '/config/db.php';
$out = "";
try {
    $conn->query("ALTER TABLE notifications ADD COLUMN email_sent TINYINT(1) DEFAULT 0");
} catch(Exception $e) {}

try {
    $conn->query("ALTER TABLE users ADD COLUMN last_urgent_popup_shown TIMESTAMP NULL");
} catch(Exception $e) {}

$out .= "notifications fields: ";
$res = $conn->query("DESCRIBE notifications");
if($res){ while($row = $res->fetch_assoc()){ $out .= $row['Field']." | "; } }

file_put_contents(__DIR__ . '/db_out.txt', $out);
echo "done";
