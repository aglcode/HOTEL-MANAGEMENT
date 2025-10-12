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

// ===== GET ROOM NUMBER FROM SESSION OR KEYCARDS =====
$room_number = $_SESSION['room_number'] ?? null;
$token = $_SESSION['qr_token'] ?? null;

// If not in session, attempt to look up via token (if you have a keycards table)
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

// Validate room number
if (empty($room_number)) {
  echo json_encode(["success" => false, "message" => "Missing or invalid room number."]);
  exit;
}

// ===== SANITIZE INPUTS =====
$item_name    = $conn->real_escape_string($data["item_name"] ?? "");
$size         = $conn->real_escape_string($data["size"] ?? null);
$price        = floatval($data["price"] ?? 0);
$quantity     = intval($data["quantity"] ?? 1);
$mode_payment = $conn->real_escape_string($data["mode_payment"] ?? "cash");
$ref_number   = $data["ref_number"] ?? null;

// ===== VALIDATION =====
if (empty($item_name)) {
  echo json_encode(["success" => false, "message" => "Missing item name."]);
  exit;
}

// ===== CATEGORY LOGIC =====
$foodItems = [
  "Lomi", "Mami", "Nissin Cup (Beef)", "Nissin Cup (Chicken)", "Nissin Cup (Spicy Seafood)",
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

// ===== GCASH VALIDATION =====
if ($mode_payment === "gcash") {
  if (!preg_match('/^[0-9]{12,14}$/', $ref_number)) {
    echo json_encode(["success" => false, "message" => "Invalid GCash reference number (must be 12–14 digits)."]);
    exit;
  }
} else {
  // Make sure ref number is NULL for cash
  $ref_number = null;
}

// ===== PREPARE INSERT =====
$stmt = $conn->prepare("
  INSERT INTO orders (room_number, category, item_name, size, price, quantity, mode_payment, ref_number)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
  echo json_encode(["success" => false, "message" => "Query preparation failed."]);
  exit;
}

// === BIND PARAMETERS ===
$stmt->bind_param(
  "ssssdiss",
  $room_number,
  $category,
  $item_name,
  $size,
  $price,
  $quantity,
  $mode_payment,
  $ref_number
);

// ===== EXECUTE =====
if ($stmt->execute()) {
  echo json_encode(["success" => true, "message" => "Order successfully saved!"]);
} else {
  echo json_encode(["success" => false, "message" => "Failed to save order."]);
}

// ===== CLEANUP =====
$stmt->close();
$conn->close();