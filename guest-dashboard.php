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

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>

        :root {
          --maroon: #871D2B;
          --maroon-dark: #800000;
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
        
/* ===== GENERAL CARD STYLE ===== */
  /* ===== GLOBAL STYLES ===== */
  .stat-card {
    height: 195px;
    border: none;
    border-radius: 18px;
    padding: 20px;
    color: #fff;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
    transition: all 0.35s ease;
  }

  .stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
  }

  .stat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
  }

  .stat-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .stat-title {
    font-size: 0.95rem;
    font-weight: 600;
    text-transform: uppercase;
    opacity: 0.9;
    margin: 0;
  }

  .stat-value {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.3;
    word-wrap: break-word;
    margin-top: 15px;
    margin-bottom: 5px;
  }

  small {
    opacity: 0.8;
    font-size: 0.9rem;
  }

  /* ===== GRADIENT THEMES ===== */
  .card-room { background: linear-gradient(135deg, #007bff, #00c6ff); }
  .card-checkin { background: linear-gradient(135deg, #28a745, #8fd19e); }
  .card-checkout { background: linear-gradient(135deg, #dc3545, #ff6b81); }
  .card-time { background: linear-gradient(135deg, #ff6b6b, #feca57); }
  .card-info { background: linear-gradient(135deg, #6c757d, #adb5bd); }
  .card-payment { background: linear-gradient(135deg, #f39c12, #f1c40f); }
  .card-orders { background: linear-gradient(135deg, #20c997, #02aab0); }

  /* ===== TIME CARD SPECIAL EFFECT ===== */
  .card-time {
    animation: glowPulse 2s infinite;
  }

  @keyframes glowPulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.5); }
    70% { box-shadow: 0 0 25px 10px rgba(255, 107, 107, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0); }
  }

  /* ===== EQUAL HEIGHT ROW ===== */
  .equal-row > [class*='col-'] {
    display: flex;
  }
  .equal-row .card {
    flex: 1 1 auto;
  }

  @media (max-width: 768px) {
    .stat-value { font-size: 1.2rem; }
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

<div class="container mt-4">

  <!-- TOP INFO CARDS -->
  <div class="row mb-4 g-3 equal-row">

    <div class="col-md-3">
      <div class="card stat-card card-room">
        <div class="stat-header">
          <div class="stat-icon"><i class="fa-solid fa-door-open"></i></div>
          <h6 class="stat-title">Room Type</h6>
        </div>
        <div class="stat-value">
          <?php
            switch ($room_type) {
              case 'standard_room':
                echo 'Standard Room';
                break;
              case 'twin_room':
                echo 'Twin Room';
                break;
              case 'single':
                echo 'Single Room';
                break;
              case 'executive_room':
                echo 'Executive Room';
                break;
              default:
                echo htmlspecialchars($room_type);
            }
          ?>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card stat-card card-checkin">
        <div class="stat-header">
          <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
          <h6 class="stat-title">Check-In</h6>
        </div>
        <div class="stat-value"><?= nl2br(htmlspecialchars($check_in)) ?></div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card stat-card card-checkout">
        <div class="stat-header">
          <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
          <h6 class="stat-title">Check-Out</h6>
        </div>
        <div class="stat-value"><?= nl2br(htmlspecialchars($check_out)) ?></div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card stat-card card-time">
        <div class="stat-header">
          <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
          <h6 class="stat-title">Time Left</h6>
        </div>
        <div class="stat-value" id="timeLeftDisplay">Calculating...</div>
        <small>until check-out</small>
      </div>
    </div>

  </div>

  <!-- BOTTOM INFO CARDS -->
  <div class="row mb-4 g-3 equal-row">

    <div class="col-md-4">
      <div class="card stat-card card-info">
        <div class="stat-header">
          <div class="stat-icon"><i class="fa-solid fa-user"></i></div>
          <h6 class="stat-title">My Information</h6>
        </div>
        <div class="stat-value"><?= htmlspecialchars($guest_name) ?></div>
        <small>Room <?= htmlspecialchars($guestInfo['room_number']) ?></small>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card stat-card card-payment">
        <div class="stat-header">
          <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
          <h6 class="stat-title">Payment Info</h6>
        </div>
        <?php
          $stmt = $conn->prepare("SELECT payment_mode, gcash_reference FROM checkins WHERE room_number = ? AND status = 'checked_in' LIMIT 1");
          $stmt->bind_param("i", $guestInfo['room_number']);
          $stmt->execute();
          $payment = $stmt->get_result()->fetch_assoc();
          $stmt->close();
        ?>
        <div class="stat-value"><?= htmlspecialchars(ucfirst($payment['payment_mode'] ?? 'N/A')) ?></div>
        <?php if (!empty($payment['gcash_reference'])): ?>
          <small>Reference Number: <?= htmlspecialchars($payment['gcash_reference']) ?></small>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card stat-card card-orders text-center">
        <div class="stat-header">
          <div class="stat-icon" style="font-size:2rem; width:60px; height:60px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fa-solid fa-receipt"></i>
          </div>
          <h6 class="stat-title" style="font-size:0.95rem; font-weight:600; text-transform:uppercase; opacity:0.9; margin:0;">Total Orders</h6>
        </div>

        <?php
          $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE checkin_id = (SELECT id FROM checkins WHERE room_number = ? AND status = 'checked_in' LIMIT 1)");
          $stmt->bind_param("i", $guestInfo['room_number']);
          $stmt->execute();
          $result = $stmt->get_result()->fetch_assoc();
          $stmt->close();
        ?>

        <div class="stat-value" style="text-align:center; font-size:2.3rem; font-weight:900; margin-top:10px;">
          <?= (int)($result['total'] ?? 0) ?>
        </div>
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

<script>
document.addEventListener("DOMContentLoaded", () => {
    const timeLeftDisplay = document.getElementById("timeLeftDisplay");
    const checkoutTime = new Date("<?= $guestInfo['check_out_date'] ?>").getTime();
    const now = new Date().getTime();

    if (!checkoutTime || isNaN(checkoutTime)) {
        timeLeftDisplay.textContent = "Invalid date";
        return;
    }

    function updateTimeLeft() {
        const currentTime = new Date().getTime();
        const diff = checkoutTime - currentTime;

        if (diff <= 0) {
            timeLeftDisplay.textContent = "Checked Out";
            timeLeftDisplay.classList.add("text-danger", "fw-bold");
            clearInterval(timer);
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        let display = "";
        if (days > 0) display += `${days}d `;
        if (hours >= 0) display += `${hours}h `;
        if (minutes >= 0) display += `${minutes}m `;
        if (seconds >= 0) display += `${seconds}s`;

        timeLeftDisplay.textContent = display.trim();
    }

    updateTimeLeft(); // initial call
    const timer = setInterval(updateTimeLeft, 1000);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>