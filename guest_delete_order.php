<?php
header('Content-Type: application/json');
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "hotel_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Database connection failed."]);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || empty($data['id'])) {
  echo json_encode(["success" => false, "message" => "Invalid request data."]);
  exit;
}

$id = intval($data['id']);
$stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
  echo json_encode(["success" => true, "message" => "Order deleted successfully."]);
} else {
  echo json_encode(["success" => false, "message" => "Failed to delete order."]);
}

$stmt->close();
$conn->close();