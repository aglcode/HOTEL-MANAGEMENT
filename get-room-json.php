<?php
session_start();

require_once 'database.php'; // Include your database connection settings

$query = "SELECT status FROM rooms WHERE room_number = 101";

$result = $conn->query($query);

$data = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($data);

