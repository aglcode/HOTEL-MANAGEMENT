<?php
session_start();
require_once 'database.php';

// ✅ Auto-update booking statuses based on current time
$conn->query("
    UPDATE bookings 
    SET status = 'active'
    WHERE start_date <= NOW()
      AND end_date > NOW()
      AND status NOT IN ('cancelled', 'completed')
");

$conn->query("
    UPDATE bookings 
    SET status = 'completed'
    WHERE end_date <= NOW()
      AND status NOT IN ('cancelled', 'completed')
");

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

// ✅ Auto-update booking statuses based on current time
$conn->query("
    UPDATE bookings 
    SET status = 'active'
    WHERE start_date <= NOW()
      AND end_date > NOW()
      AND status NOT IN ('cancelled', 'completed')
");

$conn->query("
    UPDATE bookings 
    SET status = 'completed'
    WHERE end_date <= NOW()
      AND status NOT IN ('cancelled', 'completed')
");


// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    $cancellation_reason = trim($_POST['cancellation_reason']);
    $cancelled_by = $_SESSION['user_id'] ?? null; // Get the logged-in user's ID
    
    if (empty($cancellation_reason)) {
        $_SESSION['error_msg'] = 'Cancellation reason is required.';
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $cancellation_reason, $cancelled_by, $booking_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = 'Booking has been cancelled successfully.';
        } else {
            $_SESSION['error_msg'] = 'Error cancelling booking.';
        }
        $stmt->close();
    }
    header('Location: receptionist-booking.php?success=cancelled');
    exit();
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_booking'])) {
    $guest_name   = $_POST['guest_name'] ?? '';
    $email        = $_POST['email'] ?? '';
    $address      = $_POST['address'] ?? '';
    $telephone    = $_POST['telephone'] ?? '';
    $age          = isset($_POST['age']) ? (int)$_POST['age'] : 0;
    $num_people   = isset($_POST['num_people']) ? (int)$_POST['num_people'] : 1;
    $room_number  = $_POST['room_number'] ?? '';
    $duration     = $_POST['duration'] ?? '';
    $payment_mode = $_POST['payment_mode'] ?? '';
    $reference    = $_POST['reference_number'] ?? '';
    $amount_paid  = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0;
    $start_date   = $_POST['start_date'] ?? '';
    
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

$conflict_stmt = $conn->prepare("
    SELECT 1 
    FROM bookings 
    WHERE room_number = ? 
      AND status NOT IN ('cancelled', 'completed') 
      AND (
          (? < end_date AND ? > start_date)
        OR (start_date < ? AND end_date > ?)
        OR (? BETWEEN start_date AND end_date)
        OR (? BETWEEN start_date AND end_date)
      )
    LIMIT 1
");

$conflict_stmt->bind_param(
    "sssssss",
    $room_number,
    $start_date, $start_date,
    $end_date, $end_date,
    $start_date, $end_date
);

$conflict_stmt->execute();
$conflict_result = $conflict_stmt->get_result();

// ✅ NEW: Also check for rebooking conflicts in checkins table
$rebooking_conflict_stmt = $conn->prepare("
    SELECT guest_name, check_in_date, check_out_date 
    FROM checkins 
    WHERE room_number = ? 
      AND is_rebooked = 1
      AND (
          (? < check_out_date AND ? > check_in_date)
        OR (check_in_date < ? AND check_out_date > ?)
        OR (? BETWEEN check_in_date AND check_out_date)
        OR (? BETWEEN check_in_date AND check_out_date)
      )
    LIMIT 1
");

$rebooking_conflict_stmt->bind_param(
    "sssssss",
    $room_number,
    $start_date, $start_date,
    $end_date, $end_date,
    $start_date, $end_date
);

$rebooking_conflict_stmt->execute();
$rebooking_result = $rebooking_conflict_stmt->get_result();

if ($conflict_result->num_rows > 0) {
    $_SESSION['error_msg'] = '❌ Room is already booked during the selected time. Please choose a start time after the previous checkout.';
} elseif ($rebooking_result->num_rows > 0) {
    $rebooking_data = $rebooking_result->fetch_assoc();
    $rebook_checkin = date('M d, Y h:i A', strtotime($rebooking_data['check_in_date']));
    $rebook_checkout = date('M d, Y h:i A', strtotime($rebooking_data['check_out_date']));
    
    $_SESSION['error_msg'] = "❌ Room has a rebooking by " . htmlspecialchars($rebooking_data['guest_name']) . 
                            " from {$rebook_checkin} to {$rebook_checkout}. Please select a different time.";
} else {
    // ✅ Safe to insert booking
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

$conflict_stmt->close();
$rebooking_conflict_stmt->close();

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
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="style.css" rel="stylesheet">
<style>
    
/* === Sidebar Navigation === */
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
  cursor: default;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* CLICKABLE CARD EFFECT */
.clickable-card {
  cursor: pointer;
  transition: transform 0.2s;
}
.clickable-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(220, 53, 69, 0.1);
}

/* ICON BOX */
.stat-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-size: 18px;
}

/* TEXT STYLES */
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

/* Smooth hover shadow transition */
.card {
  border: none;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* === Table Styling === */
.table-responsive {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

#bookingTable {
  width: 100% !important;
  margin: 0 auto;
  border-collapse: collapse;
  table-layout: auto;
}

table th {
  background: #f8f9fa;
  color: #333;
  font-weight: 600;
  white-space: nowrap;
  padding: 14px 10px;
}

table td {
  padding: 14px 10px;
  vertical-align: middle;
}

.dataTables_wrapper .dataTables_paginate .pagination {
    margin: 0;
}

.dataTables_wrapper .dataTables_info {
    padding: 0.75rem;
}

.dataTables_wrapper .dataTables_paginate {
    padding-right: 15px; 
}
#bookingList table {
  table-layout: fixed;
  width: 100%;
  font-size: 0.85rem;
}

#bookingList th, 
#bookingList td {
  padding: 8px 8px !important;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Center all columns */
#bookingList th,
#bookingList td {
  text-align: center;
  vertical-align: middle;
}

/* Guest Details — center content and allow wrapping */
#bookingList th:nth-child(2),
#bookingList td:nth-child(2) {
  text-align: center;
  white-space: normal !important; /* allow multiple lines */
  overflow: visible !important;   /* don't clip content */
  word-break: break-word;         /* break long text (like emails) */
}

/* Optional: Make guest info look cleaner */
#bookingList td:nth-child(2) {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  line-height: 1.3;
}

.toast-container .toast {
  min-width: 300px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.toast-body {
  font-size: 15px;
  font-weight: 500;
  padding: 12px 16px;
}

.toast .btn-close {
  padding: 0.5rem;
}

/* Success toast with animation */
#successToast {
  animation: slideInRight 0.4s ease-out;
}

/* Error toast with animation */
#errorToast {
  animation: slideInRight 0.4s ease-out;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
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

/* Prevent overflow from containers */
html, body, .container-fluid, .content, .row, .table-responsive, .dataTables_wrapper {
  max-width: 100%;
  overflow-x: hidden;
}

.row {
  margin-left: 0;
  margin-right: 0;
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

#availability-message .alert {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Adjustments */
@media (max-width: 992px) {
  .content {
    margin-left: 0;
    padding: 15px;
  }

  #bookingTable th,
  #bookingTable td {
    font-size: 0.9rem;
  }

  .table-responsive {
    border-radius: 8px;
  }
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

  <?php include __DIR__ . '/includes/get-notifications.php'; ?>

  <div class="nav-links">
    <a href="receptionist-dash.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-dash.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-gauge"></i> Dashboard
    </a>
<a href="receptionist-room.php" 
   class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-room.php' ? 'active' : ''; ?> position-relative">
  <i class="fa-solid fa-bed"></i> Rooms
  <?php if (!empty($totalNotifications) && $totalNotifications > 0): ?>
    <span class="notification-badge">
      <?= $totalNotifications ?>
    </span>
  <?php endif; ?>
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
        
        
        <!-- STATISTICS CARDS (Admin Style) -->
        <div class="row mb-4">
            <!-- Upcoming Bookings -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Upcoming Bookings</p>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $upcoming_bookings ?></h3>
                    <p class="stat-change text-muted">Next 7 days</p>
                </div>
            </div>

            <!-- Active Bookings -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Active Bookings</p>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $active_bookings ?></h3>
                    <p class="stat-change text-success">+4% <span>from yesterday</span></p>
                </div>
            </div>

            <!-- Total Bookings -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Total Bookings</p>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $total_bookings ?></h3>
                    <p class="stat-change text-success">+5% <span>overall</span></p>
                </div>
            </div>

            <!-- Cancelled Bookings (Clickable) -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3 clickable-card" onclick="showCancelledBookings()">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Cancelled Bookings</p>
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $cancelled_bookings ?></h3>
                    <p class="stat-change text-muted">Click to view details</p>
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
                    <button type="submit" class="btn btn-dark me-2 flex-grow-1">
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
  <div class="card-header d-flex justify-content-between align-items-center p-3" style="background-color: #871D2B;">
    <div>
      <h2 class="h5 mb-0 text-white">
        <i class="fas fa-calendar-check me-2 text-white"></i>Booking List
      </h2>
      <p class="text-sm text-white mt-1"><?= $total_records ?> total bookings</p>
    </div>

    <div class="d-flex align-items-center gap-2">
      <!-- Show Entries -->
      <div id="customBookingLengthMenu"></div>

      <!-- Search -->
      <div class="position-relative">
        <input type="text" class="form-control ps-4" id="bookingSearchInput" placeholder="Search bookings..." style="width: 200px;">
        <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-2 text-gray-400"></i>
      </div>

      <!-- Filter by Status -->
      <select class="form-select" id="bookingFilterSelect" style="width: 150px;">
        <option value="">Filter Status</option>
        <option value="Upcoming">Upcoming</option>
        <option value="Active">Active</option>
        <option value="In Use">In Use</option>
        <option value="Completed">Completed</option>
        <option value="Cancelled">Cancelled</option>
      </select>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="bookingTable" class="table table-hover align-middle mb-0" style="width:100%;">
        <thead class="bg-gray-50 border-bottom border-gray-200">
          <tr>
            <th class="sorting px-4 py-3">#</th>
            <th class="sorting px-4 py-3">Guest Details</th>
            <th class="sorting px-4 py-3">Room Info</th>
            <th class="sorting px-4 py-3">Duration</th>
            <th class="sorting px-4 py-3">Check-in</th>
            <th class="sorting px-4 py-3">Check-out</th>
            <th class="sorting px-4 py-3">Payment</th>
            <th class="sorting px-4 py-3">Token</th>
            <th class="sorting px-4 py-3">Status</th>
            <th class="sorting px-4 py-3 text-center no-print">Actions</th>
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
            SELECT check_in_date, check_out_date, status 
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

        // Decide status based on booking and checkin records
        if ($row['status'] === 'completed') {
            $status_class = "bg-success";
            $status_text = "Completed";
        } elseif ($row['status'] === 'cancelled') {
            $status_class = "bg-danger";
            $status_text = "Cancelled";
        } elseif ($latestCheckin) {
            // This guest has a checkin record for this room
            if ($latestCheckin['status'] === 'checked_in') {
                $status_class = "bg-warning text-dark";
                $status_text = "In Use";
            } elseif ($latestCheckin['status'] === 'checked_out') {
                $status_class = "bg-success";
                $status_text = "Completed";
                // Update booking status to completed
                $updateStatus = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                $updateStatus->bind_param('i', $row['id']);
                $updateStatus->execute();
                $updateStatus->close();
            } else {
                // Status is 'scheduled' - show as upcoming
                $status_class = "bg-info";
                $status_text = "Upcoming";
            }
        } else {
            // No checkin record exists for this specific guest+room combo
            // Use booking dates to determine status
            if ($now < $start) {
                $status_class = "bg-info";
                $status_text = "Upcoming";
            } elseif ($now >= $start && $now <= $end) {
                $status_class = "bg-success";
                $status_text = "Active";
            } elseif ($now > $end) {
                $status_class = "bg-success";
                $status_text = "Completed";
            } else {
                $status_class = "bg-secondary";
                $status_text = ucfirst($row['status']);
            }
        }
?>
                            <tr>
                                 <td><?= $index++ ?></td>
            <td class="text-center align-middle">
            <div class="guest-info">
                <div class="fw-semibold mb-1"><?= htmlspecialchars($row['guest_name']) ?></div>
                <div class="small text-muted"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($row['telephone']) ?></div>
                <?php if (!empty($row['email'])): ?>
                <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($row['email']) ?></div>
                <?php endif; ?>
                <div class="small text-muted"><i class="fas fa-users me-1"></i><?= $row['num_people'] ?> guest(s), Age: <?= $row['age'] ?></div>
            </div>
            </td>

            <td>
              <div class="fw-semibold">Room <?= htmlspecialchars($row['room_number']) ?></div>
              <div class="small text-muted"><?= htmlspecialchars($row['room_type'] ?? 'Standard') ?></div>
            </td>
            <td><span class="badge bg-info"><?= htmlspecialchars($row['duration']) ?> Hours</span></td>
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
              <div class="small text-muted"><?= htmlspecialchars($row['payment_mode']) ?></div>
              <div class="small text-success">Paid: ₱<?= number_format($row['amount_paid'], 2) ?></div>
            </td>
            <td><?= !empty($row['booking_token']) ? htmlspecialchars($row['booking_token']) : '<span class="text-muted">No token</span>' ?></td>
            <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
            <td class="text-center">
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="viewGuestDetails(<?= $row['id'] ?>)" title="View"><i class="fas fa-user"></i></button>
                <?php if ($status_text === 'Upcoming'): ?>
                <button class="btn btn-sm btn-outline-danger" onclick="cancelBooking(<?= $row['id'] ?>, '<?= htmlspecialchars($row['guest_name']) ?>')" title="Cancel"><i class="fas fa-times"></i></button>
                <?php endif; ?>
            </div>
            </td>
          </tr>
          <?php endwhile; } else { ?>
          <tr><td colspan="10" class="text-center py-5">
            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
            <h5>No bookings found</h5>
            <p class="text-muted">Try adjusting your search filters or create a new booking</p>
          </td></tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Booking Modal -->

<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg rounded-4 overflow-hidden">
            <!-- Header -->
            <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);">
                <h5 class="modal-title fw-bold text-dark" id="bookingModalLabel">
                    <i class="fas fa-calendar-plus me-2"></i>Reserve Your Room
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form method="POST" onsubmit="return validateBookingForm();">
                <div class="modal-body p-4" style="background: #f9faff;">
                    <div class="container-fluid">
                        <div class="row g-4">
                            <!-- Guest Information -->
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 rounded-3">
                                    <div class="card-header text-white rounded-top-3" style="background-color: #8b1d2d;">
                                        <h6 class="mb-0 fw-semibold"><i class="fas fa-user me-2"></i>Guest Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="guestName" class="form-label fw-medium">Full Name *</label>
                                            <input type="text" name="guest_name" id="guestName" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email Address *</label>
                                            <input type="email" name="email" id="email_input" class="form-control" required placeholder="your.email@example.com">
                                            <div class="form-text">We'll send your booking confirmation to this email</div>
                                            <div class="invalid-feedback">Please enter a valid email address (e.g., name@example.com).</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="telephone" class="form-label fw-medium">Phone Number *</label>
                                            <input type="text" name="telephone" id="telephone" class="form-control" required placeholder="09XX-XXX-XXXX" maxlength="13">
                                            <small class="text-muted">Enter a valid 11-digit phone number (format: 09XX-XXX-XXXX)</small>
                                            <div class="invalid-feedback">Please enter exactly 11 digits starting with 09.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="address" class="form-label fw-medium">Complete Address *</label>
                                            <input type="text" name="address" id="address" class="form-control" required>
                                        </div>
                                        <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="age" class="form-label fw-medium">Age *</label>
                                            <input type="number" name="age" id="age" class="form-control" required min="18" max="120" placeholder="18">
                                            <small class="text-muted">Must be 18 years or older</small>
                                            <div class="invalid-feedback">You must be at least 18 years old to book.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="numPeople" class="form-label fw-medium">Guests *</label>
                                            <input 
                                                type="number" 
                                                name="num_people" 
                                                id="numPeople" 
                                                class="form-control" 
                                                min="1" 
                                                required 
                                                oninput="validateGuestCount(this);">
                                            <div id="guestError" class="text-danger small mt-1 d-none">Number of guests must be at least 1.</div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Details -->
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 rounded-3">
                                    <div class="card-header text-white rounded-top-3" style="background-color: #8b1d2d;">
                                        <h6 class="mb-0 fw-semibold"><i class="fas fa-calendar-alt me-2"></i>Booking Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
    <label for="roomNumber" class="form-label fw-medium">Select Room *</label>
    <select name="room_number" id="roomNumber" class="form-select" required onchange="updatePrice(); checkRoomAvailability();">
        <option value="">Choose your preferred room</option>
        <?php
        // Show all rooms, even if currently booked, so future booking is allowed
        $room_query = "
            SELECT room_number, room_type, price_3hrs, price_6hrs, price_12hrs, price_24hrs, price_ot, status
            FROM rooms
            ORDER BY room_number ASC
        ";
        $room_result = $conn->query($room_query);

        while ($room = $room_result->fetch_assoc()) {
            // Disable only maintenance rooms
            $disabled = ($room['status'] === 'maintenance') ? 'disabled' : '';
            $label = ucfirst($room['status']);
            $display_text = "Room {$room['room_number']} - {$room['room_type']}";
            
            // Add status indicator for currently booked rooms
            if ($room['status'] === 'booked') {
                $display_text .= " (Currently Booked)";
            }
            
            echo "<option value='{$room['room_number']}' 
                    data-price3='{$room['price_3hrs']}'
                    data-price6='{$room['price_6hrs']}'
                    data-price12='{$room['price_12hrs']}'
                    data-price24='{$room['price_24hrs']}'
                    data-priceOt='{$room['price_ot']}'
                    data-status='{$room['status']}'
                    $disabled>
                    $display_text
                  </option>";
        }
        ?>
    </select>
</div>

<!-- Availability message display area -->
<div id="availability-message" class="mb-3"></div>

                                        <div class="mb-3">
                                            <label for="duration" class="form-label fw-medium">Stay Duration *</label>
                                            <select name="duration" id="duration" class="form-select" required>
                                                <option value="3">3 Hours</option>
                                                <option value="6">6 Hours</option>
                                                <option value="12">12 Hours</option>
                                                <option value="24">24 Hours</option>
                                                <option value="48">48 Hours</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="startDate" class="form-label fw-medium">Check-in Date & Time *</label>
                                            <input type="datetime-local" name="start_date" id="startDate" class="form-control" required min="">
                                        </div>
                                        <div class="mb-3">
                                            <label for="endDate" class="form-label fw-medium">Estimated Check-out *</label>
                                            <input type="text" id="endDate" class="form-control bg-light" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label for="totalPrice" class="form-label fw-medium">Total Price</label>
                                            <input type="text" id="totalPrice" class="form-control bg-light fw-semibold" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="card shadow-sm border-0 mt-4 rounded-3">
                        <div class="card-header text-white rounded-top-3" style="background: linear-gradient(135deg, #6a1520 0%, #8b1d2d 100%);">
                                <h6 class="mb-0 fw-semibold">
                                    <i class="fas fa-credit-card me-2"></i>Payment Information
                                </h6>
                            </div>
                            <div class="card-body bg-white rounded-bottom">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label for="paymentMode" class="form-label fw-medium">Payment Method *</label>
                                        <select name="payment_mode" id="paymentMode" class="form-select" required onchange="togglePaymentFields();">
                                            <option value="">Select payment method</option>
                                            <option value="Cash">💵 Cash Payment</option>
                                            <option value="GCash">📱 GCash</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="amountPaid" class="form-label fw-medium">Amount to Pay *</label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="background: linear-gradient(135deg, #6a1520 0%, #8b1d2d 100%); color: #fff;">₱</span>
                                            <input type="number" name="amount_paid" id="amountPaid" class="form-control" min="0" step="0.01" required oninput="calculateChange();">
                                        </div>
                                    </div>
                                </div>

                                <!-- GCash Section -->
                                <div id="gcashSection" class="row mt-4" style="display: none;">
                                    <div class="col-md-8">
                                        <div class="p-3 rounded-3" style="background: #f0f4ff;">
                                            <strong class="d-block mb-2"><i class="fas fa-info-circle me-2"></i>GCash Payment Instructions:</strong>
                                            <ol class="mb-0 ps-3 text-dark">
                                                <li>Send your payment to the GCash number provided</li>
                                                <li>Take a screenshot of the transaction</li>
                                                <li>Enter the 13-digit reference number below</li>
                                            </ol>
                                            <div class="mt-3">
                                                <label for="referenceNumber" class="form-label fw-medium">GCash Reference Number *</label>
                                                <input 
                                                    type="text" 
                                                    name="reference_number" 
                                                    id="referenceNumber" 
                                                    class="form-control" 
                                                    placeholder="Enter 13-digit reference number" 
                                                    maxlength="13"
                                                    oninput="limitReferenceNumber(this); validateReferenceNumber();">
                                                
                                                <small class="text-muted">Found in your GCash transaction receipt</small>

                                                <!-- Error messages -->
                                                <div id="refErrorEmpty" class="text-danger small mt-1 d-none">Please enter the GCash reference number.</div>
                                                <div id="refErrorDigit" class="text-danger small mt-1 d-none">Reference number must contain digits only.</div>
                                                <div id="refErrorLength" class="text-danger small mt-1 d-none">Reference number must be exactly 13 digits.</div>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-stretch">
                                        <div class="text-center p-3 rounded-3 shadow-sm" style="background: linear-gradient(135deg, #e0e7ff 0%, #fbc2eb 100%);">
                                            <div class="fw-bold mb-2" style="color: #0063F7; font-size: 1.2rem;">
                                                <i class="fab fa-google-pay"></i> G<span style="color:#0063F7;">Pay</span>
                                            </div>
                                            <p class="mb-1 text-dark">GCash Number:</p>
                                            <h5 class="fw-bold text-primary mb-2">09123456789</h5>
                                            <p class="mb-1 text-dark">Account Name:</p>
                                            <p class="fw-semibold text-primary mb-0">Gitarra Apartelle</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cash Section -->
                                <div class="row mt-3" id="cashSection" style="display: none;">
                                    <div class="col-md-6">
                                        <label for="changeAmount" class="form-label fw-medium">Change</label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="background: linear-gradient(135deg, #6a1520 0%, #8b1d2d 100%); color: #fff;">₱</span>
                                            <input type="text" id="changeAmount" class="form-control" readonly value="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary rounded-pill px-4" style="background: linear-gradient(135deg, #6a1520 0%, #8b1d2d 100%); border: none; font-weight:600;">
                        <i class="fas fa-calendar-check me-2"></i>Confirm & Reserve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Cancel Booking Modal (complete) -->
<div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
<form method="POST" id="cancelBookingForm">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="cancelBookingModalLabel"><i class="fas fa-times-circle me-2"></i>Cancel Booking</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="booking_id" id="bookingIdToCancel" value="">
          <div class="mb-3">
            <label class="form-label fw-medium">Guest</label>
            <div class="fw-semibold" id="guestNameToCancel">—</div>
          </div>

          <div class="mb-3">
            <label for="cancellationReason" class="form-label fw-medium">Cancellation Reason *</label>
            <textarea name="cancellation_reason" id="cancellationReason" class="form-control" rows="4" placeholder="Reason for cancellation" required></textarea>
          </div>

          <div class="alert alert-warning small">
            Cancelling a booking will set its status to <strong>Cancelled</strong>. This action can be reviewed in the Cancelled Bookings panel.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <!-- name="delete_booking" is how your PHP block detects a cancellation POST -->
          <button type="submit" name="delete_booking" class="btn btn-danger">Confirm Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- End Cancel Booking Modal -->

<!-- Toast container for All Notifications -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
  <!-- Success Toast -->
  <?php if (isset($_SESSION['success_msg'])): ?>
    <div id="successToast" class="toast align-items-center text-bg-success border-0 fade" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          <?= htmlspecialchars($_SESSION['success_msg']) ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
    <?php unset($_SESSION['success_msg']); ?>
  <?php endif; ?>

  <!-- Error Toast -->
  <?php if (isset($_SESSION['error_msg'])): ?>
    <div id="errorToast" class="toast align-items-center text-bg-danger border-0 fade" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-exclamation-circle me-2"></i>
          <?= htmlspecialchars($_SESSION['error_msg']) ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
    <?php unset($_SESSION['error_msg']); ?>
  <?php endif; ?>

  <!-- Cancellation Toast -->
  <?php if (isset($_GET['success']) && $_GET['success'] === 'cancelled'): ?>
    <div id="cancelSuccessToast" class="toast align-items-center text-bg-success border-0 fade" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          Booking cancelled successfully!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>

// Initialize and show all toasts
document.addEventListener("DOMContentLoaded", function () {
  // Show all toasts with appropriate delays
  const toasts = [
    { id: 'successToast', delay: 5000 },
    { id: 'errorToast', delay: 7000 },
    { id: 'cancelSuccessToast', delay: 5000 }
  ];

  toasts.forEach(item => {
    const toastEl = document.getElementById(item.id);
    if (toastEl) {
      const toast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: item.delay
      });
      toast.show();
    }
  });
});


