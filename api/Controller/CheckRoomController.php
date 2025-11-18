<?php

namespace Api\Controller;

use Response;

class CheckRoomController {
    private $conn;
    private $roomNumber;

    public function __construct($conn, $roomNumber)
    {
        $this->conn = $conn;
        $this->roomNumber = intval($roomNumber);
    }

    public function __invoke()
    {
        // Validate room number
        if ($this->roomNumber <= 0) {
            new Response(false, '', null, 400);
            return;
        }

        // Check database connection
        if ($this->conn->connect_error) {
            new Response(false, '', null, 500);
            return;
        }

        // Get room status from database
        $stmt = $this->conn->prepare("
            SELECT status 
            FROM rooms 
            WHERE room_number = ?
        ");

        if (!$stmt) {
            new Response(false, '', null, 500);
            return;
        }

        $stmt->bind_param("i", $this->roomNumber);
        
        if (!$stmt->execute()) {
            $stmt->close();
            new Response(false, '', null, 500);
            return;
        }

        $result = $stmt->get_result();
        $roomData = $result->fetch_assoc();
        $stmt->close();

        if (!$roomData) {
            new Response(false, '', null, 404);
            return;
        }

        if($roomData['status'] === 'available') 
        {
            new Response(false, '', null, 200);
        }else 
        {
            new Response(true, '', null, 200);
        }
    }
}