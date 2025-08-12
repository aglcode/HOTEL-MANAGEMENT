<?php
require_once 'database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $room_number = $input['room_number'];
    $start_date = $input['start_date'];
    $duration = $input['duration'];
    
    if (!$room_number || !$start_date || !$duration) {
        throw new Exception('Missing required parameters');
    }

    // Calculate end date
    $start_datetime = new DateTime($start_date);
    $end_datetime = clone $start_datetime;
    $end_datetime->modify("+$duration hours");

    // Check for conflicts in bookings table
    $query = "SELECT COUNT(*) as conflicts FROM bookings 
              WHERE room_number = ? 
              AND status IN ('upcoming', 'active') 
              AND NOT (
                  end_date <= ? OR start_date >= ?
              )";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("iss", $room_number, $start_date, $end_datetime->format('Y-m-d H:i:s'));
    $stmt->execute();
    $result = $stmt->get_result();
    $conflicts = $result->fetch_assoc()['conflicts'];

    // Also check room status
    $room_query = "SELECT status FROM rooms WHERE room_number = ?";
    $room_stmt = $conn->prepare($room_query);
    if (!$room_stmt) {
        throw new Exception('Room query prepare failed: ' . $conn->error);
    }
    
    $room_stmt->bind_param("i", $room_number);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    $room_data = $room_result->fetch_assoc();
    
    if (!$room_data) {
        throw new Exception('Room not found');
    }
    
    $room_status = $room_data['status'];

    $available = ($conflicts == 0 && $room_status == 'available');

    echo json_encode(['available' => $available, 'debug' => [
        'conflicts' => $conflicts,
        'room_status' => $room_status,
        'start_date' => $start_date,
        'end_date' => $end_datetime->format('Y-m-d H:i:s')
    ]]);

    $stmt->close();
    $room_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'available' => false]);
}
?>