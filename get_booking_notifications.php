<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

try {
    // ✅ Count new bookings (created in last 24 hours)
    $newBookingsQuery = $conn->query("
        SELECT COUNT(*) as new_count 
        FROM bookings 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND status NOT IN ('cancelled', 'completed')
    ");
    $newBookingsCount = $newBookingsQuery->fetch_assoc()['new_count'] ?? 0;

    // ✅ Count upcoming bookings (check-in within next 24 hours)
    $upcomingBookingsQuery = $conn->query("
        SELECT COUNT(*) as upcoming_count 
        FROM bookings 
        WHERE start_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
          AND status NOT IN ('cancelled', 'completed')
    ");
    $upcomingBookingsCount = $upcomingBookingsQuery->fetch_assoc()['upcoming_count'] ?? 0;

    // ✅ Total notification count
    $totalNotifications = $newBookingsCount + $upcomingBookingsCount;

    echo json_encode([
        'success' => true,
        'count' => $totalNotifications,
        'new_bookings' => $newBookingsCount,
        'upcoming_bookings' => $upcomingBookingsCount
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>