<?php
include "database.php";

$room_id = $_GET['room'] ?? null;
$token   = $_GET['token'] ?? null;

if (!$room_id || !$token || !isset($rooms[$room_id]) || $rooms[$room_id]["token"] !== $token) {
    die("❌ Invalid access!");
}

$guest = $rooms[$room_id];
?>
<!DOCTYPE html>
<html>
<head><title>Stay Info</title></head>
<body>
  <h2>📅 Stay Information - Room <?php echo $room_id; ?></h2>
  <p>Guest: <?php echo $guest["guest_name"]; ?></p>
  <p>Check-in: <?php echo $guest["checkin"]; ?></p>
  <p>Checkout: <?php echo $guest["checkout"]; ?></p>
  <p>Balance: ₱<?php echo $guest["balance"]; ?></p>
</body>
</html>
