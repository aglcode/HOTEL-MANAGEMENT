<?php
require_once 'database.php';
header('Content-Type: application/json');

// Fetch currently checked-in sessions
$activeCheckins = [];
$activeQuery = "SELECT id, room_number, check_in_date FROM checkins WHERE status = 'checked_in'";
$activeResult = $conn->query($activeQuery);

if ($activeResult && $activeResult->num_rows > 0) {
    while ($r = $activeResult->fetch_assoc()) {
        $activeCheckins[$r['room_number']] = [
            'id' => $r['id'],
            'check_in_date' => $r['check_in_date']
        ];
    }
}

if (empty($activeCheckins)) {
    echo json_encode([]);
    exit;
}

$groupedOrders = [];

// For each active room, fetch orders created AFTER their check-in time
foreach ($activeCheckins as $roomNumber => $checkinData) {
    $query = "
        SELECT * 
        FROM orders 
        WHERE room_number = ? 
        AND created_at >= ?
        AND status IN ('pending', 'served')
        ORDER BY created_at ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $roomNumber, $checkinData['check_in_date']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $groupedOrders[$roomNumber] = [];
        while ($row = $result->fetch_assoc()) {
            $groupedOrders[$roomNumber][] = $row;
        }
    }
    $stmt->close();
}

echo json_encode($groupedOrders);
?>