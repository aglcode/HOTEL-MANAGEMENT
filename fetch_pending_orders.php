<?php
require_once 'database.php';
header('Content-Type: application/json');

// Fetch active (checked-in) rooms
$activeRooms = [];
$activeQuery = "SELECT room_number FROM checkins WHERE NOW() BETWEEN check_in_date AND check_out_date";
$activeResult = $conn->query($activeQuery);

if ($activeResult && $activeResult->num_rows > 0) {
    while ($r = $activeResult->fetch_assoc()) {
        $activeRooms[] = $r['room_number'];
    }
}

if (empty($activeRooms)) {
    echo json_encode([]);
    exit;
}

// Convert to a comma-separated list for SQL
$roomList = "'" . implode("','", $activeRooms) . "'";

// Fetch pending and served orders for active rooms
$query = "
    SELECT * 
    FROM orders 
    WHERE room_number IN ($roomList)
    AND status IN ('pending', 'served')
    ORDER BY room_number, created_at ASC
";
$result = $conn->query($query);

$groupedOrders = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $room = $row['room_number'];
        $groupedOrders[$room][] = $row;
    }
}

echo json_encode($groupedOrders);
?>
