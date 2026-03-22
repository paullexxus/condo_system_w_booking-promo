<?php
// Script to release payouts to hosts 24 hours after guest check-in
// Run this script daily via cron/Task Scheduler: php scripts/release_payouts.php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

// Find reservations that have been checked-in at least 24 hours ago, payment_status = 'paid'
$rows = get_multiple_results(
    "SELECT r.reservation_id, r.user_id, r.unit_id, r.branch_id, p.payment_id, p.amount, b.host_id
     FROM reservations r
     JOIN payments p ON p.reservation_id = r.reservation_id
     JOIN branches b ON r.branch_id = b.branch_id
     WHERE r.reservation_status = 'checked-in'
       AND r.checked_in_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
       AND p.payout_released = 0"
);

foreach ($rows as $row) {
    $hostId = $row['host_id'];
    $reservationId = $row['reservation_id'];
    $paymentId = $row['payment_id'];
    $amount = $row['amount'];

    try {
        // Create payout record
        execute_query(
            "INSERT INTO payouts (host_id, reservation_id, payment_id, amount, method, status, created_at) VALUES (?, ?, ?, ?, ?, 'released', NOW())",
            [$hostId, $reservationId, $paymentId, $amount, 'auto']
        );

        // Mark payment as payout released
        execute_query("UPDATE payments SET payout_released = 1, payout_released_at = NOW() WHERE payment_id = ?", [$paymentId]);

        // Notify host
        sendNotification($hostId, 'Payout Released', "Payout for reservation #$reservationId has been released. Amount: ₱$amount", 'payment', 'system');

        echo "Released payout for reservation $reservationId to host $hostId\n";
    } catch (Exception $e) {
        echo "Failed to release payout for reservation $reservationId: " . $e->getMessage() . "\n";
    }
}

echo "Payout release run complete.\n";
