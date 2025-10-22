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
// Validate QR token in DB (fetch guest info from checkins)
// =========================
$stmt = $conn->prepare("
    SELECT 
        k.*, 
        c.guest_name, 
        c.check_in_date, 
        c.check_out_date, 
        r.room_type,
        c.status AS checkin_status
    FROM keycards k
    LEFT JOIN checkins c ON k.room_number = c.room_number AND c.status = 'checked_in'
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
$check_in   = !empty($guestInfo['check_in_date']) ? date('F j, Y g:i A', strtotime($guestInfo['check_in_date'])) : 'N/A';
$check_out  = !empty($guestInfo['check_out_date']) ? date('F j, Y g:i A', strtotime($guestInfo['check_out_date'])) : 'N/A';
$status     = ucfirst($guestInfo['checkin_status'] ?? 'Pending');

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

        :root {
          --maroon: #800000;
          --maroon-dark: #5a0000;
          --matte-black: #1c1c1c;
          --text-gray: #6c757d;
          --card-bg: #f8f8f8ff;
          --hover-bg: #f3f3f3ff;
        }

/* === Sidebar Container === */
    .sidebar {
      width: 260px;
      height: 100vh;
      background: var(--matte-black);
      border-right: 1px solid #e5e7eb;
      position: fixed;
      top: 0;
      left: 0;
      display: flex;
      flex-direction: column;
      padding: 20px 0;
      font-family: 'Poppins', sans-serif;
    }

    /* === Logo / Header === */
    .sidebar h4 {
      text-align: center;
      font-weight: 700;
      color: var(--card-bg);
      margin-bottom: 30px;
    }

/* === User Info === */
.user-info {
  text-align: center;
  background: var(--matte-black);
  border-radius: 10px;
  padding: 15px;
  margin: 0 20px 25px 20px;
}

.user-info i {
  font-size: 40px;
  color: var(--card-bg);
  margin-bottom: 5px;
}

.user-info p {
  margin: 0;
  font-size: 14px;
  color: var(--card-bg);
}

.user-info h6 {
  margin: 0;
  font-weight: 600;
  color: var(--card-bg);
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
  color: var(--card-bg);
  text-decoration: none;
  padding: 12px 18px;
  border-radius: 8px;
  margin: 4px 10px;
  transition: all 0.2s ease;
}

.nav-links a i {
  font-size: 19px;
  color: var(--card-bg);
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
  background: var(--maroon);
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
<<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <h4>Gitarra Apartelle</h4>

  <!-- User Info -->
  <div class="user-info">
    <i class="fa-solid fa-user-circle"></i>
    <p>Welcome</p>
    <h6><?= htmlspecialchars($guest_name) ?></h6>
    <p style="font-size: 14px; color: #6b7280;">Room <?= htmlspecialchars($room) ?></p>
  </div>

  <!-- Nav Links -->
  <div class="nav-links">
    <a href="guest-dashboard.php?room=<?= urlencode($room) ?>&token=<?= urlencode($token) ?>"
       class="<?php echo basename($_SERVER['PHP_SELF']) == 'guest-dashboard.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-gauge"></i> Dashboard
    </a>

    <a href="guest-order.php?room=<?= urlencode($room) ?>&token=<?= urlencode($token) ?>"
       class="<?php echo basename($_SERVER['PHP_SELF']) == 'guest-order.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-bowl-food"></i> Order
    </a>
  </div>

  <!-- Sign Out -->
  <div class="signout">
    <a href="signin.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
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