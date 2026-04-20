<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';

echo "Migrating promos to promo_codes...\n";

$sql = "INSERT INTO promo_codes (
            code, discount_type, discount_value, usage_limit, used_count, valid_until, status, is_active, scope
        ) 
        SELECT 
            code, type, value, usage_limit, used_count, expires_at, 
            CASE WHEN is_active=1 THEN 'active' ELSE 'inactive' END, 
            is_active, 'global' 
        FROM promos 
        ON DUPLICATE KEY UPDATE 
            discount_value = VALUES(discount_value),
            is_active = VALUES(is_active)";

if ($conn->query($sql)) {
    echo "Migration complete. Rows affected: " . $conn->affected_rows . "\n";
} else {
    echo "Migration failed: " . $conn->error . "\n";
}
