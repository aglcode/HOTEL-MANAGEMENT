<?php
$room  = $_GET['room'] ?? '';
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
  <title>Stay Info - Room <?php echo htmlspecialchars($room); ?></title>
</head>
<body>
  <h1>🕒 Stay Info - Room <?php echo htmlspecialchars($room); ?></h1>

  <?php
  // TODO: fetch this from database
  $guestName = "John Doe";
  $checkIn   = "2025-09-25";
  $checkOut  = "2025-09-28";

  echo "<p>Guest: <strong>$guestName</strong></p>";
  echo "<p>Room: <strong>$room</strong></p>";
  echo "<p>Check-in: $checkIn</p>";
  echo "<p>Check-out: $checkOut</p>";
  ?>

  <br>
  <a href="room.php?room=<?php echo $room; ?>&token=<?php echo $token; ?>">⬅ Back to Room Portal</a>
</body>
</html>
