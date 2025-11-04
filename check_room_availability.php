<?php
// Suppress all output except our JSON
ini_set('display_errors', 0);
error_reporting(0);

// Clean any output buffer
if (ob_get_level()) {
    ob_clean();
}

require_once 'database.php';

// Set JSON header immediately
header('Content-Type: application/json');

try {
    // Get raw input
    $raw_input = file_get_contents('php://input');
    
    if (empty($raw_input)) {
        throw new Exception('No input received');
    }
    
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validate required fields
    $required_fields = ['room_number', 'start_date', 'duration'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required parameter: $field");
        }
    }
    
    $room_number = (int)$input['room_number'];
    $start_date = $input['start_date'];
    $duration = (int)$input['duration'];
    
    // Validate data types
    if ($room_number <= 0 || $duration <= 0) {
        throw new Exception('Invalid room_number or duration');
    }

    // Calculate end date
    $start_datetime = new DateTime($start_date);
    $end_datetime = clone $start_datetime;
    $end_datetime->modify("+$duration hours");

    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

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
    
    $start_date_formatted = $start_datetime->format('Y-m-d H:i:s');
    $end_date_formatted = $end_datetime->format('Y-m-d H:i:s');
    
    $stmt->bind_param("iss", $room_number, $start_date_formatted, $end_date_formatted);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conflicts = $row ? (int)$row['conflicts'] : 0;

    // Check room status
    $room_query = "SELECT status FROM rooms WHERE room_number = ?";
    $room_stmt = $conn->prepare($room_query);
    if (!$room_stmt) {
        throw new Exception('Room query prepare failed: ' . $conn->error);
    }
    
    $room_stmt->bind_param("i", $room_number);
    
    if (!$room_stmt->execute()) {
        throw new Exception('Room query execution failed: ' . $room_stmt->error);
    }
    
    $room_result = $room_stmt->get_result();
    $room_data = $room_result->fetch_assoc();
    
    if (!$room_data) {
        throw new Exception('Room not found');
    }
    
    $room_status = $room_data['status'];

    $available = ($conflicts == 0 && $room_status == 'available');

    $response = [
        'available' => $available,
        'debug' => [
            'conflicts' => $conflicts,
            'room_status' => $room_status,
            'start_date' => $start_date_formatted,
            'end_date' => $end_date_formatted,
            'room_number' => $room_number,
            'duration' => $duration
        ]
    ];

    // Clean any remaining output buffer before sending JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode($response);

    $stmt->close();
    $room_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    // Clean any output buffer before sending error JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(), 
        'available' => false
    ]);
}
?>