<?php
// Compatibility wrapper:
// Some older pages link to /admin/amenity_management.php.
// The real page is /modules/amenities.php.

include_once __DIR__ . '/../includes/session.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../config/db.php';

checkRole(['admin']);

$target = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/modules/amenities.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $target);
exit;

