<?php
require_once 'database.php';
header('Content-Type: application/json');

if (!isset($_POST['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID']);
    exit;
}

$orderId = intval($_POST['order_id']);

$query = "UPDATE orders SET status = 'served' WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $orderId);
$success = $stmt->execute();

echo json_encode(['success' => $success]);
?>
