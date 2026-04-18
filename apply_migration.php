<?php
// apply_migration.php
require_once 'config/db.php';

echo "Starting migration...\n";

$sql_file = 'database/migrations_p4_elite_hardening.sql';
if (!file_exists($sql_file)) {
    die("Error: Migration file not found at $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Standardize line endings and split by semicolon, but handle delimiters
// A more robust way to handle this in PHP:
$commands = explode(';', $sql_content);

// This simple explode will fail on the DELIMITER sections.
// Let's use a more sophisticated approach for triggers.

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error . "\n");
}

// Strip comments
$sql_content = preg_replace('/--.*$/m', '', $sql_content);

// Handle DELIMITER sections
// We'll split the script into parts: Before first DELIMITER, then the DELIMITER block, then AFTER.
// Or just use multi_query if the server allows it, but it's tricky with DELIMITER.

// Alternative: Split by DELIMITER // ... DELIMITER ;
$parts = preg_split('/DELIMITER\s+\/\/|DELIMITER\s+;/', $sql_content);

foreach ($parts as $part) {
    $part = trim($part);
    if (empty($part)) continue;

    // If it contains CREATE TRIGGER, it's the trigger part
    if (stripos($part, 'CREATE TRIGGER') !== false) {
        if ($db->query($part)) {
            echo "Trigger created successfully.\n";
        } else {
            echo "Error creating trigger: " . $db->error . "\n";
            echo "SQL: " . substr($part, 0, 100) . "...\n";
        }
    } else {
        // Standard SQL commands
        $queries = explode(';', $part);
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;
            
            if ($db->query($query)) {
                echo "Executed: " . substr($query, 0, 50) . "...\n";
            } else {
                echo "Error: " . $db->error . "\n";
                echo "SQL: " . $query . "\n";
            }
        }
    }
}

$db->close();
echo "Migration complete.\n";
?>
