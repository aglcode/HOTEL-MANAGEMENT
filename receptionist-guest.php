<?php
session_start();
require_once 'database.php';

// ✅ AUTO-UPDATE STATUS BASED ON TIME (runs on every page load)
// Update scheduled → checked_in when check-in time passes
$conn->query("
    UPDATE checkins 
    SET status = 'checked_in' 
    WHERE status = 'scheduled' 
    AND check_in_date <= NOW()
");

// Update checked_in → checked_out when check-out time passes
$conn->query("
    UPDATE checkins 
    SET status = 'checked_out' 
    WHERE status = 'checked_in' 
    AND check_out_date <= NOW()
");

// Also make rooms available when guests are auto-checked-out
$conn->query("
    UPDATE rooms r
    INNER JOIN checkins c ON r.room_number = c.room_number
    SET r.status = 'available'
    WHERE c.status = 'checked_out' 
    AND c.check_out_date <= NOW()
    AND r.status = 'occupied'
");

// Expire keycards for checked-out guests
$conn->query("
    UPDATE keycards k
    INNER JOIN checkins c ON k.room_number = c.room_number
    SET k.status = 'expired'
    WHERE c.status = 'checked_out' 
    AND c.check_out_date <= NOW()
    AND k.status = 'active'
");

// Handle AJAX requests for check-out and extend functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $guest_id = (int)($_POST['guest_id'] ?? 0);
    $action = $_POST['action'];
    
// ✅ CHECKOUT PROCESS
if ($action === 'checkout' && $guest_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM checkins WHERE id = ?");
    $stmt->bind_param('i', $guest_id);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($guest) {
        $total_cost = floatval($guest['total_price']);
        $amount_paid = floatval($guest['amount_paid']);
        $balance = $total_cost - $amount_paid;

        if ($balance > 0) {
            echo json_encode([
                'success' => false,
                'payment_required' => true,
                'message' => 'Additional payment required',
                'guest_id' => $guest_id,
                'guest_name' => $guest['guest_name'],
                'room_number' => $guest['room_number'],
                'room_type' => $guest['room_type'] ?? '',
                'total_cost' => number_format($total_cost, 2),
                'amount_paid' => number_format($amount_paid, 2),
                'balance' => number_format($balance, 2),
                'balance_raw' => $balance
            ]);
            exit;
        }

        // ✅ Mark guest checked out
        $stmt = $conn->prepare("
            UPDATE checkins
            SET status = 'checked_out', check_out_date = NOW()
            WHERE id = ? AND status IN ('checked_in', 'scheduled')
        ");
        $stmt->bind_param('i', $guest_id);
        $stmt->execute();
        $stmt->close();

        // ✅ Make room available
        $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE room_number = ?");
        $stmt->bind_param('i', $guest['room_number']);
        $stmt->execute();
        $stmt->close();

        // ✅ Expire keycard
        $stmt = $conn->prepare("UPDATE keycards SET status='expired' WHERE room_number = ? AND status='active'");
        $stmt->bind_param('i', $guest['room_number']);
        $stmt->execute();
        $stmt->close();

        // ✅ Complete related booking
        $bkSel = $conn->prepare("
            SELECT id FROM bookings 
            WHERE guest_name = ? AND room_number = ? 
            AND status NOT IN ('cancelled','completed')
            ORDER BY start_date DESC LIMIT 1
        ");
        $bkSel->bind_param('si', $guest['guest_name'], $guest['room_number']);
        $bkSel->execute();
        $bkRes = $bkSel->get_result();
        if ($bkRes && $bkRow = $bkRes->fetch_assoc()) {
            $bkUpd = $conn->prepare("UPDATE bookings SET status='completed' WHERE id=?");
            $bkUpd->bind_param('i', $bkRow['id']);
            $bkUpd->execute();
            $bkUpd->close();
        }
        $bkSel->close();

        // ✅ 🧹 DELETE all orders (pending or served) for that room
        $room_number_str = (string)$guest['room_number']; // orders.room_number is VARCHAR(10)
        $del = $conn->prepare("
            DELETE FROM orders 
            WHERE room_number = ? 
            AND status IN ('pending','served')
        ");
        $del->bind_param('s', $room_number_str);
        $del->execute();
        $deleted_orders = $del->affected_rows;
        $del->close();

        // ✅ Return success JSON
        echo json_encode([
            'success' => true,
            'message' => 'Guest checked out successfully. ' . $deleted_orders . ' order(s) deleted.',
            'deleted_orders' => $deleted_orders
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Guest not found or already checked out']);
    }
    exit;
}




if ($action === 'add_payment' && $guest_id > 0) {
    $additional_amount = floatval($_POST['additional_amount'] ?? 0);
    $payment_mode = $_POST['payment_mode'] ?? 'cash';
    $gcash_reference = $_POST['gcash_reference'] ?? '';
    $replace_flag = isset($_POST['replace']) && $_POST['replace'] == '1';

    if ($additional_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
        exit;
    }

    // Fetch current guest data
    $stmt = $conn->prepare("SELECT total_price, amount_paid FROM checkins WHERE id = ?");
    $stmt->bind_param('i', $guest_id);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$guest) {
        echo json_encode(['success' => false, 'message' => 'Guest not found']);
        exit;
    }

    $total_price = floatval($guest['total_price']);
    $previous_paid = floatval($guest['amount_paid']);

    // ✅ If replace flag true (first payment), overwrite. Otherwise, ADD to previous.
    if ($replace_flag) {
        $new_total_paid = $additional_amount;
    } else {
        $new_total_paid = $previous_paid + $additional_amount;
    }

    // ✅ Compute change or due
    $balance = $total_price - $new_total_paid;
    $new_change = $balance < 0 ? abs($balance) : 0;
    $new_due = $balance > 0 ? $balance : 0;

    // ✅ Update record
    $stmt = $conn->prepare("
        UPDATE checkins
        SET amount_paid = ?, change_amount = ?, payment_mode = ?, gcash_reference = ?
        WHERE id = ?
    ");
    $stmt->bind_param('ddssi', $new_total_paid, $new_change, $payment_mode, $gcash_reference, $guest_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Log payment
    $stmtp = $conn->prepare("
        INSERT INTO payments (payment_date, amount, payment_mode)
        VALUES (NOW(), ?, ?)
    ");
    $stmtp->bind_param('ds', $additional_amount, $payment_mode);
    $stmtp->execute();
    $stmtp->close();

    // ✅ Response
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'new_amount_paid' => number_format($new_total_paid, 2),
        'change_amount' => number_format($new_change, 2),
        'due_amount' => number_format($new_due, 2),
        'can_checkout' => $new_due <= 0
    ]);
    exit;
}








// ✅ EXTEND STAY (Fixed ₱120 fee)
if ($action === 'extend' && $guest_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, r.room_type 
        FROM checkins c 
        JOIN rooms r ON c.room_number = r.room_number
        WHERE c.id = ? AND c.status='checked_in'
    ");
    $stmt->bind_param('i', $guest_id);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($guest) {
        // Fixed extension fee ₱120
        $extension_fee = 120;

        // Extend checkout by 1 hour
        $current_checkout = new DateTime($guest['check_out_date']);
        $new_checkout = $current_checkout->modify('+1 hour');
        $new_checkout_str = $new_checkout->format('Y-m-d H:i:s');

        $new_total = $guest['total_price'] + $extension_fee;

        $stmt = $conn->prepare("
            UPDATE checkins 
            SET check_out_date=?, total_price=?, stay_duration=stay_duration+1 
            WHERE id=?
        ");
        $stmt->bind_param('sdi', $new_checkout_str, $new_total, $guest_id);
        $stmt->execute();
        $stmt->close();

        // Extend keycard
        $stmt_k = $conn->prepare("
            UPDATE keycards 
            SET valid_to=?, status='active' 
            WHERE room_number=? ORDER BY id DESC LIMIT 1
        ");
        $stmt_k->bind_param('si', $new_checkout_str, $guest['room_number']);
        $stmt_k->execute();
        $stmt_k->close();

        echo json_encode([
            'success' => true,
            'message' => 'Stay extended by 1 hour',
            'new_checkout' => $new_checkout->format('M j, Y g:i A'),
            'additional_cost' => number_format($extension_fee, 2),
            'new_total' => number_format($new_total, 2),
            'payment_required' => true,
            'payment_details' => [
                'guest_id' => (int)$guest_id,
                'guest_name' => $guest['guest_name'],
                'room_number' => $guest['room_number'],
                'room_type' => $guest['room_type'] ?? '',
                'amount_due' => number_format($extension_fee, 2),
                'amount_due_raw' => $extension_fee
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Guest not found or already checked out']);
    }
    exit;
}


// ✅ CHECK PAYMENT STATUS (used by receptionist-room)
if ($action === 'check_payment_status' && isset($_POST['room_number'])) {
    $room_number = intval($_POST['room_number']);
    $stmt = $conn->prepare("
        SELECT id, guest_name, total_price, amount_paid
        FROM checkins
        WHERE room_number = ? AND status='checked_in'
        ORDER BY check_in_date DESC LIMIT 1
    ");
    $stmt->bind_param('i', $room_number);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($guest) {
        $balance = floatval($guest['total_price']) - floatval($guest['amount_paid']);
        if ($balance > 0) {
            echo json_encode([
                'payment_required' => true,
                'guest_id' => $guest['id'],
                'guest_name' => $guest['guest_name'],
                'room_number' => $room_number,
                'amount_due' => number_format($balance, 2)
            ]);
        } else {
            echo json_encode(['payment_required' => false]);
        }
    } else {
        echo json_encode(['payment_required' => false]);
    }
    exit;
}
}


// Total Bookings Count
$total_bookings_result = $conn->query("SELECT COUNT(*) AS total FROM checkins");
$total_bookings_row = $total_bookings_result->fetch_assoc();
$total_bookings = $total_bookings_row['total'];

// Total Check-in Guests Count (now uses status column)
$checkin_count_result = $conn->query("SELECT COUNT(*) AS total FROM checkins WHERE status = 'checked_in'");
$checkin_count_row = $checkin_count_result->fetch_assoc();
$currently_checked_in = $checkin_count_row['total'];



// Fetch currently checked-in guests (updated to use status)
$current_guests = $conn->query("SELECT 
    id,
    guest_name, 
    address, 
    telephone, 
    room_number, 
    room_type, 
    stay_duration,
    check_in_date, 
    check_out_date,
    total_price,
    amount_paid,
    change_amount,
    payment_mode,
    gcash_reference,
    status
FROM checkins
WHERE status IN ('checked_in', 'scheduled')
ORDER BY check_in_date DESC");

// Guest Check-in History - Updated SQL query to use checkins table
$search = isset($_GET['search']) ? trim($conn->real_escape_string($_GET['search'])) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$history_sql = "SELECT 
    id,
    guest_name,
    address,
    telephone,
    room_number,
    room_type,
    stay_duration,
    total_price,
    amount_paid,
    change_amount,
    payment_mode,
    check_in_date,
    check_out_date,
    gcash_reference,
    receptionist_id,
    status
FROM checkins WHERE 1=1";


// Apply filter (updated to use status column)
if ($filter === 'recent') {
    $history_sql .= " AND check_in_date > DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter === 'past') {
    $history_sql .= " AND status = 'checked_out'";
} elseif ($filter === 'current') {
    $history_sql .= " AND status = 'checked_in'";
} elseif ($filter === 'scheduled') {
    $history_sql .= " AND status = 'scheduled'";
} elseif ($filter === 'all') {
    // Show all records - no additional filter needed
}

// Apply search
if (!empty($search)) {
    $history_sql .= " AND (guest_name LIKE '%$search%' OR room_number LIKE '%$search%' OR payment_mode LIKE '%$search%' OR address LIKE '%$search%')";
}

$history_sql .= " ORDER BY check_in_date DESC";
$history_guests = $conn->query($history_sql);

// Handle export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="guest-checkin-history-' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'Guest ID', 'Guest Name', 'Address', 'Telephone', 'Room Number', 
        'Room Type', 'Stay Duration (hrs)', 'Check-In Date', 'Check-Out Date', 
        'Payment Mode', 'Amount Paid', 'Change', 'Total Price', 'GCash Reference', 'Status'
    ]);
    
    // Add data rows
    while ($row = $history_guests->fetch_assoc()) {
        fputcsv($output, [
            $row['id'] ?? 'N/A',
            $row['guest_name'] ?? 'Guest',
            $row['address'] ?? 'No address',
            $row['telephone'] ?? 'No contact',
            $row['room_number'] ?? 'N/A',
            $row['room_type'] ?? 'Standard',
            $row['stay_duration'] ?? 'N/A',
            date('F j, Y; g:iA', strtotime($row['check_in_date'] ?? 'now')),
            date('F j, Y; g:iA', strtotime($row['check_out_date'] ?? 'now')),
            $row['payment_mode'] ?? 'cash',
            $row['amount_paid'] ?? 0,
            $row['change_amount'] ?? 0,
            $row['total_price'] ?? 0,
            $row['gcash_reference'] ?? 'N/A',
            $row['status'] ?? 'N/A'
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}

// Reset the result pointer for normal page display
$history_guests = $conn->query($history_sql);

// Calculate statistics from checkins table (updated to use status)
$total_checkins_result = $conn->query("SELECT COUNT(*) AS total_checkins FROM checkins");
$total_checkins = $total_checkins_result->fetch_assoc()['total_checkins'];

$current_checkins_result = $conn->query("SELECT COUNT(*) AS current_checkins FROM checkins WHERE status = 'checked_in'");
$current_checkins = $current_checkins_result->fetch_assoc()['current_checkins'];

$past_checkins_result = $conn->query("SELECT COUNT(*) AS past_checkins FROM checkins WHERE status = 'checked_out'");
$past_checkins = $past_checkins_result->fetch_assoc()['past_checkins'];

$scheduled_checkins_result = $conn->query("SELECT COUNT(*) AS scheduled_checkins FROM checkins WHERE status = 'scheduled'");
$scheduled_checkins = $scheduled_checkins_result->fetch_assoc()['scheduled_checkins'];




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Guest Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
        <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
  <style>
    .payment-modal-popup {
    overflow-x: hidden !important;
}

.payment-modal-popup .swal2-html-container {
    overflow-x: hidden !important;
}

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
    }
    
    /* Guest Card Styling */
    .guest-card {
      transition: all 0.3s ease;
      border: none;
      border-radius: 10px;
      overflow: hidden;
    }
    
    .guest-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .guest-card .card-body {
      padding: 1.5rem;
    }
    
    .guest-info {
      display: flex;
      align-items: center;
      margin-bottom: 1rem;
    }
    
    .guest-avatar {
      width: 60px;
      height: 60px;
      background-color: #e9ecef;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 1rem;
      font-size: 1.5rem;
      color: #6c757d;
    }
    
    .guest-details h5 {
      margin-bottom: 0.25rem;
      font-weight: 600;
    }
    
    .guest-details p {
      margin-bottom: 0;
      color: #6c757d;
      font-size: 0.9rem;
    }
    
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

    .card { 
      border-radius: 10px; 
      box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    }

    table th { background: #f8f9fa; }
    table td, table th { padding: 12px; }
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

<!-- Content -->
<div class="content">
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-0">Guest Management</h2>
    <p class="text-muted mb-0">Manage and track all guest information</p>
  </div>
  <div class="clock-box text-end text-dark">
    <div id="currentDate" class="fw-semibold"></div>
    <div id="currentTime" class="fs-5"></div>
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

<div class="container-fluid px-0">

  <!-- STATISTICS CARDS (Admin Style) -->
<div class="row mb-4">
  <!-- Current Check-ins -->
  <div class="col-md-4 mb-3">
    <div class="card stat-card h-100 p-3">
      <div class="d-flex justify-content-between align-items-center">
        <p class="stat-title">Current Guests</p>
        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
          <i class="fas fa-user-check"></i>
        </div>
      </div>
      <h3 class="fw-bold mb-1"><?= $currently_checked_in ?></h3>
      <p class="stat-change text-success">+2% <span>from last week</span></p>
    </div>
  </div>

  <!-- Total Bookings -->
  <div class="col-md-4 mb-3">
    <div class="card stat-card h-100 p-3">
      <div class="d-flex justify-content-between align-items-center">
        <p class="stat-title">Total Bookings</p>
        <div class="stat-icon bg-success bg-opacity-10 text-success">
          <i class="fas fa-calendar-check"></i>
        </div>
      </div>
      <h3 class="fw-bold mb-1"><?= $total_bookings ?></h3>
      <p class="stat-change text-success">+5% <span>overall</span></p>
    </div>
  </div>

  
</div>

  <!-- Quick Actions -->
  <div class="card mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Quick Actions</h5>
      <i class="fas fa-bolt"></i>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3 mb-2">
          <a href="receptionist-booking.php?action=new" class="btn btn-outline-primary w-100">
            <i class="fas fa-calendar-plus me-2"></i>New Booking
          </a>
        </div>
        <div class="col-md-3 mb-2">
          <a href="receptionist-room.php" class="btn btn-outline-success w-100">
            <i class="fas fa-sign-in-alt me-2"></i>Check-in Guest
          </a>
        </div>

        <div class="col-md-3 mb-2">
          <a href="?export=csv" class="btn btn-outline-secondary w-100">
            <i class="fas fa-file-export me-2"></i>Export to CSV
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Search and Filter Controls -->
  <div class="search-filter-container mb-4 no-print">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label for="searchInput" class="form-label">Search Guests</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input type="text" name="search" id="searchInput" class="form-control" placeholder="Name, Room, Payment..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <label for="filterSelect" class="form-label">Filter By</label>
        <select name="filter" id="filterSelect" class="form-select" onchange="this.form.submit()">
          <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Guests</option>
          <option value="current" <?= $filter === 'current' ? 'selected' : '' ?>>Currently Checked-In</option>
          <option value="recent" <?= $filter === 'recent' ? 'selected' : '' ?>>Recent (Last 7 Days)</option>
          <option value="past" <?= $filter === 'past' ? 'selected' : '' ?>>Past Check-ins</option>
        </select>
      </div>
      <div class="col-md-4 d-flex">
        <button type="submit" class="btn btn-dark me-2 flex-grow-1">
          <i class="fas fa-filter me-2"></i>Apply Filters
        </button>
        <a href="receptionist-guest.php" class="btn btn-outline-secondary flex-grow-1">
          <i class="fas fa-redo me-2"></i>Reset
        </a>
      </div>
    </form>
  </div>

  <!-- Currently Checked-In Guests Section -->
  <div class="card mb-4">
    <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #871D2B;">
      <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Currently Checked-In Guests</h5>
      <span class="badge bg-light text-dark rounded-pill"><?= $current_guests->num_rows ?> Active Guests</span>
    </div>
    <div class="card-body p-0">
      <?php if ($current_guests->num_rows > 0): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Guest Information</th>
                <th>Room Details</th>
                <th>Contact Information</th>
                <th>Check-In/Out</th>
                <th>Payment Details</th>
                <th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($guest = $current_guests->fetch_assoc()): 
                $check_in = new DateTime($guest['check_in_date'] ?? 'now');
                $check_out = new DateTime($guest['check_out_date'] ?? '+3 hours');
                $duration = $check_in->diff($check_out);
              ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="avatar-sm bg-primary bg-opacity-10 text-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                      <i class="fas fa-user"></i>
                    </div>
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($guest['guest_name'] ?? 'Guest') ?></div>
                      <small class="text-muted">ID: #<?= htmlspecialchars($guest['id'] ?? 'N/A') ?></small>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <span class="badge bg-info text-dark mb-1">Room <?= htmlspecialchars($guest['room_number'] ?? 'N/A') ?></span>
                    <small class="text-muted"><?= ucfirst($guest['room_type'] ?? 'Standard') ?> Room</small>
                    <small class="text-muted">Duration: <?= htmlspecialchars($guest['stay_duration'] ?? 'N/A') ?> hrs</small>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <small><i class="fas fa-phone me-2"></i><?= htmlspecialchars($guest['telephone'] ?? 'No contact') ?></small>
                    <small><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($guest['address'] ?? 'No address') ?></small>
                    <?php if (!empty($guest['gcash_reference'])): ?>
                    <small><i class="fas fa-mobile-alt me-2"></i>GCash: <?= htmlspecialchars($guest['gcash_reference']) ?></small>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <small><i class="fas fa-sign-in-alt me-2"></i><?= $check_in->format('M j, Y; g:iA') ?></small>
                    <small><i class="fas fa-sign-out-alt me-2"></i><?= $check_out->format('M j, Y; g:iA') ?></small>
                    <small><i class="fas fa-clock me-2"></i><?= $duration->days > 0 ? $duration->days . ' day(s) ' : '' ?><?= $duration->h > 0 ? $duration->h . ' hour(s)' : '' ?></small>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <span class="badge bg-<?= strtolower($guest['payment_mode'] ?? 'cash') === 'cash' ? 'success' : 'primary' ?> mb-1">
                      <?= ucfirst($guest['payment_mode'] ?? 'Cash') ?>
                    </span>

                    <?php 
                    // Ensure accurate numeric handling
                    $total = floatval($guest['total_price'] ?? 0);
                    $paid = floatval($guest['amount_paid'] ?? 0);
                    $change_amount = round($paid - $total, 2);

                    // Always show Total and Paid
                    ?>
                    <small class="text-muted">Total: ₱<?= number_format($total, 2) ?></small>
                    <small class="text-muted">Paid: ₱<?= number_format($paid, 2) ?></small>

                    <?php if ($change_amount > 0.00): ?>
                        <small class="text-info">Change: ₱<?= number_format($change_amount, 2) ?></small>
                    <?php elseif ($change_amount < 0.00): ?>
                        <small class="text-danger">Due: ₱<?= number_format(abs($change_amount), 2) ?></small>
                    <?php endif; ?>
                  </div>
                </td>


                <td class="no-print">
                  <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-danger" onclick="checkOutGuest(<?= $guest['id'] ?? 0 ?>)">
                      <i class="fas fa-sign-out-alt"></i> Check Out
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="extendStay(<?= $guest['id'] ?? 0 ?>)">
                      <i class="fas fa-clock"></i> Extend
                    </button>
                        <button class="btn btn-sm btn-outline-info" onclick="printReceipt(<?= $guest['id'] ?? 0 ?>)">
                          <i class="fas fa-receipt"></i> Receipt
                        </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-center py-5">
          <i class="fas fa-users fa-3x text-muted mb-3"></i>
          <h5>No Guests Currently Checked In</h5>
          <p class="text-muted">All rooms are available for new check-ins</p>
          <a href="receptionist-room.php" class="btn btn-primary mt-2">
            <i class="fas fa-sign-in-alt me-2"></i>Check-in New Guest
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

<!-- Receipt Modal -->
<div id="receiptModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999;">
    <div style="background:#fff; width:320px; margin:5% auto; padding:15px; border-radius:8px; position:relative;">
        <div id="receiptContent"></div>
        <div style="margin-top:10px; text-align:center;">
            <button onclick="window.print()" style="padding:5px 10px; background:#4CAF50; color:#fff; border:none; border-radius:4px;">Print</button>
            
            <button onclick="downloadReceipt()" style="padding:5px 10px; background:#2196F3; color:#fff; border:none; border-radius:4px;">Download</button>
            
            <button onclick="closeReceipt()" style="padding:5px 10px; background:#999; color:#fff; border:none; border-radius:4px;">Close</button>
        </div>
    </div>
</div>

<!-- Guest Check-In History -->
<div class="card mb-4">
  <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Guest Check-In History</h5>
    <div class="d-flex align-items-center">
      <span class="badge bg-light text-dark rounded-pill me-2"><?= $history_guests->num_rows ?> Records</span>
      <a href="?export=csv&search=<?= urlencode($search) ?>&filter=<?= $filter ?>" class="btn btn-sm btn-light">
        <i class="fas fa-download me-1"></i>Export CSV
      </a>
    </div>
  </div>

  
  <!-- Search and Filter Controls -->
  <div class="card-body border-bottom">
    <div class="row g-3">
      <div class="col-md-6">
        <form method="GET" action="receptionist-guest.php" class="d-flex">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          <input type="text" class="form-control" name="search" placeholder="Search by guest name, room, address..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-primary ms-2">
            <i class="fas fa-search"></i>
          </button>
        </form>
      </div>
      <div class="col-md-6">
        <form method="GET" action="receptionist-guest.php" class="d-flex">
          <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
          <select name="filter" class="form-select" onchange="this.form.submit()">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Records</option>
            <option value="current" <?= $filter === 'current' ? 'selected' : '' ?>>Currently Checked In</option>
            <option value="past" <?= $filter === 'past' ? 'selected' : '' ?>>Checked Out</option>
            <option value="recent" <?= $filter === 'recent' ? 'selected' : '' ?>>Recent (Last 7 Days)</option>
          </select>
        </form>
      </div>
    </div>
  </div>
  
  <div class="card-body p-0">
    <?php if ($history_guests->num_rows > 0): ?>
      <div class="table-responsive">
        <table id="historyTable" class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Guest Information</th>
              <th>Contact Details</th>
              <th>Room Details</th>
              <th>Stay Information</th>
              <th>Check-In Date</th>
              <th>Check-Out Date</th>
              <th>Payment Details</th>
              <th>Total Amount</th>
              <th class="no-print">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $history_guests->fetch_assoc()): 
              $checkout_date = new DateTime($row['check_out_date'] ?? 'now');
              $checkin_date = new DateTime($row['check_in_date'] ?? 'now');
              $now_dt = new DateTime();

              $status = strtolower($row['status'] ?? '');

              if ($status === 'checked_out') {
                  $is_checked_out = true;
                  $is_active = false;
              } elseif ($status === 'checked_in') {
                  $is_checked_out = false;
                  $is_active = true;
              } elseif ($status === 'scheduled' && $checkout_date <= $now_dt) {
                  // If scheduled but already past checkout date → mark as checked out
                  $is_checked_out = true;
                  $is_active = false;
                  // Optionally auto-correct status in DB
                  $conn->query("UPDATE checkins SET status='checked_out' WHERE id=" . (int)$row['id']);
              } else {
                  $is_checked_out = false;
                  $is_active = false;
              }


              // If not active and not checked out by dates, check bookings table for a completed booking
              if (! $is_checked_out && ! $is_active) {
                  $bk_guest = $row['guest_name'];
                  $bk_room = (int)($row['room_number'] ?? 0);
                  $bkStmt = $conn->prepare("SELECT id FROM bookings WHERE guest_name = ? AND room_number = ? AND status = 'completed' LIMIT 1");
                  $bkStmt->bind_param("si", $bk_guest, $bk_room);
                  $bkStmt->execute();
                  $bkStmt->store_result();
                  if ($bkStmt->num_rows > 0) {
                      $is_checked_out = true;
                      $is_active = false;
                  }
                  $bkStmt->close();
              }
            ?>
            <tr class="<?= $is_active ? 'table-success' : ($is_checked_out ? 'table-light' : '') ?>">
              <td>
                <div class="d-flex align-items-center">
                  <div class="avatar-sm bg-<?= $is_active ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $is_active ? 'success' : 'secondary' ?> rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fas fa-user"></i>
                  </div>
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars($row['guest_name'] ?? 'Guest') ?></div>
                    <small class="text-muted">ID: #<?= $row['id'] ?? 'N/A' ?></small>
                  </div>
                </div>
              </td>
              <td>
                <div class="small">
                  <div class="mb-1">
                    <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                    <?= htmlspecialchars($row['address'] ?? 'No address') ?>
                  </div>
                  <div>
                    <i class="fas fa-phone me-2 text-muted"></i>
                    <?= htmlspecialchars($row['telephone'] ?? 'No contact') ?>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge bg-primary text-white mb-1">Room <?= htmlspecialchars($row['room_number'] ?? 'N/A') ?></span><br>
                <small class="text-muted"><?= ucfirst($row['room_type'] ?? 'Standard') ?> Room</small>
              </td>
              <td>
                <div class="small">
                  <div class="fw-semibold text-info"><?= htmlspecialchars($row['stay_duration'] ?? 'N/A') ?> hours</div>
                  <small class="text-muted">Duration</small>
                </div>
              </td>
              <td>
                <small class="text-success">
                  <i class="fas fa-sign-in-alt me-1"></i>
                  <?= $checkin_date->format('M j, Y') ?><br>
                  <span class="fw-bold"><?= $checkin_date->format('g:i A') ?></span>
                </small>
              </td>
              <td>
                <small class="text-<?= $is_checked_out ? 'danger' : 'warning' ?>">
                  <i class="fas fa-sign-out-alt me-1"></i>
                  <?= $checkout_date->format('M j, Y') ?><br>
                  <span class="fw-bold"><?= $checkout_date->format('g:i A') ?></span>
                </small>
              </td>
              <td>
                <div class="small">
                  <span class="badge bg-<?= strtolower($row['payment_mode'] ?? 'cash') === 'cash' ? 'success' : 'primary' ?> mb-1">
                    <?= ucfirst($row['payment_mode'] ?? 'Cash') ?>
                  </span><br>
                  <div class="text-muted">Paid: ₱<?= number_format($row['amount_paid'] ?? 0, 2) ?></div>
                  <?php 
                  $total = floatval($row['total_price'] ?? 0);
                  $paid = floatval($row['amount_paid'] ?? 0);
                  $change_amount = $paid - $total;

                  // Round to avoid floating-point rounding issues
                  $change_amount = round($change_amount, 2);

                  if ($change_amount > 0.00): // Guest paid more → Change
                  ?>
                      <div class="text-info">Change: ₱<?= number_format($change_amount, 2) ?></div>
                  <?php elseif ($change_amount < 0.00): // Guest paid less → Due
                  ?>
                      <div class="text-danger fw-bold">Due: ₱<?= number_format(abs($change_amount), 2) ?></div>
                  <?php endif; ?>

                  <?php if (!empty($row['gcash_reference'])): ?>
                      <div class="text-primary small">Ref: <?= htmlspecialchars($row['gcash_reference']) ?></div>
                  <?php endif; ?>
                </div>
              </td>

              <td class="fw-bold text-success">₱<?= number_format($row['total_price'] ?? 0, 2) ?></td>
              <td class="no-print">
                <?php if ($is_active): ?>
                  <span class="badge bg-success"><i class="fas fa-clock me-1"></i>In Use</span>
                <?php elseif ($is_checked_out): ?>
                  <span class="badge bg-secondary"><i class="fas fa-check-circle me-1"></i>Checked Out</span>
                <?php else: ?>
                  <span class="badge bg-warning"><i class="fas fa-hourglass-half me-1"></i>Scheduled</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Summary Statistics -->
      <div class="card-footer bg-light">
        <div class="row text-center">
          <div class="col-md-3">
            <div class="fw-bold text-primary"><?= $total_checkins ?></div>
            <small class="text-muted">Total Check-ins</small>
          </div>
          <div class="col-md-3">
            <div class="fw-bold text-success"><?= $current_checkins ?></div>
            <small class="text-muted">Currently Active</small>
          </div>
          <div class="col-md-3">
            <div class="fw-bold text-secondary"><?= $past_checkins ?></div>
            <small class="text-muted">Checked Out</small>
          </div>

        </div>
      </div>
      
    <?php else: ?>
      <div class="text-center py-5">
        <i class="fas fa-history fa-3x text-muted mb-3"></i>
        <h5>No Check-In History Found</h5>
        <p class="text-muted">No guest records found matching your criteria</p>
        <?php if (!empty($search) || $filter !== 'all'): ?>
        <a href="receptionist-guest.php" class="btn btn-outline-primary">
          <i class="fas fa-refresh me-2"></i>Show All Records
        </a>
        <?php else: ?>
        <small class="text-muted">Guest check-ins will appear here once they are recorded</small>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Toast container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
  <?php if (isset($_GET['success']) && $_GET['success'] === 'checkedin'): ?>
    <div id="checkinSuccessToast" class="toast align-items-center text-bg-success border-0 fade" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          Guest checked in successfully!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['success']) && $_GET['success'] === 'checkedout'): ?>
    <div id="checkoutSuccessToast" class="toast align-items-center text-bg-success border-0 fade" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          Guest checked out successfully!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['success']) && $_GET['success'] === 'payment'): ?>
    <div id="paymentSuccessToast" class="toast align-items-center text-bg-success border-0 fade" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-money-bill-wave me-2"></i>
          Payment processed successfully!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['success']) && $_GET['success'] === 'extended'): ?>
    <div id="extendSuccessToast" class="toast align-items-center text-bg-info border-0 fade" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-clock me-2"></i>
          Stay extended successfully!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
</div>


<!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>

  // toast 
  document.addEventListener("DOMContentLoaded", () => {
  const successToast = document.getElementById("checkinSuccessToast");
  if (successToast) {
    const toast = new bootstrap.Toast(successToast);
    toast.show();
  }

    // Checkout toast
  const checkoutToast = document.getElementById('checkoutSuccessToast');
  if (checkoutToast) {
    const toast = new bootstrap.Toast(checkoutToast, { delay: 3000 });
    toast.show();
  }
  
  // Payment toast
  const paymentToast = document.getElementById('paymentSuccessToast');
  if (paymentToast) {
    const toast = new bootstrap.Toast(paymentToast, { delay: 3000 });
    toast.show();
  }
  
  // Extend toast
  const extendToast = document.getElementById('extendSuccessToast');
  if (extendToast) {
    const toast = new bootstrap.Toast(extendToast, { delay: 3000 });
    toast.show();
  }
});

  document.getElementById("searchInput").addEventListener("input", function() {
    if (this.value === "") {
      // Keep the current filter when clearing search
      window.location.href = "receptionist-guest.php?filter=<?= $filter ?>";
    }
  });


  // Update clock
  function updateClock() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateStr = now.toLocaleDateString('en-PH', options);
    const timeStr = now.toLocaleTimeString('en-PH');

    document.getElementById('currentDate').innerText = dateStr;
    document.getElementById('currentTime').innerText = timeStr;
  }

  setInterval(updateClock, 1000);
  updateClock();

// Function to check out a guest
function checkOutGuest(guestId) {
    if (!guestId) {
        Swal.fire("Error", "Invalid guest ID", "error");
        return;
    }

    Swal.fire({
        title: "Confirm Checkout",
        text: "Are you sure you want to check out this guest?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, check out",
        cancelButtonText: "Cancel",
        background: '#1a1a1a',
        color: '#fff',
        confirmButtonColor: '#8b1d2d', 
        cancelButtonColor: '#555'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("receptionist-guest.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `action=checkout&guest_id=${guestId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect with success parameter for toast
                    window.location.href = '?success=checkedout';
                } else if (data.payment_required) {
                    showPaymentForm({
                        guest_id: data.guest_id,
                        guest_name: data.guest_name,
                        room_number: data.room_number,
                        room_type: data.room_type,
                        total_cost: data.total_cost,
                        amount_paid: data.amount_paid,
                        amount_due: data.balance,
                        amount_due_raw: data.balance_raw
                    }, true); 
                } else {
                    Swal.fire("Error", data.message, "error");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire("Error", "An error occurred while checking out the guest.", "error");
            });
        }
    });
}

function showPaymentForm(paymentDetails, autoCheckout = false) {
  const amountDueRaw = Number(paymentDetails.amount_due_raw ?? paymentDetails.balance_raw ?? paymentDetails.balance_amount ?? 0) || 0;
  const amountDueDisplay = paymentDetails.amount_due ?? paymentDetails.balance ?? paymentDetails.balance_due ?? parseFloat(amountDueRaw).toFixed(2);

  Swal.fire({
    title: '<span style="font-weight: 600; color: #fff;">Complete Payment</span>',
    html: `
      <div style="background: #111; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: inset 0 0 6px rgba(255,255,255,0.05);">
          <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333;">
              <span style="color: #bbb;">Guest:</span>
              <span style="color: #fff; font-weight: 500;">${paymentDetails.guest_name || ''}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333;">
              <span style="color: #bbb;">Room:</span>
              <span style="color: #fff; font-weight: 500;">${paymentDetails.room_type || ''} Room ${paymentDetails.room_number || ''}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333;">
              <span style="color: #bbb;">Total Cost:</span>
              <span style="color: #fff; font-weight: 500;">₱${paymentDetails.total_cost || '0.00'}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333;">
              <span style="color: #bbb;">Amount Paid:</span>
              <span style="color: #fff; font-weight: 500;">₱${paymentDetails.amount_paid || '0.00'}</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0;">
              <span style="color: #dc3545; font-weight: 600;">Balance Due:</span>
              <span style="color: #dc3545; font-weight: 700;">₱${amountDueDisplay}</span>
          </div>
      </div>

      <div style="text-align: left; margin-bottom: 15px;">
          <label style="display: block; color: #ccc; font-size: 14px; margin-bottom: 6px; font-weight: 500;">Enter Payment:</label>
          <input type="number" id="payment_amount" placeholder="0.00" min="0" step="0.01"
              style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 6px; font-size: 15px; background: #222; color: #eee; box-sizing: border-box;" />
      </div>

      <div style="text-align: left;">
          <label style="display: block; color: #ccc; font-size: 14px; margin-bottom: 6px; font-weight: 500;">Payment Method:</label>
          <select id="payment_mode" style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 6px; font-size: 15px; background: #222; color: #eee;">
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
          </select>
      </div>

      <div id="gcash_ref_wrapper" style="display:none; margin-top: 15px; text-align: left;">
          <label style="display: block; color: #ccc; font-size: 14px; margin-bottom: 6px; font-weight: 500;">GCash Reference:</label>
          <input id="gcash_reference" placeholder="Enter GCash reference number"
              style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 6px; font-size: 15px; background: #222; color: #eee; box-sizing: border-box;" />
      </div>
    `,
    background: '#1a1a1a',
    color: '#fff',
    showCancelButton: true,
    confirmButtonText: 'Submit Payment',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#8b1d2d', // maroon button
    cancelButtonColor: '#555', // gray cancel
    customClass: {
      popup: 'payment-modal-popup'
    },
    width: '500px',
    padding: '1.5rem',
    didOpen: () => {
      const modeSelect = document.getElementById('payment_mode');
      const gcashWrapper = document.getElementById('gcash_ref_wrapper');
      modeSelect.addEventListener('change', () => {
        gcashWrapper.style.display = modeSelect.value === 'gcash' ? 'block' : 'none';
      });
    },
    preConfirm: () => {
      const amount = parseFloat(document.getElementById("payment_amount").value);
      const mode = document.getElementById("payment_mode").value;
      const gcash_reference = document.getElementById("gcash_reference") ? document.getElementById("gcash_reference").value.trim() : '';

      if (!amount || amount <= 0) {
        Swal.showValidationMessage("Please enter a valid amount.");
        return false;
      }

      return { amount, mode, gcash_reference };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      const payload = new URLSearchParams();
      payload.append('action', 'add_payment');
      payload.append('guest_id', paymentDetails.guest_id);
      // If client-side knows amount_paid is 0 (initial payment), tell server to replace.
      const currentlyPaid = Number(paymentDetails.amount_paid ?? 0) || 0;
      const replaceFlag = currentlyPaid <= 0 ? '1' : '0';

      payload.append('additional_amount', result.value.amount);
      payload.append('payment_mode', result.value.mode);
      payload.append('gcash_reference', result.value.gcash_reference);
      payload.append('replace', replaceFlag);

      fetch("receptionist-guest.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: payload.toString()
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (autoCheckout && data.can_checkout) {
            fetch("receptionist-guest.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: `action=checkout&guest_id=${paymentDetails.guest_id}`
            })
            .then(response => response.json())
            .then(checkoutData => {
              if (checkoutData.success) {
                window.location.href = '?success=checkedout';
              } else {
                Swal.fire("Error", checkoutData.message || "Checkout failed", "error");
              }
            })
            .catch(error => {
              console.error("Checkout Error:", error);
              Swal.fire("Error", "An error occurred during checkout.", "error");
            });
          } else {
            window.location.href = '?success=payment';
          }
        } else {
          Swal.fire("Error", data.message || "Payment failed", "error");
        }
      })
      .catch(error => {
        console.error("Error:", error);
        Swal.fire("Error", "An error occurred while processing payment.", "error");
      });
    }
  });
}



// ✅ Process payment
function processPayment(guestId, amount, mode, gcashRef, isCheckout) {
    const formData = new URLSearchParams();
    formData.append('action', 'add_payment');
    formData.append('guest_id', guestId);
    formData.append('additional_amount', amount);
    formData.append('payment_mode', mode);
    formData.append('gcash_reference', gcashRef);

    fetch('receptionist-guest.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Payment Processed!',
                html: `
                    <p>${data.message}</p>
                    <p><strong>New Amount Paid:</strong> ₱${data.new_amount_paid}</p>
                    ${
                        data.change_amount && parseFloat(data.change_amount) > 0
                            ? `<p style="color:blue;"><strong>Change:</strong> ₱${data.change_amount}</p>`
                            : ''
                    }
                `,
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                if (isCheckout && data.can_checkout) {
                    // ✅ Automatically check out guest if full payment done
                    checkOutGuest(guestId);
                } else {
                    // ✅ Refresh to show updated "Paid", "Change", or "Due" values
                    location.reload();
                }
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Failed to process payment', 'error');
    });
}



function proceedWithCheckout(guestId) {
    fetch('receptionist-guest.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=checkout&guest_id=${guestId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Just redirect - no alert!
            window.location.href = '?success=checkedout';
        } else if (data.payment_required) {
            Swal.fire({
                title: 'Additional Payment Required',
                html: `...`,
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        } else {
            Swal.fire('Error', data.message || 'Checkout failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'An error occurred while checking out the guest.', 'error');
    });
}


// Function to extend guest stay
function extendStay(guestId) {
    if (!guestId) {
        Swal.fire("Error", "Invalid guest ID", "error");
        return;
    }

    Swal.fire({
        title: "Extend Stay?",
        text: "Extend stay by 1 hour? Additional charges will apply.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, extend",
        cancelButtonText: "Cancel",
        background: '#1a1a1a',
        color: '#fff',
        confirmButtonColor: '#8b1d2d', 
        cancelButtonColor: '#555'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("receptionist-guest.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `action=extend&guest_id=${guestId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect with extend success toast
                    window.location.href = '?success=extended';
                } else {
                    Swal.fire("Error", data.message, "error");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire("Error", "An error occurred while extending the stay.", "error");
            });
        }
    });
}

function printReceipt(guestId) {
    if (!guestId) {
        alert('Invalid guest ID');
        return;
    }

    fetch(`get-guest-receipt.php?guest_id=${guestId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Error: ' + data.message);
                return;
            }

            const guest = data.guest;
            const roomCharge = parseFloat(guest.total_price || 0);
            const ordersTotal = parseFloat(guest.orders_total || 0);
            const grandTotal = parseFloat(guest.grand_total || 0);
            const paid = parseFloat(guest.amount_paid || 0);
            
            const change = paid > grandTotal ? paid - grandTotal : 0;
            const due = grandTotal > paid ? grandTotal - paid : 0;

            // Format current date and time
            const now = new Date();
            const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
            const currentDate = now.toLocaleDateString('en-US', dateOptions);
            const currentTime = now.toLocaleTimeString('en-US', timeOptions);

            // Build orders table rows
            let ordersRows = '';
            if (guest.orders && guest.orders.length > 0) {
                guest.orders.forEach(order => {
                    const unitPrice = parseFloat(order.price);
                    const quantity = parseInt(order.quantity);
                    ordersRows += `
                        <tr>
                            <td>${order.item_name}</td>
                            <td class="text-center">${quantity}</td>
                            <td class="text-end">₱${unitPrice.toFixed(2)}</td>
                        </tr>
                    `;
                });
            }

            const receiptContent = `
                <style>
                    body {
                        background-color: #f8f9fa;
                        font-family: "Poppins", Arial, sans-serif;
                        padding: 40px 0;
                        margin: 0;
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
                    .receipt-header h2 {
                        font-weight: 700;
                        margin: 0;
                        color: #333;
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
                        font-size: 1.2rem;
                    }
                    .guest-info {
                        color: #555;
                        display: block;
                        text-align: center;
                        width: 100%;
                    }
                    .timestamp {
                        text-align: center;
                        font-size: 0.9rem;
                        color: #6c757d;
                        margin-bottom: 15px;
                    }
                    hr {
                        border: 0;
                        border-top: 1px solid #dee2e6;
                        margin: 15px 0;
                    }
                    table {
                        width: 100%;
                        font-size: 0.95rem;
                        border-collapse: collapse;
                    }
                    th {
                        text-transform: uppercase;
                        font-size: 0.8rem;
                        color: #6c757d;
                        border-bottom: 1px solid #dee2e6;
                        padding: 8px 0;
                        font-weight: 600;
                    }
                    td {
                        padding: 8px 0;
                        border: none;
                    }
                    .text-center { text-align: center; }
                    .text-end { text-align: right; }
                    .summary-section {
                        margin-top: 20px;
                    }
                    .summary-row {
                        display: flex;
                        justify-content: space-between;
                        padding: 8px 0;
                        font-size: 0.95rem;
                    }
                    .summary-row.room-charge {
                        border-top: 1px solid #dee2e6;
                        padding-top: 12px;
                    }
                    .summary-row.total-row {
                        border-top: 2px solid #333;
                        padding-top: 12px;
                        margin-top: 8px;
                        font-weight: 700;
                        font-size: 1.1rem;
                    }
                    .summary-row.total-row .amount {
                        font-size: 1.4rem;
                    }
                    .payment-info {
                        margin-top: 15px;
                        padding-top: 15px;
                        border-top: 1px solid #dee2e6;
                    }
                    .payment-row {
                        display: flex;
                        justify-content: space-between;
                        padding: 5px 0;
                        font-size: 0.95rem;
                    }
                    .payment-row.highlight {
                        color: #871D2B;
                        font-weight: 600;
                    }
                    .footer-text {
                        text-align: center;
                        margin-top: 25px;
                        font-size: 0.9rem;
                        color: #6c757d;
                    }
                    .footer-text strong {
                        display: block;
                        margin-bottom: 5px;
                        color: #333;
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
                    }
                </style>

                <div class="receipt-card">
                    <div class="receipt-header">
                        <h2>Gitarra Apartelle</h2>
                        <small>Premium Hospitality Services</small>
                    </div>

                    <hr>
                    <h5 class="room-title">Room ${guest.room_number} ${guest.room_type ? '(' + guest.room_type + ')' : ''}</h5>
                    <div class="guest-info">Guest: ${guest.guest_name}</div>
                    <div class="timestamp">
                        ${currentDate} • ${currentTime}
                    </div>
                    <div class="timestamp" style="margin-top: -8px;">
                        Check-in: ${guest.check_in_date} | Check-out: ${guest.check_out_date}
                    </div>
                    <hr>

                    ${ordersRows ? `
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${ordersRows}
                        </tbody>
                    </table>
                    ` : ''}

                    <div class="summary-section">
                        ${ordersRows ? `
                        <div class="summary-row">
                            <span>Orders Subtotal</span>
                            <span>₱${ordersTotal.toFixed(2)}</span>
                        </div>
                        ` : ''}
                        
                        <div class="summary-row room-charge">
                            <span>Room Charge (${guest.stay_duration} hrs)</span>
                            <span>₱${roomCharge.toFixed(2)}</span>
                        </div>

                        <div class="summary-row total-row">
                            <span>TOTAL</span>
                            <span class="amount">₱${grandTotal.toFixed(2)}</span>
                        </div>
                    </div>

                    <div class="payment-info">
                        <div class="payment-row">
                            <span>Amount Paid</span>
                            <span>₱${paid.toFixed(2)}</span>
                        </div>
                        ${change > 0 ? `
                        <div class="payment-row highlight">
                            <span>Change</span>
                            <span>₱${change.toFixed(2)}</span>
                        </div>
                        ` : due > 0 ? `
                        <div class="payment-row highlight">
                            <span>Balance Due</span>
                            <span>₱${due.toFixed(2)}</span>
                        </div>
                        ` : ''}
                        <div class="payment-row">
                            <span>Payment Method</span>
                            <span>${guest.payment_mode?.toUpperCase() ?? 'CASH'}</span>
                        </div>
                    </div>

                    <hr>
                    <div class="footer-text">
                        <strong>Thank you for your patronage!</strong>
                        <span>Receipt #: GIT-${guest.id}-${new Date().getFullYear()}</span>
                    </div>
                </div>
            `;

            document.getElementById('receiptContent').innerHTML = receiptContent;
            document.getElementById('receiptModal').style.display = 'block';
        })
        .catch(error => {
            console.error(error);
            alert('Failed to load receipt data');
        });
}

function closeReceipt() {
    document.getElementById('receiptModal').style.display = 'none';
}

  function proceedWithCheckout(guestId) {
    fetch('receptionist-guest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=checkout&guest_id=${guestId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            if (data.payment_details) {
                alert(`${data.message}\n\nTotal Cost: ₱${data.payment_details.total_cost}\nAmount Paid: ₱${data.payment_details.amount_paid}\nBalance Due: ₱${data.payment_details.balance_due}`);
            } else {
                alert('Error: ' + data.message);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while checking out the guest.');
    });
}

  function showBillingModal(content, allowCheckout) {
      // Create or update billing modal
      let modal = document.getElementById('billingModal');
      if (!modal) {
          modal = document.createElement('div');
          modal.innerHTML = `
              <div class="modal fade" id="billingModal" tabindex="-1">
                  <div class="modal-dialog">
                      <div class="modal-content">
                          <div class="modal-header">
                              <h5 class="modal-title">Billing Details</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body" id="billingContent"></div>
                          <div class="modal-footer" id="billingFooter"></div>
                      </div>
                  </div>
              </div>`;
          document.body.appendChild(modal);
      }
      
      document.getElementById('billingContent').innerHTML = content;
      
      const footer = document.getElementById('billingFooter');
      footer.innerHTML = allowCheckout ? 
          '<button type="button" class="btn btn-success" onclick="proceedWithCheckout(currentGuestId)">Proceed with Checkout</button>' :
          '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
      
      const billingModal = new bootstrap.Modal(document.getElementById('billingModal'));
      billingModal.show();
  }

      function downloadReceipt() {
          const element = document.getElementById('receiptContent');
          const filename = 'receipt_' + new Date().toISOString().replace(/[:.]/g, '-') + '.pdf';

          html2pdf().set({
              margin: 0.4,
              filename: filename,
              image: { type: 'jpeg', quality: 0.98 },
              html2canvas: { scale: 2 },
              jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
          }).from(element).save();
      }

  // DATA Table
$(document).ready(function() {
  var table = $('#historyTable').DataTable({
    paging: true,
    lengthChange: false,
    searching: false,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: true,
    pageLength: 5,
    language: {
      search: "_INPUT_",
      searchPlaceholder: "Search records...",
      info: "Showing _START_ to _END_ of _TOTAL_ records",
      infoEmpty: "No records available",
      emptyTable: "<i class='fas fa-history fa-3x text-muted mb-3'></i><p class='mb-0'>No history found</p>",
      paginate: {
        first: "«",
        previous: "‹",
        next: "›",
        last: "»"
      }
    },
    columnDefs: [
      { orderable: false, targets: [0, 8] }
    ],

    dom: '<"top"f>t<"bottom"ip><"clear">'
  });


  const pagination = $('#historyTable_paginate').detach();
  const info = $('#historyTable_info').detach();


setTimeout(() => {
  const scrollPos = window.pageYOffset; // Save current scroll position
  
  $('.card-footer.bg-light .row').after(
    $('<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">')
      .append(info)
      .append(pagination)
  );
  
  window.scrollTo(0, scrollPos); // Restore scroll position
}, 200);
});
</script>

</body>
</html>




