<?php
header('Content-Type: application/json');
session_start();
require_once 'database.php';

// ✅ Set timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// ✅ Decode JSON
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid or missing input data."]);
    exit;
}

$room_number = $_SESSION['room_number'] ?? null;
if (!$room_number) {
    echo json_encode(["success" => false, "message" => "No active session found."]);
    exit;
}

// ✅ Get active check-in
$stmt = $conn->prepare("SELECT id FROM checkins WHERE room_number = ? AND status = 'checked_in' LIMIT 1");
$stmt->bind_param("s", $room_number);
$stmt->execute();
$stmt->bind_result($checkin_id);
$stmt->fetch();
$stmt->close();

if (!$checkin_id) {
    echo json_encode(["success" => false, "message" => "No active check-in found for this room."]);
    exit;
}

$item_name = trim($data["item_name"] ?? "");
$quantity  = intval($data["quantity"] ?? 1);
if (empty($item_name)) {
    echo json_encode(["success" => false, "message" => "Missing item name."]);
    exit;
}

// ✅ Get supply info
$stmt = $conn->prepare("SELECT id, price, category, quantity FROM supplies WHERE name = ? AND is_archived = 0 LIMIT 1");
$stmt->bind_param("s", $item_name);
$stmt->execute();
$supply = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supply) {
    echo json_encode(["success" => false, "message" => "Item not found."]);
    exit;
}

$supply_id     = $supply["id"];
$unit_price    = (float)$supply["price"];
$category      = $supply["category"];
$current_stock = (int)$supply["quantity"];

if ($current_stock != 999 && $quantity > $current_stock) {
    echo json_encode(["success" => false, "message" => "Only $current_stock left in stock."]);
    exit;
}

$status = 'pending';
$size = null;
$created_at = date('Y-m-d H:i:s');

// ✅ Check if same item already exists (merge logic)
$check = $conn->prepare("
    SELECT id, quantity 
    FROM orders 
    WHERE checkin_id = ? 
      AND room_number = ? 
      AND LOWER(item_name) = LOWER(?) 
      AND status = 'pending'
    LIMIT 1
");
$check->bind_param("iss", $checkin_id, $room_number, $item_name);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

// If existing item is already prepared/served → DO NOT MERGE it
if ($existing && ($existing['status'] === 'prepared' || $existing['status'] === 'served')) {
    $existing = false;  // force insert new row
}

if ($existing) {
    // ✅ Merge quantity and update total price
    $new_quantity = $existing['quantity'] + $quantity;
    $total_price = $unit_price * $new_quantity; // total = unit * quantity

    $update = $conn->prepare("
        UPDATE orders 
        SET quantity = ?, price = ?, status = 'pending'
        WHERE id = ?
    ");
    $update->bind_param("idi", $new_quantity, $total_price, $existing['id']);
    $success = $update->execute();
    $update->close();

} else {
    // Insert new row with total price
    $total_price = $unit_price * $quantity;

    $insert = $conn->prepare("
        INSERT INTO orders 
        (checkin_id, supply_id, room_number, category, item_name, size, price, quantity, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    $insert->bind_param(
        "iissssdis",
        $checkin_id,
        $supply_id,
        $room_number,
        $category,
        $item_name,
        $size,
        $total_price,
        $quantity,
        $created_at
    );
    $success = $insert->execute();
    $insert->close();
}

// ✅ Deduct from stock if limited
if ($success && $current_stock != 999) {
    $new_stock = max(0, $current_stock - $quantity);

    // Update stock
    $update_stock = $conn->prepare("UPDATE supplies SET quantity = ? WHERE id = ?");
    $update_stock->bind_param("ii", $new_stock, $supply_id);
    $update_stock->execute();
    $update_stock->close();

    // ✅ Auto-update status when stock reaches 0
    if ($new_stock == 0) {
        $update_status = $conn->prepare("UPDATE supplies SET status = 'unavailable' WHERE id = ?");
        $update_status->bind_param("i", $supply_id);
        $update_status->execute();
        $update_status->close();
    }
}

echo json_encode([
    "success" => $success,
    "message" => $success ? "Order added/merged successfully!" : "Failed to save order."
]);

$conn->close();
?>