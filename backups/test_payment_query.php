<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

// Test the exact query from payment_management.php
$sql = "
    SELECT p.*, r.reservation_id, r.check_in_date, r.check_out_date, u.full_name
    FROM payments p
    LEFT JOIN reservations r ON p.reservation_id = r.reservation_id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY p.payment_date DESC
    LIMIT 20
";

echo "<h2>Testing Payment Query</h2>";
echo "<p>SQL: " . htmlspecialchars($sql) . "</p>";

if ($stmt = $conn->prepare($sql)) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        echo "<p style='color: green;'><strong>✓ Query executed successfully!</strong></p>";
        echo "<p>Rows returned: " . $result->num_rows . "</p>";
        
        if ($result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $key => $val) {
                    echo "<td><strong>$key:</strong> " . htmlspecialchars((string)$val) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'><strong>✗ Execution failed:</strong> " . htmlspecialchars($stmt->error) . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ Prepare failed:</strong> " . htmlspecialchars($conn->error) . "</p>";
}

$conn->close();
?>
