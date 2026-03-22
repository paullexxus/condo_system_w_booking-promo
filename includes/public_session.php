<?php
// Public session management - allows viewing without login
// Ensure consistent session settings
require_once __DIR__ . '/../config/constants.php';
session_start();

// No login requirement for public pages
// Users can browse without being logged in
?>
