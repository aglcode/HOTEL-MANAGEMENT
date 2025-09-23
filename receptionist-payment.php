<?php
session_start();
require_once 'database.php';

// Fetch all completed or paid bookings
$query = "
    SELECT b.id AS booking_id, b.guest_name, b.email, b.room_number, b.total_price, b.status, b.start_date, b.duration
    FROM bookings b
    ORDER BY b.id DESC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Payment Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
<style>
    body {
        font-family: 'Poppins', sans-serif;
    }

    .sidebar {
        width: 250px;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
    }

    .content {
        margin-left: 265px;
        max-width: 1400px;
        margin-right: auto;
        padding: 20px;
    }

    .card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease;
        background: #fff;
        padding: 20px;
    }

    .card:hover {
        transform: translateY(-2px);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    table th {
        background: #f8f9fa;
        text-align: left;
        padding: 14px 16px;
        font-weight: 600;
    }

    table td {
        padding: 14px 16px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }

    table tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
        transition: background-color 0.2s ease;
    }

    .badge {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 20px;
    }
</style>

</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="user-info mb-4 text-center">
      <i class="fa-solid fa-user-circle mb-2" style="font-size: 60px;"></i>
      <h5 class="mb-1">Welcome,</h5>
      <p class="mb-0">Receptionist</p>
    </div>
    <a href="receptionist-dash.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="receptionist-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
    <a href="receptionist-guest.php"><i class="fa-solid fa-users"></i> Guest</a>
    <a href="receptionist-booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a>
    <a href="receptionist-payment.php" class="active"><i class="fa-solid fa-money-check"></i> Payment</a>
    <a href="signin.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>

  <!-- Content -->
  <div class="content">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-money-check"></i> Payment History</h2>
    <div class="card p-3">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Guest Name</th>
            <th>Email</th>
            <th>Room</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Total Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php
                $checkIn = new DateTime($row['start_date']);
                $checkOut = clone $checkIn;
                $checkOut->add(new DateInterval('PT' . $row['duration'] . 'H'));
              ?>
              <tr>
                    <td><?= $row['booking_id'] ?></td>
                    <td><?= htmlspecialchars($row['guest_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['room_number'] ?? '') ?></td>
                    <td><?= $checkIn->format('M j, Y g:i A') ?></td>
                    <td><?= $checkOut->format('M j, Y g:i A') ?></td>
                    <td>₱<?= number_format($row['total_price'], 2) ?></td>
                    <td>
                        <?php if ($row['status'] === 'completed' || $row['status'] === 'paid'): ?>
                        <span class="badge bg-success">Paid</span>
                        <?php elseif ($row['status'] === 'upcoming'): ?>
                        <span class="badge bg-warning text-dark">Pending</span>
                        <?php elseif ($row['status'] === 'cancelled'): ?>
                        <span class="badge bg-danger">Cancelled</span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars($row['status'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                    </tr>

            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center">No payment records found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
