<?php
require_once '../database.php'; // Adjust if needed

$room  = $_GET['room'] ?? '';
$token = $_GET['token'] ?? '';
$isScanner = isset($_GET['scanner']); // Optional parameter for hardware

if (!$room || !$token) {
    echo "❌ Invalid QR code data.";
    exit;
}

// Lookup keycard in the database
$stmt = $conn->prepare("
    SELECT k.*, g.name AS guest_name
    FROM keycards k
    LEFT JOIN guests g ON k.guest_id = g.id
    WHERE k.room_number = ? AND k.qr_code = ? 
      AND k.status = 'active'
      AND NOW() BETWEEN k.valid_from AND k.valid_to
");
$stmt->bind_param('is', $room, $token);
$stmt->execute();
$result = $stmt->get_result();
$keycard = $result->fetch_assoc();

if ($keycard) {
    if ($isScanner) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "ok", "message" => "Room $room unlocked!"]);
        exit;
    } else {
        // ✅ Redirect guest to their personalized dashboard
        header("Location: http://localhost/HOTEL-MANAGEMENT/guest-dashboard.php?room={$room}&token={$token}");
        exit;
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
