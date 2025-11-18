<?php
require_once 'database.php';
header('Content-Type: application/json');

// ✅ Set timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid or missing JSON"]);
    exit;
}

// Ensure required fields exist
$order_id = $data['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(["success" => false, "message" => "Missing order_id"]);
    exit;
}

// ------------------------------------
// UPDATE STATUS (prepared / preparing)
// ------------------------------------
if (isset($data['status'])) {
    $new_status = $data['status'];

    if ($new_status === "preparing") {
        // Set start time
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ?, prepare_start_at = NOW() 
            WHERE id = ?
        ");
    } elseif ($new_status === "prepared") {
        // Clear start time (finished)
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ?, prepare_start_at = NULL 
            WHERE id = ?
        ");
    } else {
        // Just change status normally
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ? 
            WHERE id = ?
        ");
    }

    $stmt->bind_param("si", $new_status, $order_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "action" => "status_updated"]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }

    exit;
}

// ------------------------------------
// UPDATE QUANTITY (from Edit button)
// ------------------------------------
if (isset($data['quantity'])) {
    $new_qty = intval($data['quantity']);

    $stmt = $conn->prepare("
        UPDATE orders 
        SET quantity = ?, 
            price = price / quantity * ? 
        WHERE id = ?
    ");

    $stmt->bind_param("iii", $new_qty, $new_qty, $order_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "action" => "quantity_updated"]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }

    exit;
}

// If no valid action:
echo json_encode([
    "success" => false,
    "message" => "No valid update action provided"
]);
exit;