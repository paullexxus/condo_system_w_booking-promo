<?php
/**
 * apply_migrations.php
 * Entry point to run all pending database migrations.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/MigrationManager.php';

// Security Check: Only allow via CLI or Admin users
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/includes/session.php';
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die("Access denied. CLI or Administrator privileges required.");
    }
}

echo "<pre>";
echo "Starting Database Migrations...\n";
echo "---------------------------------\n";

try {
    $manager = new MigrationManager($conn);
    $count = $manager->runPendingMigrations();
    
    if ($count > 0) {
        echo "---------------------------------\n";
        echo "Finished! Applied $count migration(s).\n";
    } else {
        echo "No pending migrations found.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("apply_migrations.php Error: " . $e->getMessage());
}

echo "</pre>";
?>
