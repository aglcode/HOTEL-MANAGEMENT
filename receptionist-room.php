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
    SELECT room_number, item, status, created_at 
    FROM orders 
    ORDER BY created_at DESC
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
                $stmt_update2 = $conn->prepare("UPDATE checkins SET check_out_date = NOW() WHERE id = ?");
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
    <link href="style.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 24px;
        }
        
        .room-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
        }
        
        .room-card .card-header {
            font-weight: 600;
            padding: 12px 15px;
        }
        
        .room-card.available .card-header {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .room-card.booked .card-header {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .room-card.maintenance .card-header {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
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
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
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
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Room Status</h5>
            <i class="fas fa-bed"></i>
        </div>
        <div class="card-body">
            <div class="row">
                <?php while ($room = $resultRooms->fetch_assoc()): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card room-card <?= $room['status'] ?>"
                            onclick="cardClicked(event, <?= $room['room_number']; ?>, '<?= $room['status'] ?>')">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Room #<?= htmlspecialchars($room['room_number']); ?></span>
                                <span class="status-badge status-<?= $room['status'] ?>"><?= ucfirst($room['status']) ?></span>
                            </div>

                                <?php if (!empty($roomOrders[$room['room_number']])): ?>
                                    <div class="mb-3">
                                        <h6 class="fw-bold">Orders</h6>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($roomOrders[$room['room_number']] as $ord): ?>
                                                <li>
                                                    ✅ <?= htmlspecialchars($ord['item']) ?> 
                                                    <small class="text-muted">(<?= date("H:i", strtotime($ord['created_at'])) ?>)</small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

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
                                            <button type="submit" name="extend" class="btn btn-sm btn-warning">
                                                <i class="fas fa-clock me-1"></i> Extend
                                            </button>
                                        </form>
                                        <form method="POST" action="receptionist-room.php" class="d-inline checkout-form">
                                            <input type="hidden" name="room_number" value="<?= $room['room_number']; ?>">
                                            <button type="submit" name="checkout" class="btn btn-sm btn-danger">
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
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Booking Summary</h5>
        <i class="fas fa-calendar-check"></i>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
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
            e.preventDefault();
        }
    });
});

// Confirmation before Check Out
document.querySelectorAll('.checkout-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!confirm('Do you really want to check out this guest?')) {
            e.preventDefault();
        }
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
