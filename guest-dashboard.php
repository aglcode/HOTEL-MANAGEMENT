<?php
session_start();
require_once 'database.php';
date_default_timezone_set('Asia/Manila');

// =========================
// Validate QR access or session
// =========================
$room  = $_GET['room'] ?? ($_SESSION['room_number'] ?? null);
$token = $_GET['token'] ?? ($_SESSION['qr_token'] ?? null);

if (empty($room) || empty($token)) {
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Access Denied</h3>
        <p>Missing or invalid access token. Please scan your room QR code again.</p>
    </div>');
}

// =========================
// Check room + keycard status
// =========================
$stmt = $conn->prepare("
    SELECT 
        k.id AS key_id,
        k.status AS key_status,
        r.status AS room_status
    FROM keycards k
    JOIN rooms r ON k.room_number = r.room_number
    WHERE k.room_number = ? AND k.qr_code = ?
    LIMIT 1
");
$stmt->bind_param("is", $room, $token);
$stmt->execute();
$res = $stmt->get_result();
$info = $res->fetch_assoc();
$stmt->close();

if (!$info) {
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Invalid QR</h3>
        <p>Invalid or missing keycard record. Please contact the front desk.</p>
    </div>');
}

// If room is available → block access
if ($info['room_status'] === 'available') {
    session_destroy();
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Access Denied</h3>
        <p>This room has been checked out. Please contact the front desk.</p>
    </div>');
}

// If room is occupied but keycard expired → reactivate automatically
if ($info['room_status'] !== 'available' && $info['key_status'] !== 'active') {
    $stmt2 = $conn->prepare("UPDATE keycards SET status = 'active' WHERE id = ?");
    $stmt2->bind_param("i", $info['key_id']);
    $stmt2->execute();
    $stmt2->close();
}

// =========================
// Validate QR token in DB (fetch guest info)
// =========================
$stmt = $conn->prepare("
    SELECT 
        k.*, 
        g.name AS guest_name, 
        b.start_date, 
        b.end_date, 
        r.room_type, 
        b.status AS booking_status
    FROM keycards k
    LEFT JOIN guests g ON k.guest_id = g.id
    LEFT JOIN bookings b ON k.room_number = b.room_number
    LEFT JOIN rooms r ON k.room_number = r.room_number
    WHERE k.room_number = ? 
      AND k.qr_code = ?
    LIMIT 1
");
$stmt->bind_param("is", $room, $token);
$stmt->execute();
$guestInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$guestInfo) {
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Invalid or Expired QR Code</h3>
        <p>Your session has expired. Please contact the front desk.</p>
    </div>');
}

// Save session for continuity
$_SESSION['room_number'] = $guestInfo['room_number'];
$_SESSION['qr_token'] = $guestInfo['qr_code'];

// Extract guest info
$guest_name = $guestInfo['guest_name'] ?? 'Guest';
$room_type  = $guestInfo['room_type'] ?? 'Standard Room';
$check_in   = !empty($guestInfo['start_date']) ? date('F j, Y g:i A', strtotime($guestInfo['start_date'])) : 'N/A';
$check_out  = !empty($guestInfo['end_date'])   ? date('F j, Y g:i A', strtotime($guestInfo['end_date']))   : 'N/A';
$status     = ucfirst($guestInfo['booking_status'] ?? 'Pending');

// =========================
// Auto-cancel overdue bookings (simplified)
// =========================
function autoCancelOverdueBookings($conn) {
    try {
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $conn->query("
            UPDATE bookings 
            SET status = 'cancelled' 
            WHERE status = 'upcoming' 
            AND start_date <= '$cutoffTime'
        ");
    } catch (Exception $e) {
        // silent fail
    }
}
autoCancelOverdueBookings($conn);

// =========================
// Fetch announcements
// =========================
$announcements_result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Dashboard - Room <?= htmlspecialchars($room) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

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
        }
        
        .stat-card {
            text-align: center;
            border: none;
            border-top-left-radius: 28%;
            border-top-right-radius: 20%;
            border-bottom-left-radius: 20%;
            border-bottom-right-radius: 28%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        .stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 150px;
            height: 90px;
            border-top-left-radius: 45%;
            border-top-right-radius: 20%;
            border-bottom-left-radius: 20%;
            border-bottom-right-radius: 45%;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .announcement-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .announcement-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="user-info mb-4 text-center">
    <i class="fa-solid fa-user-circle mb-2" style="font-size: 60px;"></i>
    <h5 class="mb-1"><?= htmlspecialchars($guest_name) ?></h5>
    <p id="user-role" class="mb-0">Room <?= htmlspecialchars($room) ?></p>
  </div>

  <a href="guest-dashboard.php?room=<?= urlencode($room) ?>&token=<?= urlencode($token) ?>" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guest-dashboard.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-gauge"></i> Dashboard
  </a>
  <a href="guest-order.php?room=<?= urlencode($room) ?>&token=<?= urlencode($token) ?>" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guest-order.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-bowl-food"></i> Order
  </a>
  <a href="signin.php" class="text-danger">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </a>

  <div class="sidebar-logo text-center mt-4">
    <img src="image/logo-dark.png" alt="Gitarra Apartelle Logo" style="width: 130px; opacity: 0.9;">
  </div>
</div>

<!-- Content -->
<div class="content p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
      <div style="margin-left: 20px;">
          <h2 class="fw-bold mb-0">Dashboard</h2>
          <p class="text-muted mb-0">Welcome to Gitarra Apartelle</p>
      </div>
      <div class="clock-box text-end">
          <div id="currentDate" class="fw-semibold"></div>
          <div id="currentTime"></div>
      </div>
  </div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <!-- Room Type -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="card-body">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                    <i class="fa-solid fa-door-open"></i>&nbsp;<h6>Room Type</h6>
                </div>
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($room_type) ?></h6>
            </div>
        </div>
    </div>

    <!-- Check-In -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-2">
            <div class="card-body">
                <div class="stat-icon bg-success bg-opacity-10 text-success mx-auto mb-3">
                    <i class="fas fa-calendar-check"></i>&nbsp;<h6>Check-In<br>Date</h6>
                </div>
                <h6 class="fw-bold mb-0"><?= $check_in ?></h6>
            </div>
        </div>
    </div>

    <!-- Check-Out -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-2">
            <div class="card-body">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger mx-auto mb-3">
                    <i class="fas fa-calendar-day"></i>&nbsp;<h6>Check-Out<br>Date</h6>
                </div>
                <h6 class="fw-bold mb-0"><?= $check_out ?></h6>
            </div>
        </div>
    </div>

    <!-- Status -->
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="card-body">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3">
                    <i class="fa-solid fa-circle-info"></i>&nbsp;<h6>Status</h6>
                </div>
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($status) ?></h6>
            </div>
        </div>
    </div>
</div>


<script>
function updateClock() {
    const now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
}
setInterval(updateClock, 1000);
updateClock();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
