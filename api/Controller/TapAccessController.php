<?php
 
namespace Api\Controller;

use Response;

class TapAccessController
{
    private $conn;
    private $roomNumber;
    private $code;
    private $roomId;
    private $roomStatus;

    public function __construct($conn, $roomNumber, $code)
    {
        $this->conn = $conn;
        $this->roomNumber = intval($roomNumber);
        $this->code = trim($code);
    }

    /**
     * Main method to process tap access request
     */
    public function process()
    {
        // Validate inputs
        if (!$this->validateInputs()) {
            return;
        }

        // Check database connection
        if (!$this->checkDatabaseConnection()) {
            return;
        }

        // Get and validate room
        if (!$this->getRoomData()) {
            return;
        }

        // Check room availability
        if (!$this->checkRoomAvailability()) {
            return;
        }

        // Validate RFID card
        if (!$this->validateCard()) {
            return;
        }

        // Update tapped_at if room is booked or occupied
        $this->updateTappedAt();

        // Grant access
        new Response(true, 'Access granted', null, 200);
    }

    /**
     * Validate input parameters
     */
    private function validateInputs()
    {
        if ($this->roomNumber <= 0 || empty($this->code)) {
            new Response(false, 'Missing room number or RFID code.', null, 400);
            return false;
        }
        return true;
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection()
    {
        if ($this->conn->connect_error) {
            new Response(false, 'Database connection failed.', null, 500);
            return false;
        }
        return true;
    }

    /**
     * Get room data from database
     */
    private function getRoomData()
    {
        $stmt = $this->conn->prepare("
            SELECT id, status 
            FROM rooms 
            WHERE room_number = ?
        ");

        if (!$stmt) {
            new Response(false, 'Database query preparation failed.', null, 500);
            return false;
        }

        $stmt->bind_param("i", $this->roomNumber);
        
        if (!$stmt->execute()) {
            $stmt->close();
            new Response(false, 'Database query execution failed.', null, 500);
            return false;
        }

        $result = $stmt->get_result();
        $roomData = $result->fetch_assoc();
        $stmt->close();

        if (!$roomData) {
            new Response(false, 'Room not found.', null, 404);
            return false;
        }

        $this->roomId = $roomData['id'];
        $this->roomStatus = $roomData['status'];
        
        return true;
    }

    /**
     * Check if room is available (deny access if available)
     */
    private function checkRoomAvailability()
    {
        if ($this->roomStatus === 'available') {
            new Response(false, 'Room is not booked. Access denied.', null, 403);
            return false;
        }
        return true;
    }

    /**
     * Validate RFID card against cards table
     */
    private function validateCard()
    {
        $stmt = $this->conn->prepare("
            SELECT id 
            FROM cards 
            WHERE room_id = ? AND code = ?
            LIMIT 1
        ");

        if (!$stmt) {
            new Response(false, 'Database query preparation failed.', null, 500);
            return false;
        }

        $stmt->bind_param("is", $this->roomId, $this->code);
        
        if (!$stmt->execute()) {
            $stmt->close();
            new Response(false, 'Database query execution failed.', null, 500);
            return false;
        }

        $result = $stmt->get_result();
        $cardFound = $result->num_rows > 0;
        $stmt->close();

        if (!$cardFound) {
            new Response(false, 'Invalid card code for this room.', null, 403);
            return false;
        }

        return true;
    }

    /**
     * Update tapped_at timestamp for booked/occupied rooms
     */
    private function updateTappedAt()
    {
        if (!in_array($this->roomStatus, ['booked', 'occupied'])) {
            return;
        }

        // Get the latest check-in record for this room
        $stmt = $this->conn->prepare("
            SELECT id, check_in_date 
            FROM checkins 
            WHERE room_number = ? AND status IN ('scheduled', 'checked_in')
            ORDER BY check_in_date DESC 
            LIMIT 1
        ");

        if (!$stmt) {
            return;
        }

        $stmt->bind_param("i", $this->roomNumber);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return;
        }

        $result = $stmt->get_result();
        $checkinData = $result->fetch_assoc();
        $stmt->close();

        if (!$checkinData) {
            return;
        }

        // Update tapped_at to current timestamp
        $stmtUpdate = $this->conn->prepare("
            UPDATE checkins 
            SET tapped_at = NOW() 
            WHERE id = ?
        ");

        if ($stmtUpdate) {
            $stmtUpdate->bind_param("i", $checkinData['id']);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    }
}
