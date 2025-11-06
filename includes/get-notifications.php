<?php
require_once __DIR__ . '/../database.php'; 

// Current time
$now = date('Y-m-d H:i:s');

try {
    // Unified query to count all pending check-in bookings (no duplicates)
    $query = $conn->query("
        SELECT COUNT(DISTINCT b.id) AS total_count
        FROM bookings b
        LEFT JOIN checkins c 
          ON c.room_number = b.room_number
         AND c.guest_name COLLATE utf8mb4_0900_ai_ci = b.guest_name COLLATE utf8mb4_0900_ai_ci
        WHERE b.status NOT IN ('cancelled', 'completed')
          AND b.end_date > NOW()
          AND (
              b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              OR (b.start_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR))
          )
          AND c.id IS NULL
    ");

    $row = $query->fetch_assoc();
    $totalNotifications = $row['total_count'] ?? 0;
} catch (Exception $e) {
    $totalNotifications = 0;
}
?>


