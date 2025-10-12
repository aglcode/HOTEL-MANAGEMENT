<?php
require_once 'database.php';

if (!isset($_GET['room_number'])) {
  echo "Missing room number.";
  exit;
}

$room = $_GET['room_number'];
$query = "SELECT * FROM orders WHERE room_number = ? AND status = 'served'";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $room);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<p>No served orders found for this room.</p>";
  exit;
}

$total = 0;
date_default_timezone_set('Asia/Manila');
$currentDate = date('F j, Y');
$currentTime = date('g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt - Room <?= htmlspecialchars($room) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: "Poppins", Arial, sans-serif;
      padding: 40px 0;
    }
    .receipt-card {
      background: #fff;
      max-width: 500px;
      margin: auto;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      padding: 30px;
    }
    .receipt-header {
      text-align: center;
      margin-bottom: 20px;
    }
    .receipt-header i {
      font-size: 35px;
      color: #871D2B;
      background: #f1f1f1;
      padding: 15px;
      border-radius: 50%;
      margin-bottom: 10px;
    }
    .receipt-header h2 {
      font-weight: 700;
      margin: 0;
    }
    .receipt-header small {
      color: #6c757d;
      font-size: 0.9rem;
    }
    .room-title {
      text-align: center;
      font-weight: 600;
      margin-top: 20px;
      margin-bottom: 5px;
    }
    .timestamp {
      text-align: center;
      font-size: 0.9rem;
      color: #6c757d;
    }
    table {
      margin-top: 20px;
      width: 100%;
      font-size: 0.95rem;
    }
    th {
      text-transform: uppercase;
      font-size: 0.8rem;
      color: #6c757d;
      border-bottom: 1px solid #dee2e6;
    }
    td {
      padding: 8px 0;
      border: none;
    }
    .subtotal, .total-row {
      border-top: 1px solid #dee2e6;
      padding-top: 10px;
    }
    .total-row span {
      font-weight: 700;
      font-size: 1.1rem;
    }
    .total-amount {
      font-weight: 700;
      font-size: 1.4rem;
    }
    .footer-text {
      text-align: center;
      margin-top: 25px;
      font-size: 0.9rem;
    }
    .footer-text strong {
      display: block;
      margin-bottom: 5px;
    }
    @media print {
      body {
        background: white;
        padding: 0;
      }
      .receipt-card {
        box-shadow: none;
        border: none;
      }
      button { display: none; }
    }
  </style>
</head>
<body>
  <div class="receipt-card">
    <div class="receipt-header">
      <h2>Gitarra Apartelle</h2>
      <small>Premium Hospitality Services</small>
    </div>

    <hr>
    <h5 class="room-title">Room <?= htmlspecialchars($room) ?></h5>
    <div class="timestamp">
      <i class="fa-regular fa-calendar me-1"></i> <?= $currentDate ?> &nbsp;
      <i class="fa-regular fa-clock me-1"></i> <?= $currentTime ?>
    </div>
    <hr>

    <table class="table table-borderless">
      <thead>
        <tr>
          <th>Item</th>
          <th class="text-center">Qty</th>
          <th class="text-end">Price</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()):
          $itemTotal = $row['price'];
          $total += $itemTotal;
        ?>
        <tr>
          <td><?= htmlspecialchars($row['item_name']) ?></td>
          <td class="text-center"><?= $row['quantity'] ?></td>
          <td class="text-end">₱<?= number_format($itemTotal, 2) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <hr>
    <div class="d-flex justify-content-between subtotal">
      <span>Subtotal</span>
      <span>₱<?= number_format($total, 2) ?></span>
    </div>

    <div class="d-flex justify-content-between total-row mt-2">
      <span>TOTAL</span>
      <span class="total-amount">₱<?= number_format($total, 2) ?></span>
    </div>

    <hr>
    <div class="footer-text">
      <strong>Thank you for your patronage!</strong>
    </div>
  </div>
</body>
</html>
