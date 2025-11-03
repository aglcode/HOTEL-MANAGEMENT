<?php
session_start();
require_once 'database.php';

// ✅ Update scheduled → checked_in when check-in time arrives
$conn->query("
    UPDATE checkins 
    SET status = 'checked_in',
        last_modified = NOW()
    WHERE status = 'scheduled' 
    AND check_in_date <= NOW()
");

// ✅ Update checked_in → checked_out when checkout time passes
// 30-second grace period prevents immediate checkout after rebook/extend
$conn->query("
    UPDATE checkins 
    SET status = 'checked_out',
        last_modified = NOW()
    WHERE status IN ('checked_in', 'scheduled')
    AND check_out_date <= NOW()
    AND last_modified < DATE_SUB(NOW(), INTERVAL 30 SECOND)
    AND NOT EXISTS (
        SELECT 1 FROM bookings 
        WHERE bookings.guest_name = checkins.guest_name COLLATE utf8mb4_unicode_ci
        AND bookings.room_number = checkins.room_number
        AND bookings.status IN ('upcoming', 'confirmed')
        AND bookings.end_date > NOW()
    )
");

// ✅ Make rooms available when guests are checked out
$conn->query("
    UPDATE rooms r
    INNER JOIN checkins c ON r.room_number = c.room_number
    SET r.status = 'available'
    WHERE c.status = 'checked_out' 
    AND c.check_out_date <= NOW()
    AND r.status = 'occupied'
");

// ✅ Expire keycards for checked-out guests
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
    status,
    last_modified,
    previous_charges,
    is_rebooked,
    rebooked_from
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
    status,
    last_modified
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
        'Payment Mode', 'Amount Paid', 'Change', 'Total Price', 'GCash Reference', 'Status', 'Last Modified'
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
            $row['status'] ?? 'N/A',
            date('F j, Y; g:iA', strtotime($row['last_modified'] ?? 'now'))
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


// Generate room schedules for conflict detection (for rebooking)
$rebook_schedule_query = "SELECT 
    room_number, 
    start_date, 
    end_date, 
    guest_name,
    status
FROM bookings 
WHERE status NOT IN ('cancelled', 'completed')
ORDER BY start_date ASC";

$rebook_schedule_result = $conn->query($rebook_schedule_query);
$rebook_schedules = [];

while ($schedule = $rebook_schedule_result->fetch_assoc()) {
    $room_num = $schedule['room_number'];
    if (!isset($rebook_schedules[$room_num])) {
        $rebook_schedules[$room_num] = [];
    }
    $rebook_schedules[$room_num][] = [
        'start_date' => $schedule['start_date'],
        'end_date' => $schedule['end_date'],
        'guest_name' => $schedule['guest_name'] ?? '',
        'status' => $schedule['status'] ?? ''
    ];
}

// Also include check-ins that are scheduled or currently checked in
$rebook_checkin_query = "SELECT 
    id, 
    room_number, 
    check_in_date, 
    check_out_date, 
    guest_name,
    status
FROM checkins 
WHERE status IN ('scheduled', 'checked_in')
ORDER BY check_in_date ASC";

$rebook_checkin_result = $conn->query($rebook_checkin_query);
$rebook_checkin_data = [];

while ($checkin = $rebook_checkin_result->fetch_assoc()) {
    $room_num = $checkin['room_number'];
    if (!isset($rebook_checkin_data[$room_num])) {
        $rebook_checkin_data[$room_num] = [];
    }
    $rebook_checkin_data[$room_num][] = [
        'checkin_id' => $checkin['id'],
        'start_date' => $checkin['check_in_date'],
        'end_date' => $checkin['check_out_date'],
        'guest_name' => $checkin['guest_name'] ?? '',
        'status' => $checkin['status'] ?? ''
    ];
}

// 🔍 Debug output (remove after testing)
error_log("=== REBOOK SCHEDULES DEBUG ===");
error_log("Bookings: " . json_encode($rebook_schedules));
error_log("Checkins: " . json_encode($rebook_checkin_data));
error_log("=============================");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Guest Management</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
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
                    // ✅ FIXED: Calculate correct totals for rebooked vs regular guests
                    $current_total = floatval($guest['total_price'] ?? 0);
                    $previous_charges = floatval($guest['previous_charges'] ?? 0);
                    $is_rebooked = ($guest['is_rebooked'] == 1);
                    $paid = floatval($guest['amount_paid'] ?? 0);
                    
                    // If rebooked, overall total = previous charges + current charges
                    // If not rebooked, overall total = current charges only
                    $overall_total = $is_rebooked && $previous_charges > 0 
                        ? $previous_charges + $current_total 
                        : $current_total;
                    
                    // Calculate change/due based on overall total
                    $change_amount = round($paid - $overall_total, 2);
                    
                    // Debug logging (remove after fixing)
                    error_log("Guest ID {$guest['id']}: is_rebooked={$is_rebooked}, previous={$previous_charges}, current={$current_total}, overall={$overall_total}, paid={$paid}, change={$change_amount}");
                    ?>
                    
                    <?php if ($is_rebooked && $previous_charges > 0): ?>
                        <!-- Show breakdown for rebooked guests -->
                        <small class="text-muted">Previous: ₱<?= number_format($previous_charges, 2) ?></small>
                        <small class="text-muted">Additional: ₱<?= number_format($current_total, 2) ?></small>
                        <small class="text-muted fw-bold">Total: ₱<?= number_format($overall_total, 2) ?></small>
                    <?php else: ?>
                        <!-- Show simple total for regular guests -->
                        <small class="text-muted">Total: ₱<?= number_format($overall_total, 2) ?></small>
                    <?php endif; ?>
                    
                    <small class="text-muted">Paid: ₱<?= number_format($paid, 2) ?></small>

                    <?php if ($change_amount > 0.00): ?>
                        <small class="text-info">Change: ₱<?= number_format($change_amount, 2) ?></small>
                    <?php elseif ($change_amount < 0.00): ?>
                        <small class="text-danger fw-bold">Due: ₱<?= number_format(abs($change_amount), 2) ?></small>
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

                        <button class="btn btn-sm btn-outline-primary" onclick="openRebookModal(<?= $guest['id'] ?? 0 ?>)">
                      <i class="fas fa-redo"></i> Rebook
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

<!-- Rebook Modal -->
<div class="modal fade" id="rebookModal" tabindex="-1" aria-labelledby="rebookModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-4 overflow-hidden">

      <!-- Header -->
      <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);">
        <h5 class="modal-title fw-bold" id="rebookModalLabel">
          <i class="fas fa-redo me-2"></i>Rebook Guest
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Form -->
      <form id="rebookForm" onsubmit="submitRebook(); return false;">
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
                    <input type="hidden" id="rebook_guest_id" name="guest_id">

                    <div class="mb-3">
                      <label class="form-label fw-medium">Guest Name</label>
                      <input type="text" class="form-control bg-light" id="rebook_guest_name" name="guest_name" readonly>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-medium">Telephone</label>
                      <input type="text" class="form-control bg-light" id="rebook_telephone" name="telephone" readonly>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-medium">Address</label>
                      <input type="text" class="form-control bg-light" id="rebook_address" name="address" readonly>
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
                      <label class="form-label fw-medium">Select Room *</label>
                      <select class="form-select" id="rebook_room_number" name="room_number" required>
                        <option value="">Choose a room...</option>
                      </select>
                      <small class="text-muted">Room type and price will update automatically</small>
                    </div>
                    <div id="rebook_availability_message" class="mb-3"></div>
                    <div class="mb-3">
                      <label class="form-label fw-medium">Room Type</label>
                      <input type="text" class="form-control bg-light" id="rebook_room_type" name="room_type" readonly>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-medium">Check-in Date & Time *</label>
                      <input type="datetime-local" class="form-control" id="rebook_checkin_date" name="check_in_date" required>
                    </div>
                    <div class="mb-3">
                      <label for="rebook_duration" class="form-label fw-medium">Stay Duration *</label>
                      <select name="stay_duration" id="rebook_duration" class="form-select" required onchange="calculateRebookDetails();">
                        <option value="3">3 Hours</option>
                        <option value="6">6 Hours</option>
                        <option value="12">12 Hours</option>
                        <option value="24">24 Hours</option>
                        <option value="48">48 Hours</option>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-medium">Check-out Date & Time</label>
                      <input type="text" class="form-control bg-light" id="rebook_checkout_date" readonly>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-medium">Total Price</label>
                      <input type="text" class="form-control bg-light fw-semibold text-success" id="rebook_total_price" readonly>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Information -->
            <div class="card shadow-sm border-0 mt-4 rounded-3">
              <div class="card-header text-white rounded-top-3" style="background: linear-gradient(135deg, #6a1520 0%, #8b1d2d 100%);">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
              </div>
              <div class="card-body bg-white rounded-bottom">
                <div class="row g-3 align-items-end">
                  <div class="col-md-6">
                    <label class="form-label fw-medium">Payment Mode *</label>
                    <select class="form-select" id="rebook_payment_mode" name="payment_mode" required onchange="toggleRebookGcash();">
                      <option value="">Select payment method</option>
                      <option value="cash">💵 Cash Payment</option>
                      <option value="gcash">📱 GCash</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-medium">Amount Paid *</label>
                    <div class="input-group">
                      <span class="input-group-text" style="background: linear-gradient(135deg, #6a1520 0%, #8b1d2d 100%); color: #fff;">₱</span>
                      <input type="number" class="form-control" id="rebook_amount_paid" name="amount_paid" min="0" step="0.01" required oninput="calculateRebookChange();">
                    </div>
                    <small id="rebook_amount_error" class="text-danger d-none">Amount paid cannot be less than total price.</small>
                  </div>
                </div>

                <!-- GCash Section -->
                <div id="rebook_gcash_ref_wrapper" class="row mt-4" style="display:none;">
                  <div class="col-md-8">
                    <div class="p-3 rounded-3" style="background: #f0f4ff;">
                      <strong class="d-block mb-2"><i class="fas fa-info-circle me-2"></i>GCash Payment Instructions:</strong>
                      <ol class="mb-0 ps-3 text-dark">
                        <li>Send your payment to the GCash number provided</li>
                        <li>Take a screenshot of the transaction</li>
                        <li>Enter the 13-digit reference number below</li>
                      </ol>
                      <div class="mt-3">
                        <label class="form-label fw-medium">GCash Reference (13 digits)</label>
                        <input type="text" class="form-control" id="rebook_gcash_reference" name="gcash_reference" maxlength="13" pattern="\d{13}">
                        <small id="rebook_gcash_error" class="text-danger d-none">Please enter a valid 13-digit GCash reference number.</small>
                        <small class="text-muted">Found in your GCash transaction receipt</small>
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
                <div class="row mt-3" id="rebook_cash_section">
                  <div class="col-md-6">
                    <label class="form-label fw-medium">Change</label>
                    <div class="input-group">
                      <span class="input-group-text" style="background: linear-gradient(135deg, #6a1520 0%, #8b1d2d 100%); color: #fff;">₱</span>
                      <input type="text" class="form-control" id="rebook_change_amount" readonly value="0.00">
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
            <i class="fas fa-check me-2"></i>Confirm Rebook
          </button>
        </div>
      </form>
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

    $status = strtolower(trim($row['status'] ?? ''));
    $guest_id = $row['id'] ?? 0;
    $guest_name = $row['guest_name'] ?? '';
    $room_number = (int)($row['room_number'] ?? 0);

    // ✅ FIXED: Simplified and accurate status determination
    $is_checked_out = false;
    $is_active = false;
    $is_upcoming = false;

    // Primary: Trust the database status first
    if ($status === 'checked_out') {
        $is_checked_out = true;
    } 
    elseif ($status === 'checked_in') {
        // Double-check: Is checkout time actually in the future?
        if ($checkout_date > $now_dt) {
            $is_active = true;
        } else {
            // Checkout has passed but DB not updated yet
            $is_checked_out = true;
        }
    }
    elseif ($status === 'scheduled') {
        // Check if check-in time has already passed
        if ($checkin_date <= $now_dt && $checkout_date > $now_dt) {
            // Should be checked_in, not scheduled
            $is_active = true;
        } elseif ($checkout_date <= $now_dt) {
            // Both times have passed
            $is_checked_out = true;
        } else {
            // Truly upcoming
            $is_upcoming = true;
        }
    }
    else {
        // Unknown status - determine by time
        if ($checkout_date <= $now_dt) {
            $is_checked_out = true;
        } elseif ($checkin_date <= $now_dt && $checkout_date > $now_dt) {
            $is_active = true;
        } elseif ($checkin_date > $now_dt) {
            $is_upcoming = true;
        }
    }
    
    // ✅ Debug logging (remove after fixing)
    error_log("Guest: $guest_name | DB Status: $status | Badge: " . 
              ($is_active ? 'In Use' : ($is_checked_out ? 'Checked Out' : ($is_upcoming ? 'Upcoming' : 'Unknown'))));
?>
            <tr class="<?= $is_active ? 'table-success' : ($is_checked_out ? 'table-light' : ($is_upcoming ? 'table-info' : '')) ?>">
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
                      <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>In Use</span>
                  <?php elseif ($is_checked_out): ?>
                      <span class="badge bg-secondary"><i class="fas fa-times-circle me-1"></i>Checked Out</span>
                  <?php elseif ($is_upcoming): ?>
                      <span class="badge bg-info"><i class="fas fa-calendar-plus me-1"></i>Upcoming</span>
                  <?php else: ?>
                      <span class="badge bg-warning"><i class="fas fa-hourglass-half me-1"></i>Unknown</span>
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

  // Store booked schedules for rebooking
const rebookRoomSchedules = <?php echo json_encode($rebook_schedules ?? []); ?>;
const rebookCheckinSchedules = <?php echo json_encode($rebook_checkin_data ?? []); ?>;

// Exclude current guest from conflict detection
function checkRebookRoomAvailability() {
    const roomSelect = document.getElementById('rebook_room_number');
    const checkinInput = document.getElementById('rebook_checkin_date');
    const durationSelect = document.getElementById('rebook_duration');
    const messageDiv = document.getElementById('rebook_availability_message');
    
    // Get current guest info
    const currentGuestId = parseInt(document.getElementById('rebook_guest_id')?.value) || 0;
    const currentGuestName = document.getElementById('rebook_guest_name')?.value?.trim() || '';
    
    const roomNumber = roomSelect?.value;
    const checkinTime = checkinInput?.value;
    const durationValue = durationSelect?.value;
    
    // Clear previous messages
    if (messageDiv) {
        messageDiv.innerHTML = '';
    }
    
    // If fields are not filled, don't validate yet
    if (!roomNumber || !checkinTime || !durationValue) {
        if (checkinInput) checkinInput.setCustomValidity('');
        return true;
    }
    
    const duration = parseInt(durationValue);
    const selectedCheckin = new Date(checkinTime);
    const selectedCheckout = new Date(selectedCheckin.getTime() + duration * 60 * 60 * 1000);
    
    // Get schedules with fallback
    const bookingsList = Array.isArray(rebookRoomSchedules[roomNumber]) 
        ? rebookRoomSchedules[roomNumber] 
        : [];
    
    const checkinsList = Array.isArray(rebookCheckinSchedules[roomNumber]) 
        ? rebookCheckinSchedules[roomNumber] 
        : [];
    
    console.log('🔍 === REBOOK CONFLICT CHECK ===');
    console.log('Guest ID:', currentGuestId, '| Name:', currentGuestName);
    console.log('Room:', roomNumber);
    console.log('New Period:', selectedCheckin.toLocaleString(), '->', selectedCheckout.toLocaleString());
    console.log('Bookings to check:', bookingsList.length);
    console.log('Checkins to check:', checkinsList.length);
    
    const now = new Date();
    
    // ============================================
    // 🔧 FILTER OUT: Same guest's schedules
    // ============================================
    
    const filteredBookings = bookingsList.filter(schedule => {
        const bookedStart = new Date(schedule.start_date);
        const bookedEnd = new Date(schedule.end_date);
        const guestName = (schedule.guest_name || '').trim().toLowerCase();
        
        // 1. Exclude if same guest (case-insensitive match)
        if (guestName === currentGuestName.toLowerCase()) {
            console.log('❌ Excluded Booking (same guest):', schedule.guest_name, bookedStart.toLocaleString());
            return false;
        }
        
        // 2. Exclude if booking is completely in the past
        if (bookedEnd < now) {
            console.log('❌ Excluded Booking (past):', schedule.guest_name, bookedEnd.toLocaleString());
            return false;
        }
        
        // 3. Exclude cancelled/completed
        if (schedule.status && ['cancelled', 'completed'].includes(schedule.status.toLowerCase())) {
            console.log('❌ Excluded Booking (cancelled/completed):', schedule.guest_name);
            return false;
        }
        
        console.log('✅ Include Booking:', schedule.guest_name, bookedStart.toLocaleString(), '->', bookedEnd.toLocaleString());
        return true;
    });
    
    const filteredCheckins = checkinsList.filter(schedule => {
        const bookedStart = new Date(schedule.start_date);
        const bookedEnd = new Date(schedule.end_date);
        const guestName = (schedule.guest_name || '').trim().toLowerCase();
        const checkinId = parseInt(schedule.checkin_id) || 0;
        
        // 1. Exclude if same checkin ID (this is the guest being rebooked)
        if (checkinId === currentGuestId) {
            console.log('❌ Excluded Checkin (same ID):', checkinId, schedule.guest_name);
            return false;
        }
        
        // 2. Exclude if same guest name
        if (guestName === currentGuestName.toLowerCase()) {
            console.log('❌ Excluded Checkin (same guest):', schedule.guest_name, bookedStart.toLocaleString());
            return false;
        }
        
        // 3. Exclude if checkin is completely in the past
        if (bookedEnd < now) {
            console.log('❌ Excluded Checkin (past):', schedule.guest_name, bookedEnd.toLocaleString());
            return false;
        }
        
        // 4. Exclude checked_out status
        if (schedule.status && schedule.status.toLowerCase() === 'checked_out') {
            console.log('❌ Excluded Checkin (checked out):', schedule.guest_name);
            return false;
        }
        
        console.log('✅ Include Checkin:', schedule.guest_name, bookedStart.toLocaleString(), '->', bookedEnd.toLocaleString());
        return true;
    });
    
    const allSchedules = [...filteredBookings, ...filteredCheckins];
    console.log('📊 Total schedules after filtering:', allSchedules.length);
    
    // ============================================
    // 🔧 CHECK FOR OVERLAPS with OTHER guests
    // ============================================
    
    let isAvailable = true;
    let conflictSchedule = null;
    
    for (let schedule of allSchedules) {
        const bookedStart = new Date(schedule.start_date);
        const bookedEnd = new Date(schedule.end_date);
        
        console.log('⚙️ Checking:', schedule.guest_name);
        console.log('   Booked:', bookedStart.toLocaleString(), '->', bookedEnd.toLocaleString());
        
        // Overlap check: (start1 < end2) AND (end1 > start2)
        const hasOverlap = selectedCheckin < bookedEnd && selectedCheckout > bookedStart;
        
        console.log('   Overlap?', hasOverlap);
        
        if (hasOverlap) {
            isAvailable = false;
            conflictSchedule = schedule;
            console.log('❌ CONFLICT DETECTED with', schedule.guest_name);
            break;
        }
    }
    
    console.log('🎯 Result: Available =', isAvailable);
    console.log('================================');
    
    // ============================================
    // 🎨 DISPLAY RESULT
    // ============================================
    
    if (isAvailable) {
        if (messageDiv) {
            const selectedOption = roomSelect.options[roomSelect.selectedIndex];
            const isOccupied = selectedOption.dataset.isOccupied === '1';
            
            let successMessage = `
                <div class="alert alert-success border-0 shadow-sm" style="background-color: #d4edda; border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-2" style="font-size: 1.2rem;"></i>
                        <span class="text-success fw-medium">✓ Room is available for selected time period</span>
                    </div>`;
            
            if (isOccupied) {
                successMessage += `
                    <div class="mt-2 ps-4">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Note: This room is currently occupied but will be available during your selected booking time.
                        </small>
                    </div>`;
            }
            
            successMessage += `</div>`;
            messageDiv.innerHTML = successMessage;
        }
        if (checkinInput) checkinInput.setCustomValidity('');
        return true;
    } else {
        const bookedStart = new Date(conflictSchedule.start_date);
        const bookedEnd = new Date(conflictSchedule.end_date);
        
        const formatOptions = {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        
        const formattedStart = bookedStart.toLocaleString('en-US', formatOptions);
        const formattedEnd = bookedEnd.toLocaleString('en-US', formatOptions);
        
        if (messageDiv) {
            messageDiv.innerHTML = `
                <div class="alert alert-danger border-0 shadow-sm" style="background-color: #f8d7da; border-radius: 12px;">
                    <div class="mb-2">
                        <strong class="text-danger" style="font-size: 1.1rem;">⚠️ Time Conflict Detected</strong>
                    </div>
                    <p class="mb-0 text-danger">
                        This room is already booked by <strong>${conflictSchedule.guest_name}</strong> from <strong>${formattedStart}</strong> to <strong>${formattedEnd}</strong>.<br>
                        Please select a different time or room.
                    </p>
                </div>`;
        }
        
        if (checkinInput) {
            checkinInput.setCustomValidity('This time slot conflicts with an existing booking');
        }
        return false;
    }
}

// Add debug logging (remove after testing)
console.log('Rebook Schedules:', rebookRoomSchedules);
console.log('Checkin Schedules:', rebookCheckinSchedules);

// Fetch available rooms for rebooking
function fetchAvailableRooms() {
  fetch('get-available-rooms.php')
    .then(response => response.json())
    .then(data => {
      const select = document.getElementById('rebook_room_number');
      select.innerHTML = '<option value="">Choose a room...</option>';
      
      data.rooms.forEach(room => {
        const option = document.createElement('option');
        option.value = room.room_number;
        option.dataset.type = room.room_type;
        option.dataset.isOccupied = room.is_occupied ? '1' : '0';
        
        // Store all pricing tiers as data attributes
        option.dataset.price3hrs = room.price_3hrs;
        option.dataset.price6hrs = room.price_6hrs;
        option.dataset.price12hrs = room.price_12hrs;
        option.dataset.price24hrs = room.price_24hrs;
        option.dataset.priceOt = room.price_ot;
        
        // Add "(CURRENTLY BOOKED)" indicator for occupied rooms
        const occupiedLabel = room.is_occupied ? ' (Currently Booked)' : '';
        option.textContent = `Room ${room.room_number} - ${room.room_type}${occupiedLabel}`;
        
        // Optional: Add a visual indicator (different color/style)
        if (room.is_occupied) {
          option.style.color = '#dc3545'; // Red color for occupied rooms
          option.style.fontWeight = '500';
        }
        
        select.appendChild(option);
      });
    })
    .catch(error => console.error('Error fetching rooms:', error));
}

function openRebookModal(guestId) {
  if (!guestId) {
    Swal.fire('Error', 'Invalid guest ID', 'error');
    return;
  }

  fetch(`get-guest-data.php?guest_id=${guestId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const guest = data.guest;
        
        // Fill guest information
        document.getElementById('rebook_guest_id').value = guest.id;
        document.getElementById('rebook_guest_name').value = guest.guest_name;
        document.getElementById('rebook_telephone').value = guest.telephone;
        document.getElementById('rebook_address').value = guest.address;
        
        // ✅ Set minimum check-in time to current checkout
        const currentCheckout = new Date(guest.check_out_date);
        
        // Format for datetime-local input (YYYY-MM-DDTHH:MM)
        const year = currentCheckout.getFullYear();
        const month = String(currentCheckout.getMonth() + 1).padStart(2, '0');
        const day = String(currentCheckout.getDate()).padStart(2, '0');
        const hours = String(currentCheckout.getHours()).padStart(2, '0');
        const minutes = String(currentCheckout.getMinutes()).padStart(2, '0');
        
        const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        
        console.log('✅ Current checkout:', guest.check_out_date);
        console.log('✅ Minimum rebook time:', minDateTime);
        
        // Set the minimum attribute and default value
        const checkinInput = document.getElementById('rebook_checkin_date');
        // Don't set min attribute - we'll validate manually for same-day only
        checkinInput.value = minDateTime; // Default to checkout time
        
        // Store original checkout for validation
        checkinInput.dataset.originalCheckout = guest.check_out_date;
        
        // Add validation message
        const checkinLabel = document.querySelector('label[for="rebook_checkin_date"]');
        if (checkinLabel) {
          // Remove any existing validation message
          const existingMsg = checkinLabel.querySelector('.validation-msg');
          if (existingMsg) existingMsg.remove();
          
          // Add new validation message
          const validationMsg = document.createElement('small');
          validationMsg.className = 'validation-msg d-block text-info mt-1';
          validationMsg.innerHTML = `<i class="fas fa-info-circle me-1"></i>For same-day rebooking, must be ${currentCheckout.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })} or later`;
          checkinLabel.appendChild(validationMsg);
        }
        
        // Reset other fields
        document.getElementById('rebook_duration').value = 3;
        document.getElementById('rebook_payment_mode').value = 'cash';
        document.getElementById('rebook_amount_paid').value = '';
        document.getElementById('rebook_gcash_reference').value = '';
        document.getElementById('rebook_room_type').value = '';
        document.getElementById('rebook_checkout_date').value = '';
        document.getElementById('rebook_total_price').value = '';
        document.getElementById('rebook_change_amount').value = '';
        
        // Clear any previous messages
        document.getElementById('rebook_availability_message').innerHTML = '';
        
        fetchAvailableRooms();
        
        const modal = new bootstrap.Modal(document.getElementById('rebookModal'));
        modal.show();
      } else {
        Swal.fire('Error', data.message || 'Failed to fetch guest data', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Swal.fire('Error', 'An error occurred while fetching guest data', 'error');
    });
}

// Calculate price based on duration using tiered pricing
function calculateTieredPrice(duration, roomData) {
  const dur = parseInt(duration);
  
  console.log('🔢 Calculating price for duration:', dur, 'hours');
  console.log('📊 Room pricing data:', roomData);
  
  let price = 0;
  
  if (dur <= 3) {
    price = parseFloat(roomData.price3hrs) || 0;
    console.log('✅ Using 3-hour rate: ₱' + price);
  } else if (dur <= 6) {
    price = parseFloat(roomData.price6hrs) || 0;
    console.log('✅ Using 6-hour rate: ₱' + price);
  } else if (dur <= 12) {
    price = parseFloat(roomData.price12hrs) || 0;
    console.log('✅ Using 12-hour rate: ₱' + price);
  } else if (dur <= 24) {
    price = parseFloat(roomData.price24hrs) || 0;
    console.log('✅ Using 24-hour rate: ₱' + price);
  } else {
    const basePrice = parseFloat(roomData.price24hrs) || 0;
    const extraHours = dur - 24;
    const overtimeRate = parseFloat(roomData.priceOt) || 0;
    price = basePrice + (extraHours * overtimeRate);
    console.log('✅ Using 24hrs+ rate: ₱' + basePrice + ' + (' + extraHours + ' × ₱' + overtimeRate + ') = ₱' + price);
  }
  
  console.log('💵 Final calculated price: ₱' + price);
  return price;
}

function calculateRebookDetails() {
    console.log('🔄 calculateRebookDetails() called');
    
    const durationSelect = document.getElementById('rebook_duration');
    const checkinInput = document.getElementById('rebook_checkin_date');
    const roomSelect = document.getElementById('rebook_room_number');
    const checkoutDisplay = document.getElementById('rebook_checkout_date');
    const totalPriceInput = document.getElementById('rebook_total_price');
    
    if (!durationSelect || !checkinInput || !roomSelect || !checkoutDisplay || !totalPriceInput) {
        console.error('❌ Required elements not found!');
        return;
    }
    
    // ✅ Get the SELECTED duration (not calculated)
    const selectedHours = parseInt(durationSelect.value) || 0;
    const checkinDate = checkinInput.value;
    const selectedOption = roomSelect.options[roomSelect.selectedIndex];
    
    console.log('📋 Input values:');
    console.log('  Selected duration:', selectedHours, 'hours');
    console.log('  Check-in date:', checkinDate);
    console.log('  Room selected:', selectedOption.value);
    
    if (!checkinDate || !selectedOption.value || selectedHours <= 0) {
        return;
    }
    
    // ✅ Parse check-in date
    const checkin = new Date(checkinDate);
    
    // ✅ Calculate checkout = check-in + selected hours (NOT from original checkout!)
    const checkout = new Date(checkin.getTime() + selectedHours * 60 * 60 * 1000);
    
    console.log('✅ CALCULATION:');
    console.log('  Check-in:', checkin.toLocaleString());
    console.log('  + Duration:', selectedHours, 'hours');
    console.log('  = Checkout:', checkout.toLocaleString());
    
    // Format checkout display
    const formattedCheckout = checkout.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    checkoutDisplay.value = formattedCheckout;
    
    // ✅ Get room pricing from dropdown data attributes
    const roomData = {
        price3hrs: selectedOption.dataset.price3hrs || '0',
        price6hrs: selectedOption.dataset.price6hrs || '0',
        price12hrs: selectedOption.dataset.price12hrs || '0',
        price24hrs: selectedOption.dataset.price24hrs || '0',
        priceOt: selectedOption.dataset.priceOt || '0'
    };
    
    console.log('💰 Room pricing data:', roomData);
    
    // ✅ Calculate price based ONLY on selected duration
    const totalPrice = calculateTieredPrice(selectedHours, roomData);
    
    if (totalPrice <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Calculation Error',
            text: 'Unable to calculate room price.',
            confirmButtonColor: '#8b1d2d'
        });
        return;
    }
    
    totalPriceInput.value = totalPrice.toFixed(2);
    console.log('💵 Final price for', selectedHours, 'hours: ₱' + totalPrice.toFixed(2));
    
    // ✅ Store the selected duration (not calculated!)
    durationSelect.dataset.calculatedDuration = selectedHours;
    
    calculateRebookChange();
    checkRebookRoomAvailability();
}

// Toggle GCash reference field visibility
function toggleRebookGcash() {
    const mode = document.getElementById("rebook_payment_mode").value;
    const gcashWrapper = document.getElementById("rebook_gcash_ref_wrapper");
    
    if (gcashWrapper) {
        gcashWrapper.style.display = mode === "gcash" ? "flex" : "none";
        console.log('💳 Payment mode changed to:', mode);
    }
}

// Calculate change amount
function calculateRebookChange() {
    const totalPriceInput = document.getElementById("rebook_total_price");
    const amountPaidInput = document.getElementById("rebook_amount_paid");
    const changeAmountInput = document.getElementById("rebook_change_amount");
    
    if (!totalPriceInput || !amountPaidInput || !changeAmountInput) {
        console.error('❌ Change calculation elements not found');
        return;
    }
    
    const total = parseFloat(totalPriceInput.value) || 0;
    const paid = parseFloat(amountPaidInput.value) || 0;
    const change = paid - total;
    
    changeAmountInput.value = change > 0 ? change.toFixed(2) : "0.00";
    
    console.log('💵 Change calculation:');
    console.log('  Total: ₱' + total.toFixed(2));
    console.log('  Paid: ₱' + paid.toFixed(2));
    console.log('  Change: ₱' + (change > 0 ? change.toFixed(2) : '0.00'));
}

// Enhanced fetchAvailableRooms with pricing validation
function fetchAvailableRooms() {
    console.log('🏠 Fetching available rooms...');
    
    fetch('get-available-rooms.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('📋 Available rooms data received:', data);
            
            if (!data.success || !data.rooms) {
                throw new Error('Invalid data format received');
            }
            
            const select = document.getElementById('rebook_room_number');
            if (!select) {
                console.error('❌ Room select element not found');
                return;
            }
            
            select.innerHTML = '<option value="">Choose a room...</option>';
            
            let roomsAdded = 0;
            
            data.rooms.forEach(room => {
                const option = document.createElement('option');
                option.value = room.room_number;
                option.dataset.type = room.room_type;
                option.dataset.isOccupied = room.is_occupied ? '1' : '0';
                
                // ✅ CRITICAL: Store all pricing tiers as data attributes
                option.dataset.price3hrs = room.price_3hrs || '0';
                option.dataset.price6hrs = room.price_6hrs || '0';
                option.dataset.price12hrs = room.price_12hrs || '0';
                option.dataset.price24hrs = room.price_24hrs || '0';
                option.dataset.priceOt = room.price_ot || '0';
                
                console.log(`📊 Room ${room.room_number} pricing:`, {
                    '3hrs': room.price_3hrs,
                    '6hrs': room.price_6hrs,
                    '12hrs': room.price_12hrs,
                    '24hrs': room.price_24hrs,
                    'OT': room.price_ot,
                    'occupied': room.is_occupied
                });
                
                // Validate that room has pricing data
                if (!room.price_3hrs || parseFloat(room.price_3hrs) <= 0) {
                    console.warn(`⚠️ Room ${room.room_number} has no valid pricing data!`);
                }
                
                // Add "(CURRENTLY BOOKED)" indicator for occupied rooms
                const occupiedLabel = room.is_occupied ? ' (Currently Booked)' : '';
                option.textContent = `Room ${room.room_number} - ${room.room_type}${occupiedLabel}`;
                
                // Visual indicator for occupied rooms
                if (room.is_occupied) {
                    option.style.color = '#dc3545';
                    option.style.fontWeight = '500';
                }
                
                select.appendChild(option);
                roomsAdded++;
            });
            
            console.log(`✅ ${roomsAdded} room(s) added to dropdown`);
            
            if (roomsAdded === 0) {
                console.warn('⚠️ No rooms available');
                select.innerHTML = '<option value="">No rooms available</option>';
            }
        })
        .catch(error => {
            console.error('❌ Error fetching rooms:', error);
            Swal.fire({
                icon: 'error',
                title: 'Failed to Load Rooms',
                text: error.message || 'Could not fetch available rooms. Please try again.',
                confirmButtonColor: '#8b1d2d'
            });
        });
}

// ============================================
// 🎯 EVENT LISTENERS
// ============================================

// Duration change listener
document.getElementById('rebook_duration')?.addEventListener('change', function() {
    console.log('⏱️ Duration changed to:', this.value, 'hours');
    calculateRebookDetails();
});

// Simplify the frontend validation
document.getElementById('rebook_checkin_date')?.addEventListener('change', function() {
    const selectedTime = this.value;
    if (!selectedTime) return;
    
    const selectedTimestamp = new Date(selectedTime).getTime();
    const nowTimestamp = new Date().getTime();
    
    // Only warn about past dates, don't auto-correct
    if (selectedTimestamp < nowTimestamp) {
        console.log('⚠️ Selected time is in the past');
        // You can show a warning but don't prevent the user
    }
    
    calculateRebookDetails();
});

// Room selection change listener
document.getElementById('rebook_room_number')?.addEventListener('change', function() {
    console.log('🏠 Room changed to:', this.value);
    
    const selectedOption = this.options[this.selectedIndex];
    const roomType = selectedOption.dataset.type || '';
    
    console.log('  Room type:', roomType);
    console.log('  Pricing data:');
    console.log('    3hrs: ₱' + selectedOption.dataset.price3hrs);
    console.log('    6hrs: ₱' + selectedOption.dataset.price6hrs);
    console.log('    12hrs: ₱' + selectedOption.dataset.price12hrs);
    console.log('    24hrs: ₱' + selectedOption.dataset.price24hrs);
    console.log('    OT: ₱' + selectedOption.dataset.priceOt);
    
    document.getElementById('rebook_room_type').value = roomType;
    calculateRebookDetails();
});

// Amount paid input listener
document.getElementById('rebook_amount_paid')?.addEventListener('input', function() {
    console.log('💰 Amount paid changed to: ₱' + this.value);
    calculateRebookChange();
});

// Payment mode change listener
document.getElementById('rebook_payment_mode')?.addEventListener('change', toggleRebookGcash);

// GCash reference input validation (numbers only)
document.getElementById('rebook_gcash_reference')?.addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});

console.log('✅ Rebook calculation functions loaded successfully');

// Validate amount paid against total price
document.getElementById('rebook_amount_paid')?.addEventListener('input', function() {
  const totalText = document.getElementById('rebook_total_price').value;
  const total = parseFloat(totalText.replace('₱', '').replace(',', '')) || 0;
  const paid = parseFloat(this.value) || 0;
  const errorEl = document.getElementById('rebook_amount_error');

  if (paid < total && total > 0) {
    errorEl.classList.remove('d-none');
  } else {
    errorEl.classList.add('d-none');
  }

  calculateRebookChange();
});

// Validate GCash reference format (13 digits)
document.getElementById('rebook_gcash_reference')?.addEventListener('input', function() {
  const errorEl = document.getElementById('rebook_gcash_error');
  const isValid = /^\d{13}$/.test(this.value);

  if (this.value && !isValid) {
    errorEl.classList.remove('d-none');
  } else {
    errorEl.classList.add('d-none');
  }
});




// Submit rebook
function submitRebook() {
    const form = document.getElementById('rebookForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const checkinInput = document.getElementById('rebook_checkin_date');
    const messageDiv = document.getElementById('rebook_availability_message');
    const selectedTime = checkinInput.value;
    
    if (!selectedTime) {
        alert('Please select a check-in time');
        return;
    }

    console.log('🚀 Submitting rebook - checking availability...');
    const isAvailable = checkRebookRoomAvailability();
    console.log('📋 Availability check result:', isAvailable);
    
    if (!isAvailable) {
        console.log('❌ Blocking submission - room not available');
        
        if (messageDiv) {
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        return;
    }
    
    console.log('✅ Proceeding with rebook submission');

    // Validate payment
    const totalText = document.getElementById('rebook_total_price').value;
    const total = parseFloat(totalText.replace('₱', '').replace(',', '')) || 0;
    const paid = parseFloat(document.getElementById('rebook_amount_paid').value) || 0;
    const mode = document.getElementById('rebook_payment_mode').value;
    const gcashRef = document.getElementById('rebook_gcash_reference').value.trim();

    if (paid < total) {
        Swal.fire('Invalid Payment', 'Amount paid cannot be less than total price.', 'warning');
        return;
    }

    if (mode === 'gcash' && !/^\d{13}$/.test(gcashRef)) {
        Swal.fire('Invalid GCash Reference', 'Please enter a valid 13-digit GCash reference number.', 'warning');
        return;
    }
  
    const formData = new FormData(form);
    const totalPrice = parseFloat(document.getElementById('rebook_total_price').value) || 0;
    const amountPaid = parseFloat(document.getElementById('rebook_amount_paid').value) || 0;
    const changeAmount = amountPaid - totalPrice;
  
    formData.append('action', 'rebook');
    formData.append('total_price', totalPrice);
    formData.append('change_amount', changeAmount >= 0 ? changeAmount : 0);

    // ✅ FIX: Use the SELECTED duration from dropdown, not calculated
    const durationSelect = document.getElementById('rebook_duration');
    const selectedDuration = parseInt(durationSelect.value) || 0;
    
    formData.set('stay_duration', selectedDuration);
    console.log('✅ Using selected duration:', selectedDuration, 'hours');
      
    const checkInInput = document.getElementById('rebook_checkin_date');
    if (checkInInput && checkInInput.value) {
      const localDateTime = new Date(checkInInput.value);
      
      const mysqlDateTime = localDateTime.getFullYear() + '-' + 
                            String(localDateTime.getMonth() + 1).padStart(2, '0') + '-' + 
                            String(localDateTime.getDate()).padStart(2, '0') + ' ' + 
                            String(localDateTime.getHours()).padStart(2, '0') + ':' + 
                            String(localDateTime.getMinutes()).padStart(2, '0') + ':00';
      
      formData.set('check_in_date', mysqlDateTime);
      
      console.log('🕒 Selected datetime-local value:', checkInInput.value);
      console.log('🕒 Formatted for MySQL:', mysqlDateTime);
    }

    console.log('📤 Sending rebook data to server...');
    
    Swal.fire({
        title: 'Processing...',
        text: 'Rebooking guest, please wait...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
        
    fetch('process-rebook.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📥 Response:', data);
        
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message || 'Guest has been rebooked successfully',
                icon: 'success',
                confirmButtonColor: '#8b1d2d'
            }).then(() => {
                // ✅ FIX: Reload page to show updated duration
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to rebook guest',
                icon: 'error',
                confirmButtonColor: '#8b1d2d'
            });
        }
    })
    .catch(error => {
        console.error('❌ Fetch Error:', error);
        
        Swal.close();
        
        Swal.fire({
            title: 'Network Error',
            text: error.message || 'An error occurred while connecting to server',
            icon: 'error',
            confirmButtonColor: '#8b1d2d'
        });
    });
}


// ============================================
// 🎯 EVENT LISTENERS FOR REBOOK MODAL
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Room selection listener
    const roomSelect = document.getElementById('rebook_room_number');
    if (roomSelect) {
        roomSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const roomType = selectedOption.dataset.type || '';
            const isOccupied = selectedOption.dataset.isOccupied === '1';
            
            document.getElementById('rebook_room_type').value = roomType;
            
            if (isOccupied && this.value) {
                const messageDiv = document.getElementById('rebook_availability_message');
                messageDiv.innerHTML = `
                    <div class="alert alert-info border-0 shadow-sm" style="background-color: #d1ecf1; border-radius: 12px;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            <span class="text-info fw-medium">
                                This room is currently occupied. Please verify the booking time doesn't conflict.
                            </span>
                        </div>
                    </div>`;
            }
            
            calculateRebookDetails();
        });
    }

    // ✅ NEW: Check-in date/time validation listener
    const checkinInput = document.getElementById('rebook_checkin_date');
    if (checkinInput) {
        checkinInput.addEventListener('change', function() {
            const minTime = this.min;
            const selectedTime = this.value;
            const messageDiv = document.getElementById('rebook_availability_message');
            
            if (!selectedTime || !minTime) {
                return;
            }
            
            // ✅ Convert to timestamps for accurate comparison
            const minTimestamp = new Date(minTime).getTime();
            const selectedTimestamp = new Date(selectedTime).getTime();
            
            console.log('📅 Check-in validation:');
            console.log('  Selected:', new Date(selectedTime).toLocaleString());
            console.log('  Minimum:', new Date(minTime).toLocaleString());
            console.log('  Selected timestamp:', selectedTimestamp);
            console.log('  Minimum timestamp:', minTimestamp);
            console.log('  Is valid?', selectedTimestamp >= minTimestamp);
            
            // ✅ Only show error if ACTUALLY before the minimum time
            if (selectedTimestamp < minTimestamp) {
                console.log('❌ Selected time is before checkout time!');
                
                if (messageDiv) {
                    const minDate = new Date(minTime);
                    messageDiv.innerHTML = `
                        <div class="alert alert-warning border-0 shadow-sm" style="background-color: #fff3cd; border-radius: 12px;">
                            <div class="mb-2">
                                <strong class="text-warning" style="font-size: 1.1rem;">⚠️ Invalid Check-in Time</strong>
                            </div>
                            <p class="mb-0 text-dark">
                                Check-in time must be <strong>${minDate.toLocaleString('en-US', {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                })}</strong> or later.
                            </p>
                        </div>`;
                    
                    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                // Reset to minimum time
                this.value = minTime;
            } else {
                console.log('✅ Valid check-in time selected');
                
                // Clear warning message if valid
                if (messageDiv) {
                    const hasWarning = messageDiv.querySelector('.alert-warning');
                    if (hasWarning) {
                        messageDiv.innerHTML = '';
                    }
                }
            }
            
            // Recalculate after validation
            calculateRebookDetails();
        });
        
        // ✅ ADDED: Also validate on input (while typing)
        checkinInput.addEventListener('input', function() {
            const minTime = this.min;
            const selectedTime = this.value;
            
            if (!selectedTime || !minTime) {
                return;
            }
            
            const minTimestamp = new Date(minTime).getTime();
            const selectedTimestamp = new Date(selectedTime).getTime();
            
            // If user manually types an invalid time, correct it
            if (selectedTimestamp < minTimestamp) {
                console.log('⚠️ User typed invalid time, correcting...');
                this.value = minTime;
            }
        });
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
          <label style="display: block; color: #ccc; font-size: 14px; margin-bottom: 6px; font-weight: 500;">GCash Reference (13 digits):</label>
          <input id="gcash_reference" placeholder="Enter 13-digit reference number" maxlength="13"
              style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 6px; font-size: 15px; background: #222; color: #eee; box-sizing: border-box;" />
          <small style="color: #888; font-size: 12px; display: block; margin-top: 4px;">Only numbers allowed (exactly 13 digits)</small>
      </div>
    `,
    background: '#1a1a1a',
    color: '#fff',
    showCancelButton: true,
    confirmButtonText: 'Submit Payment',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#8b1d2d',
    cancelButtonColor: '#555',
    customClass: {
      popup: 'payment-modal-popup'
    },
    width: '500px',
    padding: '1.5rem',
    didOpen: () => {
      const modeSelect = document.getElementById('payment_mode');
      const gcashWrapper = document.getElementById('gcash_ref_wrapper');
      const gcashInput = document.getElementById('gcash_reference');
      
      // Show/hide GCash reference field based on payment method
      modeSelect.addEventListener('change', () => {
        gcashWrapper.style.display = modeSelect.value === 'gcash' ? 'block' : 'none';
      });

      // Restrict input to numbers only
      gcashInput.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
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

      // Validate GCash reference if GCash is selected
      if (mode === 'gcash') {
        if (!gcash_reference) {
          Swal.showValidationMessage("Please enter a GCash reference number.");
          return false;
        }
        
        // Check if it contains only digits
        if (!/^\d+$/.test(gcash_reference)) {
          Swal.showValidationMessage("GCash reference must contain only numbers.");
          return false;
        }
        
        // Check if it's exactly 13 digits
        if (gcash_reference.length !== 13) {
          Swal.showValidationMessage("GCash reference must be exactly 13 digits.");
          return false;
        }
      }

      return { amount, mode, gcash_reference };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      const payload = new URLSearchParams();
      payload.append('action', 'add_payment');
      payload.append('guest_id', paymentDetails.guest_id);
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
                    // Automatically check out guest if full payment done
                    checkOutGuest(guestId);
                } else {
                    // Refresh to show updated "Paid", "Change", or "Due" values
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
            
            console.log('=== RECEIPT DATA ===');
            console.log('Total Price:', guest.total_price);
            console.log('Previous Charges:', guest.previous_charges);
            console.log('New Charges:', guest.new_charges);
            console.log('Is Rebooked:', guest.is_rebooked);
            console.log('Orders Total:', guest.orders_total);
            console.log('Grand Total:', guest.grand_total);
            console.log('===================');
            
            const roomCharge = parseFloat(guest.total_price || 0);
            const ordersTotal = parseFloat(guest.orders_total || 0);
            const previousCharges = parseFloat(guest.previous_charges || 0);
            const newCharges = parseFloat(guest.new_charges || 0);
            const isRebooked = guest.is_rebooked || previousCharges > 0;
            
            // Grand total calculation
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

            // Build rebooking info section
            let rebookSection = '';
            if (isRebooked && guest.rebook_info) {
                const oldInfo = guest.rebook_info;
                rebookSection = `
                    <div class="rebook-notice">
                        <div class="alert alert-info" style="background: #e3f2fd; border: 1px solid #2196f3; border-radius: 8px; padding: 12px; margin: 15px 0;">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <i class="fas fa-redo" style="color: #2196f3; margin-right: 8px;"></i>
                                <strong style="color: #1976d2;">Rebooked Guest</strong>
                            </div>
                            <div style="font-size: 0.85rem; color: #555; line-height: 1.6;">
                                <div>Previous Stay: Room ${oldInfo.old_room} (${oldInfo.old_duration} hrs)</div>
                                <div>Previous Period: ${new Date(oldInfo.old_checkin).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })} - ${new Date(oldInfo.old_checkout).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            // ✅ FIXED: Charges breakdown
            let chargesBreakdown = '';
            if (isRebooked && previousCharges > 0) {
                // Show breakdown for rebooked guests
                const totalRoomCharges = previousCharges + newCharges;
                chargesBreakdown = `
                    <div class="summary-row" style="background: #f8f9fa; padding: 8px 0; margin: 8px 0; border-radius: 4px;">
                        <span style="color: #666;">Previous Stay Charges</span>
                        <span style="color: #666;">₱${previousCharges.toFixed(2)}</span>
                    </div>
                    <div class="summary-row" style="background: #e8f5e9; padding: 8px 0; margin: 8px 0; border-radius: 4px;">
                        <span style="color: #2e7d32; font-weight: 600;">Additional Charges (Rebook)</span>
                        <span style="color: #2e7d32; font-weight: 600;">₱${newCharges.toFixed(2)}</span>
                    </div>
                    <div class="summary-row" style="padding-top: 8px; border-top: 1px dashed #dee2e6;">
                        <span style="font-weight: 500;">Total Room Charges (${guest.stay_duration} hrs)</span>
                        <span style="font-weight: 500;">₱${totalRoomCharges.toFixed(2)}</span>
                    </div>
                `;
            } else {
                // Regular single stay
                chargesBreakdown = `
                    <div class="summary-row room-charge">
                        <span>Room Charge (${guest.stay_duration} hrs)</span>
                        <span>₱${roomCharge.toFixed(2)}</span>
                    </div>
                `;
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
                    .rebook-notice {
                        margin: 15px 0;
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
                        Current Stay: ${guest.check_in_date} - ${guest.check_out_date}
                    </div>
                    ${rebookSection}
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
                    <div class="summary-section">
                        <div class="summary-row">
                            <span>Orders Subtotal</span>
                            <span>₱${ordersTotal.toFixed(2)}</span>
                        </div>
                    </div>
                    <hr style="margin: 10px 0; border-top: 1px dashed #dee2e6;">
                    ` : ''}

                    <div class="summary-section">
                        ${chargesBreakdown}

                        ${ordersRows ? `
                        <div class="summary-row" style="padding-top: 8px; border-top: 1px dashed #dee2e6;">
                            <span>Orders Total</span>
                            <span>₱${ordersTotal.toFixed(2)}</span>
                        </div>
                        ` : ''}

                        <div class="summary-row total-row">
                            <span>GRAND TOTAL</span>
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
                        ${isRebooked ? '<div style="margin-top: 8px; font-size: 0.8rem; color: #2196f3;"><i class="fas fa-info-circle"></i> This receipt includes charges from rebooked stay</div>' : ''}
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



