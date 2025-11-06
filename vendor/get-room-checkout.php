<?php
header('Content-Type: application/json');
require_once 'database.php';

$room_number = intval($_GET['room_number'] ?? 0);

if (!$room_number) {
    echo json_encode(['success' => false, 'message' => 'Invalid room number']);
    exit;
}

// Get the latest active booking for this room
$stmt = $conn->prepare("
    SELECT check_out_date, stay_duration, last_modified
    FROM checkins
    WHERE room_number = ?
      AND status = 'checked_in'
      AND check_in_date <= NOW()
      AND check_out_date > NOW()
    ORDER BY last_modified DESC
    LIMIT 1
");

$stmt->bind_param('i', $room_number);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if ($booking) {
    echo json_encode([
        'success' => true,
        'check_out_date' => $booking['check_out_date'],
        'stay_duration' => $booking['stay_duration'],
        'last_modified' => $booking['last_modified']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No active booking found'
    ]);
}
?>