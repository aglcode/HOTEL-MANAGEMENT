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
        c.status AS checkin_status,
        c.room_number AS checkin_room_number,
        c.id AS checkin_id
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
$checkin_room_number = $guestInfo['checkin_room_number'] ?? $room;
$checkin_id = $guestInfo['checkin_id'] ?? null;

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
        
        
        /* ================================
   RESPONSIVE GUEST DASHBOARD FIXES
   ================================ */

/* --- For tablets (≤ 992px) --- */
@media (max-width: 992px) {

  .sidebar {
    width: 230px;
  }

  .content {
    margin-left: 240px;
    padding: 20px;
  }

  .stat-card {
    height: auto;
    padding: 18px;
  }

  .stat-value {
    font-size: 1.1rem;
  }

  .stat-icon {
    width: 50px;
    height: 50px;
    font-size: 1.6rem;
  }

  .user-info {
    padding: 12px;
  }
}


/* --- For mobile screens (≤ 768px) --- */
@media (max-width: 768px) {

  /* Sidebar becomes collapsible */
  .sidebar {
    position: fixed;
    width: 100%;
    height: auto;
    padding: 12px 0;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    z-index: 999;
  }

  .sidebar h4 {
    margin-bottom: 0;
    font-size: 1rem;
  }

  .user-info {
    display: none; /* Hide large user info block */
  }

  .nav-links {
    flex-direction: row;
    justify-content: center;
    padding: 0;
    margin-top: 5px;
  }

  .nav-links a {
    margin: 0 5px;
    padding: 10px;
    font-size: 14px;
  }

  .nav-links a i {
    font-size: 16px;
  }

  .signout {
    display: none; /* Hide to save space */
  }

  /* Main content shifts down below sidebar */
  .content {
    margin-left: 0;
    margin-top: 90px;
    padding: 15px;
  }

  /* Dashboard title + date */
  .d-flex.mb-4 {
    flex-direction: column;
    align-items: flex-start !important;
    gap: 5px;
  }

  .clock-box {
    text-align: left !important;
  }

  /* Cards become stacked */
  .stat-card {
    height: auto;
    padding: 16px;
    margin-bottom: 15px;
  }

  .stat-icon {
    width: 45px;
    height: 45px;
  }

  .stat-title {
    font-size: 0.8rem;
  }

  .stat-value {
    font-size: 1rem;
  }

  small {
    font-size: 0.75rem;
  }

  /* Orders card responsive */
  #ordersCard {
    text-align: center;
    padding: 18px;
  }

  #ordersBadge {
    transform: scale(0.9);
  }

  /* Order accordion */
  .accordion-button {
    font-size: 0.85rem;
    padding: 10px 12px;
  }

  .accordion-body {
    font-size: 0.85rem;
  }

  .room-avatar {
    width: 38px;
    height: 38px;
    font-size: 0.9rem;
  }
}


