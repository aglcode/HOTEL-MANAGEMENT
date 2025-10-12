<?php
require_once 'database.php';
header('Content-Type: application/json');

if (isset($_POST['order_id'])) {
    // --- Update single order ---
    $orderId = intval($_POST['order_id']);
    $query = "UPDATE orders SET status = 'served' WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $orderId);
    $success = $stmt->execute();

    echo json_encode(['success' => $success]);
    exit;
}

if (isset($_POST['room_number'])) {
    // --- Update all orders in one room ---
    $roomNumber = $_POST['room_number'];
    $query = "UPDATE orders SET status = 'served' WHERE room_number = ? AND status = 'pending'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $roomNumber);
    $success = $stmt->execute();

    echo json_encode(['success' => $success]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Missing parameters']);
?>
