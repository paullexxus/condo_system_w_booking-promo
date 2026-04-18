<?php
$logPath = "C:\\wamp64\\logs\\php_error.log";
if (file_exists($logPath)) {
    $lines = file($logPath);
    $last_lines = array_slice($lines, -50);
    echo implode("", $last_lines);
} else {
    echo "Log file not found.";
}
