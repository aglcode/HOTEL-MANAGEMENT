<?php
session_start();
require_once 'database.php';

// Handle AJAX requests for check-out and extend functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $guest_id = (int)($_POST['guest_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($action === 'checkout' && $guest_id > 0) {
        // Get guest information
        $stmt = $conn->prepare("SELECT * FROM checkins WHERE id = ? AND check_out_date > NOW()");
        $stmt->bind_param('i', $guest_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $guest = $result->fetch_assoc();
        $stmt->close();
        
        if ($guest) {
            // Check if payment is sufficient
            $total_cost = floatval($guest['total_price']);
            $amount_paid = floatval($guest['amount_paid']);
            $balance = $total_cost - $amount_paid;
            
            if ($balance > 0) {
                // Payment insufficient - return payment details for additional payment
                echo json_encode([
                    'success' => false, 
                    'payment_required' => true,
                    'message' => 'Additional payment required',
                    'payment_details' => [
                        'guest_id' => $guest_id,
                        'guest_name' => $guest['guest_name'],
                        'room_number' => $guest['room_number'],
                        'total_cost' => number_format($total_cost, 2),
                        'amount_paid' => number_format($amount_paid, 2),
                        'balance_due' => number_format($balance, 2),
                        'balance_amount' => $balance
                    ]
                ]);
            } else {
                // Payment sufficient - proceed with checkout
                // Update check-out date to now
                $stmt = $conn->prepare("UPDATE checkins SET check_out_date = NOW() WHERE id = ?");
                $stmt->bind_param('i', $guest_id);
                $stmt->execute();
                $stmt->close();
                
                // Update room status to available
                $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE room_number = ?");
                $stmt->bind_param('i', $guest['room_number']);
                $stmt->execute();
                $stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Guest checked out successfully']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Guest not found or already checked out']);
        }
        exit;
    }

    // Add new action for additional payment
    if ($action === 'add_payment' && $guest_id > 0) {
        $additional_amount = floatval($_POST['additional_amount'] ?? 0);
        $payment_mode = $_POST['payment_mode'] ?? 'cash';
        $gcash_reference = $_POST['gcash_reference'] ?? '';
        
        if ($additional_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
            exit;
        }
        
        // Get current guest data
        $stmt = $conn->prepare("SELECT * FROM checkins WHERE id = ?");
        $stmt->bind_param('i', $guest_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $guest = $result->fetch_assoc();
        $stmt->close();
        
        if ($guest) {
            $new_amount_paid = floatval($guest['amount_paid']) + $additional_amount;
            $new_total_cost = floatval($guest['total_price']);
            $new_change = max(0, $new_amount_paid - $new_total_cost);
            
            // Update payment information
            $stmt = $conn->prepare("
                UPDATE checkins 
                SET amount_paid = ?, change_amount = ?, payment_mode = ?, gcash_reference = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('ddssi', $new_amount_paid, $new_change, $payment_mode, $gcash_reference, $guest_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Payment added successfully',
                'new_amount_paid' => number_format($new_amount_paid, 2),
                'change_amount' => number_format($new_change, 2),
                'can_checkout' => $new_amount_paid >= $new_total_cost
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Guest not found']);
        }
        exit;
    }
    
    if ($action === 'extend' && $guest_id > 0) {
        // Get guest and room information
        $stmt = $conn->prepare("
            SELECT c.*, r.price_3hrs 
            FROM checkins c 
            JOIN rooms r ON c.room_number = r.room_number 
            WHERE c.id = ? AND c.check_out_date > NOW()
        ");
        $stmt->bind_param('i', $guest_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $guest = $result->fetch_assoc();
        $stmt->close();
        
        if ($guest) {
            // Calculate new check-out time (extend by 1 hour)
            $current_checkout = new DateTime($guest['check_out_date']);
            $new_checkout = $current_checkout->modify('+1 hour');
            $new_checkout_str = $new_checkout->format('Y-m-d H:i:s');
            
            // Calculate additional price (1 hour extension = price_3hrs / 3)
            $hourly_rate = $guest['price_3hrs'] / 3;
            $new_total_price = $guest['total_price'] + $hourly_rate;
            
            // Update check-out time, stay duration, and total price
            $new_duration = $guest['stay_duration'] + 1;
            $stmt = $conn->prepare("
                UPDATE checkins 
                SET check_out_date = ?, stay_duration = ?, total_price = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('sidi', $new_checkout_str, $new_duration, $new_total_price, $guest_id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stay extended by 1 hour',
                'new_checkout' => $new_checkout->format('M j, Y g:i A'),
                'new_total' => number_format($new_total_price, 2),
                'additional_cost' => number_format($hourly_rate, 2)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Guest not found or already checked out']);
        }
        exit;
    }
}

// Total Bookings Count
$total_bookings_result = $conn->query("SELECT COUNT(*) AS total FROM checkins");
$total_bookings_row = $total_bookings_result->fetch_assoc();
$total_bookings = $total_bookings_row['total'];

// Total Check-in Guests Count
$checkin_count_result = $conn->query("SELECT COUNT(*) AS total FROM checkins WHERE NOW() BETWEEN check_in_date AND check_out_date");
$checkin_count_row = $checkin_count_result->fetch_assoc();
$currently_checked_in = $checkin_count_row['total'];

// Calculate total revenue from checkins table
$revenue_result = $conn->query("SELECT SUM(total_price) AS total_revenue FROM checkins");
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'] ?? 0;

// Fetch currently checked-in guests
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
    gcash_reference
FROM checkins
WHERE check_out_date > NOW()
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
    receptionist_id
FROM checkins WHERE 1=1";

// Apply filter
if ($filter === 'recent') {
    $history_sql .= " AND check_in_date > DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter === 'past') {
    $history_sql .= " AND check_out_date < NOW()";
} elseif ($filter === 'current') {
    $history_sql .= " AND NOW() BETWEEN check_in_date AND check_out_date";
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
        'Payment Mode', 'Amount Paid', 'Change', 'Total Price', 'GCash Reference'
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
            $row['gcash_reference'] ?? 'N/A'
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}

// Reset the result pointer for normal page display
$history_guests = $conn->query($history_sql);

// Calculate statistics from checkins table
$total_checkins_result = $conn->query("SELECT COUNT(*) AS total_checkins FROM checkins");
$total_checkins = $total_checkins_result->fetch_assoc()['total_checkins'];

$current_checkins_result = $conn->query("SELECT COUNT(*) AS current_checkins FROM checkins WHERE NOW() BETWEEN check_in_date AND check_out_date");
$current_checkins = $current_checkins_result->fetch_assoc()['current_checkins'];

$past_checkins_result = $conn->query("SELECT COUNT(*) AS past_checkins FROM checkins WHERE check_out_date < NOW()");
$past_checkins = $past_checkins_result->fetch_assoc()['past_checkins'];

$total_revenue_result = $conn->query("SELECT SUM(total_price) AS total_revenue FROM checkins");
$total_revenue_checkins = $total_revenue_result->fetch_assoc()['total_revenue'] ?? 0;


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gitarra Apartelle - Guest Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
  <style>
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
    
    /* Print-friendly styles */
    @media print {
      .sidebar, .search-filter-container, .no-print {
        display: none !important;
      }
      
      .content {
        margin-left: 0 !important;
        width: 100% !important;
      }
      
      .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
      }
      
      body {
        background-color: white !important;
      }
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="user-info">
    <a href="receptionist-profile.php" class="text-white text-decoration-none d-flex flex-column align-items-center">
      <i class="fa-solid fa-user-circle" style="font-size: 60px;"></i>
      <p class="mt-2 mb-0">Receptionist</p>
    </a>
  </div>
  <a href="receptionist-dash.php"><i class="fa-solid fa-tachometer-alt"></i> Dashboard</a>
  <a href="receptionist-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
  <a href="receptionist-guest.php" class="active"><i class="fa-solid fa-users"></i> Guest</a>
  <a href="receptionist-booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a>
  <a href="receptionist-payment.php"><i class="fa-solid fa-money-check-alt"></i> Payment</a>
  <a href="signin.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
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
  <!-- Statistics Cards -->
  <div class="row mb-4">
    <!-- Current Check-ins Card -->
    <div class="col-md-4 mb-3">
      <div class="card stat-card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
            <i class="fas fa-user-check"></i>
          </div>
          <div>
            <h3 class="fw-bold mb-1"><?= $currently_checked_in ?></h3>
            <p class="text-muted mb-0">Current Guests</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Total Bookings Card -->
    <div class="col-md-4 mb-3">
      <div class="card stat-card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div>
            <h3 class="fw-bold mb-1"><?= $total_bookings ?></h3>
            <p class="text-muted mb-0">Total Bookings</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Revenue Card -->
    <div class="col-md-4 mb-3">
      <div class="card stat-card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
            <i class="fas fa-coins"></i>
          </div>
          <div>
            <h3 class="fw-bold mb-1">₱<?= number_format($total_revenue, 2) ?></h3>
            <p class="text-muted mb-0">Total Revenue</p>
          </div>
        </div>
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
          <button onclick="window.print()" class="btn btn-outline-info w-100">
            <i class="fas fa-print me-2"></i>Print Report
          </button>
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
        <button type="submit" class="btn btn-primary me-2 flex-grow-1">
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
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Currently Checked-In Guests</h5>
      <span class="badge bg-light text-primary rounded-pill"><?= $current_guests->num_rows ?> Active Guests</span>
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
                    <small class="text-muted">Total: ₱<?= number_format($guest['total_price'] ?? 0, 2) ?></small>
                    <small class="text-muted">Paid: ₱<?= number_format($guest['amount_paid'] ?? 0, 2) ?></small>
                    <?php if (($guest['change_amount'] ?? 0) > 0): ?>
                    <small class="text-info">Change: ₱<?= number_format($guest['change_amount'], 2) ?></small>
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

  <!-- Guest Check-Out History -->
<!-- Guest Check-In History -->
<div class="card mb-4">
  <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Guest Check-In History</h5>
    <div class="d-flex align-items-center">
      <span class="badge bg-light text-info rounded-pill me-2"><?= $history_guests->num_rows ?> Records</span>
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
        <table class="table table-hover mb-0">
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
              $is_checked_out = $checkout_date <= new DateTime();
              $is_active = new DateTime() >= $checkin_date && new DateTime() < $checkout_date;
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
                  $balance = ($row['total_price'] ?? 0) - ($row['amount_paid'] ?? 0);
                  if ($balance > 0): 
                  ?>
                  <div class="text-danger fw-bold">Due: ₱<?= number_format($balance, 2) ?></div>
                  <?php elseif (($row['change_amount'] ?? 0) > 0): ?>
                  <div class="text-info">Change: ₱<?= number_format($row['change_amount'], 2) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($row['gcash_reference'])): ?>
                  <div class="text-primary small">Ref: <?= htmlspecialchars($row['gcash_reference']) ?></div>
                  <?php endif; ?>
                </div>
              </td>
              <td class="fw-bold text-success">₱<?= number_format($row['total_price'] ?? 0, 2) ?></td>
              <td class="no-print">
                <?php if ($is_active): ?>
                  <span class="badge bg-success">
                    <i class="fas fa-clock me-1"></i>Active
                  </span>
                <?php elseif ($is_checked_out): ?>
                  <span class="badge bg-secondary">
                    <i class="fas fa-check-circle me-1"></i>Checked Out
                  </span>
                <?php else: ?>
                  <span class="badge bg-warning">
                    <i class="fas fa-hourglass-half me-1"></i>Scheduled
                  </span>
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
          <div class="col-md-3">
            <div class="fw-bold text-info">₱<?= number_format($total_revenue_checkins, 2) ?></div>
            <small class="text-muted">Total Revenue</small>
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

</div>
</div>

<script>
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
        alert('Invalid guest ID');
        return;
    }
    
    if (confirm('Are you sure you want to check out this guest?')) {
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
            } else if (data.payment_required) {
                // Show payment form for insufficient payment
                showPaymentForm(data.payment_details);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while checking out the guest.');
        });
    }
  }

  function showPaymentForm(paymentDetails) {
    const modalHtml = `
        <div class="modal fade" id="paymentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Additional Payment Required</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong>Payment Insufficient!</strong><br>
                            Guest <strong>${paymentDetails.guest_name}</strong> (Room #${paymentDetails.room_number}) needs to pay additional amount.
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Total Cost:</strong><br>
                                <span class="h5 text-primary">₱${paymentDetails.total_cost}</span>
                            </div>
                            <div class="col-6">
                                <strong>Amount Paid:</strong><br>
                                <span class="h5 text-success">₱${paymentDetails.amount_paid}</span>
                            </div>
                        </div>
                        
                        <div class="alert alert-danger">
                            <strong>Balance Due: ₱${paymentDetails.balance_due}</strong>
                        </div>
                        
                        <form id="additionalPaymentForm">
                            <div class="mb-3">
                                <label for="additionalAmount" class="form-label">Additional Payment Amount</label>
                                <input type="number" class="form-control" id="additionalAmount" 
                                       min="0.01" step="0.01" value="${paymentDetails.balance_amount}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="paymentMode" class="form-label">Payment Method</label>
                                <select class="form-select" id="paymentMode" required>
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="gcashReferenceDiv" style="display: none;">
                                <label for="gcashReference" class="form-label">GCash Reference Number</label>
                                <input type="text" class="form-control" id="gcashReference">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="processAdditionalPayment(${paymentDetails.guest_id})">Add Payment & Checkout</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('paymentModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show/hide GCash reference field based on payment method
    document.getElementById('paymentMode').addEventListener('change', function() {
        const gcashDiv = document.getElementById('gcashReferenceDiv');
        if (this.value === 'gcash') {
            gcashDiv.style.display = 'block';
            document.getElementById('gcashReference').required = true;
        } else {
            gcashDiv.style.display = 'none';
            document.getElementById('gcashReference').required = false;
        }
    });
    
    // Show modal
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    paymentModal.show();
}

function processAdditionalPayment(guestId) {
    const additionalAmount = document.getElementById('additionalAmount').value;
    const paymentMode = document.getElementById('paymentMode').value;
    const gcashReference = document.getElementById('gcashReference').value;
    
    if (!additionalAmount || additionalAmount <= 0) {
        alert('Please enter a valid payment amount');
        return;
    }
    
    if (paymentMode === 'gcash' && !gcashReference.trim()) {
        alert('Please enter GCash reference number');
        return;
    }
    
    // Process additional payment
    fetch('receptionist-guest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_payment&guest_id=${guestId}&additional_amount=${additionalAmount}&payment_mode=${paymentMode}&gcash_reference=${gcashReference}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Payment added successfully!\nNew Amount Paid: ₱${data.new_amount_paid}\nChange: ₱${data.change_amount}`);
            
            // Close payment modal
            const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            paymentModal.hide();
            
            if (data.can_checkout) {
                // Proceed with checkout
                proceedWithCheckout(guestId);
            } else {
                // Refresh page to show updated payment
                location.reload();
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing payment.');
    });
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
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while checking out the guest.');
    });
}  
  // Function to extend guest stay
  function extendStay(guestId) {
      if (!guestId) {
          alert('Invalid guest ID');
          return;
      }
      
      if (confirm('Extend stay by 1 hour? Additional charges will apply.')) {
          fetch('receptionist-guest.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `action=extend&guest_id=${guestId}`
          })
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert(`${data.message}\nNew checkout time: ${data.new_checkout}\nAdditional cost: ₱${data.additional_cost}\nNew total: ₱${data.new_total}`);
                  location.reload(); // Refresh the page to show updated information
              } else {
                  alert('Error: ' + data.message);
              }
          })
          .catch(error => {
              console.error('Error:', error);
              alert('An error occurred while extending the stay.');
          });
      }
  }
  
  // Function to print guest receipt
  function printReceipt(guestId) {
      if (!guestId) {
          alert('Invalid guest ID');
          return;
      }
      
      // Fetch guest data for receipt
      fetch(`get-guest-receipt.php?guest_id=${guestId}`)
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  const guest = data.guest;
                  const receiptContent = `
                      <div class="text-center mb-4">
                          <h4 class="mb-1">Gitarra Apartelle</h4>
                          <p class="text-muted mb-1">123 Main Street, Anytown</p>
                          <p class="text-muted mb-0">Tel: (123) 456-7890</p>
                      </div>
                      
                      <div class="border-top border-bottom py-3 mb-4">
                          <div class="row mb-2">
                              <div class="col-6 text-muted">Receipt #:</div>
                              <div class="col-6 text-end">GIT-${guest.id}-${new Date().getFullYear()}</div>
                          </div>
                          <div class="row mb-2">
                              <div class="col-6 text-muted">Guest:</div>
                              <div class="col-6 text-end">${guest.guest_name}</div>
                          </div>
                          <div class="row mb-2">
                              <div class="col-6 text-muted">Room:</div>
                              <div class="col-6 text-end">#${guest.room_number} (${guest.room_type})</div>
                          </div>
                          <div class="row mb-2">
                              <div class="col-6 text-muted">Check-in:</div>
                              <div class="col-6 text-end">${new Date(guest.check_in_date).toLocaleDateString()}</div>
                          </div>
                          <div class="row">
                              <div class="col-6 text-muted">Check-out:</div>
                              <div class="col-6 text-end">${new Date(guest.check_out_date).toLocaleDateString()}</div>
                          </div>
                      </div>
                      
                      <h5 class="mb-3">Booking Details</h5>
                      <div class="table-responsive">
                          <table class="table table-sm">
                              <tbody>
                                  <tr>
                                      <td>Room Charge (${guest.stay_duration} hrs)</td>
                                      <td class="text-end">₱${parseFloat(guest.total_price).toFixed(2)}</td>
                                  </tr>
                                  <tr class="fw-bold">
                                      <td>Total</td>
                                      <td class="text-end">₱${parseFloat(guest.total_price).toFixed(2)}</td>
                                  </tr>
                                  <tr>
                                      <td>Amount Paid</td>
                                      <td class="text-end">₱${parseFloat(guest.amount_paid).toFixed(2)}</td>
                                  </tr>
                                  <tr>
                                      <td>Change</td>
                                      <td class="text-end">₱${parseFloat(guest.change_amount).toFixed(2)}</td>
                                  </tr>
                                  <tr>
                                      <td>Payment Method</td>
                                      <td class="text-end">${guest.payment_mode.toUpperCase()}</td>
                                  </tr>
                              </tbody>
                          </table>
                      </div>
                      
                      <div class="text-center mt-4">
                          <p class="mb-1">Thank you for choosing Gitarra Apartelle!</p>
                          <p class="text-muted small">This receipt is computer generated and does not require signature.</p>
                      </div>
                  `;
                  
                  document.getElementById('receiptContent').innerHTML = receiptContent;
                  const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
                  receiptModal.show();
              } else {
                  alert('Error loading receipt data');
              }
          })
          .catch(error => {
              console.error('Error:', error);
              alert('An error occurred while loading the receipt.');
          });
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
</script>

</body>
</html>




