<?php
header('Content-Type: application/json');
session_start();

// $host = "localhost";
// $user = "root";
// $pass = "";
// $db   = "hotel_db";

require_once 'database.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Database connection failed."]);
  exit;
}

$room_number = $_SESSION['room_number'] ?? null;
if (!$room_number) {
  echo json_encode(["success" => false, "message" => "No room session found."]);
  exit;
}

$stmt = $conn->prepare("SELECT r.notes 
                        FROM remarks r
                        JOIN checkins c ON r.checkin_id = c.id
                        WHERE c.room_number = ? AND c.status = 'checked_in'
                        ORDER BY r.created_at DESC
                        LIMIT 1");
$stmt->bind_param("s", $room_number);
$stmt->execute();
$stmt->bind_result($notes);
if ($stmt->fetch()) {
  echo json_encode(["success" => true, "notes" => $notes]);
} else {
  echo json_encode(["success" => false, "notes" => ""]);
}
$stmt->close();
$conn->close();