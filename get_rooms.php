<?php
require_once 'database.php';

header('Content-Type: application/json');

$query = "SELECT room_number, room_type, price_3hrs, price_6hrs, price_12hrs, price_24hrs, price_ot FROM rooms WHERE status = 'available' ORDER BY room_number";
$result = $conn->query($query);

$rooms = [];
while ($row = $result->fetch_assoc()) {
    $rooms[] = $row;
}

echo json_encode($rooms);
$conn->close();
?>