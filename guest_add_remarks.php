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

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['notes'])) {
  echo json_encode(["success" => false, "message" => "Invalid request."]);
  exit;
}

$notes = trim($data['notes']); // may be empty
$room_number = $_SESSION['room_number'] ?? null;

if (!$room_number) {
  echo json_encode(["success" => false, "message" => "No room session found."]);
  exit;
}

// ✅ Find active check-in for this room
$stmt = $conn->prepare("SELECT id FROM checkins WHERE room_number = ? AND status = 'checked_in' LIMIT 1");
$stmt->bind_param("s", $room_number);
$stmt->execute();
$stmt->bind_result($checkin_id);
$stmt->fetch();
$stmt->close();

if (!$checkin_id) {
  echo json_encode(["success" => false, "message" => "No active check-in found."]);
  exit;
}

// ✅ Check if remarks exist
$stmt = $conn->prepare("SELECT id FROM remarks WHERE checkin_id = ? LIMIT 1");
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$stmt->bind_result($remarks_id);
$stmt->fetch();
$stmt->close();

// ✅ If notes are empty → delete remarks if they exist
if ($notes === "") {
  if ($remarks_id) {
    $stmt = $conn->prepare("DELETE FROM remarks WHERE id = ?");
    $stmt->bind_param("i", $remarks_id);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
      "success" => $success,
      "message" => $success ? "Remarks removed successfully." : "Failed to remove remarks."
    ]);
    $conn->close();
    exit;
  } else {
    // Nothing to delete
    echo json_encode(["success" => true, "message" => "No remarks to remove."]);
    $conn->close();
    exit;
  }
}

// ✅ If notes are not empty → update or insert
if ($remarks_id) {
  $stmt = $conn->prepare("UPDATE remarks SET notes = ?, created_at = NOW() WHERE id = ?");
  $stmt->bind_param("si", $notes, $remarks_id);
  $success = $stmt->execute();
  $stmt->close();
  $message = $success ? "Remarks updated successfully!" : "Failed to update remarks.";
} else {
  $stmt = $conn->prepare("INSERT INTO remarks (checkin_id, notes, created_at) VALUES (?, ?, NOW())");
  $stmt->bind_param("is", $checkin_id, $notes);
  $success = $stmt->execute();
  $stmt->close();
  $message = $success ? "Remarks saved successfully!" : "Failed to save remarks.";
}

echo json_encode(["success" => $success, "message" => $message]);
$conn->close();