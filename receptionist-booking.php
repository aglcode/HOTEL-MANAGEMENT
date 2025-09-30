<?php
session_start();
require_once 'database.php';

// Generate booking token function
function generateBookingToken() {
    return 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Auto-cancel bookings that are 30 minutes past check-in time
function autoCancelOverdueBookings($conn) {
    $current_time = new DateTime();
    $cancel_time = $current_time->sub(new DateInterval('PT30M')); // 30 minutes ago
    
    $auto_cancel_query = "UPDATE bookings SET 
                         status = 'cancelled', 
                         cancellation_reason = 'Automatically cancelled - Guest did not check in within 30 minutes of designated time', 
                         cancelled_at = NOW() 
                         WHERE start_date < ? 
                         AND status != 'cancelled' 
                         AND status != 'completed'
                         AND start_date > DATE_SUB(NOW(), INTERVAL 1 DAY)";
    
    $stmt = $conn->prepare($auto_cancel_query);
    $cancel_time_str = $cancel_time->format('Y-m-d H:i:s');
    $stmt->bind_param("s", $cancel_time_str);
    $stmt->execute();
    $stmt->close();
}

// Run auto-cancellation check
autoCancelOverdueBookings($conn);



// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    if (empty($cancellation_reason)) {
        $_SESSION['error_msg'] = 'Cancellation reason is required.';
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $cancellation_reason, $booking_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = 'Booking has been cancelled successfully.';
        } else {
            $_SESSION['error_msg'] = 'Error cancelling booking.';
        }
        $stmt->close();
    }
    header('Location: receptionist-booking.php');
    exit();
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_booking'])) {
    $guest_name   = $_POST['guest_name'];
    $email        = $_POST['email'] ?? '';
    $address      = $_POST['address'];
    $telephone    = $_POST['telephone'];
    $age          = (int)$_POST['age'];
    $num_people   = isset($_POST['num_people']) ? (int)$_POST['num_people'] : 1; // Default to 1 if not provided
    $room_number  = $_POST['room_number'];
    $duration     = $_POST['duration'];
    $payment_mode = $_POST['payment_mode'];
    $reference    = $_POST['reference_number'] ?? '';
    $amount_paid  = floatval($_POST['amount_paid']);
    $start_date   = $_POST['start_date'];
    
    // Generate booking token
    $booking_token = generateBookingToken();

    if ($age < 18) {
        $_SESSION['error_msg'] = 'Guest must be at least 18 years old.';
    } elseif ($payment_mode === 'GCash' && empty($reference)) {
        $_SESSION['error_msg'] = 'GCash reference number is required.';
    } else {
        // Get room details
        $stmt = $conn->prepare("SELECT * FROM rooms WHERE room_number = ?");
        $stmt->bind_param("s", $room_number);
        $stmt->execute();
        $room_result = $stmt->get_result();

        if ($room_result->num_rows === 0) {
            $_SESSION['error_msg'] = 'Invalid room selected.';
        } else {
            $room = $room_result->fetch_assoc();

            // Calculate end date
            $start_dt = new DateTime($start_date);
            $hours = match ($duration) {
                '3' => 3,
                '6' => 6,
                '12' => 12,
                '24' => 24,
                default => 48
            };
            $start_dt->modify("+{$hours} hours");
            $end_date = $start_dt->format('Y-m-d H:i:s');

            // Total price
            $total_price = match ($duration) {
                '3' => $room['price_3hrs'],
                '6' => $room['price_6hrs'],
                '12' => $room['price_12hrs'],
                '24' => $room['price_24hrs'],
                default => $room['price_ot']
            };
            
            // Calculate change
            $change = $amount_paid - $total_price;

            // Conflict check (fix: check for overlap only with non-cancelled bookings)
            $conflict_stmt = $conn->prepare("
                SELECT 1 FROM bookings 
                WHERE room_number = ? 
                  AND status NOT IN ('cancelled', 'completed') 
                  AND (
                    (? < end_date AND ? > start_date)
                  )
                LIMIT 1
            ");
            $conflict_stmt->bind_param("sss", $room_number, $start_date, $end_date);
            $conflict_stmt->execute();
            $conflict_result = $conflict_stmt->get_result();

            if ($conflict_result->num_rows > 0) {
                $_SESSION['error_msg'] = 'Room is already booked during the selected time.';
            } else {
                $insert = $conn->prepare("INSERT INTO bookings 
                    (guest_name, email, address, telephone, age, num_people, room_number, duration, payment_mode, reference_number, amount_paid, change_amount, total_price, start_date, end_date, booking_token, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', NOW())");
                $insert->bind_param("ssssiissssddssss",
                    $guest_name, $email, $address, $telephone, $age, $num_people, $room_number,
                    $duration, $payment_mode, $reference, $amount_paid, $change,
                    $total_price, $start_date, $end_date, $booking_token
                );
                
                if ($insert->execute()) {
                    // Send email if email is provided
                    if (!empty($email)) {
                        $bookingDetails = [
                            'room' => $room_number,
                            'duration' => $duration,
                            'start_date' => $start_date,
                            'total_price' => number_format($total_price, 2)
                        ];
                        sendBookingEmail($email, $guest_name, $booking_token, $bookingDetails);
                    }
                    
                    $_SESSION['success_msg'] = "Booking successful! Token: $booking_token";
                    header('Location: receptionist-booking.php');
                    exit();
                } else {
                    $_SESSION['error_msg'] = 'Error creating booking.';
                }
            }
        }
    }
}

// Define a placeholder for sendBookingEmail function
function sendBookingEmail($email, $guest_name, $booking_token, $bookingDetails) {
    // Placeholder implementation
    // You can replace this with actual email-sending logic
    error_log("Sending booking email to $email for $guest_name with token $booking_token");
}

// Filter and pagination
$where = [];
$params = [];
$types = "";
$limit = 10;
$page = max((int)($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// Get filter values
$filter_guest = $_GET['guest_name'] ?? '';
$filter_room = $_GET['room_number'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_status = $_GET['status'] ?? 'all';

if (!empty($filter_guest)) {
    $where[] = "guest_name LIKE ?";
    $params[] = '%' . $filter_guest . '%';
    $types .= "s";
}

if (!empty($filter_room)) {
    $where[] = "room_number LIKE ?";
    $params[] = '%' . $filter_room . '%';
    $types .= "s";
}

if (!empty($filter_date)) {
    $where[] = "DATE(start_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

// Status filter
if ($filter_status === 'upcoming') {
    $where[] = "b.start_date > NOW() AND b.status != 'cancelled'";
} elseif ($filter_status === 'active') {
    $where[] = "b.start_date <= NOW() AND b.end_date >= NOW() AND b.status != 'cancelled'";
} elseif ($filter_status === 'completed') {
    $where[] = "b.end_date < NOW() AND b.status != 'cancelled'";
} elseif ($filter_status === 'cancelled') {
    $where[] = "b.status = 'cancelled'";
}

// Build query
$query = "SELECT b.*, r.room_type FROM bookings b 
          LEFT JOIN rooms r ON b.room_number = r.room_number";
if ($where) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY b.start_date DESC LIMIT ?, ?";
$types .= "ii";
$params[] = $offset;
$params[] = $limit;

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$total_bookings_query = "SELECT COUNT(*) as total FROM bookings b WHERE b.status != 'cancelled'";
$total_bookings_result = $conn->query($total_bookings_query);
$total_bookings = $total_bookings_result->fetch_assoc()['total'] ?? 0;

$upcoming_bookings_query = "SELECT COUNT(*) as total FROM bookings b WHERE b.start_date > NOW() AND b.status != 'cancelled'";
$upcoming_bookings_result = $conn->query($upcoming_bookings_query);
$upcoming_bookings = $upcoming_bookings_result->fetch_assoc()['total'] ?? 0;

$active_bookings_query = "SELECT COUNT(*) as total FROM bookings b WHERE b.start_date <= NOW() AND b.end_date >= NOW() AND b.status != 'cancelled'";
$active_bookings_result = $conn->query($active_bookings_query);
$active_bookings = $active_bookings_result->fetch_assoc()['total'] ?? 0;

// Replace total revenue with cancelled bookings count
$cancelled_bookings_query = "SELECT COUNT(*) as total FROM bookings b WHERE b.status = 'cancelled'";
$cancelled_bookings_result = $conn->query($cancelled_bookings_query);
$cancelled_bookings = $cancelled_bookings_result->fetch_assoc()['total'] ?? 0;

// Total pages for pagination
$count_query = "SELECT COUNT(*) as total FROM bookings b LEFT JOIN rooms r ON b.room_number = r.room_number";
if ($where) {
    $count_query .= " WHERE " . implode(" AND ", $where);
}
$count_stmt = $conn->prepare($count_query);
// Remove the LIMIT parameters for count query
$count_params = array_slice($params, 0, -2);
$count_types = substr($types, 0, -2);
if (!empty($count_params)) {
    $count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Booking Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Enhanced Table Styling */
        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            vertical-align: middle;
            padding: 0.75rem 1rem;
            border-color: #f0f0f0;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.03);
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        /* Status Badge Styling */
        .badge-status {
            padding: 0.5rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        /* Search and Filter Controls */
        .search-filter-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        /* Card Styling */
        .card {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
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
        
        /* Guest Avatar */
        .guest-avatar {
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #6c757d;
            margin-right: 1rem;
        }
        
        /* Booking Token */
        .booking-token {
            background-color: #e8f5e9;
            border: 2px dashed #4caf50;
            border-radius: 8px;
            padding: 0.5rem;
            text-align: center;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 0.8rem;
            color: #2e7d32;
        }
        
        /* Clickable card cursor */
        .clickable-card {
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .clickable-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
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


    <!-- Content -->
    <div class="content">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Booking Management</h2>
                <p class="text-muted mb-0">Manage and track all room bookings</p>
            </div>
            <div class="clock-box text-end text-dark">
                <div id="currentDate" class="fw-semibold"></div>
                <div id="currentTime" class="fs-5"></div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
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
            <!-- Upcoming Bookings Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?php echo $upcoming_bookings; ?></h3>
                            <p class="text-muted mb-0">Upcoming Bookings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Bookings Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?php echo $active_bookings; ?></h3>
                            <p class="text-muted mb-0">Active Bookings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Bookings Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?php echo $total_bookings; ?></h3>
                            <p class="text-muted mb-0">Total Bookings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cancelled Bookings Card (Clickable) -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 clickable-card" onclick="showCancelledBookings()">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?php echo $cancelled_bookings; ?></h3>
                            <p class="text-muted mb-0">Cancelled Bookings</p>
                            <small class="text-muted">Click to view details</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions - Fixed button target -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Quick Actions</h5>
        <i class="fas fa-bolt"></i>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#bookingModal">
                    <i class="fas fa-calendar-plus me-2"></i>New Booking
                </button>
            </div>
        </div>
    </div>
</div>
        
        <!-- Search and Filter Controls -->
        <div class="search-filter-container mb-4 no-print">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="searchInput" class="form-label">Search Guest</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="guest_name" id="searchInput" class="form-control" placeholder="Guest name..." value="<?= htmlspecialchars($filter_guest) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="roomInput" class="form-label">Room Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-bed"></i></span>
                        <input type="text" name="room_number" id="roomInput" class="form-control" placeholder="Room #" value="<?= htmlspecialchars($filter_room) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="dateInput" class="form-label">Date</label>
                    <input type="date" name="date" id="dateInput" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="col-md-2">
                    <label for="statusSelect" class="form-label">Status</label>
                    <select name="status" id="statusSelect" class="form-select">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex">
                    <button type="submit" class="btn btn-primary me-2 flex-grow-1">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="receptionist-booking.php" class="btn btn-outline-secondary flex-grow-1">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Booking List Section -->
        <div class="card mb-4" id="bookingList">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Booking List</h5>
                <span class="badge bg-light text-primary rounded-pill"><?= $total_records ?> Total Bookings</span>
            </div>
            <div class="card-body p-0">
                <!-- Bookings Table -->
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Guest Details</th>
                                <th>Room Info</th>
                                <th>Duration</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Payment</th>
                                <th>Token</th>
                                <th>Status</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                $index = $offset + 1;
                                while ($row = $result->fetch_assoc()):
                                    $now = new DateTime();
                                    $start = new DateTime($row['start_date']);
                                    $end = new DateTime($row['end_date']);

                                    // Get latest checkin (if any) for this guest + room
                                    $latestCheckin = null;
                                    $lcStmt = $conn->prepare("
                                        SELECT check_in_date, check_out_date 
                                        FROM checkins 
                                        WHERE guest_name = ? AND room_number = ? 
                                        ORDER BY check_in_date DESC 
                                        LIMIT 1
                                    ");
                                    $lcStmt->bind_param("ss", $row['guest_name'], $row['room_number']);
                                    $lcStmt->execute();
                                    $lcRes = $lcStmt->get_result();
                                    if ($lcRes && $lcRow = $lcRes->fetch_assoc()) {
                                        $latestCheckin = $lcRow;
                                    }
                                    $lcStmt->close();

                                    // Also detect any current occupant for the room (any guest)
                                    $currentOccupant = null;
                                    $occStmt = $conn->prepare("
                                        SELECT guest_name 
                                        FROM checkins 
                                        WHERE room_number = ? 
                                          AND check_in_date <= NOW() 
                                          AND check_out_date > NOW() 
                                        ORDER BY check_in_date DESC 
                                        LIMIT 1
                                    ");
                                    $occStmt->bind_param("s", $row['room_number']);
                                    $occStmt->execute();
                                    $occRes = $occStmt->get_result();
                                    if ($occRes && $occRow = $occRes->fetch_assoc()) {
                                        $currentOccupant = $occRow['guest_name'];
                                    }
                                    $occStmt->close();

                                    // Decide status: prefer latest checkin status if present (override booking.status)
                                    if ($latestCheckin) {
                                        $ci_out = new DateTime($latestCheckin['check_out_date']);
                                        $ci_in = new DateTime($latestCheckin['check_in_date']);
                                        if ($ci_in <= $now && $ci_out > $now) {
                                            $status_class = "bg-warning text-dark";
                                            $status_text = "In Use";
                                        } elseif ($ci_out <= $now) {
                                            $status_class = "bg-secondary";
                                            $status_text = "Checked Out";
                                        } else {
                                            // future checkin exists but not started
                                            $status_class = "bg-info";
                                            $status_text = "Upcoming";
                                        }
                                    } else {
                                        // Fallback to booking.status/time logic
                                        if ($row['status'] === 'cancelled') {
                                            $status_class = "bg-danger";
                                            $status_text = "Cancelled";
                                        } elseif ($row['status'] === 'completed') {
                                            $status_class = "bg-secondary";
                                            $status_text = "Completed";
                                        } elseif ($currentOccupant) {
                                            $status_class = "bg-warning text-dark";
                                            $status_text = "In Use";
                                        } elseif ($now < $start) {
                                            $status_class = "bg-info";
                                            $status_text = "Upcoming";
                                        } elseif ($now >= $start && $now <= $end) {
                                            $status_class = "bg-success";
                                            $status_text = "Active";
                                        } elseif ($now > $end) {
                                            $status_class = "bg-secondary";
                                            $status_text = "Completed";
                                        } else {
                                            $status_class = "bg-secondary";
                                            $status_text = ucfirst($row['status']);
                                        }
                                    }
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= $index++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="guest-avatar">
                                            <?= strtoupper(substr($row['guest_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($row['guest_name']) ?></div>
                                            <div class="small text-muted">
                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($row['telephone']) ?>
                                            </div>
                                            <?php if (!empty($row['email'])): ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($row['email']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-users me-1"></i><?= $row['num_people'] ?> guest(s), Age: <?= $row['age'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">Room <?= htmlspecialchars($row['room_number']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($row['room_type'] ?? 'Standard') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($row['duration']) ?> Hours</span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= date('M d, Y', strtotime($row['start_date'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($row['start_date'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= date('M d, Y', strtotime($row['end_date'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($row['end_date'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold">₱<?= number_format($row['total_price'], 2) ?></div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($row['payment_mode']) ?>
                                        <?php if ($row['payment_mode'] === 'GCash' && !empty($row['reference_number'])): ?>
                                            <br><small>Ref: <?= htmlspecialchars($row['reference_number']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-success">
                                        Paid: ₱<?= number_format($row['amount_paid'], 2) ?>
                                        <?php if ($row['change_amount'] > 0): ?>
                                            <br>Change: ₱<?= number_format($row['change_amount'], 2) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['booking_token'])): ?>
                                        <div class="booking-token" title="Booking Token">
                                            <?= htmlspecialchars($row['booking_token']) ?>
                                        </div>
                                        <?php if (!empty($row['email'])): ?>
                                            <div class="small text-success mt-1">
                                                <i class="fas fa-envelope-check"></i> Emailed
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No token</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                    <?php if ($row['status'] === 'cancelled' && !empty($row['cancellation_reason'])): ?>
                                        <br><small class="text-muted" title="<?= htmlspecialchars($row['cancellation_reason']) ?>">Reason: <?= htmlspecialchars(substr($row['cancellation_reason'], 0, 20)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print">
                                    <?php if ($status_text !== 'Completed'): ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewGuestDetails(<?= $row['id'] ?>)" title="View Guest Details">
                                            <i class="fas fa-user"></i>
                                        </button>
                                        <?php if ($row['status'] !== 'cancelled'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelBooking(<?= $row['id'] ?>, '<?= htmlspecialchars($row['guest_name']) ?>')" title="Cancel Booking">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted small">No actions available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            } else {
                                echo "<tr><td colspan='10'><div class='text-center py-5'><i class='fas fa-calendar-times fa-3x text-muted mb-3'></i><h5>No bookings found</h5><p class='text-muted'>Try adjusting your search filters or create a new booking</p></div></td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-3">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            if ($end_page - $start_page < 4) {
                                $start_page = max(1, $end_page - 4);
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bookingModalLabel">
                    <i class="fas fa-calendar-plus me-2"></i>Reserve Your Room
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" onsubmit="return validateBookingForm();">
                <div class="modal-body">
                    <div class="container">
                        <div class="row">
                            <!-- Guest Information -->
                            <div class="col-md-6 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Guest Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="guestName" class="form-label">Full Name *</label>
                                            <input type="text" name="guest_name" id="guestName" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" name="email" id="email" class="form-control" required>
                                            <small class="text-muted">We'll send your booking confirmation to this email</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="telephone" class="form-label">Phone Number *</label>
                                            <input type="text" name="telephone" id="telephone" class="form-control" required pattern="\d{10,11}">
                                            <small class="text-muted">Enter a valid 10-11 digit phone number</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Complete Address *</label>
                                            <input type="text" name="address" id="address" class="form-control" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="age" class="form-label">Age *</label>
                                                <input type="number" name="age" id="age" class="form-control" required>
                                                <small class="text-muted">Must be 18 or older</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="numPeople" class="form-label">Number of Guests *</label>
                                                <input type="number" name="num_people" id="numPeople" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Details -->
                            <div class="col-md-6 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Booking Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="roomNumber" class="form-label">Select Room *</label>
                                            <select name="room_number" id="roomNumber" class="form-select" required>
                                                <option value="">Choose your preferred room</option>
                                                <?php
                                                $room_query = "SELECT room_number, room_type FROM rooms WHERE status = 'available'";
                                                $room_result = $conn->query($room_query);
                                                while ($room = $room_result->fetch_assoc()) {
                                                    echo "<option value='{$room['room_number']}'>Room {$room['room_number']} ({$room['room_type']})</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="duration" class="form-label">Stay Duration *</label>
                                            <select name="duration" id="duration" class="form-select" required>
                                                <option value="3">3 Hours</option>
                                                <option value="6">6 Hours</option>
                                                <option value="12">12 Hours</option>
                                                <option value="24">24 Hours</option>
                                                <option value="48">48 Hours</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="startDate" class="form-label">Check-in Date & Time *</label>
                                            <input type="datetime-local" name="start_date" id="startDate" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="endDate" class="form-label">Estimated Check-out *</label>
                                            <input type="text" id="endDate" class="form-control bg-light" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label for="totalPrice" class="form-label">Total Price</label>
                                            <input type="text" id="totalPrice" class="form-control bg-light" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="card shadow-sm mb-4" style="border-radius: 16px; border: 1px solid #a18cd1; background: linear-gradient(90deg, #a18cd1 0%, #fbc2eb 100%);">
                            <div class="card-header text-white" style="background: transparent; border-bottom: none;">
                                <h6 class="mb-0" style="font-weight:600;">
                                    <i class="fas fa-credit-card me-2"></i>Payment Information
                                </h6>
                            </div>
                            <div class="card-body bg-white rounded-bottom" style="border-radius: 0 0 16px 16px;">
                                <div class="row align-items-end">
                                    <div class="col-md-6 mb-3">
                                        <label for="paymentMode" class="form-label">Payment Method *</label>
                                        <select name="payment_mode" id="paymentMode" class="form-select" required onchange="togglePaymentFields();">
                                            <option value="">Select payment method</option>
                                            <option value="Cash">&#x1F4B5; Cash Payment</option>
                                            <option value="GCash">&#x1F4F1; GCash</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="amountPaid" class="form-label">Amount to Pay *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-gradient" style="background: linear-gradient(90deg, #a18cd1 0%, #fbc2eb 100%); color: #fff;">₱</span>
                                            <input type="number" name="amount_paid" id="amountPaid" class="form-control" min="0" step="0.01" required oninput="calculateChange();">
                                        </div>
                                    </div>
                                </div>
                                <!-- GCash Section -->
                                <div id="gcashSection" class="row mt-3" style="display: none;">
                                    <div class="col-md-8">
                                        <div style="background: #e9f3ff; border-radius: 12px; padding: 20px; margin-bottom: 16px;">
                                            <div style="background: #b6dbff; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                                                <strong><i class="fas fa-info-circle me-2"></i>GCash Payment Instructions:</strong>
                                                <ol class="mb-0 mt-2" style="padding-left: 18px; color: #1a237e;">
                                                    <li>Send your payment to the GCash number provided</li>
                                                    <li>Take a screenshot of the transaction</li>
                                                    <li>Enter the 13-digit reference number below</li>
                                                </ol>
                                            </div>
                                            <div class="mb-2">
                                                <label for="referenceNumber" class="form-label">GCash Reference Number *</label>
                                                <input type="text" name="reference_number" id="referenceNumber" class="form-control" placeholder="Enter 13-digit reference number" maxlength="13" pattern="\d{13}">
                                                <small class="text-muted">This can be found in your GCash transaction receipt</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-stretch">
                                        <div style="background: linear-gradient(135deg, #e0e7ff 0%, #fbc2eb 100%); border-radius: 12px; padding: 20px; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                            <div style="color: #0063F7; font-weight: bold; font-size: 1.2rem; margin-bottom: 8px;">
                                                <i class="fab fa-google-pay"></i> G<span style="color:#0063F7;">Pay</span>
                                            </div>
                                            <div style="font-size: 1rem; color: #333;">GCash Number:</div>
                                            <div style="font-size: 1.5rem; font-weight: bold; color: #4f46e5; margin-bottom: 6px;">09123456789</div>
                                            <div style="font-size: 1rem; color: #333;">Account Name:</div>
                                            <div style="font-weight: 500; color: #4f46e5;">Gitarra Apartelle</div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End GCash Section -->
                                <div class="row" id="cashSection" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label for="changeAmount" class="form-label">Change</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-gradient" style="background: linear-gradient(90deg, #a18cd1 0%, #fbc2eb 100%); color: #fff;">₱</span>
                                            <input type="text" id="changeAmount" class="form-control" readonly value="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary w-100" style="background: linear-gradient(90deg, #a18cd1 0%, #fbc2eb 100%); border: none; font-weight:600;">
                        <i class="fas fa-calendar-check me-2"></i>Confirm Booking & Reserve Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelBookingModalLabel">
    <!-- Cancelled Bookings Modal -->
    <div class="modal fade" id="cancelledBookingsModal" tabindex="-1" aria-labelledby="cancelledBookingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelledBookingsModalLabel">
                        <i class="fas fa-times-circle me-2"></i>Cancelled Bookings Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="cancelledBookingsContent">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            <p class="mt-2">Loading cancelled bookings...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Guest Details Modal -->
    <div class="modal fade" id="guestDetailsModal" tabindex="-1" aria-labelledby="guestDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="guestDetailsModalLabel">
                        <i class="fas fa-user me-2"></i>Guest Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="guestDetailsContent">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            <p class="mt-2">Loading guest details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        
        setInterval(updateClock, 1000);
        updateClock();
        
        // Update price based on room and duration selection
        function updatePrice() {
            const roomSelect = document.querySelector('select[name="room_number"]');
            const durationSelect = document.querySelector('select[name="duration"]');
            const priceInput = document.getElementById('totalPrice');
            
            if (roomSelect.value && durationSelect.value) {
                const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
                const duration = durationSelect.value;
                let price = 0;
                
                switch(duration) {
                    case '3':
                        price = selectedRoom.dataset.price3;
                        break;
                    case '6':
                        price = selectedRoom.dataset.price6;
                        break;
                    case '12':
                        price = selectedRoom.dataset.price12;
                        break;
                    case '24':
                        price = selectedRoom.dataset.price24;
                        break;
                    case '48':
                        price = selectedRoom.dataset.priceOt;
                        break;
                }
                
                priceInput.value = parseFloat(price).toFixed(2);
                calculateChange();
            } else {
                priceInput.value = '';
            }
        }
        
        // Calculate change
        function calculateChange() {
            const totalPrice = parseFloat(document.getElementById('totalPrice').value) || 0;
            const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
            const paymentMode = document.getElementById('paymentMode').value;
            let change = 0;
            if (paymentMode === 'Cash') {
                change = amountPaid - totalPrice;
            }
            document.getElementById('changeAmount').value = change >= 0 ? change.toFixed(2) : '0.00';
        }
        
        // Toggle payment fields
        function togglePaymentFields() {
            const paymentMode = document.getElementById('paymentMode').value;
            const gcashSection = document.getElementById('gcashSection');
            const cashSection = document.getElementById('cashSection');
            const referenceInput = document.getElementById('referenceNumber');
            if (paymentMode === 'GCash') {
                gcashSection.style.display = 'flex';
                cashSection.style.display = 'none';
                referenceInput.required = true;
            } else if (paymentMode === 'Cash') {
                gcashSection.style.display = 'none';
                cashSection.style.display = 'flex';
                referenceInput.required = false;
                referenceInput.value = '';
            } else {
                gcashSection.style.display = 'none';
                cashSection.style.display = 'none';
                referenceInput.required = false;
                referenceInput.value = '';
            }
            calculateChange();
        }
        
        // Form validation
        function validateForm() {
            const age = parseInt(document.querySelector('input[name="age"]').value);
            const paymentMode = document.querySelector('select[name="payment_mode"]').value;
            const reference = document.querySelector('input[name="reference_number"]').value;
            const amountPaid = parseFloat(document.querySelector('input[name="amount_paid"]').value);
            const totalPrice = parseFloat(document.getElementById('totalPrice').value);
            
            if (age < 18) {
                alert('Guest must be at least 18 years old.');
                return false;
            }
            
            if (paymentMode === 'GCash' && !reference.trim()) {
                alert('GCash reference number is required.');
                return false;
            }
            
            if (amountPaid < totalPrice) {
                alert('Amount paid cannot be less than total price.');
                return false;
            }
            
            return true;
        }
        
        // Show cancelled bookings details
        function showCancelledBookings() {
            const modal = new bootstrap.Modal(document.getElementById('cancelledBookingsModal'));
            modal.show();
            
            // Load cancelled bookings data
            fetch('get_cancelled_bookings.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('cancelledBookingsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('cancelledBookingsContent').innerHTML = 
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading cancelled bookings.</div>';
                });
        }
        
        // View guest details
        function viewGuestDetails(bookingId) {
            const modal = new bootstrap.Modal(document.getElementById('guestDetailsModal'));
            modal.show();
            
            // Load guest details data
            fetch('get_guest_details.php?booking_id=' + bookingId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('guestDetailsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('guestDetailsContent').innerHTML = 
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading guest details.</div>';
                });
        }
        
        // Export data
        function exportData() {
            alert('Export functionality to be implemented');
        }
        
        function cancelBooking(bookingId, guestName) {
            document.getElementById('bookingIdToCancel').value = bookingId;
            document.getElementById('guestNameToCancel').textContent = guestName;
            new bootstrap.Modal(document.getElementById('cancelBookingModal')).show();
        }
    </script>
</body>
</html>