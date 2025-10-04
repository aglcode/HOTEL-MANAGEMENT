<?php
include "database.php";

$room_id = $_GET['room'] ?? null;
$token   = $_GET['token'] ?? null;

if (!$room_id || !$token || !isset($rooms[$room_id]) || $rooms[$room_id]["token"] !== $token) {
    die("❌ Invalid access!");
}
?>
<!DOCTYPE html>
<html>
<head><title>Order Food</title></head>
<body>
  <h2>🍽️ Food Menu - Room <?php echo $room_id; ?></h2>
  <form method="POST">
    <label><input type="checkbox" name="food[]" value="Burger"> Burger - ₱150</label><br>
    <label><input type="checkbox" name="food[]" value="Pizza"> Pizza - ₱300</label><br>
    <label><input type="checkbox" name="food[]" value="Pasta"> Pasta - ₱200</label><br>
    <button type="submit">Place Order</button>
  </form>

  <?php
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
      $order = implode(", ", $_POST['food'] ?? []);
      echo "<p>✅ Order placed: $order</p>";
      // TODO: Save to DB
  }
  ?>
</body>
</html>
