<?php
require_once 'database.php';

$result = $conn->query("
    SELECT room_number, COUNT(*) AS pending_orders
    FROM orders
    WHERE status = 'pending'
    GROUP BY room_number
");

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[$row['room_number']] = (int)$row['pending_orders'];
}

header('Content-Type: application/json');
echo json_encode($orders);
?>
