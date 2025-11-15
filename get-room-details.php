<?php
// get-room-details.php
header('Content-Type: application/json');
require_once 'database.php';

try {
    if (!isset($_GET['room_number'])) {
        throw new Exception('Room number is required');
    }
    
    $room_number = intval($_GET['room_number']);
    
    if ($room_number <= 0) {
        throw new Exception('Invalid room number');
    }
    
    // Fetch the specific room details
    $stmt = $conn->prepare("
        SELECT 
            room_number,
            room_type,
            status,
            price_3hrs,
            price_6hrs,
            price_12hrs,
            price_24hrs,
            price_ot
        FROM rooms
        WHERE room_number = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $room_number);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        throw new Exception('Room not found');
    }
    
    // Return the room data
    echo json_encode([
        'success' => true,
        'room' => [
            'room_number' => $room['room_number'],
            'room_type' => $room['room_type'],
            'status' => $room['status'],
            'price_3hrs' => $room['price_3hrs'],
            'price_6hrs' => $room['price_6hrs'],
            'price_12hrs' => $room['price_12hrs'],
            'price_24hrs' => $room['price_24hrs'],
            'price_ot' => $room['price_ot']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Get Room Details Error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>