//sweetalert2
document.getElementById("cancelBookingForm").addEventListener("submit", function(e) {
  e.preventDefault();
  
  Swal.fire({
    title: "Cancel Booking?",
    text: "This action will set the booking to Cancelled.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Yes, cancel it!"
  }).then((result) => {
    if (result.isConfirmed) {
      // Create a new FormData and submit directly
      const formData = new FormData(this);
      
      // Make sure delete_booking is included
      if (!formData.has('delete_booking')) {
        formData.append('delete_booking', '1');
      }
      
      // Submit via fetch to avoid form validation
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      }).then(() => {
        window.location.href = 'receptionist-booking.php?success=cancelled';
      });
    }
  });
});


// Store booked schedules for each room from BOOKINGS table (including ALL future bookings)
const roomSchedules = <?php
    $schedule_query = "SELECT room_number, start_date, end_date, status 
                       FROM bookings 
                       WHERE status NOT IN ('cancelled', 'completed')
                       AND end_date > NOW()";
    $schedule_result = $conn->query($schedule_query);
    $schedules = [];
    while ($schedule = $schedule_result->fetch_assoc()) {
        $room_num = $schedule['room_number'];
        if (!isset($schedules[$room_num])) {
            $schedules[$room_num] = [];
        }
        $schedules[$room_num][] = [
            'start_date' => $schedule['start_date'],
            'end_date' => $schedule['end_date'],
            'status' => $schedule['status']
        ];
    }
    echo json_encode($schedules);
