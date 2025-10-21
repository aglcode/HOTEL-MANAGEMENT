<?php
require_once 'database.php';
header('Content-Type: application/json');

$room_number = $_GET['room_number'] ?? '';
if (empty($room_number)) {
    echo json_encode(['error' => 'Missing room number']);
    exit;
}

$stmt = $conn->prepare("
    SELECT MAX(end_date) AS last_checkout
    FROM bookings
    WHERE room_number = ?
      AND status NOT IN ('cancelled', 'completed')
");
$stmt->bind_param("s", $room_number);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'last_checkout' => $result['last_checkout'] ?? null
]);
