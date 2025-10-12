<?php
header('Content-Type: application/json');
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "hotel_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Database connection failed."]);
  exit;
}

$room_number = $_SESSION['room_number'] ?? null;
$token = $_SESSION['qr_token'] ?? null;

// ✅ Fallback if token available but no session
if (!$room_number && $token) {
  $stmt = $conn->prepare("SELECT room_number FROM keycards WHERE qr_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $stmt->bind_result($foundRoom);
  if ($stmt->fetch()) $room_number = $foundRoom;
  $stmt->close();
}

// ✅ Ensure valid room
if (empty($room_number)) {
  echo json_encode(["success" => false, "message" => "No valid room number found."]);
  exit;
}

// ✅ Fetch all necessary order details (include created_at)
$stmt = $conn->prepare("
  SELECT id, item_name, size, price, quantity, mode_payment, status, created_at 
  FROM orders 
  WHERE room_number = ?
  ORDER BY created_at DESC
");
$stmt->bind_param("s", $room_number);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
  // ✅ Ensure consistent date format (for JS)
  $row['created_at'] = date("Y-m-d H:i:s", strtotime($row['created_at']));
  $orders[] = $row;
}

echo json_encode(["success" => true, "orders" => $orders]);

$stmt->close();
$conn->close();