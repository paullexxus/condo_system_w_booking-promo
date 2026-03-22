<?php
include 'config/db.php';

echo "<h2>Checking for Duplicate Bookings</h2>";

// Find duplicate reservations (same user, unit, check-in, check-out dates)
$query = "
SELECT user_id, unit_id, check_in_date, check_out_date, COUNT(*) as cnt
FROM reservations
GROUP BY user_id, unit_id, check_in_date, check_out_date
HAVING cnt > 1
";

$duplicates = $conn->query($query);

if ($duplicates->num_rows == 0) {
    echo "<p style='color: green;'>✓ No duplicate bookings found!</p>";
} else {
    echo "<p style='color: orange;'>Found " . $duplicates->num_rows . " duplicate booking groups</p>";
    
    while ($dup = $duplicates->fetch_assoc()) {
        echo "<hr>";
        echo "<p><strong>Duplicate Group:</strong></p>";
        echo "<ul>";
        echo "<li>User ID: {$dup['user_id']}</li>";
        echo "<li>Unit ID: {$dup['unit_id']}</li>";
        echo "<li>Check-in: {$dup['check_in_date']}</li>";
        echo "<li>Check-out: {$dup['check_out_date']}</li>";
        echo "<li>Count: {$dup['cnt']}</li>";
        echo "</ul>";
        
        // Get all duplicate records
        $get_dups = $conn->query("
            SELECT reservation_id, status, created_at
            FROM reservations
            WHERE user_id = " . $dup['user_id'] . " 
            AND unit_id = " . $dup['unit_id'] . "
            AND check_in_date = '" . $dup['check_in_date'] . "'
            AND check_out_date = '" . $dup['check_out_date'] . "'
            ORDER BY created_at DESC
        ");
        
        $duplicates_to_delete = [];
        $keep_id = null;
        $count = 0;
        
        while ($rec = $get_dups->fetch_assoc()) {
            $count++;
            if ($count == 1) {
                $keep_id = $rec['reservation_id'];
                echo "<p style='color: green;'>✓ Keeping Reservation ID: {$rec['reservation_id']} (Status: {$rec['status']}, Created: {$rec['created_at']})</p>";
            } else {
                $duplicates_to_delete[] = $rec['reservation_id'];
                echo "<p style='color: red;'>✗ Deleting Reservation ID: {$rec['reservation_id']} (Status: {$rec['status']}, Created: {$rec['created_at']})</p>";
            }
        }
        
        // Delete the duplicate records (keep the most recent one)
        foreach ($duplicates_to_delete as $delete_id) {
            $delete_result = $conn->query("DELETE FROM reservations WHERE reservation_id = $delete_id");
            if ($delete_result) {
                echo "<p style='color: green;'>✓ Deleted reservation ID: $delete_id</p>";
            } else {
                echo "<p style='color: red;'>ERROR deleting reservation ID: $delete_id - " . $conn->error . "</p>";
            }
        }
    }
}

echo "<hr>";
echo "<h3 style='color: green;'>Cleanup Complete!</h3>";
echo "<p><a href='renter/my_bookings.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View My Bookings</a></p>";
?>
