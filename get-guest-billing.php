<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['guest_id'])) {
    echo json_encode(['success' => false, 'message' => 'Guest ID required']);
    exit;
}

$guest_id = (int)$_GET['guest_id'];

$stmt = $conn->prepare("
    SELECT c.*, r.room_type, r.price_3hrs as base_price
    FROM checkins c 
    JOIN rooms r ON c.room_number = r.room_number 
    WHERE c.id = ?
");
$stmt->bind_param('i', $guest_id);
$stmt->execute();
$result = $stmt->get_result();
$guest = $result->fetch_assoc();
$stmt->close();

if ($guest) {
    // Calculate extensions (if stay_duration > 3, then there are extensions)
    $base_duration = 3; // Base 3-hour stay
    $extensions = max(0, $guest['stay_duration'] - $base_duration);
    $guest['extensions'] = $extensions;
    
    echo json_encode(['success' => true, 'guest' => $guest]);
} else {
    echo json_encode(['success' => false, 'message' => 'Guest not found']);
}
?>