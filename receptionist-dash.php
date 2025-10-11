<?php
session_start();
require_once 'database.php';

// Auto-cancel overdue bookings
function autoCancelOverdueBookings($conn) {
    try {
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $updateQuery = "
            UPDATE bookings 
            SET status = 'cancelled' 
            WHERE status = 'upcoming' 
            AND start_date <= '$cutoffTime'
            AND id NOT IN (
                SELECT DISTINCT booking_id 
                FROM checkins 
                WHERE booking_id IS NOT NULL
            )
        ";
        $conn->query($updateQuery);
    } catch (Exception $e) {}
}

autoCancelOverdueBookings($conn);

// Fetch stats
$current_checkins = $conn->query("SELECT COUNT(*) AS c FROM checkins WHERE NOW() BETWEEN check_in_date AND check_out_date")->fetch_assoc()['c'] ?? 0;
$total_bookings = $conn->query("SELECT COUNT(*) AS t FROM checkins")->fetch_assoc()['t'] ?? 0;
$available_rooms_count = $conn->query("SELECT COUNT(*) AS a FROM rooms WHERE status='available'")->fetch_assoc()['a'] ?? 0;
$announcements_result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcement_count = $announcements_result ? $announcements_result->num_rows : 0;
$available_rooms_result = $conn->query("SELECT room_number, room_type FROM rooms WHERE status='available' ORDER BY room_number");
$upcoming_bookings_result = $conn->query("
    SELECT b.*, r.room_type 
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_number = r.room_number 
    WHERE DATE(b.start_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
    AND b.status NOT IN ('completed', 'cancelled')
    ORDER BY b.start_date ASC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gitarra Apartelle - Receptionist Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="style.css" rel="stylesheet">

<style>
/* === Sidebar Container === */
.sidebar {
  width: 260px;
  height: 100vh;
  background: #fff;
  border-right: 1px solid #e5e7eb;
  position: fixed;
  top: 0;
  left: 0;
  display: flex;
  flex-direction: column;
  padding: 20px 0;
  font-family: 'Poppins', sans-serif;
}

/* === Header === */
.sidebar h4 {
  text-align: center;
  font-weight: 700;
  color: #111827;
  margin-bottom: 30px;
}

/* === User Info === */
.user-info {
  text-align: center;
  background: #f9fafb;
  border-radius: 10px;
  padding: 15px;
  margin: 0 20px 25px 20px;
}

.user-info i {
  font-size: 40px;
  color: #6b7280;
  margin-bottom: 5px;
}

.user-info p {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
}

.user-info h6 {
  margin: 0;
  font-weight: 600;
  color: #111827;
}

/* === Sidebar Navigation === */
.nav-links {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  padding: 0 10px;
}

.nav-links a {
  display: flex;
  align-items: center;
  gap: 14px;
  font-size: 16px;
  font-weight: 500;
  color: #374151;
  text-decoration: none;
  padding: 12px 18px;
  border-radius: 8px;
  margin: 4px 10px;
  transition: all 0.2s ease;
}

.nav-links a i {
  font-size: 19px;
  color: #374151;
  transition: color 0.2s ease;
}

/* Hover state — icon & text both turn black */
.nav-links a:hover {
  background: #f3f4f6;
  color: #111827;
}

.nav-links a:hover i {
  color: #111827;
}

/* Active state — white text & icon on dark background */
.nav-links a.active {
  background: #871D2B;
  color: #fff;
}

.nav-links a.active i {
  color: #fff;
}

/* === Sign Out === */
.signout {
  border-top: 1px solid #e5e7eb;
  padding: 15px 20px 0;
}

.signout a {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #dc2626;
  text-decoration: none;
  font-weight: 500;
  font-size: 15px;
  padding: 10px 15px;
  border-radius: 8px;
  transition: all 0.2s ease;
}

/* Hover effect — same feel as other links */
.signout a:hover {
  background: #f3f4f6;
  color: #dc2626;
}

.signout a:hover i {
  color: #dc2626;
}

/* === Main Content Offset === */
.content {
  margin-left: 270px;
  padding: 30px;
  max-width: 1400px;
}


/* === STAT CARD DESIGN MATCHING ADMIN === */
.stat-card {
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
    background: #fff;
}
.stat-card:hover {
    transform: translateY(-4px);
}
.stat-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 18px;
}
.stat-title {
    font-size: 14px;
    font-weight: 600;
    color: #555;
    margin: 0;
}
.stat-change {
    font-size: 13px;
    margin-top: 6px;
}
.stat-change span {
    font-size: 12px;
    color: #888;
}

/* List and cards */
.announcement-item:hover, .room-item:hover {
    background-color: rgba(0,0,0,0.02);
}
.booking-card {
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    background: #fff;
}
.guest-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: white;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

</head> 
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <h4>Gitarra Apartelle</h4>

  <div class="user-info">
    <i class="fa-solid fa-user-circle"></i>
    <p>Welcome</p>
    <h6>Receptionist</h6>
  </div>

  <div class="nav-links">
    <a href="receptionist-dash.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="receptionist-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
    <a href="receptionist-guest.php"><i class="fa-solid fa-users"></i> Guests</a>
    <a href="receptionist-booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a>
    <a href="receptionist-payment.php"><i class="fa-solid fa-money-check"></i> Payment</a>
  </div>

  <div class="signout">
    <a href="signin.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
  </div>
</div>


<!-- Content -->
<div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div style="margin-left: 20px;">
            <h2 class="fw-bold mb-0">Dashboard</h2>
            <p class="text-muted mb-0">Welcome to Gitarra Apartelle Management System</p>
        </div>
        <div class="clock-box text-end">
            <div id="currentDate" class="fw-semibold"></div>
            <div id="currentTime"></div>
        </div>
    </div>

    <!-- STATISTICS CARDS (same style as admin) -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Current Check-ins</p>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-user-check"></i></div>
                </div>
                <h3 class="fw-bold mb-1"><?= $current_checkins ?></h3>
                <p class="stat-change text-success">+2% <span>from last week</span></p>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Total Bookings</p>
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-calendar-check"></i></div>
                </div>
                <h3 class="fw-bold mb-1"><?= $total_bookings ?></h3>
                <p class="stat-change text-success">+5% <span>overall</span></p>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100 p-3" data-bs-toggle="collapse" data-bs-target="#availableRoomsList">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Available Rooms</p>
                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-bed"></i></div>
                </div>
                <h3 class="fw-bold mb-1"><?= $available_rooms_count ?></h3>
                <p class="stat-change text-muted">Click to view</p>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100 p-3" data-bs-toggle="collapse" data-bs-target="#announcementList">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Announcements</p>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-bullhorn"></i></div>
                </div>
                <h3 class="fw-bold mb-1"><?= $announcement_count ?></h3>
                <p class="stat-change text-muted">Click to view</p>
            </div>
        </div>
    </div>

    <!-- Available Rooms List -->
    <div class="collapse mb-4" id="availableRoomsList">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white"><h5 class="mb-0">Available Rooms</h5></div>
            <div class="card-body p-0">
                <?php if ($available_rooms_result->num_rows > 0): while($room = $available_rooms_result->fetch_assoc()): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <div><strong>Room <?= htmlspecialchars($room['room_number']) ?></strong> - <?= htmlspecialchars($room['room_type']) ?></div>
                    <span class="badge bg-success">Available</span>
                </div>
                <?php endwhile; else: ?>
                <div class="text-center p-4 text-muted">No available rooms right now.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="collapse mb-4" id="announcementList">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-white"><h5 class="mb-0">Recent Announcements</h5></div>
            <div class="card-body p-0">
                <?php if ($announcements_result && $announcements_result->num_rows > 0): while($row = $announcements_result->fetch_assoc()): ?>
                <div class="announcement-item p-3 border-bottom">
                    <h6 class="fw-semibold mb-1"><?= htmlspecialchars($row['title']) ?></h6>
                    <p class="mb-2"><?= nl2br(htmlspecialchars($row['message'])) ?></p>
                    <small class="text-muted"><i class="fas fa-user-edit me-1"></i><?= $row['created_by'] ?> • <i class="fas fa-clock ms-1 me-1"></i><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></small>
                </div>
                <?php endwhile; else: ?>
                <div class="p-4 text-center text-muted">No announcements available.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upcoming Bookings -->
    <div class="card shadow-sm">
        <div class="card-header text-white" style="background-color: #871D2B;"><h5 class="mb-0"><i class="fas fa-calendar-alt me-2" ></i>Upcoming Bookings (Next 7 Days)</h5></div>
        <div class="card-body">
            <?php if ($upcoming_bookings_result->num_rows > 0): ?>
            <div class="row g-3">
                <?php while($booking = $upcoming_bookings_result->fetch_assoc()):
                    $checkInDate = new DateTime($booking['start_date']);
                    $checkOutDate = (clone $checkInDate)->add(new DateInterval('PT'.$booking['duration'].'H'));
                    $initial = strtoupper($booking['guest_name'][0]);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card booking-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="guest-avatar me-3"><?= $initial ?></div>
                                <div>
                                    <h6 class="mb-0"><?= htmlspecialchars($booking['guest_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($booking['email']) ?></small>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between"><span>Room:</span><span><?= htmlspecialchars($booking['room_number']) ?></span></div>
                                <div class="d-flex justify-content-between"><span>Type:</span><span><?= htmlspecialchars($booking['room_type']) ?></span></div>
                                <div class="d-flex justify-content-between"><span>Guests:</span><span><?= $booking['num_people'] ?> people</span></div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between"><span>Check-in:</span><span><?= $checkInDate->format('M j, g:i A') ?></span></div>
                            <div class="d-flex justify-content-between"><span>Check-out:</span><span><?= $checkOutDate->format('M j, g:i A') ?></span></div>
                            <div class="d-flex justify-content-between mt-2"><span>Total:</span><span class="text-success fw-bold">₱<?= number_format($booking['total_price'],2) ?></span></div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-4 text-muted"><i class="fas fa-calendar-times fa-2x mb-2"></i><p>No upcoming bookings.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ✅ Pending Orders Section -->
        <div class="container mt-5">
        <h4 class="fw-bold mb-3 text-dark">Pending Orders</h4>
        <div id="order-list" class="mt-3">Loading pending orders...</div>
        </div>
        </div>

</div>

<script>
function updateClock(){
    const now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
setInterval(updateClock,1000);updateClock();

async function fetchOrders() {
  const container = document.getElementById('order-list');
  container.innerHTML = "Loading pending orders...";

  try {
    const res = await fetch('fetch_pending_orders.php');
    const data = await res.json();

    if (Object.keys(data).length === 0) {
      container.innerHTML = `<p class="text-center text-muted">No pending orders right now.</p>`;
      return;
    }

    let html = '';
    for (const [room, orders] of Object.entries(data)) {
      html += `
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Room ${room}</h5>
            <span>${orders.length} Pending Order(s)</span>
          </div>
          <div class="card-body p-0">
            <table class="table mb-0">
              <thead class="table-light">
                <tr>
                  <th>Item</th>
                  <th>Category</th>
                  <th>Size</th>
                  <th>Qty</th>
                  <th>Price</th>
                  <th>Payment</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
      `;
      orders.forEach(o => {
        html += `
          <tr>
            <td>${o.item_name}</td>
            <td>${o.category}</td>
            <td>${o.size || '-'}</td>
            <td>${o.quantity}</td>
            <td>${o.price}</td>
            <td>${o.mode_payment}</td>
            <td>
              <button class="btn btn-success btn-sm" onclick="markServed(${o.id})">
                Mark Served
              </button>
            </td>
          </tr>
        `;
      });
      html += `
              </tbody>
            </table>
          </div>
        </div>
      `;
    }

    container.innerHTML = html;
  } catch (err) {
    console.error(err);
    container.innerHTML = `<p class="text-danger">Error loading orders.</p>`;
  }
}

async function markServed(orderId) {
  if (!confirm("Mark this order as served?")) return;

  const formData = new FormData();
  formData.append("order_id", orderId);

  const res = await fetch('update_order_status.php', {
    method: 'POST',
    body: formData
  });

  const result = await res.json();
  if (result.success) {
    alert("Order marked as served!");
    fetchOrders(); // Refresh list
  } else {
    alert("Failed to update order status.");
  }
}

fetchOrders();
setInterval(fetchOrders, 8000); // auto-refresh every 8 seconds
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
