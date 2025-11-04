<?php
require_once 'database.php';
header('Content-Type: application/json');

try {
    // Query to get orders only from rooms with active check-ins
    $query = "
        SELECT o.* 
        FROM orders o
        INNER JOIN checkins c ON o.room_number = c.room_number
        WHERE c.status IN ('scheduled', 'checked_in')
        AND NOW() BETWEEN c.check_in_date AND c.check_out_date
        ORDER BY o.created_at ASC
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        $room = $row['room_number'];
        
        if (!isset($orders[$room])) {
            $orders[$room] = [];
        }
        
        $orders[$room][] = [
            'id' => $row['id'],
            'room_number' => $row['room_number'],
            'category' => $row['category'],
            'item_name' => $row['item_name'],
            'size' => $row['size'],
            'price' => $row['price'],
            'quantity' => $row['quantity'],
            'status' => $row['status'],
            'mode_payment' => $row['mode_payment'] ?? 'N/A',
            'created_at' => $row['created_at']
        ];
    }
    
    // Clean up orders from rooms that are checked out
    // This will delete orders from rooms with no active check-ins
    $cleanupQuery = "
        DELETE o FROM orders o
        LEFT JOIN checkins c ON o.room_number = c.room_number 
            AND c.status IN ('scheduled', 'checked_in')
            AND NOW() BETWEEN c.check_in_date AND c.check_out_date
        WHERE c.id IS NULL
    ";
    $conn->query($cleanupQuery);
    
    echo json_encode($orders);
    
} catch (Exception $e) {
    error_log("Error in fetch_pending_orders.php: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>