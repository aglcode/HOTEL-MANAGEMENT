<?php
session_start();
require_once 'database.php';
date_default_timezone_set('Asia/Manila'); // ✅ Consistent timezone

/**
 * ✅ Auto-cancel bookings that:
 * - are still marked as 'upcoming'
 * - started more than 30 minutes ago
 * - have no corresponding check-in
 */
function autoCancelOverdueBookings($conn) {
    try {
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));

        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'cancelled'
            WHERE status = 'upcoming'
              AND start_date <= ?
              AND id NOT IN (
                  SELECT DISTINCT booking_id 
                  FROM checkins 
                  WHERE booking_id IS NOT NULL
              )
        ");
        $stmt->bind_param('s', $cutoffTime);
        $stmt->execute();
        $stmt->close();

    } catch (Exception $e) {
        error_log('Auto-cancel failed: ' . $e->getMessage());
    }
}

autoCancelOverdueBookings($conn);

/** ✅ Dashboard Stats **/
// 🏨 Currently checked-in guests
$current_checkins = 0;

$res = $conn->query("
    SELECT COUNT(*) AS c 
    FROM checkins
    WHERE 
        status = 'checked_in'
        OR (status = 'scheduled' AND NOW() BETWEEN check_in_date AND check_out_date)
        OR (status = 'checked_in' AND NOW() > check_out_date)
");

if ($res && $row = $res->fetch_assoc()) {
    $current_checkins = (int) $row['c'];
}



// 📅 Total bookings (not checkins)
$total_bookings = 0;
$res = $conn->query("SELECT COUNT(*) AS t FROM bookings");
if ($res && $row = $res->fetch_assoc()) {
    $total_bookings = (int) $row['t'];
}

// 🛏️ Available rooms
$available_rooms_count = 0;
$res = $conn->query("SELECT COUNT(*) AS a FROM rooms WHERE status = 'available'");
if ($res && $row = $res->fetch_assoc()) {
    $available_rooms_count = (int) $row['a'];
}

// 📢 Announcements
$announcements_result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcement_count = $announcements_result ? $announcements_result->num_rows : 0;

// 🏠 List of available rooms
$available_rooms_result = $conn->query("
    SELECT room_number, room_type, price_3hrs, price_6hrs, price_12hrs, price_24hrs, price_ot
    FROM rooms
    WHERE status = 'available'
    ORDER BY room_number
");

// 📅 Upcoming bookings (next 7 days)
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

<!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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

#orderNotifCount {
  font-size: 0.75rem;
  padding: 4px 7px;
  line-height: 1;
  border-radius: 50%;
  color: white;
  background-color: red;
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

/* Notification Badge Styles */
.notification-badge {
  position: absolute;
  top: 2px;     /* move higher up */
  right: 10px;  /* slightly tighter alignment */
  background: #dc3545;
  color: white;
  border-radius: 50%;
  min-width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 5px;
  animation: pulse-badge 2s infinite;
  box-shadow: 0 2px 4px rgba(220, 53, 69, 0.4);
}

.order-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.order-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.room-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #871D2B 0%, #a82836 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.clickable-booking-card {
    transition: all 0.3s ease;
}

.clickable-booking-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(135, 29, 43, 0.2) !important;
    border: 2px solid #871D2B;
}

