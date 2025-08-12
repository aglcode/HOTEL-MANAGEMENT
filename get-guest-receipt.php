<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['guest_id'])) {
    echo json_encode(['success' => false, 'message' => 'Guest ID required']);
    exit;
}

$guest_id = (int)$_GET['guest_id'];

$stmt = $conn->prepare("SELECT * FROM checkins WHERE id = ?");
$stmt->bind_param('i', $guest_id);
$stmt->execute();
$result = $stmt->get_result();
$guest = $result->fetch_assoc();
$stmt->close();

if ($guest) {
    echo json_encode(['success' => true, 'guest' => $guest]);
} else {
    echo json_encode(['success' => false, 'message' => 'Guest not found']);
}
?>