/* --- Extra small screens (≤ 480px) --- */
@media (max-width: 480px) {

  .stat-card {
    padding: 14px;
  }

  .stat-value {
    font-size: 0.95rem;
  }

  .stat-title {
    font-size: 0.7rem;
  }

  .nav-links a {
    font-size: 12px;
    padding: 8px 10px;
    gap: 6px;
  }

  .nav-links a i {
    font-size: 14px;
  }

  .content {
    padding: 12px;
  }

  #currentDate,
  #currentTime {
    font-size: 0.85rem;
  }

  /* Order items */
  .accordion-button {
    padding: 8px 10px;
    font-size: 0.8rem;
  }

  .accordion-body {
    font-size: 0.8rem;
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
      <h6><?= htmlspecialchars($guest_name) ?></h6>
      <p style="font-size: 14px; color: #6b7280;">Room <?= htmlspecialchars($room) ?></p>
    </div>

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
      <!-- TOP INFO CARDS (first row) -->
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

      <!-- BOTTOM INFO CARDS (second row) -->
      <div class="row mb-4 g-3 equal-row">
        <div class="col-md-4">
          <div class="card stat-card card-info">
            <div class="stat-header">
              <div class="stat-icon"><i class="fa-solid fa-user"></i></div>
              <h6 class="stat-title">My Information</h6>
            </div>
            <div class="stat-value"><?= htmlspecialchars($guest_name) ?></div>
            <small>Room <?= htmlspecialchars($checkin_room_number) ?></small>
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
              $stmt->bind_param("i", $checkin_room_number);
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

        <!-- ORDERS Card (click to view) -->
        <div class="col-md-4">
          <div class="card stat-card card-orders text-center" id="ordersCard"
               data-bs-toggle="collapse"
               data-bs-target="#ordersList"
               style="cursor:pointer;">
            <div class="stat-header">
              <div class="stat-icon" style="font-size:2rem; width:60px; height:60px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fa-solid fa-receipt"></i>
              </div>
              <h6 class="stat-title" style="font-size:0.95rem; font-weight:600; text-transform:uppercase; opacity:0.9; margin:0;">Orders</h6>
            </div>

            <?php
              $stmt = $conn->prepare("SELECT COUNT(*) AS total, SUM(status = 'pending') AS pending FROM orders WHERE checkin_id = ?");
              $stmt->bind_param("i", $checkin_id);
              $stmt->execute();
              $res = $stmt->get_result()->fetch_assoc();
              $stmt->close();
              $totalOrders = (int)($res['total'] ?? 0);
              $pendingOrders = (int)($res['pending'] ?? 0);
            ?>

            <div class="stat-value" style="text-align:center; font-size:2.3rem; font-weight:900; margin-top:10px;" id="pendingOrdersCount">
              <?= $pendingOrders ?>
            </div>

            <p class="stat-change text-muted">Click to view</p>

            <!-- Badge -->
            <span id="ordersBadge"
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $pendingOrders > 0 ? '' : 'd-none' ?>"
              style="font-size: 0.7rem; padding: 5px 7px; min-width: 20px; text-align: center;">
              <?= $pendingOrders ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Collapsible My Orders section -->
      <div id="ordersList" class="collapse mt-3">
        <div class="card shadow-sm">
          <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-utensils me-2"></i> My Orders</h5>
          </div>
          <div class="card-body">
            <div id="order-list">Loading your orders...</div>
          </div>
        </div>
      </div>

    </div> <!-- container -->
  </div> <!-- content -->

  <!-- Receipt Modal -->
  <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="bg-dark text-white modal-header">
          <h5 class="modal-title">Room Receipt</h5>
        </div>
        <div class="modal-body" id="receiptContent">
          <p class="text-center text-muted">Loading receipt...</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-dark" id="printReceiptBtn"><i class="fas fa-print me-1"></i> Print</button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script>
    // Clock
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

    // Time left to checkout
    (function () {
      const timeLeftDisplay = document.getElementById("timeLeftDisplay");
      const checkoutTime = new Date("<?= $guestInfo['check_out_date'] ?>").getTime();
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
      updateTimeLeft();
      const timer = setInterval(updateTimeLeft, 1000);
    })();
  </script>

  <!-- Orders script (guest view-only) -->
  <script>
    // small helper
    function formatTime(seconds) {
      const m = Math.floor(Math.max(0, seconds)/60);
      const s = Math.max(0, seconds) % 60;
      return `${m}:${s.toString().padStart(2,'0')}`;
    }
    function escapeHtml(unsafe){ if (unsafe==null) return ''; return String(unsafe).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;'); }
    function escapeJs(unsafe){ if (unsafe==null) return ''; return String(unsafe).replace(/'/g,"\\'").replace(/"/g,'\\"'); }

    // state
    let previousData = null;
    let orderInterval = null;
    const itemTimers = {}; // { orderId: { remaining, interval } }

    async function fetchOrders(forceUpdate = false) {
      const container = document.getElementById('order-list');
      const orderCountElement = document.getElementById('pendingOrdersCount');
      const ordersBadge = document.getElementById('ordersBadge');

      try {
        // pass room param so backend can filter; fallback to general fetch if backend ignores it
        const roomParam = encodeURIComponent("<?= addslashes($checkin_room_number) ?>");
        const res = await fetch(`fetch_pending_orders.php?room=${roomParam}`);
        const data = await res.json();

        // server might return:
        // 1) { "101": [ ...orders... ] } OR
        // 2) [ {order}, {order} ] (array)
        // Normalize to a map keyed by room
        let normalized = {};
        if (Array.isArray(data)) {
          // assume array of orders (just filter by room)
          normalized[roomParam] = data.filter(o => String(o.room_number) === String(roomParam));
        } else if (data && typeof data === 'object') {
          // If it already is keyed by room, keep it; else if it's single room object with array, detect it
          const keys = Object.keys(data);
          if (keys.length === 1 && Array.isArray(data[keys[0]])) {
            normalized = data;
          } else {
            // maybe it's the array inside an object; try to find matching room
            if (data[roomParam]) normalized[roomParam] = data[roomParam];
            else {
              // fallback: find arrays within object and merge any orders for our room
              normalized[roomParam] = [];
              for (const val of Object.values(data)) {
                if (Array.isArray(val)) {
                  normalized[roomParam].push(...val.filter(o => String(o.room_number) === String(roomParam)));
                }
              }
            }
          }
        }

        const roomOrders = normalized[roomParam] || [];

        // pending count
        const pendingCount = roomOrders.filter(o => String(o.status).toLowerCase() === 'pending').length;
        if (orderCountElement) orderCountElement.textContent = pendingCount;
        if (ordersBadge) {
          if (pendingCount > 0) { ordersBadge.textContent = pendingCount; ordersBadge.classList.remove('d-none'); }
          else ordersBadge.classList.add('d-none');
        }

        // decide if UI update needed
        const dataChanged = JSON.stringify(roomOrders) !== JSON.stringify(previousData);
        if (forceUpdate || dataChanged) {
          previousData = roomOrders;
          renderOrders(roomOrders);
        }

        // Restore any server timers for preparing items (guest view only)
        for (const o of roomOrders) {
          const serverStatus = String(o.status).toLowerCase();
          if (serverStatus === 'preparing' && o.prepare_start_at) {
            const supplyQty = parseInt(o.supply_quantity ?? 0, 10);
            const prepMins = (supplyQty === 999) ? 20 : 5;
            const totalSeconds = prepMins * 60;

            const startedAt = new Date(o.prepare_start_at).getTime();
            if (!isFinite(startedAt)) continue;
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            const remaining = totalSeconds - elapsed;

            if (remaining > 0) {
              if (!itemTimers[o.id]) {
                startGuestTimer(o.id, remaining);
              } else {
                const el = document.getElementById(`timer-${o.id}`);
                if (el) el.innerHTML = `<span class="spin">⏳</span>${formatTime(itemTimers[o.id].remaining)}`;
                el && el.classList.add('timer-anim');
              }
            } else {
              // If server-side timer already expired, show Prepared in UI (no server calls from guest)
              const timerEl = document.getElementById(`timer-${o.id}`);
              if (timerEl) {
                timerEl.classList.remove('bg-danger', 'timer-anim');
                timerEl.classList.add('bg-primary');
                timerEl.textContent = 'Prepared';
              }
            }
          }
        }

      } catch (err) {
        console.error(err);
        if (container) container.innerHTML = `
          <div class="text-center py-4 text-danger">
            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
            <p>Error loading your orders.</p>
          </div>
        `;
      }
    }

    function renderOrders(orders) {
      const container = document.getElementById('order-list');
      if (!container) return;

      if (!orders || orders.length === 0) {
        container.innerHTML = `
          <div class="text-center py-4 text-muted">
            <i class="fas fa-clipboard-check fa-2x mb-2"></i>
            <p>No pending orders right now.</p>
          </div>`;
        return;
      }

      let html = '<div class="row g-3">';
      // We only render one card for this room; keep the same look but filter by room
      html += `<div class="col-12"><div class="card order-card h-100"><div class="card-body">`;

      // header (room)
      html += `
        <div class="d-flex align-items-center mb-3">
          <div class="room-avatar me-3">${escapeHtml(String("<?= htmlspecialchars($checkin_room_number) ?>").slice(-2))}</div>
          <div>
            <h6 class="mb-0">Room <?= htmlspecialchars($checkin_room_number) ?></h6>
            <small class="text-muted">Your orders</small>
          </div>
        </div>
      `;

      html += `<div class="accordion" id="accordion-<?= htmlspecialchars($checkin_room_number) ?>">`;

      orders.forEach((o, index) => {
        const supplyQty = parseInt(o.supply_quantity ?? 0, 10);
        const prepMins = (supplyQty === 999) ? 20 : 5;
        const prepSeconds = prepMins * 60;

        const serverStatus = String(o.status).toLowerCase();

        // determine display remaining
        let timerDisplay = prepSeconds;
        if (serverStatus === 'preparing' && o.prepare_start_at) {
          const startedAt = new Date(o.prepare_start_at).getTime();
          if (isFinite(startedAt)) {
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            const remainingFromDb = prepSeconds - elapsed;
            timerDisplay = remainingFromDb > 0 ? remainingFromDb : 0;
          }
        }
        if (itemTimers[o.id]) timerDisplay = itemTimers[o.id].remaining;

        const isRunning = !!itemTimers[o.id] || (serverStatus === 'preparing' && timerDisplay > 0);
        const isPrepared = serverStatus === 'prepared';
        const isServed = serverStatus === 'served';

        const badgeClass = isPrepared ? 'bg-primary' :
                           (serverStatus === 'preparing' ? 'bg-info' :
                           (serverStatus === 'pending' ? 'bg-warning text-dark' : (isServed ? 'bg-success' : 'bg-secondary')));

        let timerHtml = '';
        if (isServed) {
          timerHtml = `<span id="timer-${o.id}" class="badge bg-success">Served</span>`;
        } else if (isPrepared) {
          timerHtml = `<span id="timer-${o.id}" class="badge bg-primary">Prepared</span>`;
        } else if (isRunning) {
          timerHtml = `<span id="timer-${o.id}" class="badge timer-anim bg-info"><span class="spin">⏳</span>${formatTime(timerDisplay)}</span>`;
        } else {
          timerHtml = `<span id="timer-${o.id}" class="badge bg-info">${formatTime(timerDisplay)}</span>`;
        }

        html += `
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading-${o.id}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${o.id}" aria-expanded="false">
                ${escapeHtml(o.item_name)} (${escapeHtml(o.quantity)}) - <span class="ms-1 badge ${badgeClass}">${escapeHtml(o.status)}</span>
              </button>
            </h2>
            <div id="collapse-${o.id}" class="accordion-collapse collapse" data-bs-parent="#accordion-<?= htmlspecialchars($checkin_room_number) ?>">
              <div class="accordion-body ${isPrepared ? 'text-muted' : ''}">
                <div class="d-flex justify-content-between"><span>Category:</span><span>${escapeHtml(o.category)}</span></div>
                ${o.size ? `<div class="d-flex justify-content-between"><span>Size:</span><span>${escapeHtml(o.size)}</span></div>` : ''}
                <div class="d-flex justify-content-between"><span>Payment:</span><span class="badge bg-info">${escapeHtml(o.mode_payment ?? '')}</span></div>
                <div class="d-flex justify-content-between mt-2"><span>Price:</span><span class="text-success fw-bold">₱${parseFloat(o.price ?? 0).toFixed(2)}</span></div>

                ${ timerHtml ? `<div class="mt-2">${timerHtml}</div>` : '' }

                <div class="d-flex justify-content-end gap-2 mt-3">
                  ${isServed ? `<button class="btn btn-sm btn-outline-secondary" onclick="printReceipt('${escapeJs(o.room_number)}')"><i class="fas fa-print me-1"></i> Print Receipt</button>` : ''}
                </div>
              </div>
            </div>
          </div>
        `;
      });

      html += `</div></div></div></div>`; // close card/accordion/col
      html += '</div>';
      container.innerHTML = html;

      // sync timers UI for active JS timers
      for (const [orderId, t] of Object.entries(itemTimers)) {
        const el = document.getElementById(`timer-${orderId}`);
        if (el) {
          el.innerHTML = `<span class="spin">⏳</span>${formatTime(t.remaining)}`;
          el.classList.add('timer-anim');
        }
      }
    }

    function startGuestTimer(orderId, duration) {
      if (itemTimers[orderId]) return;
      const timerElId = `timer-${orderId}`;
      const el = document.getElementById(timerElId);
      if (el) {
        el.classList.remove('bg-success');
        el.classList.add('bg-danger', 'timer-anim');
        el.innerHTML = `<span class="spin">⏳</span>${formatTime(duration)}`;
      }
      itemTimers[orderId] = {
        remaining: duration,
        interval: setInterval(() => {
          const t = itemTimers[orderId];
          if (!t) return;
          t.remaining -= 1;
          const el2 = document.getElementById(timerElId);
          if (el2) el2.innerHTML = `<span class="spin">⏳</span>${formatTime(t.remaining)}`;
          if (t.remaining <= 0) {
            clearInterval(t.interval);
            delete itemTimers[orderId];
            // guest view: show Prepared but DO NOT call server
            const el3 = document.getElementById(timerElId);
            if (el3) {
              el3.classList.remove('bg-danger', 'timer-anim');
              el3.classList.add('bg-primary');
              el3.textContent = 'Prepared';
            }
            // refresh list to sync with server (read-only)
            setTimeout(()=> fetchOrders(true), 800);
          }
        }, 1000)
      };
    }

    async function printReceipt(roomNumber) {
      const modal = new bootstrap.Modal(document.getElementById("receiptModal"));
      const receiptContent = document.getElementById("receiptContent");
      const printBtn = document.getElementById("printReceiptBtn");
      if (!receiptContent) return;
      receiptContent.innerHTML = `<p class="text-center text-muted">Loading receipt...</p>`;
      modal.show();
      try {
        const res = await fetch(`print_receipt.php?room_number=${encodeURIComponent(roomNumber)}`);
        const html = await res.text();
        receiptContent.innerHTML = html;
        if (printBtn) printBtn.onclick = () => {
          const printWindow = window.open("","_blank");
          printWindow.document.write(html);
          printWindow.document.close();
          printWindow.print();
        };
      } catch (err) {
        console.error(err);
        receiptContent.innerHTML = `<div class="text-center text-danger">Failed to load receipt.</div>`;
      }
    }

    // init
    fetchOrders(true);
    orderInterval = setInterval(()=>fetchOrders(false), 8000);
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