.clickable-booking-card:active {
    transform: translateY(-2px);
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
 
  <?php include __DIR__ . '/includes/get-notifications.php'; ?>

  <div class="nav-links">
    <a href="receptionist-dash.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="receptionist-room.php" class="position-relative">
  <i class="fa-solid fa-bed"></i> Rooms
  <?php if (!empty($totalNotifications) && $totalNotifications > 0): ?>
    <span class="notification-badge">
      <?= $totalNotifications ?>
    </span>
  <?php endif; ?>
</a>
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
        <div class="col-md-3 mb-3" style="cursor: pointer;">
            <div class="card stat-card h-100 p-3" data-bs-toggle="collapse" data-bs-target="#currentCheckinsList">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Current Check-ins</p>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1"><?= $current_checkins ?></h3>
                <p class="stat-change text-muted">Click to view</p>
            </div>
        </div>

      <div class="col-md-3 mb-3" style="cursor: pointer;">
          <div class="card stat-card h-100 p-3" data-bs-toggle="collapse" data-bs-target="#totalBookingsList">
              <div class="d-flex justify-content-between align-items-center">
                  <p class="stat-title">Total Bookings</p>
                  <div class="stat-icon bg-success bg-opacity-10 text-success">
                      <i class="fas fa-calendar-check"></i>
                  </div>
              </div>
              <h3 class="fw-bold mb-1"><?= $total_bookings ?></h3>
              <p class="stat-change text-muted">Click to view</p>
          </div>
      </div>

        <div class="col-md-3 mb-3" style="cursor: pointer;">
            <div class="card stat-card h-100 p-3" data-bs-toggle="collapse" data-bs-target="#availableRoomsList">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Available Rooms</p>
                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-bed"></i></div>
                </div>
                <h3 class="fw-bold mb-1"><?= $available_rooms_count ?></h3>
                <p class="stat-change text-muted">Click to view</p>
            </div>
        </div>

        <div class="col-md-3 mb-3" style="cursor: pointer;">
            <div class="card stat-card h-100 p-3" data-bs-toggle="collapse" data-bs-target="#announcementList">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Announcements</p>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-bullhorn"></i></div>
                </div>
                <h3 class="fw-bold mb-1"><?= $announcement_count ?></h3>
                <p class="stat-change text-muted">Click to view</p>
            </div>
        </div>

      <div class="col-md-3 mb-3" style="cursor: pointer; position: relative;">
        <div class="card stat-card h-100 p-3 position-relative" 
            data-bs-toggle="collapse" 
            data-bs-target="#ordersList"
            id="ordersCard">
          <div class="d-flex justify-content-between align-items-center">
            <p class="stat-title">Orders</p>
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
              <i class="fas fa-utensils"></i>
            </div>
          </div>
          <h3 class="fw-bold mb-1" id="pendingOrdersCount">0</h3>
          <p class="stat-change text-muted">Click to view</p>

          <!-- 🔴 Notification badge -->
          <span id="ordersBadge" 
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
            style="font-size: 0.7rem; padding: 5px 7px; min-width: 20px; text-align: center;">
            0
          </span>
        </div>
      </div>

    </div>

<!-- Current Check-ins List -->
<div class="collapse mb-4" id="currentCheckinsList">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Current Check-ins</h5>
        </div>

        <div class="card-body p-0">
        <?php
        $current_checkins_result = $conn->query("
            SELECT 
                id,
                guest_name,
                room_number,
                room_type,
                stay_duration,
                total_price,
                telephone,
                amount_paid,
                change_amount,
                payment_mode,
                check_in_date,
                check_out_date,
                status,
                gcash_reference,
                tapped_at
            FROM checkins
            WHERE 
                status = 'checked_in'
                OR (status = 'scheduled' AND NOW() BETWEEN check_in_date AND check_out_date)
                OR (status = 'checked_in' AND NOW() > check_out_date)
            ORDER BY check_in_date DESC
        ");
        ?>

        <?php if ($current_checkins_result && $current_checkins_result->num_rows > 0): ?>

            <!-- Header Row -->
            <div class="d-flex fw-bold px-3 py-2 border-bottom bg-light text-muted small text-uppercase">
                <div class="col-3">Guest Info</div>
                <div class="col-3">Room Details</div>
                <div class="col-4">Stay & Payment</div>
                <div class="col-2 text-end">Status</div>
            </div>

            <!-- Data Rows -->
            <?php while ($row = $current_checkins_result->fetch_assoc()): ?>

                <?php
                // TIME COMPUTATIONS (correctly inside the loop)
                $now = time();
                $checkout = strtotime($row['check_out_date']);

                if ($now > $checkout) {
                    $status_note = "<span class='text-danger fw-bold'>Overstayed</span>";
                } else {
                    $remaining = $checkout - $now;
                    $hours = floor($remaining / 3600);
                    $mins = floor(($remaining % 3600) / 60);
                    $status_note = "<span class='text-success'>Time left: {$hours}h {$mins}m</span>";
                }
                ?>

                <div class="d-flex align-items-center border-bottom px-3 py-3 checkin-row">

                    <!-- Column 1: Guest Info -->
                    <div class="col-3">
                        <strong><?= htmlspecialchars($row['guest_name']) ?></strong><br>
                        <small class="text-muted">📞 <?= htmlspecialchars($row['telephone']) ?></small><br>

                        <?php if (!empty($row['tapped_at'])): ?>
                            <small class="text-muted">
                                🔑 Tapped in: <?= date('M d, Y h:i A', strtotime($row['tapped_at'])) ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <!-- Column 2: Room Details -->
                    <div class="col-3">
                        <strong>Room <?= htmlspecialchars($row['room_number']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($row['room_type']) ?></small><br>
                        <small>Stay: <?= htmlspecialchars($row['stay_duration']) ?> hr(s)</small>
                    </div>

                    <!-- Column 3: Stay & Payment -->
                    <div class="col-4 small text-muted">
                        <div><strong>In:</strong> <?= date('M d, Y h:i A', strtotime($row['check_in_date'])) ?></div>
                        <div><strong>Out:</strong> <?= date('M d, Y h:i A', strtotime($row['check_out_date'])) ?></div>
                        <div><?= $status_note ?></div> <!-- ★ TIME LEFT / OVERSTAYED -->

                        <div>
                            <strong>₱<?= number_format($row['total_price'], 2) ?></strong> total |
                            <?= ucfirst($row['payment_mode']) ?>
                            <?php if (!empty($row['gcash_reference'])): ?>
                                <br><small>Ref: <?= htmlspecialchars($row['gcash_reference']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Column 4: Status -->
                    <div class="col-2 text-end">
                        <?php
                        $badgeClass = 'bg-secondary';
                        if ($row['status'] === 'scheduled') $badgeClass = 'bg-warning text-dark';
                        elseif ($row['status'] === 'checked_in') $badgeClass = 'bg-primary';
                        elseif ($row['status'] === 'checked_out') $badgeClass = 'bg-success';
                        ?>
                        <span class="badge <?= $badgeClass ?> px-3 py-2 text-capitalize">
                            <?= str_replace('_', ' ', $row['status']) ?>
                        </span>
                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>
            <div class="text-center p-4 text-muted">No guests currently checked in.</div>
        <?php endif; ?>
        </div>
    </div>
</div>




<!-- Total Bookings List -->
<div class="collapse mb-4" id="totalBookingsList">
  <div class="card shadow-sm">
    <div class="card-header text-white" style="background-color: #871D2B;">
      <h5 class="mb-0">Total Bookings</h5>
    </div>

    <div class="card-body p-0">
      <?php
      $bookings_result = $conn->query("
        SELECT 
          b.id,
          b.guest_name,
          b.room_number,
          b.duration,
          b.total_price,
          b.amount_paid,
          b.start_date,
          b.end_date,
          b.status,
          r.room_type
        FROM bookings b
        LEFT JOIN rooms r ON b.room_number = r.room_number
        ORDER BY b.start_date DESC
        LIMIT 20
      ");
      ?>

      <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
        <div class="bookings-header d-flex fw-bold px-3 py-2 border-bottom bg-light text-muted small">
          <div class="col-4">Guest / Room</div>
          <div class="col-6">Booking Details</div>
          <div class="col-2 text-end">Status</div>
        </div>

        <?php while($row = $bookings_result->fetch_assoc()): ?>
          <?php
          // --- Smart status detection (same as Booking List logic) ---
          $now = new DateTime();
          $start = new DateTime($row['start_date']);
          $end = new DateTime($row['end_date']);
          $status_text = ucfirst($row['status']);
          $badgeClass = "bg-secondary";

          // Get latest checkin status (if any)
          $latestCheckin = null;
          $lcStmt = $conn->prepare("
              SELECT status 
              FROM checkins 
              WHERE guest_name = ? AND room_number = ? 
              ORDER BY check_in_date DESC 
              LIMIT 1
          ");
          $lcStmt->bind_param("ss", $row['guest_name'], $row['room_number']);
          $lcStmt->execute();
          $lcRes = $lcStmt->get_result();
          if ($lcRes && $lcRow = $lcRes->fetch_assoc()) {
              $latestCheckin = $lcRow['status'];
          }
          $lcStmt->close();

          // Determine correct display status
          if ($latestCheckin === 'checked_in') {
              $status_text = "In Use";
              $badgeClass = "bg-warning text-dark";
          } elseif ($latestCheckin === 'checked_out') {
              $status_text = "Completed";
              $badgeClass = "bg-success";
              // Auto-update if still marked otherwise
              if ($row['status'] !== 'completed') {
                  $update = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                  $update->bind_param('i', $row['id']);
                  $update->execute();
                  $update->close();
              }
          } else {
              // No checkin record: fallback to time-based logic
              if ($now < $start) {
                  $status_text = "Upcoming";
                  $badgeClass = "bg-info";
              } elseif ($now >= $start && $now <= $end) {
                  $status_text = "Active";
                  $badgeClass = "bg-success";
              } elseif ($now > $end) {
                  $status_text = "Completed";
                  $badgeClass = "bg-success";
                  if ($row['status'] !== 'completed') {
                      $update = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                      $update->bind_param('i', $row['id']);
                      $update->execute();
                      $update->close();
                  }
              } elseif ($row['status'] === 'cancelled') {
                  $status_text = "Cancelled";
                  $badgeClass = "bg-danger";
              }
          }
          ?>

          <div class="d-flex align-items-center border-bottom px-3 py-3 booking-row">
            
            <!-- Column 1: Guest / Room -->
            <div class="col-4">
              <strong><?= htmlspecialchars($row['guest_name']) ?></strong><br>
              <span class="text-muted">
                Room <?= htmlspecialchars($row['room_number']) ?> — <?= htmlspecialchars($row['room_type']) ?>
              </span>
            </div>

            <!-- Column 2: Booking Details -->
            <div class="col-6 small text-muted">
              <div><strong>Duration Hour/s:</strong> <?= htmlspecialchars($row['duration']) ?></div>
              <div>
                <strong>Dates:</strong>
                <?= date('M d, Y h:i A', strtotime($row['start_date'])) ?> →
                <?= date('M d, Y h:i A', strtotime($row['end_date'])) ?>
              </div>
              <div>
                <strong>Total:</strong> ₱<?= number_format($row['total_price'], 2) ?> |
                <strong>Paid:</strong> ₱<?= number_format($row['amount_paid'], 2) ?>
              </div>
            </div>

            <!-- Column 3: Status -->
            <div class="col-2 text-end">
              <span class="badge <?= $badgeClass ?> px-3 py-2 text-capitalize">
                <?= htmlspecialchars($status_text) ?>
              </span>
            </div>

          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-center p-4 text-muted">No bookings found.</div>
      <?php endif; ?>
    </div>
  </div>
</div>



<!-- Available Rooms List -->
<div class="collapse mb-4" id="availableRoomsList">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
      <h5 class="mb-0">Available Rooms</h5>
    </div>

    <div class="card-body p-0">
      <?php if ($available_rooms_result->num_rows > 0): ?>
        <?php while ($room = $available_rooms_result->fetch_assoc()): ?>
          <div class="border-bottom p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap">

              <!-- Left: Room Info -->
              <div class="col-3 fw-semibold">
                Room <?= htmlspecialchars($room['room_number']) ?> - <?= htmlspecialchars($room['room_type']) ?>
              </div>

              <!-- Middle: Prices (aligned like a table) -->
              <div class="col-7 d-flex justify-content-between text-muted small" style="font-weight: 500;">
                <span>3hrs: ₱<?= number_format($room['price_3hrs'], 2) ?></span>
                <span>6hrs: ₱<?= number_format($room['price_6hrs'], 2) ?></span>
                <span>12hrs: ₱<?= number_format($room['price_12hrs'], 2) ?></span>
                <span>24hrs: ₱<?= number_format($room['price_24hrs'], 2) ?></span>
                <span>OT/hr: ₱<?= number_format($room['price_ot'], 2) ?></span>
              </div>

              <!-- Right: Badge -->
              <div class="col-2 text-end">
                <span class="badge bg-success px-3 py-2">Available</span>
              </div>

            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-center p-4 text-muted">No available rooms right now.</div>
      <?php endif; ?>
    </div>
  </div>
</div>



    <!-- Announcements List -->
    <div class="collapse mb-4" id="announcementList">
        <div class="card shadow-sm">
            <div class="card-header text-white" style="background-color: #871D2B;"><h5 class="mb-0">Recent Announcements</h5></div>
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
    <div class="card-header text-white" style="background-color: #871D2B;">
        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Bookings (Next 7 Days)</h5>
    </div>
    <div class="card-body">
        <?php if ($upcoming_bookings_result->num_rows > 0): ?>
        <div class="row g-3">
            <?php while($booking = $upcoming_bookings_result->fetch_assoc()):
                $checkInDate = new DateTime($booking['start_date']);
                $checkOutDate = (clone $checkInDate)->add(new DateInterval('PT'.$booking['duration'].'H'));
                $initial = strtoupper($booking['guest_name'][0]);
            ?>
            <!-- ✅ MAKE CARD CLICKABLE with cursor pointer and onclick event -->
            <div class="col-md-6 col-lg-4">
                <div class="card booking-card h-100 clickable-booking-card" 
                     onclick="redirectToBooking('<?= htmlspecialchars($booking['guest_name']) ?>')"
                     style="cursor: pointer; transition: all 0.3s ease;">
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
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between"><span>Check-in:</span><span><?= $checkInDate->format('M j, g:i A') ?></span></div>
                        <div class="d-flex justify-content-between"><span>Check-out:</span><span><?= $checkOutDate->format('M j, g:i A') ?></span></div>
                        <div class="d-flex justify-content-between mt-2"><span>Total:</span><span class="text-success fw-bold">₱<?= number_format($booking['total_price'],2) ?></span></div>
                        
                        <!-- ✅ ADD VISUAL INDICATOR -->
                        <div class="text-center mt-3">
                            <small class="text-muted"><i class="fas fa-mouse-pointer me-1"></i>Click to view details</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-4 text-muted">
            <i class="fas fa-calendar-times fa-2x mb-2"></i>
            <p>No upcoming bookings.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

    <!-- ✅ Pending Orders Section -->
<div id="ordersList" class="collapse mt-3">
  <div class="card shadow-sm">
    <div class="card-header bg-danger text-white">
      <h5 class="mb-0"><i class="fas fa-utensils me-2"></i> Pending Orders</h5>
    </div>
    <div class="card-body">
      <div id="order-list">Loading pending orders...</div>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="bg-dark text-white modal-header">
        <h5 class="modal-title">Room Receipt</h5>
      </div>
      <div class="modal-body" id="receiptContent">
        <p class="text-center text-muted">Loading receipt...</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-dark" id="printReceiptBtn"><i class="fas fa-print me-1"></i> Print</button>
      </div>
    </div>
  </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ====== Utilities & Inline CSS for Timer Animation ====== */
(function injectTimerCSS(){
  const css = `
    .timer-anim .spin { display:inline-block; margin-right:6px; transform-origin:center; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    /* small visual niceties */
    .timer-anim { font-weight: 600; }
  `;
  const s = document.createElement('style'); s.type = 'text/css'; s.appendChild(document.createTextNode(css));
  document.head.appendChild(s);
})();

function redirectToBooking(guestName) {
  const encodedName = encodeURIComponent(guestName);
  window.location.href = `receptionist-booking.php?guest_name=${encodedName}`;
}

function updateClock(){
  const now = new Date();
  const dateEl = document.getElementById('currentDate');
  const timeEl = document.getElementById('currentTime');
  if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
  if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
setInterval(updateClock,1000);
updateClock();

function formatTime(seconds) {
  const m = Math.floor(Math.max(0, seconds)/60);
  const s = Math.max(0, seconds) % 60;
  return `${m}:${s.toString().padStart(2,'0')}`;
}
function escapeHtml(unsafe){ if (unsafe==null) return ''; return String(unsafe).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;'); }
function escapeJs(unsafe){ if (unsafe==null) return ''; return String(unsafe).replace(/'/g,"\\'").replace(/"/g,'\\"'); }

/* ====== State ====== */
let previousData = null;
let orderInterval = null;
// timers keyed by order id: { remaining: seconds, interval: setInterval }
const itemTimers = {};

/* ====== Fetching & badges ====== */
async function fetchOrders(forceUpdate = false) {
  const container = document.getElementById('order-list');
  const notifBadge = document.getElementById('orderNotifCount');
  const orderCountElement = document.getElementById('pendingOrdersCount');
  const ordersBadge = document.getElementById('ordersBadge');

  try {
    const res = await fetch('fetch_pending_orders.php');
    const data = await res.json();

    const dataChanged = JSON.stringify(data) !== JSON.stringify(previousData);

    // count pending orders (only for badge)
    let pendingCount = 0;
    if (data && Object.keys(data).length) {
      for (const orders of Object.values(data)) {
        pendingCount += orders.filter(o => String(o.status).toLowerCase() === 'pending').length;
      }
    }

    if (notifBadge) {
      if (pendingCount > 0) { notifBadge.textContent = pendingCount; notifBadge.classList.remove('d-none'); }
      else notifBadge.classList.add('d-none');
    }
    if (orderCountElement) orderCountElement.textContent = pendingCount;
    if (ordersBadge) {
      if (pendingCount > 0) { ordersBadge.textContent = pendingCount; ordersBadge.classList.remove('d-none'); }
      else ordersBadge.classList.add('d-none');
    }

    if (forceUpdate || dataChanged) {
      previousData = data;
      renderOrders(data);
    }

    // After rendering we need to (re)start timers for server-side 'preparing' records
    // but only when prepare_start_at exists and the timer isn't already running in this session.
    if (data && Object.keys(data).length) {
      for (const [room, orders] of Object.entries(data)) {
        for (const o of orders) {
          const serverStatus = String(o.status).toLowerCase();
          if (serverStatus === 'preparing' && o.prepare_start_at) {
            const supplyQty = parseInt(o.supply_quantity ?? 0, 10);
            const prepMins = (supplyQty === 999) ? 20 : 5;
            const totalSeconds = prepMins * 60;

            const startedAt = new Date(o.prepare_start_at).getTime();
            if (!isFinite(startedAt)) {
              // malformed timestamp — do not auto-mark; skip starting timer
              continue;
            }
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            const remaining = totalSeconds - elapsed;

            if (remaining <= 0) {
              // timer finished on server; only auto-mark if it was actually running on server
              // Avoid auto-marking if no prepare_start_at existed (we checked it exists).
              await markItemPrepared(o.id, room, true);
            } else {
              // Start timer only if not already running in JS (prevents duplication)
              if (!itemTimers[o.id]) {
                startItemTimer(o.id, remaining, room, true);
              } else {
                // ensure UI matches JS timer
                const el = document.getElementById(`timer-${o.id}`);
                if (el) el.innerHTML = `<span class="spin">⏳</span>${formatTime(itemTimers[o.id].remaining)}`;
                el && el.classList.add('timer-anim');
              }
            }
          }
        }
      }
    }

  } catch (err) {
    console.error(err);
    if (container) container.innerHTML = `
      <div class="text-center py-4 text-danger">
        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
        <p>Error loading orders.</p>
      </div>
    `;
  }
}

async function fetchRemarks(roomNumber) {
  try {
    const res = await fetch("guest_fetch_remarks.php?room_number=" + encodeURIComponent(roomNumber));
    const data = await res.json();
    return data?.notes ?? "";
  } catch (e) {
    console.error("Remarks fetch error:", e);
    return "";
  }
}

function showRemarksModal(notes) {
  Swal.fire({
    title: "<h3 style='margin-bottom:10px;'>Guest Remarks</h3>",
    html: `
      <div style="
        max-height: 250px;
        overflow-y: auto;
        text-align: left;
        padding: 15px;
        border-radius: 8px;
        background: #f8f9fa;
        border: 1px solid #dcdcdc;
        font-size: 14px;
        line-height: 1.5;
      ">
        ${notes && notes.trim() !== "" ? notes : "<i>No remarks found.</i>"}
      </div>
    `,
    icon: "info",
    confirmButtonText: "Close",
    width: 500,
    padding: "1.5em",
    backdrop: `rgba(0,0,0,0.4)`,

    // 🔴 Make the Close button red
    customClass: {
      confirmButton: "btn btn-danger"
    },
    buttonsStyling: false // Needed to apply Bootstrap classes
  });
}

/* ====== Render orders (keeps served visible; prepared hides edit/delete) ====== */
function renderOrders(data) {
  const container = document.getElementById('order-list');
  if (!container) return;

  if (!data || Object.keys(data).length === 0) {
    container.innerHTML = `
      <div class="text-center py-4 text-muted">
        <i class="fas fa-clipboard-check fa-2x mb-2"></i>
        <p>No pending orders right now.</p>
      </div>`;
    return;
  }

  let html = '<div class="row g-3">';
  for (const [room, orders] of Object.entries(data)) {
    const allServed = orders.every(o => String(o.status).toLowerCase() === 'served');
    const pendingCount = orders.filter(o => String(o.status).toLowerCase() === 'pending').length;

    html += `
      <div class="col-md-6 col-lg-4">
        <div class="card order-card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center mb-3">
              <div class="room-avatar me-3">${escapeHtml(room)}</div>
              <div>
                <h6 class="mb-0">Room ${escapeHtml(room)}</h6>
                <small class="text-muted">${allServed ? 'All Orders Served' : `Pending Orders: ${pendingCount}`}</small>
              </div>
            </div>
            <div class="accordion" id="accordion-${escapeHtml(room)}">
    `;

    orders.forEach((o, index) => {
      // compute prep seconds using supply_quantity from fetch_pending_orders.php
      const supplyQty = parseInt(o.supply_quantity ?? 0, 10);
      const prepMins = (supplyQty === 999) ? 20 : 5;
      const prepSeconds = prepMins * 60;

      // determine display remaining:
      let timerDisplay = prepSeconds;
      const serverStatus = String(o.status).toLowerCase();

      if (serverStatus === 'preparing' && o.prepare_start_at) {
        const startedAt = new Date(o.prepare_start_at).getTime();
        if (isFinite(startedAt)) {
          const elapsed = Math.floor((Date.now() - startedAt) / 1000);
          const remainingFromDb = prepSeconds - elapsed;
          timerDisplay = remainingFromDb > 0 ? remainingFromDb : 0;
        } else {
          timerDisplay = prepSeconds; // malformed timestamp => treat as not started
        }
      }

      // if JS timer exists, prefer it (session-running timer)
      if (itemTimers[o.id]) timerDisplay = itemTimers[o.id].remaining;

      // state flags
      const isRunning = !!itemTimers[o.id] || (serverStatus === 'preparing' && timerDisplay > 0);
      const isPrepared = serverStatus === 'prepared';
      const isServed = serverStatus === 'served';

      // hide edit/delete only when prepared (per request)
      const hideEditDelete = isPrepared || isServed;

      const badgeClass = isPrepared ? 'bg-primary' :
                         (serverStatus === 'preparing' ? 'bg-info' :
                         (serverStatus === 'pending' ? 'bg-warning text-dark' : (isServed ? 'bg-success' : 'bg-secondary')));

      // timer HTML: if running show animated spin + time; if prepared/served show text
      let timerHtml = '';
      if (serverStatus === 'pending' || isPrepared || isRunning || serverStatus === 'preparing' || isServed) {
        if (isRunning && !isPrepared) {
          // animated clock
          timerHtml = `<span id="timer-${o.id}" class="badge timer-anim bg-info"><span class="spin">⏳</span>${formatTime(timerDisplay)}</span>`;
        } else if (isPrepared) {
          timerHtml = `<span id="timer-${o.id}" class="badge bg-primary">Prepared</span>`;
        } else if (isServed) {
          timerHtml = `<span id="timer-${o.id}" class="badge bg-success">Served</span>`;
        } else {
          // pending but no running timer
          timerHtml = `<span id="timer-${o.id}" class="badge bg-info">${formatTime(timerDisplay)}</span>`;
        }
      }

      // controls: Prepare / Mark as Prepared / Edit / Delete
      // Prepare shown when item is pending and not running
      // Mark as Prepared shown when running
      // Prepared display when prepared
      let prepareControlHtml = '';
    
      if (isServed) {
          // Hide ALL prepare / prepared / running buttons when served
          prepareControlHtml = '';
      }
      else if (isPrepared) {
          prepareControlHtml = `
              <button class="btn btn-sm btn-outline-primary" disabled id="prepared-display-${o.id}">
                  <i class="fas fa-check me-1"></i> Prepared
              </button>`;
      }
      else if (isRunning) {
          prepareControlHtml = `
              <button class="btn btn-sm btn-outline-primary" 
                  id="prepared-btn-${o.id}" 
                  onclick="markItemPrepared(${o.id}, '${escapeJs(room)}')">
                  <i class="fas fa-check me-1"></i> Mark as Prepared
              </button>`;
      }
      else {
          // Not running, not prepared, not served → show Prepare button
          prepareControlHtml = `
              <button class="btn btn-sm btn-outline-info" 
                  id="prepare-btn-${o.id}" 
                  onclick="startItemTimer(${o.id}, ${prepSeconds}, '${escapeJs(room)}')">
                  <i class="fas fa-hourglass-start me-1"></i> Prepare
              </button>`;
      }

      html += `
        <div class="accordion-item">
          <h2 class="accordion-header" id="heading-${escapeHtml(room)}-${index}">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${escapeHtml(room)}-${index}" aria-expanded="false">
              ${escapeHtml(o.item_name)} (${escapeHtml(o.quantity)}) - <span class="ms-1 badge ${badgeClass}">${escapeHtml(o.status)}</span>
            </button>
          </h2>
          <div id="collapse-${escapeHtml(room)}-${index}" class="accordion-collapse collapse" data-bs-parent="#accordion-${escapeHtml(room)}">
            <div class="accordion-body ${isPrepared ? 'text-muted' : ''}">
              <div class="d-flex justify-content-between"><span>Category:</span><span>${escapeHtml(o.category)}</span></div>
              ${o.size ? `<div class="d-flex justify-content-between"><span>Size:</span><span>${escapeHtml(o.size)}</span></div>` : ''}
              <div class="d-flex justify-content-between"><span>Payment:</span><span class="badge bg-info">${escapeHtml(o.mode_payment)}</span></div>
              <div class="d-flex justify-content-between mt-2"><span>Price:</span><span class="text-success fw-bold">₱${parseFloat(o.price).toFixed(2)}</span></div>

              ${ timerHtml ? `<div class="mt-2">${timerHtml}</div>` : '' }

              <div class="d-flex justify-content-end gap-2 mt-3" id="controls-${o.id}">
                ${prepareControlHtml}
                <button class="btn btn-sm btn-outline-primary ${hideEditDelete || isRunning ? 'd-none' : ''}" id="edit-btn-${o.id}" onclick="editOrder(${o.id}, '${escapeJs(o.item_name)}', ${o.quantity})">
                  <i class="fas fa-edit me-1"></i>Edit
                </button>

                <button class="btn btn-sm btn-outline-danger ${hideEditDelete || isRunning ? 'd-none' : ''}" id="delete-btn-${o.id}" onclick="deleteOrder(${o.id}, '${escapeJs(room)}')">
                  <i class="fas fa-trash me-1"></i>Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
    });
    
    // After rendering: fetch remarks for each room and hide button if empty
    Object.keys(data).forEach(async room => {
      const notes = await fetchRemarks(room);
      const btn = document.getElementById(`remarks-btn-${room}`);
      if (btn) {
        if (!notes || notes.trim() === "") btn.classList.add("d-none");
        btn.onclick = () => showRemarksModal(notes);
      }
    });

    html += `
            </div> <!-- accordion -->
            <hr>
            <div class="d-flex flex-column gap-2 mt-2">
            
              <!-- REMARKS BUTTON (auto-hidden if none) -->
              <button class="btn btn-outline-dark w-100" 
                id="remarks-btn-${escapeHtml(room)}"
                  onclick="viewRemarks('${escapeJs(room)}')">
                <i class="fas fa-comment-dots me-1"></i> View Remarks
              </button>
            
              ${!allServed ? `
                <button class="btn btn-success w-100" id="serve-btn-${escapeHtml(room)}" onclick="markAllServed('${escapeJs(room)}')">
                  <i class="fas fa-check me-1"></i> Mark All Served
                </button>
                <button class="btn btn-outline-secondary w-100 d-none" id="print-btn-${escapeHtml(room)}" onclick="printReceipt('${escapeJs(room)}')">
                  <i class="fas fa-print me-1"></i> Print Receipt
                </button>
              ` : `
                <button class="btn btn-secondary w-100" disabled>
                  <i class="fas fa-check me-1"></i> All Served
                </button>
                <button class="btn btn-outline-secondary w-100" id="print-btn-${escapeHtml(room)}" onclick="printReceipt('${escapeJs(room)}')">
                  <i class="fas fa-print me-1"></i> Print Receipt
                </button>
              `}
            </div>
          </div>
        </div>
      </div>
    `;
  } // end rooms loop

  html += '</div>';
  container.innerHTML = html;

  // Ensure running timers' UI is consistent with itemTimers
  for (const [orderId, t] of Object.entries(itemTimers)) {
    const el = document.getElementById(`timer-${orderId}`);
    if (el) {
      el.innerHTML = `<span class="spin">⏳</span>${formatTime(t.remaining)}`;
      el.classList.add('timer-anim');
    }
    // hide edit/delete when JS timer running (defensive)
    const edit = document.getElementById(`edit-btn-${orderId}`);
    const del = document.getElementById(`delete-btn-${orderId}`);
    const prepBtn = document.getElementById(`prepare-btn-${orderId}`);
    const markBtn = document.getElementById(`prepared-btn-${orderId}`);
    if (edit) edit.classList.add('d-none');
    if (del) del.classList.add('d-none');
    if (prepBtn) prepBtn.classList.add('d-none');
    if (markBtn) markBtn.classList.remove('d-none');
  }
}

/* ====== Timer control functions (start/restore) ====== */
async function startItemTimer(orderId, duration, room = '', restoreMode = false) {
  // prevent duplicate timers
  if (itemTimers[orderId]) return;

  const timerElId = `timer-${orderId}`;
  const timerEl = document.getElementById(timerElId);
  const prepareBtn = document.getElementById(`prepare-btn-${orderId}`);
  const editBtn = document.getElementById(`edit-btn-${orderId}`);
  const deleteBtn = document.getElementById(`delete-btn-${orderId}`);

  // If this is a manual start (not restore), tell backend to set preparing with timestamp
  if (!restoreMode) {
    fetch('guest_update_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, status: 'preparing', start_prep: true })
    }).catch(err => console.warn('preparing status update failed', err));
  }

  // Ensure timer element shows animated clock
  if (timerEl) {
    timerEl.classList.remove('bg-success');
    timerEl.classList.add('bg-danger', 'timer-anim');
    timerEl.innerHTML = `<span class="spin">⏳</span>${formatTime(duration)}`;
  } else {
    // if timer element not present (rare) create one in controls area
    const controls = document.getElementById(`controls-${orderId}`);
    if (controls) {
      const span = document.createElement('span');
      span.id = timerElId;
      span.className = 'badge bg-danger timer-anim me-2';
      span.innerHTML = `<span class="spin">⏳</span>${formatTime(duration)}`;
      controls.parentNode.insertBefore(span, controls);
    }
  }

  // hide edit/delete and prepare button while running
  if (editBtn) editBtn.classList.add('d-none');
  if (deleteBtn) deleteBtn.classList.add('d-none');
  if (prepareBtn) prepareBtn.classList.add('d-none');

  // create Mark as Prepared button if missing
  let markBtn = document.getElementById(`prepared-btn-${orderId}`);
  if (!markBtn) {
    const controls = document.getElementById(`controls-${orderId}`);
    if (controls) {
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm btn-outline-success';
      btn.id = `prepared-btn-${orderId}`;
      btn.innerHTML = `<i class="fas fa-check me-1"></i> Mark as Prepared`;
      btn.onclick = () => markItemPrepared(orderId, room, false);
      controls.appendChild(btn);
      markBtn = btn;
    }
  } else {
    markBtn.classList.remove('d-none');
  }

  // start interval
  itemTimers[orderId] = {
    remaining: duration,
    interval: setInterval(() => {
      const t = itemTimers[orderId];
      if (!t) return;
      t.remaining -= 1;

      const el = document.getElementById(timerElId);
      if (el) el.innerHTML = `<span class="spin">⏳</span>${formatTime(t.remaining)}`;

      if (t.remaining <= 0) {
        clearInterval(t.interval);
        delete itemTimers[orderId];
        // mark prepared automatically (server-side will clear prepare_start_at)
        markItemPrepared(orderId, room, true);
      }
    }, 1000)
  };
}

/* ====== Mark item prepared (manual or auto) ====== */
async function markItemPrepared(orderId, room = '', auto = false) {
  // stop timer if running
  const t = itemTimers[orderId];
  if (t) {
    clearInterval(t.interval);
    delete itemTimers[orderId];
  }

  // UI: set timer to Prepared
  const timerEl = document.getElementById(`timer-${orderId}`);
  if (timerEl) {
    timerEl.classList.remove('bg-danger', 'timer-anim');
    timerEl.classList.add('bg-success');
    timerEl.textContent = 'Prepared';
  }

  // hide prepare/edit/delete and show prepared badge
  const prepareBtn = document.getElementById(`prepare-btn-${orderId}`);
  const markBtn = document.getElementById(`prepared-btn-${orderId}`);
  const editBtn = document.getElementById(`edit-btn-${orderId}`);
  const deleteBtn = document.getElementById(`delete-btn-${orderId}`);
  if (prepareBtn) prepareBtn.classList.add('d-none');
  if (markBtn) markBtn.classList.add('d-none');
  if (editBtn) editBtn.classList.add('d-none');
  if (deleteBtn) deleteBtn.classList.add('d-none');

  const controls = document.getElementById(`controls-${orderId}`);
  if (controls && !document.getElementById(`prepared-ind-${orderId}`)) {
    const span = document.createElement('span');
    span.id = `prepared-ind-${orderId}`;
    span.className = 'badge bg-success ms-2';
    span.textContent = 'Prepared';
    controls.appendChild(span);
  }

  // notify backend (use your existing endpoint which clears prepare_start_at on 'prepared')
  try {
    const res = await fetch('guest_update_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, status: 'prepared', clear_prep: true })
    });
    const json = await res.json();
    if (json && json.success) {
      if (!auto) {
        await Swal.fire({ icon: 'success', title: 'Prepared', text: 'Item has been marked as Prepared.', timer:1200, showConfirmButton:false });
      }
      // short delay then refresh to sync
      setTimeout(() => fetchOrders(true), 600);
    } else {
      throw new Error(json?.message || 'Failed to update status');
    }
  } catch (err) {
    console.error('Failed to mark item prepared:', err);
    await Swal.fire({ icon:'error', title:'Error', text:'Could not mark item Prepared. Refreshing list.', confirmButtonColor:'#dc3545' });
    fetchOrders(true);
  }
}

/* ====== Existing features adapted (markAllServed, editOrder, deleteOrder, printReceipt) ====== */

async function markAllServed(roomNumber) {
  const result = await Swal.fire({
    title: 'Mark All as Served?',
    text: `Are you sure you want to mark all orders in Room ${roomNumber} as served?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#198754',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, mark as served',
    cancelButtonText: 'Cancel'
  });
  if (!result.isConfirmed) return;

  const serveBtn = document.getElementById(`serve-btn-${roomNumber}`);
  const printBtn = document.getElementById(`print-btn-${roomNumber}`);
  if (serveBtn) { serveBtn.disabled = true; serveBtn.textContent = 'Updating...'; }

  try {
    const res = await fetch('update_order_status.php', { method:'POST', body: new URLSearchParams({ room_number: roomNumber }) });
    const json = await res.json();
    if (json && json.success) {
      if (serveBtn) { serveBtn.classList.remove('btn-success'); serveBtn.classList.add('btn-secondary'); serveBtn.textContent = 'All Served'; }
      if (printBtn) printBtn.classList.remove('d-none');

      // cleanup call (existing flow)
      await fetch('update_room_status.php', { method:'POST', body: new URLSearchParams({ room_number: roomNumber, status: 'served' }) });

      await Swal.fire({ icon:'success', title:'Success!', text:`All orders for Room ${roomNumber} have been marked as served.`, timer:2000, showConfirmButton:false });

      clearInterval(orderInterval);
      setTimeout(()=>{ fetchOrders(true); orderInterval = setInterval(()=>fetchOrders(false), 8000); }, 2000);
    } else {
      throw new Error(json?.message || 'Failed');
    }
  } catch (err) {
    console.error(err);
    await Swal.fire({ icon:'error', title:'Error', text:'Failed to mark all served. Try again.', confirmButtonColor:'#dc3545' });
    if (serveBtn) { serveBtn.disabled = false; serveBtn.textContent = 'Mark All Served'; }
  }
}

async function editOrder(orderId, itemName, quantity) {
  const { value: newQty } = await Swal.fire({
    title: `Edit Quantity for ${itemName}`,
    input: 'number',
    inputLabel: 'Enter new quantity:',
    inputValue: quantity,
    inputAttributes: { min: 1 },
    showCancelButton: true,
    confirmButtonText: 'Update',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#198754',
    cancelButtonColor: '#6c757d',
    inputValidator: (value) => {
      if (!value || value <= 0) return 'Quantity must be greater than 0';
    }
  });

  if (!newQty) return;

  try {
    const res = await fetch('guest_update_order.php', {
      method:'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ order_id: orderId, quantity: newQty })
    });
    const json = await res.json();
    if (json && json.success) {
      await Swal.fire({ icon:'success', title:'Updated!', text:`Order updated successfully.`, timer:1500, showConfirmButton:false });
      fetchOrders(true);
    } else {
      throw new Error(json?.message || 'Failed to update');
    }
  } catch (err) {
    console.error(err);
    Swal.fire('Error','Failed to update order. Please try again.','error');
  }
}

async function deleteOrder(orderId, roomNumber) {
  const confirmDelete = await Swal.fire({
    title: 'Delete this order?',
    text: `This will permanently remove the order from Room ${roomNumber}.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d'
  });
  if (!confirmDelete.isConfirmed) return;

  try {
    const res = await fetch('guest_delete_order.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify({ id: orderId })
    });
    const json = await res.json();
    if (json && json.success) {
      await Swal.fire({ icon:'success', title:'Deleted!', text:'The order has been permanently removed.', timer:1500, showConfirmButton:false });
      fetchOrders(true);
    } else {
      throw new Error(json?.message || 'Delete failed');
    }
  } catch (err) {
    console.error(err);
    Swal.fire('Error','Failed to delete the order. Please try again.','error');
  }
}

async function printReceipt(roomNumber) {
  const modal = new bootstrap.Modal(document.getElementById("receiptModal"));
  const receiptContent = document.getElementById("receiptContent");
  const printBtn = document.getElementById("printReceiptBtn");
  if (!receiptContent) return;
  receiptContent.innerHTML = `<p class="text-center text-muted">Loading receipt...</p>`;
  modal.show();
  try {
    const res = await fetch(`print_receipt.php?room_number=${roomNumber}`);
    const html = await res.text();
    receiptContent.innerHTML = html;
    if (printBtn) printBtn.onclick = () => {
      const printWindow = window.open("","_blank");
      printWindow.document.write(html);
      printWindow.document.close();
      printWindow.print();
    };
  } catch (err) {
    console.error(err);
    receiptContent.innerHTML = `<div class="text-center text-danger">Failed to load receipt.</div>`;
  }
}

/* ====== Init ====== */
fetchOrders(true);
orderInterval = setInterval(()=>fetchOrders(false), 8000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
