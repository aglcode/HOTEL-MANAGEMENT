<?php
$room  = $_GET['room'] ?? '';
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
  <title>Order Food - Room <?php echo htmlspecialchars($room); ?></title>
</head>
<body>
  <h1>🍔 Order Food - Room <?php echo htmlspecialchars($room); ?></h1>

  <form method="post">
    <label>Choose food:</label>
    <select name="food">
      <option value="Burger">Burger</option>
      <option value="Pizza">Pizza</option>
      <option value="Pasta">Pasta</option>
    </select>
    <br><br>
    <button type="submit">Place Order</button>
  </form>

  <?php
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $food = $_POST['food'] ?? '';
      echo "<p>✅ Order placed for <strong>" . htmlspecialchars($food) . "</strong> (Room " . htmlspecialchars($room) . ")</p>";

      // TODO: Save to DB orders table if you want
  }
  ?>
  <br>
  <a href="room.php?room=<?php echo $room; ?>&token=<?php echo $token; ?>">⬅ Back to Room Portal</a>
</body>
</html>
