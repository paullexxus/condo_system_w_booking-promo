<?php
include 'config/db.php';

echo "<h2>Database Check</h2>";

// Check branches
$result = $conn->query('SELECT * FROM branches');
echo "<h3>Branches (" . $result->num_rows . ")</h3>";
while ($row = $result->fetch_assoc()) {
    echo "<p>ID: {$row['branch_id']}, Name: {$row['branch_name']}, Host ID: {$row['host_id']}</p>";
}

// Check units
$result = $conn->query('SELECT * FROM units LIMIT 10');
$count_result = $conn->query('SELECT COUNT(*) as cnt FROM units');
$count_row = $count_result->fetch_assoc();
echo "<h3>Units (" . $count_row['cnt'] . ")</h3>";
while ($row = $result->fetch_assoc()) {
    echo "<p>ID: {$row['unit_id']}, Name: {$row['unit_name']}, Host ID: {$row['host_id']}, Branch ID: {$row['branch_id']}</p>";
}

// Check reservations
$result = $conn->query('SELECT * FROM reservations');
echo "<h3>Reservations (" . $result->num_rows . ")</h3>";
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>No reservations found!</p>";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "<p>ID: {$row['reservation_id']}, User: {$row['user_id']}, Unit: {$row['unit_id']}, Status: {$row['status']}</p>";
    }
}

// Check Juan Santos data
echo "<h3>Juan Santos Info</h3>";
$juan = $conn->query("SELECT * FROM users WHERE full_name = 'Juan Santos' OR email LIKE '%juan%'")->fetch_assoc();
if ($juan) {
    echo "<p>ID: {$juan['user_id']}, Name: {$juan['full_name']}, Role: {$juan['role']}</p>";
} else {
    echo "<p style='color: red;'>Juan Santos not found!</p>";
}
?>
