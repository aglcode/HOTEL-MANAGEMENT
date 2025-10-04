<?php
// Simulated DB check (replace with real query)
$room  = $_GET['room'] ?? '';
$token = $_GET['token'] ?? '';

$validRoom  = 101;
$validToken = "ABC123";

// Detect if scanner (expects JSON) or phone (browser)
$isScanner = isset($_GET['scanner']); // ?scanner=1 will simulate hardware

if ($room == $validRoom && $token == $validToken) {
    if ($isScanner) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "ok", "message" => "Room $room unlocked!"]);
    } else {
        // Redirect guest to their room website
        header("Location: /rooms/room.php?room=$room&token=$token");
    }
} else {
    if ($isScanner) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "denied"]);
    } else {
        echo "❌ Invalid or expired keycard.";
    }
}
?>
