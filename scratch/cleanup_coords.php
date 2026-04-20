<?php
require 'config/db.php';
$conn->query("UPDATE units SET latitude = NULL, longitude = NULL WHERE (latitude = 0 OR latitude IS NULL) AND (longitude = 0 OR longitude IS NULL)");
$u_rows = $conn->affected_rows;
$conn->query("UPDATE branches SET latitude = NULL, longitude = NULL WHERE (latitude = 0 OR latitude IS NULL) AND (longitude = 0 OR longitude IS NULL)");
$b_rows = $conn->affected_rows;
echo "Units updated: $u_rows. Branches updated: $b_rows.\n";
unlink(__FILE__);
?>
