<?php
session_start();
require_once 'database.php';
header('Content-Type: application/json');

try {
    // query to count only bookings that is not yet checked in (not expired/cancelled/completed)
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
    $count = $row['total_count'] ?? 0;

    echo json_encode([
        'success' => true,
        'count' => $count
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
