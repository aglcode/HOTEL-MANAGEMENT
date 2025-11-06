<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['guest_id'])) {
    echo json_encode(['success' => false, 'message' => 'Guest ID required']);
    exit;
}

$guest_id = intval($_GET['guest_id']);

$stmt = $conn->prepare("
    SELECT id, guest_name, telephone, address, room_number, check_out_date
    FROM checkins 
    WHERE id = ?
");

$stmt->bind_param('i', $guest_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $guest = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'guest' => $guest
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Guest not found'
    ]);
}

$stmt->close();
$conn->close();
?>