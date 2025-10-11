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

$roomOrders = [];
$orderResult = $conn->query("
    SELECT room_number, item_name, status, created_at FROM orders ORDER BY created_at DESC
");
while ($order = $orderResult->fetch_assoc()) {
    $roomOrders[$order['room_number']][] = $order;
}

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

            // ✅ ADD THIS: Extend keycard validity
            $stmt_k = $conn->prepare("UPDATE keycards SET valid_to = ?, status = 'active' WHERE room_number = ? ORDER BY id DESC LIMIT 1");
            $stmt_k->bind_param("si", $new_check_out_date, $room_number);
            $stmt_k->execute();
            $stmt_k->close();
        }
        $stmt->close();
    }

    if (isset($_POST['checkout'])) {
        // 1. Get total_price, amount_paid and guest_name
        $stmt = $conn->prepare("SELECT total_price, amount_paid, guest_name FROM checkins WHERE room_number = ? ORDER BY check_out_date DESC LIMIT 1");
        $stmt->bind_param('i', $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $checkin = $result->fetch_assoc();
        $stmt->close();
    
        if ($checkin) {
            $total_price = (float)$checkin['total_price'];
            $amount_paid = (float)$checkin['amount_paid'];
            $guest_name = $checkin['guest_name'];
    
            // Proceed with checkout: update room and checkin
            $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE room_number = ?");
            $stmt->bind_param('i', $room_number);
            $stmt->execute();
            $stmt->close();
    
            $stmt_update = $conn->prepare("UPDATE checkins SET check_out_date = NOW() WHERE room_number = ? AND guest_name = ? ORDER BY check_in_date DESC LIMIT 1");
            // Note: MySQL does not support ORDER BY in UPDATE with LIMIT reliably across versions;
            // do a safe two-step: find id then update
            $sel = $conn->prepare("SELECT id FROM checkins WHERE room_number = ? AND guest_name = ? ORDER BY check_in_date DESC LIMIT 1");
            $sel->bind_param('is', $room_number, $guest_name);
            $sel->execute();
            $selRes = $sel->get_result();
            if ($selRes && $selRow = $selRes->fetch_assoc()) {
                $checkin_id = $selRow['id'];
                $sel->close();
                $stmt_update2 = $conn->prepare("
                    UPDATE checkins 
                    SET check_out_date = NOW(), status = 'checked_out' 
                    WHERE id = ?
                ");
                $stmt_update2->bind_param('i', $checkin_id);
                $stmt_update2->execute();
                $stmt_update2->close();

                // Mark related booking as completed (if any)
                $bkSel = $conn->prepare("SELECT id FROM bookings WHERE guest_name = ? AND room_number = ? AND status NOT IN ('cancelled','completed') ORDER BY start_date DESC LIMIT 1");
                $bkSel->bind_param("si", $guest_name, $room_number);
                $bkSel->execute();
                $bkRes = $bkSel->get_result();
                if ($bkRes && $bkRow = $bkRes->fetch_assoc()) {
                    $booking_id = (int)$bkRow['id'];
                    $bkSel->close();
                    $bkUpd = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                    $bkUpd->bind_param('i', $booking_id);
                    $bkUpd->execute();
                    $bkUpd->close();
                } else {
                    $bkSel->close();
                }

                // ✅ ADD THIS: Expire keycard upon checkout
                $stmt_k2 = $conn->prepare("UPDATE keycards SET status='expired' WHERE room_number = ? AND status = 'active'");
                $stmt_k2->bind_param("i", $room_number);
                $stmt_k2->execute();
                $stmt_k2->close();

            } else {
                $sel->close();
            }
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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


    /* STAT CARD DESIGN */
    .stat-card {
        border-radius: 12px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: #fff;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
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

    /* Optional smooth card shadow transition */
    .card {
        border: none;
    }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-available {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .status-booked {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .status-maintenance {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .countdown-timer {
            font-weight: 600;
        }
        
        
    .sidebar {
      width: 250px;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
    }
    .content { margin-left: 265px; padding: 20px; }
    .card { border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    table th { background: #f8f9fa; }
    table td, table th { padding: 12px; }

    .toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #dc3545; /* red */
    color: white;
    padding: 12px 20px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.4s, transform 0.4s;
    transform: translateY(-20px);
    z-index: 9999;
}
.toast.show {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}

.table thead th {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  padding: 0.75rem;
  font-size: 0.75rem;
  letter-spacing: 0.05em;
}

.table th.sorting {
  cursor: pointer;
  position: relative;
}

.table th.sorting_asc::after,
.table th.sorting_desc::after {
  content: '';
  position: absolute;
  right: 0.5rem;
  font-size: 0.7em;
  color: #6c757d;
}

.table th.sorting_asc::after { content: '↑'; }
.table th.sorting_desc::after { content: '↓'; }

.table td {
  padding: 0.75rem;
  vertical-align: middle;
  font-size: 0.875rem;
  color: #4a5568;
}

.table .badge {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  border: 1px solid;
  transition: all 0.2s ease;
}

.bg-blue-100 { background-color: #ebf8ff; }
.text-blue-800 { color: #2b6cb0; }
.border-blue-200 { border-color: #bee3f8; }
.bg-info-100 { background-color: #e6f7ff; }
.text-info-800 { color: #2b6cb0; }
.border-info-200 { border-color: #bee3f8; }
.bg-gray-100 { background-color: #f7fafc; }
.text-gray-800 { color: #2d3748; }
.border-gray-200 { border-color: #edf2f7; }
.bg-green-100 { background-color: #f0fff4; }
.text-green-800 { color: #2f855a; }
.border-green-200 { border-color: #c6f6d5; }
.bg-amber-100 { background-color: #fffaf0; }
.text-amber-800 { color: #975a16; }
.border-amber-200 { border-color: #fed7aa; }

.table-hover tbody tr:hover {
  background-color: #f8f9fa;
  transition: background-color 0.15s ease;
}


@media (max-width: 768px) {
    .stat-card {
        text-align: center;
    }
    .stat-icon {
        margin: 0 auto 10px auto;
    }
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
    <a href="receptionist-dash.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-dash.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-gauge"></i> Dashboard
    </a>
    <a href="receptionist-room.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-room.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-bed"></i> Rooms
    </a>
    <a href="receptionist-guest.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-guest.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-users"></i> Guests
    </a>
    <a href="receptionist-booking.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-booking.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-calendar-check"></i> Booking
    </a>
    <a href="receptionist-payment.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-payment.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-money-check"></i> Payment
    </a>
  </div>

  <div class="signout">
    <a href="signin.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
  </div>
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

<!-- STATISTICS CARDS (Admin Style) -->
<div class="row mb-4">
    <!-- Total Rooms -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Total Rooms</p>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-door-closed"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $totalRooms ?></h3>
            <p class="stat-change text-muted">Updated Today</p>
        </div>
    </div>

    <!-- Available Rooms -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Available Rooms</p>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $availableRooms ?></h3>
            <p class="stat-change text-success">+3% <span>from last week</span></p>
        </div>
    </div>

    <!-- Booked Rooms -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Booked Rooms</p>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-key"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $bookedRooms ?></h3>
            <p class="stat-change text-danger">-1% <span>this week</span></p>
        </div>
    </div>

    <!-- Maintenance Rooms -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Under Maintenance</p>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $maintenanceRooms ?></h3>
            <p class="stat-change text-muted">Scheduled repairs</p>
        </div>
    </div>
</div>

    <!-- Room List -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Room Status</h5>
            <i class="fas fa-bed"></i>
        </div>
        <div class="card-body">
            <div class="row">
                <?php while ($room = $resultRooms->fetch_assoc()): 
    // 🔔 Fetch pending orders for this room
    $orderCountQuery = $conn->prepare("
        SELECT COUNT(*) AS pending_orders 
        FROM orders 
        WHERE room_number = ? AND status = 'pending'
    ");
    $orderCountQuery->bind_param('i', $room['room_number']);
    $orderCountQuery->execute();
    $orderResult = $orderCountQuery->get_result();
    $orderCount = $orderResult->fetch_assoc()['pending_orders'] ?? 0;
    $orderCountQuery->close();?>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card room-card <?= $room['status'] ?>"
                            onclick="cardClicked(event, <?= $room['room_number']; ?>, '<?= $room['status'] ?>')">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex flex-column">
                                    <span>Room #<?= htmlspecialchars($room['room_number']); ?></span>
                                </div>
                                <span class="status-badge status-<?= $room['status'] ?>"><?= ucfirst($room['status']) ?></span>
                            </div>

                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted"><i class="fas fa-tag me-2"></i>Type:</span>
                                    <span class="fw-semibold"><?= ucfirst($room['room_type']) ?></span>
                                </div>
                                
                                <?php if ($room['status'] === 'booked' && !empty($room['check_out_date'])): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted"><i class="fas fa-clock me-2"></i>Time Left:</span>
                                        <span class="countdown-timer" data-room="<?= $room['room_number']; ?>" data-checkout="<?= $room['check_out_date']; ?>">Loading...</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-3">
                                        <form method="POST" action="receptionist-room.php" class="d-inline extend-form">
                                        <input type="hidden" name="room_number" value="<?= $room['room_number']; ?>">
                                        <input type="hidden" name="extend" value="1">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i class="fas fa-clock me-1"></i> Extend
                                        </button>
                                        </form>

                                        <form method="POST" action="receptionist-room.php" class="d-inline checkout-form">
                                        <input type="hidden" name="room_number" value="<?= $room['room_number']; ?>">
                                        <input type="hidden" name="checkout" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-sign-out-alt me-1"></i> Check Out
                                        </button>
                                        </form>
                                    </div>
                                <?php elseif ($room['status'] === 'available'): ?>
                                    <div class="d-flex justify-content-center mt-3">
                                        <a href="check-in.php?room_number=<?= $room['room_number']; ?>" class="btn btn-sm btn-success">
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
    <div class="card-header text-black d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Booking Summary</h5>
        <div class="d-flex align-items-center gap-3 flex-wrap">
        <!-- Custom dropdown and search -->
        <div id="customBookingLengthMenu"></div>
        <input id="bookingSearchInput" type="text" class="form-control form-control-sm" placeholder="Search bookings..." style="width: 200px;">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="bookingSummaryTable" class="table table-hover mb-0">
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
                                $room_number = (int)$booking['room_number'];
                                $booking_start = $booking['start_date'];
                                $booking_end = $booking['end_date'];
                                $guest_name = $booking['guest_name'];
                                $now = new DateTime();

                                $booking_end_dt = new DateTime($booking_end);
                                $booking_finished = $now >= $booking_end_dt;

                                // New: determine current occupant (if any) and whether this booking's guest already checked out
                                $already_checked_in = false;
                                $occupied_by_other = false;
                                $checked_out_for_booking = false;
                                $current_occupant = null;

                                // 1) Check current occupant for the room (active checkin where NOW() is between check_in_date and check_out_date)
                                $currStmt = $conn->prepare("
                                    SELECT guest_name, check_out_date 
                                    FROM checkins 
                                    WHERE room_number = ? 
                                      AND check_in_date <= NOW() 
                                      AND check_out_date > NOW() 
                                    ORDER BY check_in_date DESC 
                                    LIMIT 1
                                ");
                                $currStmt->bind_param("i", $room_number);
                                $currStmt->execute();
                                $currRes = $currStmt->get_result();
                                if ($currRes && $rowCurr = $currRes->fetch_assoc()) {
                                    $current_occupant = $rowCurr['guest_name'];
                                    if ($current_occupant === $guest_name) {
                                        $already_checked_in = true; // the booking's guest is currently in the room
                                    } else {
                                        $occupied_by_other = true; // occupied by another guest
                                    }
                                }
                                $currStmt->close();

                                // 2) Check if this booking's guest already checked out (a checkin record that ended already)
                                if (! $already_checked_in) {
                                    // More robust: any checkin for this guest+room that has already ended
                                    $coStmt = $conn->prepare("
                                        SELECT id 
                                        FROM checkins 
                                        WHERE room_number = ? 
                                          AND guest_name = ? 
                                          AND check_out_date <= NOW()
                                        ORDER BY check_out_date DESC
                                        LIMIT 1
                                    ");
                                    $coStmt->bind_param("is", $room_number, $guest_name);
                                    $coStmt->execute();
                                    $coRes = $coStmt->get_result();
                                    if ($coRes && $coRes->num_rows > 0) {
                                        $checked_out_for_booking = true;
                                    }
                                    $coStmt->close();
                                }

                    ?>
                    <tr>
                        <td class="align-middle"><?= htmlspecialchars($booking['guest_name']) ?></td>
                        <td class="align-middle"><?= date("M d, Y h:i A", strtotime($booking['start_date'])) ?></td>
                        <td class="align-middle"><?= date("M d, Y h:i A", strtotime($booking['end_date'])) ?></td>
                        <td class="align-middle"><?= $booking['room_number'] ?></td>
                        <td class="align-middle"><?= $booking['duration'] ?> hrs</td>
                        <td class="align-middle"><?= $booking['num_people'] ?></td>
                        <td class="align-middle">
                            <?php
                                $room_check = $conn->prepare("SELECT status FROM rooms WHERE room_number = ?");
                                $room_check->bind_param("i", $booking['room_number']);
                                $room_check->execute();
                                $room_result = $room_check->get_result();
                                $room = $room_result->fetch_assoc();
                                $room_check->close();

                                // New decision logic (note: prefer Checked Out before Room Unavailable)
                                // - If room is currently occupied by this booking's guest => "In Use by {name}"
                                // - Else if booking_finished OR the booking's guest already checked out => "Checked Out"
                                // - Else if occupied by other guest => "Room Unavailable"
                                // - Else if room is available => show Check In button
                                if ($already_checked_in):
                            ?>
                                <span class="badge bg-success">In Use by <?= htmlspecialchars($guest_name) ?></span>
                            <?php elseif ($checked_out_for_booking || $booking_finished): ?>
                                <span class="badge bg-secondary">Checked Out</span>
                            <?php elseif ($occupied_by_other): ?>
                                <span class="badge bg-warning text-dark">Room Unavailable</span>
                            <?php elseif ($room && $room['status'] === 'available'): 
                                     $guest = urlencode($booking['guest_name']);
                                     $checkin = urlencode($booking['start_date']);
                                     $checkout = urlencode($booking['end_date']);
                                     $num_people = (int)$booking['num_people'];
                            ?>
                                <a href="check-in.php?room_number=<?= $booking['room_number']; ?>&guest_name=<?= $guest; ?>&checkin=<?= $checkin; ?>&checkout=<?= $checkout; ?>&num_people=<?= $num_people; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-sign-in-alt me-1"></i> Check In
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">Room Unavailable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

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

// SweetAlert Confirmation before Extending Stay
document.querySelectorAll('.extend-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Extend Stay?',
            text: "Do you want to extend this stay by 1 hour?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Yes, extend',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});


// SweetAlert Confirmation before Check Out
document.querySelectorAll('.checkout-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // prevent immediate submit

        Swal.fire({
            title: 'Are you sure?',
            text: "Do you really want to check out this guest?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, check out',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit(); // proceed with form submission
            }
        });
    });
});


// Handle card click
function cardClicked(event, roomNumber, status) {
    // Prevent the click if user clicked a button inside the card
    if (event.target.tagName.toLowerCase() === 'button' || event.target.closest('form')) {
        return;
    }

    if (status === 'booked') {
        // Show toast instead of redirect
        let toast = document.getElementById('roomToast');
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
        event.preventDefault();
        return false;
    }

    // If available, continue to check-in
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

function refreshOrderBadges() {
  fetch('fetch_pending_orders.php')
    .then(res => res.json())
    .then(data => {
      for (const [room, count] of Object.entries(data)) {
        const badge = document.querySelector(`#order-badge-${room}`);
        if (badge) {
          badge.textContent = count > 0
            ? `${count} New ${count > 1 ? 'Orders' : 'Order'}`
            : 'No Orders';
          badge.className = count > 0
            ? 'badge bg-danger mt-1'
            : 'badge bg-secondary mt-1';
        }
      }
    });
}
setInterval(refreshOrderBadges, 5000); // Refresh every 5 seconds

// DATA TABLE
$(document).ready(function() {
  var bookingSummary = $('#bookingSummaryTable').DataTable({
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: true,
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50, 100],
    dom: 'rt<"row mt-3"<"col-sm-5"i><"col-sm-7"p>>', // no header controls here
    language: {
      emptyTable: "<i class='fas fa-calendar-times fa-3x text-muted mb-3'></i><p class='mb-0'>No bookings found</p>",
      info: "Showing _START_ to _END_ of _TOTAL_ bookings",
      infoEmpty: "No entries available",
      infoFiltered: "(filtered from _MAX_ total bookings)",
      lengthMenu: "Show _MENU_ bookings",
      paginate: {
        first: "«",
        last: "»",
        next: "›",
        previous: "‹"
      }
    }
  });

  // === Custom Length Dropdown ===
  bookingSummary.on('init', function () {
    var lengthSelect = $('#bookingSummaryTable_length select')
      .addClass('form-select form-select-sm')
      .css('width', '80px');

    $('#customBookingLengthMenu').html(
      '<label class="d-flex align-items-center gap-2 mb-0 text-white">' +
        '<span>Show</span>' +
        lengthSelect.prop('outerHTML') +
        '<span>bookings</span>' +
      '</label>'
    );

    $('#bookingSummaryTable_length').hide();
  });

  // === Custom Search ===
  $('#bookingSearchInput').on('keyup', function() {
    bookingSummary.search(this.value).draw();
  });

  // === Sorting Icons ===
  bookingSummary.on('order.dt', function() {
    $('th.sorting', bookingSummary.table().header()).removeClass('sorting_asc sorting_desc');
    bookingSummary.columns().every(function(index) {
      var order = bookingSummary.order()[0];
      if (order[0] === index) {
        $('th:eq(' + index + ')', bookingSummary.table().header())
          .addClass(order[1] === 'asc' ? 'sorting_asc' : 'sorting_desc');
      }
    });
  });
});

</script>
</body>
</html>

<?php
// Only close the connection if it's still open and is an object
if (isset($conn) && $conn instanceof mysqli) {
    if (@$conn->ping()) {
        $conn->close();
    }
}
?>