?>;

// ✅ UPDATED: Include ALL check-ins including rebookings (checked_out with is_rebooked = 1)
const checkinSchedules = <?php
    $checkin_query = "SELECT room_number, check_in_date, check_out_date, status, is_rebooked, guest_name
                      FROM checkins 
                      WHERE (status IN ('scheduled', 'checked_in'))
                      OR (status = 'checked_out' AND is_rebooked = 1)
                      AND check_out_date > NOW()";
    $checkin_result = $conn->query($checkin_query);
    $checkin_data = [];
    while ($checkin = $checkin_result->fetch_assoc()) {
        $room_num = $checkin['room_number'];
        if (!isset($checkin_data[$room_num])) {
            $checkin_data[$room_num] = [];
        }
        $checkin_data[$room_num][] = [
            'start_date' => $checkin['check_in_date'],
            'end_date' => $checkin['check_out_date'],
            'status' => $checkin['status'],
            'is_rebooked' => $checkin['is_rebooked'],
            'guest_name' => $checkin['guest_name']
        ];
    }
    echo json_encode($checkin_data);
?>;

function checkRoomAvailability() {
    const roomSelect = document.getElementById('roomNumber');
    const checkinInput = document.getElementById('startDate');
    const durationSelect = document.getElementById('duration');
    const messageDiv = document.getElementById('availability-message');
    
    const roomNumber = roomSelect?.value;
    const checkinTime = checkinInput?.value;
    const durationValue = durationSelect?.value;
    
    if (!roomNumber || !checkinTime || !durationValue) {
        messageDiv.innerHTML = '';
        if (checkinInput) checkinInput.setCustomValidity('');
        return true;
    }
    
    const duration = parseInt(durationValue);
    const selectedCheckin = new Date(checkinTime);
    const selectedCheckout = new Date(selectedCheckin.getTime() + duration * 60 * 60 * 1000);
    
    // ✅ Combine both bookings and check-ins schedules (including rebookings)
    const bookingsList = roomSchedules[roomNumber] || [];
    const checkinsList = checkinSchedules[roomNumber] || [];
    const allSchedules = [...bookingsList, ...checkinsList];
    
    let isAvailable = true;
    let conflictSchedule = null;
    let conflictType = null;
    
    for (let schedule of allSchedules) {
        const bookedCheckin = new Date(schedule.start_date);
        const bookedCheckout = new Date(schedule.end_date);
        
        // Check for overlap
        if (selectedCheckin < bookedCheckout && selectedCheckout > bookedCheckin) {
            isAvailable = false;
            conflictSchedule = schedule;
            // ✅ Determine if it's a rebooking conflict
            conflictType = schedule.is_rebooked ? 'rebooking' : 'booking';
            break;
        }
    }
    
    if (isAvailable) {
        messageDiv.innerHTML = `
            <div class="alert alert-success border-0 shadow-sm" style="background-color: #d4edda; border-radius: 12px;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 1.2rem;"></i>
                    <span class="text-success fw-medium">✅ Room is available for selected time period</span>
                </div>
            </div>`;
        if (checkinInput) checkinInput.setCustomValidity('');
        return true;
    } else {
        const bookedCheckin = new Date(conflictSchedule.start_date);
        const bookedCheckout = new Date(conflictSchedule.end_date);
        
        const formatOptions = {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        
        const formattedCheckin = bookedCheckin.toLocaleString('en-US', formatOptions);
        const formattedCheckout = bookedCheckout.toLocaleString('en-US', formatOptions);
        
        // ✅ Different message for rebooking conflicts
        const conflictMessage = conflictType === 'rebooking' 
            ? `This room has a <strong>rebooking</strong> by <strong>${conflictSchedule.guest_name || 'a guest'}</strong>`
            : 'This room is booked';
        
        messageDiv.innerHTML = `
            <div class="alert alert-danger border-0 shadow-sm" style="background-color: #f8d7da; border-radius: 12px;">
                <div class="mb-2">
                    <strong class="text-danger" style="font-size: 1.1rem;">❌ Room Not Available</strong>
                </div>
                <p class="mb-0 text-danger">
                    ${conflictMessage} from <strong>${formattedCheckin}</strong> to <strong>${formattedCheckout}</strong>.<br>
                    Please select a different time or room.
                </p>
            </div>`;
        
        if (checkinInput) checkinInput.setCustomValidity('This time slot conflicts with an existing booking or rebooking');
        return false;
    }
}

// Add event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    const roomSelect = document.getElementById('roomNumber');
    const checkinInput = document.getElementById('startDate');
    const durationSelect = document.getElementById('duration');
    const bookingModal = document.getElementById('bookingModal');
    
    if (roomSelect) roomSelect.addEventListener('change', checkRoomAvailability);
    if (checkinInput) checkinInput.addEventListener('change', checkRoomAvailability);
    if (durationSelect) durationSelect.addEventListener('change', checkRoomAvailability);
    
    // Prevent form submission if there's a conflict
    if (bookingModal) {
        const bookingForm = bookingModal.querySelector('form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(e) {
                const isAvailable = checkRoomAvailability();
                
                if (!isAvailable) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const messageDiv = document.getElementById('availability-message');
                    if (messageDiv) {
                        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    setTimeout(() => {
                        alert('⚠️ Cannot proceed with booking!\n\nThis room is not available for the selected time period (including rebookings). Please choose a different time or room.');
                    }, 300);
                    
                    return false;
                }
            });
        }
    }
});

