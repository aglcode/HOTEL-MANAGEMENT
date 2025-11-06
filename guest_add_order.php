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

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
  echo json_encode(["success" => false, "message" => "Invalid or missing input data."]);
  exit;
}

$room_number = $_SESSION['room_number'] ?? null;
$token = $_SESSION['qr_token'] ?? null;

$guest_id = null;
if ($room_number) {
  $stmt = $conn->prepare("SELECT id FROM checkins WHERE room_number = ? AND status = 'checked_in' LIMIT 1");
  $stmt->bind_param("s", $room_number);
  $stmt->execute();
  $stmt->bind_result($guest_id);
  $stmt->fetch();
  $stmt->close();
}

if (!$guest_id) {
  echo json_encode(["success" => false, "message" => "No active check-in found for this room."]);
  exit;
}

$item_name = trim($conn->real_escape_string($data["item_name"] ?? ""));
$size      = isset($data["size"]) ? $conn->real_escape_string($data["size"]) : null;
$quantity  = intval($data["quantity"] ?? 1);

if (empty($item_name)) {
  echo json_encode(["success" => false, "message" => "Missing item name."]);
  exit;
}

$stmt = $conn->prepare("SELECT id, price, category, quantity FROM supplies WHERE name = ? AND is_archived = 0 LIMIT 1");
$stmt->bind_param("s", $item_name);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
  echo json_encode(["success" => false, "message" => "Item not found in supplies table."]);
  exit;
}

$supply_id = intval($row['id']);
$unit_price = floatval($row['price']);
$category = $row['category'];
$current_stock = intval($row['quantity']);
$stmt->close();

if ($current_stock <= 0) {
  echo json_encode(["success" => false, "message" => "Item is out of stock."]);
  exit;
}

if ($quantity > $current_stock) {
  echo json_encode(["success" => false, "message" => "Only {$current_stock} left in stock."]);
  exit;
}

if ($size) {
  $check = $conn->prepare("SELECT id, quantity FROM orders WHERE checkin_id = ? AND room_number = ? AND supply_id = ? AND size = ?");
  $check->bind_param("isis", $guest_id, $room_number, $supply_id, $size);
} else {
  $check = $conn->prepare("SELECT id, quantity FROM orders WHERE checkin_id = ? AND room_number = ? AND supply_id = ? AND size IS NULL");
  $check->bind_param("isi", $guest_id, $room_number, $supply_id);
}

$check->execute();
$result = $check->get_result();

if ($row = $result->fetch_assoc()) {
  $existingQty = intval($row["quantity"]);
  $newQty = $existingQty + $quantity;
  $newTotal = $unit_price * $newQty;
  $update = $conn->prepare("UPDATE orders SET quantity = ?, price = ?, created_at = NOW() WHERE id = ?");
  $update->bind_param("idi", $newQty, $newTotal, $row["id"]);
  $success = $update->execute();
  $update->close();
} else {
  $totalPrice = $unit_price * $quantity;
  $insert = $conn->prepare("
    INSERT INTO orders (checkin_id, room_number, supply_id, category, item_name, size, price, quantity, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  $insert->bind_param("isissdii", $guest_id, $room_number, $supply_id, $category, $item_name, $size, $totalPrice, $quantity);
  $success = $insert->execute();
  $insert->close();
}

$check->close();

if ($success) {
  // ✅ Do NOT update stock if quantity is 999 (infinite stock)
  if ($current_stock != 999) {
    $new_stock = max(0, $current_stock - $quantity);

    // Update the stock quantity
    $update_stock = $conn->prepare("UPDATE supplies SET quantity = ? WHERE id = ?");
    $update_stock->bind_param("ii", $new_stock, $supply_id);
    $update_stock->execute();
    $update_stock->close();

    // Update the status automatically only if not infinite stock
    $status = $new_stock <= 0 ? 'unavailable' : 'available';
    $update_status = $conn->prepare("UPDATE supplies SET status = ? WHERE id = ?");
    $update_status->bind_param("si", $status, $supply_id);
    $update_status->execute();
    $update_status->close();
  }
  // else -> if 999, skip any stock or status change (admin controls status manually)
}

echo json_encode([
  "success" => $success,
  "message" => $success
    ? "Order saved successfully and stock updated!"
    : "Failed to save order."
]);

$conn->close();