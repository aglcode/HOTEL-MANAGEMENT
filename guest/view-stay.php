<?php
include '../database.php'; // must define $conn (mysqli)

$room = $_GET['room'] ?? '';
$room = (int)$room; // force integer

// Fetch bookings for this room
$stmt = $conn->prepare("SELECT * FROM bookings WHERE room_number = ?");
$stmt->bind_param("i", $room);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($bookings) {
    echo "<h2>Stay Info for Room " . htmlspecialchars($room) . "</h2>";
    foreach ($bookings as $b) {
        $guest    = $b['guest_name'] ?? 'Unknown';
        $checkIn  = $b['check_in'] ?? $b['checkin_date'] ?? $b['date_in'] ?? 'N/A';
        $checkOut = $b['check_out'] ?? $b['checkout_date'] ?? $b['date_out'] ?? 'N/A';

        echo "Guest: " . htmlspecialchars($guest) . "<br>";
        echo "Check-in: " . htmlspecialchars($checkIn) . "<br>";
        echo "Check-out: " . htmlspecialchars($checkOut) . "<br><hr>";
    }
} else {
    echo "❌ No bookings found for Room " . htmlspecialchars($room);
}
