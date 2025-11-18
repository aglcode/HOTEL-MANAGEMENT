<?php
require_once 'database.php'; 
header('Content-Type: application/json');

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['id'])) {
    echo json_encode([
        "success" => false, 
        "message" => "Invalid request data."
    ]);
    exit;
}

$order_id = intval($data['id']);

$stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true, 
        "message" => "Order deleted successfully."
    ]);
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Failed to delete order."
    ]);
}

$stmt->close();
$conn->close();