<?php
require_once 'database.php';
header('Content-Type: application/json');

// --- Update a single order by ID ---
if (isset($_POST['order_id'])) {
    $orderId = intval($_POST['order_id']);

    $stmt = $conn->prepare("UPDATE orders SET status = 'served' WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $success = $stmt->affected_rows > 0;
    $stmt->close();

    echo json_encode(['success' => $success]);
    exit;
}

// --- Update all pending orders in one room ---
if (isset($_POST['room_number'])) {
    $roomNumber = trim($_POST['room_number']);

    $stmt = $conn->prepare("UPDATE orders SET status = 'served' WHERE room_number = ? AND status = 'pending'");
    $stmt->bind_param("s", $roomNumber);
    $stmt->execute();

    $success = $stmt->affected_rows > 0;
    $stmt->close();

    echo json_encode(['success' => $success]);
    exit;
}

// --- Fallback ---
echo json_encode(['success' => false, 'message' => 'Missing parameters']);
?>
