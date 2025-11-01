<?php
// ============================================
// Enhanced get-available-rooms.php
// Fetches all rooms with pricing and status
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide errors from JSON output

session_start();
require_once 'database.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

try {
    // Validate database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Fetch ALL rooms (including occupied ones) with their status and pricing
    $query = "SELECT 
        r.room_number,
        r.room_type,
        r.status,
        r.price_3hrs,
        r.price_6hrs,
        r.price_12hrs,
        r.price_24hrs,
        r.price_ot,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM checkins c 
                WHERE c.room_number = r.room_number 
                AND c.status IN ('checked_in', 'scheduled')
            ) THEN 1
            ELSE 0
        END as is_occupied
    FROM rooms r
    ORDER BY r.room_number ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $rooms = [];
    $roomCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Validate pricing data exists
        $price3hrs = floatval($row['price_3hrs'] ?? 0);
        $price6hrs = floatval($row['price_6hrs'] ?? 0);
        $price12hrs = floatval($row['price_12hrs'] ?? 0);
        $price24hrs = floatval($row['price_24hrs'] ?? 0);
        $priceOt = floatval($row['price_ot'] ?? 0);
        
        // Log warning if pricing is missing (optional - for debugging)
        if ($price3hrs <= 0) {
            error_log("WARNING: Room {$row['room_number']} has no valid 3-hour pricing");
        }
        
        $rooms[] = [
            'room_number' => intval($row['room_number']),
            'room_type' => $row['room_type'] ?? 'standard_room',
            'status' => $row['status'] ?? 'available',
            'is_occupied' => (bool)($row['is_occupied'] ?? false),
            // ✅ Ensure all pricing fields are numbers (not strings)
            'price_3hrs' => $price3hrs,
            'price_6hrs' => $price6hrs,
            'price_12hrs' => $price12hrs,
            'price_24hrs' => $price24hrs,
            'price_ot' => $priceOt
        ];
        
        $roomCount++;
    }
    
    // Log success for debugging
    error_log("SUCCESS: Fetched {$roomCount} rooms with pricing data");
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'rooms' => $rooms,
        'count' => $roomCount,
        'message' => "{$roomCount} room(s) found"
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log error
    error_log("ERROR in get-available-rooms.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'rooms' => [],
        'count' => 0,
        'message' => 'Failed to fetch rooms',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    
    http_response_code(500);
    
} finally {
    // Close database connection
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>