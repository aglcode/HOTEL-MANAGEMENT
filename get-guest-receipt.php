<?php
session_start();
require_once 'database.php';
header('Content-Type: application/json');

if (!isset($_GET['guest_id'])) {
    echo json_encode(['success' => false, 'message' => 'Guest ID required']);
    exit;
}

$guest_id = (int)$_GET['guest_id'];

// ✅ Fetch guest information + check-in details
$stmt = $conn->prepare("
    SELECT c.*, r.room_type 
    FROM checkins c 
    LEFT JOIN rooms r ON c.room_number = r.room_number 
    WHERE c.id = ?
");
$stmt->bind_param('i', $guest_id);
$stmt->execute();
$result = $stmt->get_result();
$guest = $result->fetch_assoc();
$stmt->close();

if (!$guest) {
    echo json_encode(['success' => false, 'message' => 'Guest not found']);
    exit;
}

// --- Compute stay details ---
$check_in = new DateTime($guest['check_in_date']);
$check_out = new DateTime($guest['check_out_date']);
$interval = $check_in->diff($check_out);
$stay_duration = ($interval->days * 24) + $interval->h;

$total_price = (float)($guest['total_price'] ?? 0);
$amount_paid = (float)($guest['amount_paid'] ?? 0);

// --- Extension fee (₱120/hour after 3 hours) ---
$base_hours = 3;
$extension_hours = max(0, $stay_duration - $base_hours);
$extension_fee = $extension_hours * 120;
$base_rate = $total_price - $extension_fee;

$guest['stay_duration'] = $stay_duration;
$guest['extended_hours'] = $guest['extended_hours'] ?? 0;
$guest['extension_fee'] = $extension_fee;
$guest['base_rate'] = $base_rate;

// --- Fetch orders for this room, after check-in time ---
$room_number = $guest['room_number'];
$check_in_date = $guest['check_in_date'];

$order_stmt = $conn->prepare("
    SELECT id, category, item_name, size, price, quantity, status, created_at
    FROM orders 
    WHERE room_number = ? 
      AND created_at >= ? 
    ORDER BY created_at DESC
");
$order_stmt->bind_param('ss', $room_number, $check_in_date);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

$orders = [];
$orders_total = 0;

while ($order = $order_result->fetch_assoc()) {
    $order['created_at'] = date("Y-m-d H:i:s", strtotime($order['created_at']));
    $orders[] = $order;
    $orders_total += (float)$order['price']; // only price, not qty
}
$order_stmt->close();

// --- Combine totals ---
$guest['orders'] = $orders;
$guest['orders_total'] = $orders_total;
$guest['grand_total'] = $total_price + $orders_total;
$guest['change_amount'] = $amount_paid - $guest['grand_total'];

echo json_encode(['success' => true, 'guest' => $guest]);
$conn->close();
?>
