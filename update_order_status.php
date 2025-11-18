<?php
require_once 'database.php';
header('Content-Type: application/json');

// Ensure timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// ==========================================================
// 1. SERVE A SINGLE ORDER
// ==========================================================
if (isset($_POST['order_id'])) {

    $orderId = intval($_POST['order_id']);

    // Mark as served + clear any running prep timer
    $stmt = $conn->prepare("
        UPDATE orders 
        SET status = 'served', prepare_start_at = NULL 
        WHERE id = ?
    ");
    
    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $success = $stmt->affected_rows > 0;
    $stmt->close();

    echo json_encode(['success' => $success]);
    exit;
}


// ==========================================================
// 2. SERVE ALL ORDERS IN A ROOM (pending or prepared)
// ==========================================================
if (isset($_POST['room_number'])) {

    $roomNumber = trim($_POST['room_number']);

    // Serve all orders that are NOT already served
    $stmt = $conn->prepare("
        UPDATE orders 
        SET status = 'served', prepare_start_at = NULL
        WHERE room_number = ?
        AND status IN ('pending', 'prepared')
    ");
    
    $stmt->bind_param("s", $roomNumber);
    $stmt->execute();

    $success = $stmt->affected_rows > 0;
    $stmt->close();

    echo json_encode(['success' => $success]);
    exit;
}


// ==========================================================
// 3. FALLBACK: Missing parameters
// ==========================================================
echo json_encode([
    'success' => false,
    'message' => 'Missing order_id or room_number'
]);
exit;
?>