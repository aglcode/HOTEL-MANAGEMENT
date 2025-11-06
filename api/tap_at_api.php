<?php
require_once '../database.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$room = $_GET['room'] ?? '';
$rfid = $_GET['rfid'] ?? '';

if (!$room || !$rfid) {
    echo json_encode(['success' => false, 'message' => 'Missing room or RFID.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT k.*, r.status AS room_status
    FROM keycards k
    JOIN rooms r ON k.room_number = r.room_number
    WHERE k.qr_code = ? AND k.room_number = ?
    ORDER BY k.id DESC LIMIT 1
");
$stmt->bind_param("si", $rfid, $room);
$stmt->execute();
$result = $stmt->get_result();
$keycard = $result->fetch_assoc();
$stmt->close();

if (!$keycard) {
    echo json_encode(['success' => false, 'message' => 'Invalid keycard']);
    exit;
}

$now = date('Y-m-d H:i:s');
if ($keycard['valid_to'] < $now) {
    echo json_encode(['success' => false, 'message' => 'Keycard expired']);
    exit;
}

$stmt2 = $conn->prepare("
    SELECT guest_name, status 
    FROM checkins
    WHERE room_number = ? AND status = 'checked_in'
    ORDER BY id DESC LIMIT 1
");
$stmt2->bind_param("i", $room);
$stmt2->execute();
$res2 = $stmt2->get_result();
$checkin = $res2->fetch_assoc();
$stmt2->close();

if (!$checkin) {
    echo json_encode(['success' => false, 'message' => 'No active guest']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Access granted', 'guest' => $checkin['guest_name']]);
?>
