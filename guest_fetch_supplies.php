<?php
include 'database.php'; // connects to hotel_db

header('Content-Type: application/json');

// Fetch active supplies only (not archived)
$query = "SELECT name, quantity, status FROM supplies WHERE is_archived = 0";
$result = $conn->query($query);

$stocks = [];

while ($row = $result->fetch_assoc()) {
    $item_name = strtolower(trim($row['name']));
    $stocks[$item_name] = [
        "quantity" => (int)$row['quantity'],
        "status"   => $row['status'] ?? 'available'
    ];
}

echo json_encode($stocks);