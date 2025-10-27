<?php
session_start();
require_once 'database.php';

// Handle adding an announcement
if (isset($_POST['add_announcement'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $created_by = isset($_SESSION['role']) ? $_SESSION['role'] : 'Admin'; // Assuming user type is stored in session
    
    $sql = "INSERT INTO announcements (title, message, created_by) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $title, $message, $created_by);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['success_msg'] = "Announcement posted successfully!";
    header("Location: admin-dashboard.php");
    exit();
}

// Handle deleting an announcement
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['success_msg'] = "Announcement deleted successfully!";
    header("Location: admin-dashboard.php");
    exit();
}

// Handle resolving a feedback/complaint
if (isset($_GET['resolve'])) {
    $id = $_GET['resolve'];
    $sql = "UPDATE feedback SET status = 'resolved' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['success_msg'] = "Feedback marked as resolved!";
    header("Location: admin-dashboard.php");
    exit();
}

// Create feedback table if it doesn't exist
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'feedback'");
    if ($table_check->num_rows == 0) {
        // Table doesn't exist, create it
        $create_table_sql = "CREATE TABLE `feedback` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `guest_name` varchar(100) NOT NULL,
            `email` varchar(100) DEFAULT NULL,
            `type` enum('feedback','complaint') NOT NULL,
            `message` text NOT NULL,
            `status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $conn->query($create_table_sql);
    }
} catch (Exception $e) {
    // If there's an error creating the table, just continue
}

// Fetch all announcements
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");

// Get check-in statistics
$total_checkins = 0;
$current_guests = 0;
$total_revenue = 0;

try {
    // Total check-ins (all time)
    $result = $conn->query("SELECT COUNT(*) as total FROM checkins");
    if ($result && $row = $result->fetch_assoc()) {
        $total_checkins = $row['total'];
    }
    
    // Current guests (checked in but not checked out)
    $result = $conn->query("SELECT COUNT(*) as current FROM checkins WHERE check_out_date IS NULL OR check_out_date > NOW()");
    if ($result && $row = $result->fetch_assoc()) {
        $current_guests = $row['current'];
    }
    
    // Total revenue
    $result = $conn->query("SELECT SUM(amount_paid) as revenue FROM checkins");
    if ($result && $row = $result->fetch_assoc()) {
        $total_revenue = $row['revenue'] ?: 0;
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get low stock items count
$low_stock_count = 0;
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM supplies WHERE quantity < 5");
    if ($result && $row = $result->fetch_assoc()) {
        $low_stock_count = $row['count'];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get recent feedback/complaints
$feedback = [];
try {
    $result = $conn->query("SELECT * FROM feedback ORDER BY created_at DESC LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $feedback[] = $row;
        }
    }
} catch (Exception $e) {
    // Handle error silently
}

// Get history logs - Combined bookings and check-ins with receptionist info
$history_logs = [];
try {
    // Get bookings that are pending, upcoming, or cancelled
    $bookings_query = "
        SELECT 
            b.id,
            b.guest_name,
            b.email,
            b.room_number,
            b.start_date as date,
            b.end_date,
            b.duration,
            b.total_price,
            b.status,
            b.booking_token as reference,
            u.name as receptionist_name,
            'Booking' as type,
            b.created_at
        FROM bookings b
        LEFT JOIN users u ON b.cancelled_by = u.user_id
        WHERE b.status IN ('pending', 'upcoming', 'cancelled')
        ORDER BY b.created_at DESC
        LIMIT 10
    ";
    
    $result = $conn->query($bookings_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $history_logs[] = $row;
        }
    }
    
    // Get check-ins with receptionist info
    $checkins_query = "
        SELECT 
            c.id,
            c.guest_name,
            '' as email,
            c.room_number,
            c.check_in_date as date,
            c.check_out_date as end_date,
            c.stay_duration as duration,
            c.total_price,
            c.status,
            CASE 
                WHEN c.gcash_reference IS NOT NULL AND c.gcash_reference != '' THEN c.gcash_reference
                ELSE c.payment_mode
            END as reference,
            u.name as receptionist_name,
            'Check-in' as type,
            c.check_in_date as created_at
        FROM checkins c
        LEFT JOIN users u ON c.receptionist_id = u.user_id
        ORDER BY c.check_in_date DESC
        LIMIT 10
    ";
    
    $result = $conn->query($checkins_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $history_logs[] = $row;
        }
    }
    
    // Remove duplicates: if both booking and check-in exist for same guest/room/date, keep only check-in
    $filtered_logs = [];
    $checkin_keys = [];
    
    // First pass: collect all check-in keys
    foreach ($history_logs as $log) {
        if ($log['type'] == 'Check-in') {
            $key = strtolower(trim($log['guest_name'])) . '_' . $log['room_number'] . '_' . date('Y-m-d', strtotime($log['date']));
            $checkin_keys[$key] = true;
            $filtered_logs[] = $log;
        }
    }
    
    // Second pass: add bookings that don't have a matching check-in
    foreach ($history_logs as $log) {
        if ($log['type'] == 'Booking') {
            $key = strtolower(trim($log['guest_name'])) . '_' . $log['room_number'] . '_' . date('Y-m-d', strtotime($log['date']));
            if (!isset($checkin_keys[$key])) {
                $filtered_logs[] = $log;
            }
        }
    }
    
    // Sort combined logs by created_at descending
    usort($filtered_logs, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Keep only top 15 records
    $history_logs = array_slice($filtered_logs, 0, 15);
    
} catch (Exception $e) {
    // Handle error silently
    error_log("History logs error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="style.css" rel="stylesheet">
    
    <style>
        /* History Logs Styles */
.history-item {
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}

.history-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
    transform: translateX(5px);
}

.history-item.booking {
    border-left-color: #0d6efd;
}

.history-item.checkin {
    border-left-color: #198754;
}

.type-badge {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 12px;
}
        
        .stat-card {
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
            background: #fff;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        .stat-title {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin: 0;
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

        .stat-change {
            font-size: 13px;
            margin-top: 6px;
        }

        .stat-change span {
            font-size: 12px;
            color: #888;
        }

        
        .announcement-item {
            transition: background-color 0.2s ease;
        }
        
        .announcement-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .feedback-item {
            border-left: 4px solid transparent;
        }
        
        .feedback-item.complaint {
            border-left-color: #dc3545;
        }
        
        .feedback-item.feedback {
            border-left-color: #198754;
        }
        
        .feedback-item.resolved {
            opacity: 0.7;
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
      font-family: 'Inter', sans-serif;
    }

    /* === Logo / Header === */
    .sidebar h4 {
      text-align: center;
      font-weight: 700;
      color: #111827;
      margin-bottom: 30px;
    }

    /* === User Info Section === */
    .user-info {
      text-align: center;
      background: #f9fafb;
      border-radius: 10px;
      padding: 15px;
      margin: 0 20px 25px 20px;
    }

    .user-info i {
      font-size: 30px;
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

    /* Hover effect — same feel as the other links */
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
        
    </style>

</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h4>Gitarra Apartelle</h4>

    <div class="user-info">
      <i class="fa-solid fa-user-circle"></i>
      <p>Welcome Admin</p>
      <h6>Admin</h6>
    </div>

    <div class="nav-links">
      <a href="admin-dashboard.php" class="active"><i class="fa-solid fa-border-all"></i> Dashboard</a>
      <a href="admin-user.php"><i class="fa-solid fa-users"></i> Users</a>
      <a href="admin-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
      <a href="admin-report.php"><i class="fa-solid fa-file-lines"></i> Reports</a>
      <a href="admin-supplies.php"><i class="fa-solid fa-cube"></i> Supplies</a>
      <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
      <a href="admin-archive.php"><i class="fa-solid fa-archive"></i> Archived</a>
    </div>

    <div class="signout">
      <a href="admin-logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
  </div>

<div class="content p-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <!-- Dashboard header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">Dashboard</h2>
                    <p class="text-muted mb-0">Welcome to Gitarra Apartelle Management System</p>
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
            <!-- Current Guests Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Current Guests</p>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $current_guests ?></h3>
                    <p class="stat-change text-success">+12% <span>from last month</span></p>
                </div>
            </div>

            <!-- Total Check-ins Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Total Check-ins</p>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $total_checkins ?></h3>
                    <p class="stat-change text-success">+8% <span>from last month</span></p>
                </div>
            </div>

            <!-- Revenue Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Total Revenue</p>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1">₱<?= number_format($total_revenue, 2) ?></h3>
                    <p class="stat-change text-success">+5% <span>from last month</span></p>
                </div>
            </div>

            <!-- Low Stock Items Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Low Stock Items</p>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $low_stock_count ?></h3>
                    <p class="stat-change text-danger">-3% <span>from last month</span></p>
                </div>
            </div>
        </div>


        
        <div class="row">
            <!-- Announcements Section -->
            <div class="col-md-6 mb-4">
                <div class="row h-100">
                    <!-- Post Announcement Card -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #871D2B;">
                                <h5 class="mb-0">Post an Announcement</h5>
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" class="form-control" placeholder="Enter announcement title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea name="message" class="form-control" rows="4" placeholder="Enter your message here" required></textarea>
                                    </div>
                                    <button type="submit" name="add_announcement" class="btn text-white w-100" style="background-color: #871D2B;">
                                        <i class="fas fa-paper-plane me-2"></i>Post Announcement
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Announcements Card -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header text-white d-flex justify-content-between align-items-center" 
style="background-color: #871D2B;"
>
                                <h5 class="mb-0">Recent Announcements</h5>
                                <i class="fas fa-list-ul"></i>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($announcements && $announcements->num_rows > 0): ?>
                                    <?php while ($row = $announcements->fetch_assoc()): ?>
                                        <div class="announcement-item p-3 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="mb-0"><?= htmlspecialchars($row['title']) ?></h5>
                                                <a href="admin-dashboard.php?delete=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                            <p class="mb-2"><?= nl2br(htmlspecialchars($row['message'])) ?></p>
                                            <div class="d-flex align-items-center text-muted">
                                                <i class="fas fa-user-edit me-2"></i>
                                                <small>Posted by: <?= $row['created_by'] ?></small>
                                                <i class="fas fa-clock ms-3 me-2"></i>
                                                <small><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></small>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="p-4 text-center">
                                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                        <p>No announcements yet. Be the first to post!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Feedback and Complaints Section -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Customer Feedback</h5>
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($feedback) > 0): ?>
                            <?php foreach ($feedback as $item): ?>
                                <div class="feedback-item p-3 border-bottom <?= $item['type'] ?> <?= $item['status'] == 'resolved' ? 'resolved' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge bg-<?= $item['type'] == 'complaint' ? 'danger' : 'success' ?> me-2">
                                                <?= ucfirst($item['type']) ?>
                                            </span>
                                            <span class="badge bg-<?= $item['status'] == 'resolved' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($item['status']) ?>
                                            </span>
                                        </div>
                                        <?php if ($item['status'] == 'pending'): ?>
                                            <a href="admin-dashboard.php?resolve=<?= $item['id'] ?>" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Mark as Resolved
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-2"><?= nl2br(htmlspecialchars($item['message'])) ?></p>
                                    <div class="d-flex align-items-center text-muted">
                                        <i class="fas fa-user me-2"></i>
                                        <small><?= htmlspecialchars($item['guest_name']) ?></small>
                                        <?php if ($item['email']): ?>
                                            <i class="fas fa-envelope ms-3 me-2"></i>
                                            <small><?= htmlspecialchars($item['email']) ?></small>
                                        <?php endif; ?>
                                        <i class="fas fa-clock ms-3 me-2"></i>
                                        <small><?= date('M d, Y h:i A', strtotime($item['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p>No feedback or complaints yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

  <!-- History Logs Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #871D2B;">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>History Logs</h5>
                <small>Recent Bookings & Check-ins</small>
            </div>
            <div class="card-body p-0">
                <?php if (count($history_logs) > 0): ?>
                    <div class="table-responsive p-3">
                        <table id="historyLogsTable" class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Guest Name</th>
                                    <th>Room</th>
                                    <th>Date</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Receptionist</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history_logs as $log): ?>
                                    <tr class="history-item <?= strtolower($log['type']) ?>">
                                        <td>
                                            <span class="type-badge <?= $log['type'] == 'Booking' ? 'bg-primary text-white' : 'bg-success text-white' ?>">
                                                <i class="fas fa-<?= $log['type'] == 'Booking' ? 'calendar-check' : 'door-open' ?> me-1"></i>
                                                <?= $log['type'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['guest_name']) ?></strong>
                                            <?php if (!empty($log['email'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($log['email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $log['room_number'] ?></span>
                                        </td>
                                        <td>
                                            <small><?= date('M d, Y', strtotime($log['date'])) ?></small>
                                            <br><small class="text-muted"><?= date('h:i A', strtotime($log['date'])) ?></small>
                                        </td>
                                        <td>
                                            <?= $log['duration'] ?> hrs
                                        </td>
                                        <td>
                                            <strong>₱<?= number_format($log['total_price'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = ucfirst($log['status']);
                                            
                                            switch($log['status']) {
                                                case 'completed':
                                                    $statusClass = 'bg-success';
                                                    break;
                                                case 'checked_out':
                                                    $statusClass = 'bg-secondary';
                                                    $statusText = 'Checked Out';
                                                    break;
                                                case 'active':
                                                    $statusClass = 'bg-info';
                                                    break;
                                                case 'checked_in':
                                                    $statusClass = 'bg-success';
                                                    $statusText = 'Checked In';
                                                    break;
                                                case 'upcoming':
                                                case 'scheduled':
                                                    $statusClass = 'bg-warning';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'bg-danger';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="status-badge <?= $statusClass ?> text-white">
                                                <?= $statusText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['receptionist_name'])): ?>
                                                <i class="fas fa-user me-1"></i>
                                                <small><?= htmlspecialchars($log['receptionist_name']) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">N/A</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['reference'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($log['reference']) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <p>No history logs available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
            // Initialize DataTables for History Logs
    $(document).ready(function() {
        $('#historyLogsTable').DataTable({
            "order": [[3, "desc"]], // Sort by date column (index 3) descending
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "language": {
                "search": "Search logs:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ logs",
                "infoEmpty": "No logs available",
                "infoFiltered": "(filtered from _MAX_ total logs)",
                "zeroRecords": "No matching logs found",
                "paginate": {
                    "first": "<<",
                    "last": ">>",
                    "next": ">",
                    "previous": "<"
                }
            }
        });
    });

        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }

        setInterval(updateClock, 1000);
        updateClock();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>