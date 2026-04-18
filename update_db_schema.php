<?php
session_start();
require_once __DIR__ . '/config/db.php';

echo "<h2>Database Synchronization</h2>";

// Add email_sent to notifications
$sql1 = "ALTER TABLE notifications ADD COLUMN email_sent TINYINT(1) DEFAULT 0";
try {
    $conn->query($sql1);
    echo "<p>Added email_sent to notifications.</p>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<p>email_sent already exists in notifications.</p>";
    } else {
        echo "<p>Error adding email_sent: " . $e->getMessage() . "</p>";
    }
}

// Add last_urgent_popup_shown to users
$sql2 = "ALTER TABLE users ADD COLUMN last_urgent_popup_shown TIMESTAMP NULL";
try {
    $conn->query($sql2);
    echo "<p>Added last_urgent_popup_shown to users.</p>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<p>last_urgent_popup_shown already exists in users.</p>";
    } else {
        echo "<p>Error adding last_urgent_popup_shown: " . $e->getMessage() . "</p>";
    }
}
// Phase 2 Notifications Refinements
$updates = [
    "ALTER TABLE notifications ADD COLUMN is_archived TINYINT(1) DEFAULT 0",
    "ALTER TABLE notifications ADD COLUMN email_retries TINYINT DEFAULT 0",
    "ALTER TABLE notifications ADD COLUMN next_retry_at TIMESTAMP NULL",
    "ALTER TABLE notifications ADD INDEX idx_priority (priority)",
    "ALTER TABLE notifications ADD INDEX idx_is_read (is_read)",
    "ALTER TABLE notifications ADD INDEX idx_created_at (created_at)"
];

foreach ($updates as $sql) {
    try {
        $conn->query($sql);
        echo "<p>Successfully executed: $sql</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "<p>Already exists: $sql</p>";
        } else {
            echo "<p>Error executing $sql: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<p><strong>Updates finished!</strong></p>";
?>
