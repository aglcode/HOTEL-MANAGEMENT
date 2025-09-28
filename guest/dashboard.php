<?php
include '../database.php'; // must define $conn (mysqli)

$room = $_GET['room'] ?? '';
$room = (int)$room; // ensure it's integer

// Fetch bookings for this room
$stmt = $conn->prepare("SELECT * FROM bookings WHERE room_number = ?");
$stmt->bind_param("i", $room);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch room data
$stmt = $conn->prepare("SELECT * FROM rooms WHERE room_number = ?");
$stmt->bind_param("i", $room);
$stmt->execute();
$roomResult = $stmt->get_result();
$roomData = $roomResult->fetch_assoc();
$stmt->close();

if (!$roomData) {
    die("❌ Invalid room");
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Room Dashboard</title>
</head>
<body>
  <h2>Welcome to Room <?php echo htmlspecialchars($roomData['room_number']); ?></h2>

  <?php if ($bookings): ?>
    <h3>Stay Information</h3>
    <?php foreach ($bookings as $b): ?>
        Guest: <?php echo htmlspecialchars($b['guest_name'] ?? 'Unknown'); ?><br>
        Check-in: <?php echo htmlspecialchars($b['check_in'] ?? 'N/A'); ?><br>
        Check-out: <?php echo htmlspecialchars($b['check_out'] ?? 'N/A'); ?><br>
        <hr>
    <?php endforeach; ?>
  <?php else: ?>
    <p>❌ No bookings found for this room.</p>
  <?php endif; ?>

  <h3>Options</h3>
  <a href="order-food.php?room=<?php echo urlencode($room); ?>">🍔 Order Food</a><br>
  <a href="view-stay.php?room=<?php echo urlencode($room); ?>">🕒 View Stay Info</a><br>
</body>
</html>
