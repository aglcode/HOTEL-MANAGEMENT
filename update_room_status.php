<?php
require_once 'database.php';
header('Content-Type: application/json');

$room_number = $_POST['room_number'] ?? null;
$status      = $_POST['status'] ?? null;

if (!$room_number || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing room number or status']);
    exit;
}

$room_number = trim((string)$room_number);
$status = strtolower(trim((string)$status));

if ($status === 'checked_out') {
    // ✅ 1️⃣ Update check-in record
    $stmt = $conn->prepare("UPDATE checkins SET status = 'checked_out' WHERE room_number = ?");
    $stmt->bind_param("i", $room_number);
    $stmt->execute();
    $stmt->close();

    // ✅ 2️⃣ Free up the room
    $updateRoom = $conn->prepare("UPDATE rooms SET status = 'available' WHERE room_number = ?");
    $updateRoom->bind_param("i", $room_number);
    $updateRoom->execute();
    $updateRoom->close();

    // ✅ 3️⃣ Delete all orders (pending or served)
    $del = $conn->prepare("DELETE FROM orders WHERE room_number = ? AND status IN ('pending','served')");
    $del->bind_param("s", $room_number);
    $del->execute();
    $affected = $del->affected_rows;
    $del->close();

    echo json_encode([
        'success' => true,
        'message' => "Guest checked out — {$affected} order(s) deleted and room set to available."
    ]);
    exit;
}

// ✅ 4️⃣ Handle other statuses (checked_in, etc.)
$stmt = $conn->prepare("UPDATE checkins SET status = ? WHERE room_number = ?");
$stmt->bind_param("si", $status, $room_number);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => "Room status updated to {$status}."]);
?>