// Real-time email validation
const emailInput = document.querySelector('input[name="email"]');
if (emailInput) {
    // Email validation regex pattern
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    emailInput.addEventListener('input', function() {
        const email = this.value.trim();
        
        if (email.length > 0 && !emailPattern.test(email)) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        
        if (email.length > 0 && !emailPattern.test(email)) {
            this.classList.add('is-invalid');
        }
    });
    
    // Add form submission validation
    const form = emailInput.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const email = emailInput.value.trim();
            
            if (!emailPattern.test(email)) {
                e.preventDefault();
                emailInput.classList.add('is-invalid');
                emailInput.focus();
            }
        });
    }
}

// Phone number validation and auto-formatting
const phoneInput = document.querySelector('input[name="telephone"]');
if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
        // Remove all non-digit characters
        let value = this.value.replace(/\D/g, '');
        
        // Limit to 11 digits
        if (value.length > 11) {
            value = value.slice(0, 11);
        }
        
        // Auto-format to 09XX-XXX-XXXX
        if (value.length >= 4) {
            value = value.slice(0, 4) + '-' + value.slice(4);
        }
        if (value.length >= 8) {
            value = value.slice(0, 8) + '-' + value.slice(8);
        }
        
        this.value = value;
        
        // Validation: must be exactly 11 digits and start with 09
        const digitsOnly = value.replace(/\D/g, '');
        if (digitsOnly.length === 11 && digitsOnly.startsWith('09')) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else if (digitsOnly.length > 0) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        } else {
            this.classList.remove('is-invalid');
            this.classList.remove('is-valid');
        }
    });
    
    phoneInput.addEventListener('blur', function() {
        const digitsOnly = this.value.replace(/\D/g, '');
        
        if (digitsOnly.length > 0 && (digitsOnly.length !== 11 || !digitsOnly.startsWith('09'))) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    });
    
    // Form submission validation
    const form = phoneInput.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const digitsOnly = phoneInput.value.replace(/\D/g, '');
            
            if (digitsOnly.length !== 11 || !digitsOnly.startsWith('09')) {
                e.preventDefault();
                phoneInput.classList.add('is-invalid');
                phoneInput.classList.remove('is-valid');
                phoneInput.focus();
            }
        });
    }
}
// Age validation (18+)
const ageInput = document.querySelector('input[name="age"]');
if (ageInput) {
    ageInput.addEventListener('input', function() {
        const age = parseInt(this.value);
        
        // Remove invalid class when empty
        if (this.value === '') {
            this.classList.remove('is-invalid');
            this.classList.remove('is-valid');
            return;
        }
        
        // Validate age
        if (age < 18 || age > 120 || isNaN(age)) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });
    
    ageInput.addEventListener('blur', function() {
        const age = parseInt(this.value);
        
        if (this.value !== '' && (age < 18 || age > 120 || isNaN(age))) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    });
    
    // Prevent typing negative numbers and decimals
    ageInput.addEventListener('keydown', function(e) {
        // Prevent minus sign, plus sign, decimal point, and 'e'
        if (e.key === '-' || e.key === '+' || e.key === '.' || e.key === 'e' || e.key === 'E') {
            e.preventDefault();
        }
    });
    
    // Form submission validation
    const form = ageInput.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const age = parseInt(ageInput.value);
            
            if (age < 18 || age > 120 || isNaN(age) || ageInput.value === '') {
                e.preventDefault();
                ageInput.classList.add('is-invalid');
                ageInput.classList.remove('is-valid');
                ageInput.focus();
                
                // Show alert for better UX
                alert('You must be at least 18 years old to make a booking.');
            }
        });
    }
}
        // GCASH REFERENCE VALIDATION
        function limitReferenceNumber(input) {
    input.value = input.value.replace(/\D/g, ''); // allow digits only
    if (input.value.length > 13) input.value = input.value.slice(0, 13);
}

        function validateReferenceNumber() {
            const input = document.getElementById("referenceNumber");
            const value = input.value.trim();

            const errorEmpty = document.getElementById("refErrorEmpty");
            const errorDigit = document.getElementById("refErrorDigit");
            const errorLength = document.getElementById("refErrorLength");

            // Hide all error messages initially
            errorEmpty.classList.add("d-none");
            errorDigit.classList.add("d-none");
            errorLength.classList.add("d-none");
            input.classList.remove("is-invalid");

            if (value === "") {
                errorEmpty.classList.remove("d-none");
                input.classList.add("is-invalid");
                return false;
            }

            const onlyDigits = /^[0-9]+$/;
            if (!onlyDigits.test(value)) {
                errorDigit.classList.remove("d-none");
                input.classList.add("is-invalid");
                return false;
            }

            if (value.length !== 13) {
                errorLength.classList.remove("d-none");
                input.classList.add("is-invalid");
                return false;
            }

            input.classList.remove("is-invalid");
            return true;
        }

        function validateGuestCount(input) {
    const error = document.getElementById("guestError");

    if (input.value === "" || Number(input.value) < 1) {
        input.classList.add("is-invalid");
        error.classList.remove("d-none");
        input.value = ""; // clear invalid input
    } else {
        input.classList.remove("is-invalid");
        error.classList.add("d-none");
    }
}

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

        async function updateRoomAvailability() {
        const roomNumber = document.getElementById("roomNumber").value;
        const startDateInput = document.getElementById("startDate");
        if (!roomNumber) return;

        try {
            const response = await fetch(`get_last_checkout.php?room_number=${roomNumber}`);
            const data = await response.json();

            if (data.last_checkout) {
                // If the room has a future booking, force start after that time
                const minDate = new Date(data.last_checkout);
                minDate.setMinutes(minDate.getMinutes() - minDate.getTimezoneOffset());
                startDateInput.min = minDate.toISOString().slice(0, 16);
            } else {
                // Default to now
                disablePastDateTime();
            }
        } catch (err) {
            console.error("Error fetching last checkout:", err);
            disablePastDateTime();
        }
    }
    document.getElementById("roomNumber").addEventListener("change", updateRoomAvailability);

    document.addEventListener('DOMContentLoaded', function() {
  const roomSelect = document.getElementById('roomNumber');
  if (!roomSelect) return;

  // Loop through each room option
  for (let option of roomSelect.options) {
    const isOccupied = option.dataset.occupied === '1' || option.dataset.status === 'booked';
    if (isOccupied) {
      // Optional: Add a visual indicator (different color/style)
      option.style.color = '#dc3545'; // Red color for occupied rooms
      option.style.fontWeight = '500';
    }
  }
});


    // --- NEW: Auto update estimated checkout ---
    function updateCheckoutTime() {
        const startDateInput = document.getElementById('startDate');
        const durationSelect = document.querySelector('select[name="duration"]');
        const endDateInput = document.getElementById('endDate');

        const startDate = new Date(startDateInput.value);
        const duration = parseInt(durationSelect.value);

        if (!isNaN(startDate.getTime()) && duration > 0) {
            const endDate = new Date(startDate.getTime() + duration * 60 * 60 * 1000);
            endDateInput.value = endDate.toLocaleString('en-PH', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit', 
                hour12: true 
            });
        } else {
            endDateInput.value = '';
        }
    }

        function updatePrice() {
            const roomSelect = document.getElementById('roomNumber');
            const durationSelect = document.getElementById('duration');
            const priceInput = document.getElementById('totalPrice');

            if (!roomSelect.value || !durationSelect.value) {
                priceInput.value = '';
                return;
            }

            const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
            const duration = durationSelect.value;
            let price = 0;

            switch (duration) {
                case '3':  price = selectedRoom.dataset.price3; break;
                case '6':  price = selectedRoom.dataset.price6; break;
                case '12': price = selectedRoom.dataset.price12; break;
                case '24': price = selectedRoom.dataset.price24; break;
                case '48': price = selectedRoom.dataset.priceOt; break;
                default:   price = 0;
            }

            price = parseFloat(price) || 0;
            priceInput.value = price.toFixed(2);
            calculateChange();
            updateCheckoutTime();
        }

    // --- Calculate change ---
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

    // --- Toggle payment fields ---
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

    // --- Form validation ---
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

    // --- Show cancelled bookings ---
    function showCancelledBookings() {
        const modal = new bootstrap.Modal(document.getElementById('cancelledBookingsModal'));
        modal.show();
        
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

    // --- View guest details ---
    function viewGuestDetails(bookingId) {
        const modal = new bootstrap.Modal(document.getElementById('guestDetailsModal'));
        modal.show();
        
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

    // --- Export data ---
    function exportData() {
        alert('Export functionality to be implemented');
    }

    // --- Cancel booking ---
    function cancelBooking(bookingId, guestName) {
        // Prevent opening modal for cancelled bookings
        const row = event.target.closest('tr');
        const statusBadge = row.querySelector('.badge');
        if (statusBadge && statusBadge.textContent.trim() === 'Cancelled') {
            alert('This booking is already cancelled.');
            return;
        }
        
        document.getElementById('bookingIdToCancel').value = bookingId;
        document.getElementById('guestNameToCancel').textContent = guestName;
        new bootstrap.Modal(document.getElementById('cancelBookingModal')).show();
    }

    // --- Bind event listeners ---
    document.addEventListener('DOMContentLoaded', () => {
        const startDateInput = document.getElementById('startDate');
        const durationSelect = document.querySelector('select[name="duration"]');
        const roomSelect = document.querySelector('select[name="room_number"]');

        if (startDateInput && durationSelect) {
            startDateInput.addEventListener('change', updateCheckoutTime);
            durationSelect.addEventListener('change', updateCheckoutTime);
        }

        if (roomSelect && durationSelect) {
            roomSelect.addEventListener('change', updatePrice);
            durationSelect.addEventListener('change', updatePrice);
        }
    });

    // DATA TABLES
// ✅ Ensure jQuery and DataTables JS are loaded before this
$(document).ready(function () {
  var bookingTable = $('#bookingTable').DataTable({
    paging: true,
    lengthChange: false,
    searching: false,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: true,
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50, 100],

    // ✅ Improved DOM layout (pagination now visible & aligned)
    dom:
      '<"top d-flex justify-content-between align-items-center mb-3"lf>' +
      'rt' +
      '<"bottom d-flex justify-content-between align-items-center mt-3"ip>',

    language: {
      emptyTable:
        "<div class='text-center py-4'>" +
        "<i class='fas fa-calendar-times fa-3x text-muted mb-2'></i><br>" +
        "<span>No bookings found</span></div>",
      info: "Showing _START_ to _END_ of _TOTAL_ bookings",
      infoEmpty: "No entries available",
      infoFiltered: "(filtered from _MAX_ total bookings)",
      paginate: {
        first: "«",
        last: "»",
        next: "›",
        previous: "‹",
      },
    },
  });

  // ✅ Move built-in length dropdown into your custom area
  bookingTable.on('init', function () {
    var lengthSelect = $('#bookingTable_length select')
      .addClass('form-select form-select-sm')
      .css('width', '80px');

    // Add dropdown to your custom menu container
    $('#customBookingLengthMenu').html(
      '<label class="d-flex align-items-center gap-2 mb-0">' +
        '<span>Show</span>' +
        lengthSelect.prop('outerHTML') +
        '<span>bookings</span>' +
      '</label>'
    );

    // Hide default DataTables length control to avoid duplication
    $('#bookingTable_length').hide();
  });


  // ✅ Filter by status (column index 8)
  $('#bookingFilterSelect').on('change', function () {
    bookingTable.column(8).search(this.value).draw();
  });
});


// --- Disable past date/time ---
function disablePastDateTime() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset()); // fix timezone
    const formatted = now.toISOString().slice(0, 16);
    document.getElementById("startDate").min = formatted;
}
disablePastDateTime();

</script>

</body>
</html>