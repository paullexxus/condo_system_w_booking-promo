<?php
// scripts/run_migrations.php
// Run from CLI: php scripts/run_migrations.php

require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from the command line.\n";
}

// Ensure migrations folder exists
$migrationsDir = __DIR__ . '/../migrations';
if (!is_dir($migrationsDir)) {
    echo "Migrations directory not found: $migrationsDir\n";
    exit(1);
}

// Create schema_migrations table if not exists
$createTableSql = "CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    checksum VARCHAR(128) DEFAULT NULL,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if (!$conn->query($createTableSql)) {
    echo "Failed to create schema_migrations table: " . $conn->error . "\n";
    exit(1);
}

// Get .sql files sorted
$files = glob($migrationsDir . '/*.sql');
sort($files, SORT_STRING);

if (empty($files)) {
    echo "No migration files found in $migrationsDir\n";
    exit(0);
}

foreach ($files as $file) {
    $basename = basename($file);

    // Check if applied
    $escaped = $conn->real_escape_string($basename);
    $res = $conn->query("SELECT id FROM schema_migrations WHERE filename = '$escaped' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        echo "Skipping (already applied): $basename\n";
        continue;
    }

    echo "Applying migration: $basename...\n";
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "Empty or unreadable migration file: $basename\n";
        continue;
    }

    // Execute multi-statement SQL
    if ($conn->multi_query($sql)) {
        // consume results
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        // record migration
        $checksum = hash('sha256', $sql);
        $stmt = $conn->prepare("INSERT INTO schema_migrations (filename, checksum, applied_at) VALUES (?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('ss', $basename, $checksum);
            $stmt->execute();
            $stmt->close();
        }

        echo "Applied: $basename\n";
    } else {
        echo "Failed to apply $basename: " . $conn->error . "\n";
        exit(1);
    }
}

echo "All migrations processed.\n";

?>