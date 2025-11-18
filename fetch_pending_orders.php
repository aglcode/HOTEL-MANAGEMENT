<?php
require_once 'database.php';
header('Content-Type: application/json');

try {
    // ✅ Fetch orders + supply stock
    $query = "
        SELECT 
            o.id,
            o.checkin_id,
            o.supply_id,
            o.room_number,
            o.category,
            o.item_name,
            o.size,
            o.price,
            o.quantity,
            o.status,
            o.prepare_start_at,
            o.created_at,
            s.quantity AS supply_quantity
        FROM orders o
        INNER JOIN checkins c 
            ON o.checkin_id = c.id 
           AND o.room_number = c.room_number
        LEFT JOIN supplies s               -- ✅ Added
            ON o.supply_id = s.id
        WHERE c.status = 'checked_in'
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
            'checkin_id' => $row['checkin_id'],
            'supply_id' => $row['supply_id'],
            'room_number' => $row['room_number'],
            'category' => $row['category'],
            'item_name' => $row['item_name'],
            'size' => $row['size'],
            'price' => $row['price'],
            'quantity' => $row['quantity'],
            'status' => $row['status'],
            'prepare_start_at' => $row['prepare_start_at'],
            'created_at' => $row['created_at'],
            'supply_quantity' => $row['supply_quantity'] ?? null   
        ];
    }

    echo json_encode($orders);

} catch (Exception $e) {
    error_log("Error in fetch_pending_orders.php: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>