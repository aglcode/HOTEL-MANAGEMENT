<?php
$room  = $_GET['room'] ?? '';
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
  <title>Room <?php echo htmlspecialchars($room); ?> Guest Portal</title>
</head>
<body>
  <h1>Welcome to Room <?php echo htmlspecialchars($room); ?></h1>
  <p>Your key is active (token: <?php echo htmlspecialchars($token); ?>).</p>

  <ul>
    <li><a href="order-food.php?room=<?php echo $room; ?>&token=<?php echo $token; ?>">🍔 Order Food</a></li>
    <li><a href="stay-info.php?room=<?php echo $room; ?>&token=<?php echo $token; ?>">🕒 View Stay Info</a></li>
    <li><a href="../api/unlock.php?room=<?php echo $room; ?>&token=<?php echo $token; ?>&scanner=1">🔓 Unlock Door (simulate scanner)</a></li>
  </ul>
</body>
</html>
