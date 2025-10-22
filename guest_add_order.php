<?php
header('Content-Type: application/json');
session_start();

// ===== DATABASE CONNECTION =====
$host = "localhost";
$user = "root";
$pass = "";
$db   = "hotel_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Database connection failed."]);
  exit;
}

// ===== GET RAW JSON =====
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
  echo json_encode(["success" => false, "message" => "Invalid or missing input data."]);
  exit;
}

// ===== GET ROOM NUMBER =====
$room_number = $_SESSION['room_number'] ?? null;
$token = $_SESSION['qr_token'] ?? null;

// If not in session, try QR token
if (!$room_number && $token) {
  $stmtKey = $conn->prepare("SELECT room_number FROM keycards WHERE qr_token = ?");
  $stmtKey->bind_param("s", $token);
  $stmtKey->execute();
  $stmtKey->bind_result($foundRoom);
  if ($stmtKey->fetch()) {
    $room_number = $foundRoom;
  }
  $stmtKey->close();
}

// Validate
if (empty($room_number)) {
  echo json_encode(["success" => false, "message" => "Missing or invalid room number."]);
  exit;
}

// ===== SANITIZE INPUTS =====
$item_name = trim($conn->real_escape_string($data["item_name"] ?? ""));
$size      = isset($data["size"]) ? $conn->real_escape_string($data["size"]) : null;
$price     = floatval($data["price"] ?? 0);     // price per unit
$quantity  = intval($data["quantity"] ?? 1);    // new quantity to add

if (empty($item_name)) {
  echo json_encode(["success" => false, "message" => "Missing item name."]);
  exit;
}

// ===== CATEGORY DETECTION =====
$foodItems = [
  "Mami", "Nissin Cup (Beef)", "Nissin Cup (Chicken)", "Nissin Cup (Spicy Seafood)",
  "Longganisa", "Sisig", "Bopis", "Tocino", "Tapa", "Hotdog", "Dinuguan", "Chicken Adobo",
  "Bicol Express", "Chicharon", "Chicken Skin", "Shanghai (3pcs)", "Gulay (3pcs)", "Toge (4pcs)",
  "French Fries (BBQ)", "French Fries (Sour Cream)", "French Fries (Cheese)", "Cheese Sticks (12pcs)",
  "Tinapay (3pcs)", "Tinapay with Spread (3pcs)", "Burger Regular", "Burger with Cheese",
  "Nagaraya Butter Yellow (Small)", "Niva Country Cheddar (Small)", "Bottled Water (500ml)",
  "Purified Hot Water Only (Mug)", "Ice Bucket", "Coke Mismo", "Royal Mismo", "Sting Energy Drink",
  "Dragon Fruit", "Mango", "Cucumber", "Avocado", "Chocolate", "Taro", "Ube", "Strawberry",
  "Del Monte Pineapple Juice", "Instant Coffee", "Brewed Coffee", "Hot Tea (Green)",
  "Hot Tea (Black)", "Milo Hot Chocolate Drink"
];

$category = in_array($item_name, $foodItems) ? "Food" : "Non-Food";

// ===== CHECK IF ITEM ALREADY EXISTS =====
if ($size) {
  $check = $conn->prepare("SELECT id, quantity, price FROM orders WHERE room_number = ? AND item_name = ? AND size = ?");
  $check->bind_param("sss", $room_number, $item_name, $size);
} else {
  $check = $conn->prepare("SELECT id, quantity, price FROM orders WHERE room_number = ? AND item_name = ? AND size IS NULL");
  $check->bind_param("ss", $room_number, $item_name);
}

$check->execute();
$result = $check->get_result();

if ($row = $result->fetch_assoc()) {
  // ✅ Merge duplicate: add quantity and recalc total price
  $existingQty = intval($row["quantity"]);
  $newQty = $existingQty + $quantity;

  // Calculate new total price based on per-item price
  $unitPrice = $price; // ensure it's per-item price
  $newTotalPrice = $unitPrice * $newQty;

  // Update existing record
  $update = $conn->prepare("
    UPDATE orders
    SET quantity = ?, price = ?, created_at = NOW()
    WHERE id = ?
  ");
  $update->bind_param("idi", $newQty, $newTotalPrice, $row["id"]);
  $success = $update->execute();
  $update->close();
} else {
  // ✅ Insert new row
  $totalPrice = $price * $quantity;
  $insert = $conn->prepare("
    INSERT INTO orders (room_number, category, item_name, size, price, quantity, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $insert->bind_param("sssdii", $room_number, $category, $item_name, $size, $totalPrice, $quantity);
  $success = $insert->execute();
  $insert->close();
}

$check->close();

if ($success) {
  echo json_encode(["success" => true, "message" => "Order saved successfully!"]);
} else {
  echo json_encode(["success" => false, "message" => "Failed to save order."]);
}

$conn->close();