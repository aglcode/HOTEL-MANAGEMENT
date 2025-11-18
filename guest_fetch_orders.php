<?php
header('Content-Type: application/json');
session_start();
require_once 'database.php';

// ✅ Set timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// ✅ Validate session
$room_number = trim($_SESSION['room_number'] ?? '');
$token = $_SESSION['qr_code'] ?? '';

if (empty($room_number) && !empty($token)) {
  $stmt = $conn->prepare("SELECT room_number FROM keycards WHERE qr_code = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $stmt->bind_result($foundRoom);
  if ($stmt->fetch()) $room_number = trim($foundRoom);
  $stmt->close();
}

if (empty($room_number)) {
  echo json_encode(["success" => false, "message" => "No valid room number found."]);
  exit;
}

// ✅ Get active checkin info
$stmt = $conn->prepare("
  SELECT id, check_in_date 
  FROM checkins 
  WHERE room_number = ? AND status = 'checked_in' 
  LIMIT 1
");
$stmt->bind_param("s", $room_number);
$stmt->execute();
$stmt->bind_result($checkin_id, $check_in_date);
$stmt->fetch();
$stmt->close();

if (empty($checkin_id)) {
  echo json_encode(["success" => false, "message" => "No active check-in for this room."]);
  exit;
}

// ✅ Fetch only orders tied to current checkin
$stmt = $conn->prepare("
    SELECT 
      o.id,
      o.item_name,
      o.size,
      o.price,
      o.quantity,
      o.status,
      o.created_at,
      o.supply_id,
      s.quantity AS supply_quantity
    FROM orders o
    LEFT JOIN supplies s ON o.supply_id = s.id
    WHERE o.room_number = ? AND o.checkin_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param("si", $room_number, $checkin_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
  $row['created_at'] = date("Y-m-d H:i:s", strtotime($row['created_at']));
  $orders[] = $row;
}

echo json_encode(["success" => true, "orders" => $orders]);
$stmt->close();
$conn->close();