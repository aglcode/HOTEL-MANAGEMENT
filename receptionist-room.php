<?php
session_start();

require_once 'database.php'; // Include your database connection settings

// Auto-update booked rooms without active timer to available
$expiredRooms = $conn->query("
    SELECT r.room_number
    FROM rooms r
    LEFT JOIN (
        SELECT room_number, MAX(check_out_date) AS latest_checkout
        FROM checkins
        GROUP BY room_number
    ) c ON r.room_number = c.room_number
    WHERE r.status = 'booked' AND (c.latest_checkout IS NULL OR c.latest_checkout <= NOW())
");

while ($room = $expiredRooms->fetch_assoc()) {
    $room_number = (int)$room['room_number'];
    $conn->query("UPDATE rooms SET status = 'available' WHERE room_number = $room_number");
}

$bookedRooms = $conn->query("SELECT COUNT(*) AS booked FROM rooms WHERE status = 'booked'")->fetch_assoc()['booked'] ?? 0;
$maintenanceRooms = $conn->query("SELECT COUNT(*) AS maintenance FROM rooms WHERE status = 'maintenance'")->fetch_assoc()['maintenance'] ?? 0;


$totalRooms = $conn->query("SELECT COUNT(*) AS total FROM rooms")->fetch_assoc()['total'] ?? 0;
$availableRooms = $conn->query("SELECT COUNT(*) AS available FROM rooms WHERE status = 'available'")->fetch_assoc()['available'] ?? 0;

// Booking count
$booking_count_result = $conn->query("SELECT COUNT(*) AS total FROM bookings");
$booking_count_row = $booking_count_result->fetch_assoc();
$booking_count = $booking_count_row['total'] ?? 0;

// Booking data result for listing
$bookings_result = $conn->query("SELECT * FROM bookings ORDER BY start_date DESC");

// Room list with check-out
$allRoomsQuery = "
    SELECT r.room_number, r.room_type, r.status,
        (SELECT c.check_out_date
         FROM checkins c
         WHERE c.room_number = r.room_number AND c.check_out_date > NOW()
         ORDER BY c.check_out_date DESC
         LIMIT 1) AS check_out_date
    FROM rooms r
";
$resultRooms = $conn->query($allRoomsQuery);

// Actions: Extend or Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_number'])) {
    $room_number = (int)$_POST['room_number'];

    if (isset($_POST['extend'])) {
        $stmt = $conn->prepare("SELECT check_out_date FROM checkins WHERE room_number = ? AND check_out_date > NOW() ORDER BY check_out_date DESC LIMIT 1");
        $stmt->bind_param('i', $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $check_out_date = $result->fetch_assoc()['check_out_date'] ?? null;

        if ($check_out_date) {
            $new_check_out_date = date('Y-m-d H:i:s', strtotime($check_out_date . ' +1 hour'));
            $stmt_update = $conn->prepare("UPDATE checkins SET check_out_date = ? WHERE room_number = ?");
            $stmt_update->bind_param('si', $new_check_out_date, $room_number);
            $stmt_update->execute();
            $stmt_update->close();
        }
        $stmt->close();
    }

    if (isset($_POST['checkout'])) {
        // 1. Get total_price and amount_paid
        $stmt = $conn->prepare("SELECT total_price, amount_paid FROM checkins WHERE room_number = ? ORDER BY check_out_date DESC LIMIT 1");
        $stmt->bind_param('i', $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $checkin = $result->fetch_assoc();
        $stmt->close();
    
        if ($checkin) {
            $total_price = (float)$checkin['total_price'];
            $amount_paid = (float)$checkin['amount_paid'];
    
            
    
            // 2. Proceed with checkout if paid fully
            $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE room_number = ?");
            $stmt->bind_param('i', $room_number);
            $stmt->execute();
            $stmt->close();
    
            $stmt_update = $conn->prepare("UPDATE checkins SET check_out_date = NOW() WHERE room_number = ?");
            $stmt_update->bind_param('i', $room_number);
            $stmt_update->execute();
            $stmt_update->close();
        }
    }
    

    header("Location: receptionist-room.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Room Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
<style>
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 0.75rem;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.table td {
    padding: 0.75rem;
    vertical-align: middle;
    font-size: 0.875rem;
    color: #4a5568;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    transition: background-color 0.15s ease;
}

.card-footer,
.bg-gray-50 {
    background-color: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

/* Badges */
.badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border: 1px solid;
    transition: all 0.2s ease;
}
.bg-green-100 { background-color: #f0fff4; }
.text-green-800 { color: #2f855a; }
.border-green-200 { border-color: #c6f6d5; }
.bg-yellow-100 { background-color: #fef9c3; }
.text-yellow-800 { color: #854d0e; }
.border-yellow-200 { border-color: #fef08a; }
.bg-amber-100 { background-color: #fffaf0; }
.text-amber-800 { color: #975a16; }
.border-amber-200 { border-color: #fed7aa; }
.bg-gray-100 { background-color: #f7fafc; }
.text-gray-800 { color: #2d3748; }
.border-gray-200 { border-color: #edf2f7; }

/* Action buttons */
.user-actions .action-btn {
  color: #9b9da2ff;                
  transition: color .15s ease;   
  text-decoration: none;
  cursor: pointer;
}
.user-actions .action-btn.edit:hover {
  color: #2563eb; /* blue */
}
.user-actions .action-btn.delete:hover {
  color: #dc2626; /* red */
}

/* Sidebar & content */
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

/* Responsive */
@media (max-width: 768px) {
    .table-responsive {
        display: block;
        overflow-x: auto;
    }
}

/* Card base */
.room-card {
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
}
.room-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

/* Header */
.room-card .card-header {
  font-weight: 600;
  padding: 12px 15px;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f9fafb;
  border-top-left-radius: 0.75rem;
  border-top-right-radius: 0.75rem;
}

/* Status colors */
.status-badge {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
  border-radius: 9999px;
  border: 1px solid;
  font-weight: 500;
}
.status-available {
  background-color: #f0fff4;
  color: #2f855a;
  border-color: #c6f6d5;
}
.status-booked {
  background-color: #fef9c3;
  color: #854d0e;
  border-color: #fef08a;
}
.status-maintenance {
  background-color: #fffaf0;
  color: #975a16;
  border-color: #fed7aa;
}

/* Room info rows */
.room-info {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.75rem;
  font-size: 0.875rem;
}
.room-info span:first-child {
  color: #6b7280; /* muted */
}
.countdown-timer {
  font-weight: 600;
  color: #2563eb; /* blue */
}

/* Buttons inside cards */
.room-card .btn {
  font-size: 0.75rem;
  padding: 0.35rem 0.75rem;
  border-radius: 9999px;
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="user-info mb-4 text-center">
    <i class="fa-solid fa-user-circle mb-2" style="font-size: 60px;"></i>
    <h5 class="mb-1">Welcome,</h5>
    <p id="user-role" class="mb-0">Receptionist</p>
  </div>

  <a href="receptionist-dash.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-dash.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-gauge"></i> Dashboard
  </a>
  <a href="receptionist-room.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-room.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-bed"></i> Rooms
  </a>
  <a href="receptionist-guest.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-guest.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-users"></i> Guest
  </a>
  <a href="receptionist-booking.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-booking.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-calendar-check"></i> Booking
  </a>
  <a href="receptionist-payment.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-payment.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-money-check"></i> Payment
  </a>
  <a href="signin.php" class="text-danger">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </a>
</div>



<div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Room Management</h2>
            <p class="text-muted mb-0">Manage rooms and check-in/check-out guests</p>
        </div>
        <div class="clock-box text-end">
            <div id="currentDate" class="fw-semibold"></div>
            <div id="currentTime"></div>
        </div>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= $_SESSION['success_msg'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_msg']); endif; ?>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= $_SESSION['error_msg'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_msg']); endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- Total Rooms Card -->
        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-1"><?= $totalRooms ?></h3>
                        <p class="text-muted mb-0">Total Rooms</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Available Rooms Card -->
        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-1"><?= $availableRooms ?></h3>
                        <p class="text-muted mb-0">Available Rooms</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booked Rooms Card -->
        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="fas fa-key"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-1"><?= $bookedRooms ?></h3>
                        <p class="text-muted mb-0">Booked Rooms</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Maintenance Rooms Card -->
        <div class="col-md-3 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-1"><?= $maintenanceRooms ?></h3>
                        <p class="text-muted mb-0">Under Maintenance</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Room List -->
<div class="card mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <h5 class="mb-0">Room Status</h5>
    <i class="fas fa-bed"></i>
  </div>
  <div class="card-body">
    <div class="row">
      <?php while ($room = $resultRooms->fetch_assoc()): ?>
        <div class="col-md-4 mb-3">
          <div class="card room-card <?= $room['status'] ?>" onclick="cardClicked(event, <?= $room['room_number']; ?>)">
            <div class="card-header">
              <span>Room #<?= htmlspecialchars($room['room_number']); ?></span>
              <span class="status-badge status-<?= $room['status'] ?>"><?= ucfirst($room['status']) ?></span>
            </div>
            <div class="card-body">
              <div class="room-info">
                <span><i class="fas fa-tag me-2"></i>Type:</span>
                <span class="fw-semibold"><?= ucfirst($room['room_type']) ?></span>
              </div>

              <?php if ($room['status'] === 'booked' && !empty($room['check_out_date'])): ?>
                <div class="room-info">
                  <span><i class="fas fa-clock me-2"></i>Time Left:</span>
                  <span class="countdown-timer" data-room="<?= $room['room_number']; ?>" data-checkout="<?= $room['check_out_date']; ?>">Loading...</span>
                </div>
                <div class="d-flex justify-content-between mt-3">
                  <form method="POST" action="receptionist-room.php" class="d-inline extend-form">
                    <input type="hidden" name="room_number" value="<?= $room['room_number']; ?>">
                    <button type="submit" name="extend" class="btn btn-warning">
                      <i class="fas fa-clock me-1"></i> Extend
                    </button>
                  </form>
                  <form method="POST" action="receptionist-room.php" class="d-inline checkout-form">
                    <input type="hidden" name="room_number" value="<?= $room['room_number']; ?>">
                    <button type="submit" name="checkout" class="btn btn-danger">
                      <i class="fas fa-sign-out-alt me-1"></i> Check Out
                    </button>
                  </form>
                </div>
              <?php elseif ($room['status'] === 'available'): ?>
                <div class="d-flex justify-content-center mt-3">
                  <a href="check-in.php?room_number=<?= $room['room_number']; ?>" class="btn btn-success">
                    <i class="fas fa-sign-in-alt me-1"></i> Check In
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>


    <!-- Booking Summary Table -->
<div class="card mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Booking Summary</h5>
      <span class="badge bg-primary">
        <?= $summary_result->num_rows ?? 0; ?> bookings
      </span>
    </div>
    <!-- Custom search box -->
    <div class="input-group" style="width: 250px;">
      <input type="text" id="bookingSearch" class="form-control form-control-sm" placeholder="Search bookings...">
      <span class="input-group-text"><i class="fas fa-search"></i></span>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="bookingTable" class="table table-bordered table-hover">
        <thead class="table-light">
          <tr>
            <th>Guest Name</th>
            <th>Check-In</th>
            <th>Check-Out</th>
            <th>Room #</th>
            <th>Duration</th>
            <th>Guests</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $summary_result = $conn->query("SELECT guest_name, start_date, end_date, room_number, duration, num_people FROM bookings ORDER BY start_date DESC");
            if ($summary_result->num_rows > 0):
              while ($booking = $summary_result->fetch_assoc()):
          ?>
          <tr>
            <td><?= htmlspecialchars($booking['guest_name']) ?></td>
            <td><?= date("M d, Y h:i A", strtotime($booking['start_date'])) ?></td>
            <td><?= date("M d, Y h:i A", strtotime($booking['end_date'])) ?></td>
            <td>
              <span class="badge bg-gray-100 text-gray-800 border-gray-200">
                <?= $booking['room_number'] ?>
              </span>
            </td>
            <td>
              <span class="badge bg-yellow-100 text-yellow-800 border-yellow-200">
                <?= $booking['duration'] ?> hrs
              </span>
            </td>
            <td>
              <span class="badge bg-green-100 text-green-800 border-green-200">
                <?= $booking['num_people'] ?>
              </span>
            </td>
            <td class="text-center user-actions">
              <?php
                $room_check = $conn->prepare("SELECT status FROM rooms WHERE room_number = ?");
                $room_check->bind_param("i", $booking['room_number']);
                $room_check->execute();
                $room_result = $room_check->get_result();
                $room = $room_result->fetch_assoc();
                $room_check->close();

                if ($room && $room['status'] === 'available'):
                  $guest = urlencode($booking['guest_name']);
                  $checkin = urlencode($booking['start_date']);
                  $checkout = urlencode($booking['end_date']);
                  $num_people = (int)$booking['num_people'];
              ?>
              <span class="action-btn edit" 
                    onclick="window.location.href='check-in.php?room_number=<?= $booking['room_number']; ?>&guest_name=<?= $guest; ?>&checkin=<?= $checkin; ?>&checkout=<?= $checkout; ?>&num_people=<?= $num_people; ?>'">
                <i class="fas fa-sign-in-alt"></i>
              </span>
              <?php else: ?>
              <span class="badge bg-amber-100 text-amber-800 border-amber-200">Unavailable</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr>
            <td colspan="7" class="text-center py-4">
              <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
              <p class="mb-0">No bookings found</p>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleIcon = document.getElementById('sidebar-toggle');
    sidebar.classList.toggle('active');
    toggleIcon.classList.toggle('open');
    toggleIcon.setAttribute('aria-expanded', sidebar.classList.contains('active'));
}

document.querySelectorAll('.countdown-timer').forEach(function (timer) {
    const checkOutTime = new Date(timer.getAttribute('data-checkout')).getTime();
    const roomNumber = timer.getAttribute('data-room');

    const interval = setInterval(() => {
        const now = new Date().getTime();
        const distance = checkOutTime - now;

        if (distance <= 0) {
            clearInterval(interval);
            timer.textContent = "Expired";

            fetch('receptionist-room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `room_number=${roomNumber}&checkout=1`
            }).then(() => location.reload());
        } else {
            const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((distance % (1000 * 60)) / 1000);
            timer.textContent = `${h}h ${m}m ${s}s`;
        }
    }, 1000);
});

// Confirmation before Extend
document.querySelectorAll('.extend-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!confirm('Do you want to extend this stay by 1 hour?')) {
            e.preventDefault();  // Prevent form submission if "Cancel"
        }
    });
});

// Confirmation before Check Out
document.querySelectorAll('.checkout-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!confirm('Do you really want to check out this guest?')) {
            e.preventDefault();  // Prevent form submission if "Cancel"
        }
    });
});


function cardClicked(event, roomNumber) {
    // Prevent the click if the user clicked a button inside the card
    if (event.target.tagName.toLowerCase() === 'button' || event.target.closest('form')) {
        return;
    }

    // Otherwise, redirect to check-in.php
    window.location.href = `check-in.php?room_number=${roomNumber}`;
}

function updateClock() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    const timeStr = now.toLocaleTimeString('en-US');

    document.getElementById('currentDate').innerText = dateStr;
    document.getElementById('currentTime').innerText = timeStr;
}

setInterval(updateClock, 1000);
updateClock(); // run once immediately
</script>


</body>
</html>

<?php $conn->close(); ?>
