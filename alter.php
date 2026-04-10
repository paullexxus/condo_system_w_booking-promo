<?php
require 'config/db.php';
$sql = "ALTER TABLE units ADD COLUMN pricing_type ENUM('daily', 'monthly') DEFAULT 'monthly'";
if(execute_query($sql)) { echo "Success"; } else { echo "Failed or already exists"; }
?>
