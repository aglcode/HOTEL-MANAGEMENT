<?php
header('Content-Type: application/json');
session_start();

// =====================
// DATABASE CONNECTION
// =====================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "hotel_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Database connection failed."]);
  exit;
}

// =====================
// VALIDATE INPUT
// =====================
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || empty($data['id']) || empty($data['quantity'])) {
  echo json_encode(["success" => false, "message" => "Invalid request data."]);
  exit;
}

$id = intval($data['id']);
$newQty = intval($data['quantity']);

// =====================
// GET CURRENT ORDER
// =====================
$stmt = $conn->prepare("SELECT price, quantity FROM orders WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode(["success" => false, "message" => "Order not found."]);
  exit;
}

$order = $result->fetch_assoc();
$oldTotal = floatval($order['price']);
$oldQty = intval($order['quantity']);
if ($oldQty <= 0) $oldQty = 1;

// ✅ Calculate new price proportionally
// Example: old price = 180, old qty = 3 → per unit = 60
// new qty = 1 → new price = 60 × 1 = 60
$unitPrice = $oldTotal / $oldQty;
$newTotal = $unitPrice * $newQty;

// =====================
// UPDATE ORDER
// =====================
$update = $conn->prepare("UPDATE orders SET quantity = ?, price = ? WHERE id = ?");
$update->bind_param("idi", $newQty, $newTotal, $id);

if ($update->execute()) {
  echo json_encode([
    "success" => true,
    "message" => "Order updated successfully.",
    "new_price" => number_format($newTotal, 2)
  ]);
} else {
  echo json_encode(["success" => false, "message" => "Failed to update order."]);
}

$update->close();
$stmt->close();
$conn->close();