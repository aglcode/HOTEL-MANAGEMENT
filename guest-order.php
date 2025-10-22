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
    die('
        <div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
            <h3>❌ Access Denied</h3>
            <p>Missing or invalid access token. Please scan your room QR code again.</p>
        </div>
    ');
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
$res  = $stmt->get_result();
$info = $res->fetch_assoc();
$stmt->close();

if (!$info) {
    die('
        <div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
            <h3>❌ Invalid QR</h3>
            <p>Invalid or missing keycard record. Please contact the front desk.</p>
        </div>
    ');
}

// If room is available → block access
if ($info['room_status'] === 'available') {
    session_destroy();
    die('
        <div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
            <h3>❌ Access Denied</h3>
            <p>This room has been checked out. Please contact the front desk.</p>
        </div>
    ');
}

// If room is occupied but keycard expired → reactivate automatically
if ($info['room_status'] !== 'available' && $info['key_status'] !== 'active') {
    $stmt2 = $conn->prepare("UPDATE keycards SET status = 'active' WHERE id = ?");
    $stmt2->bind_param("i", $info['key_id']);
    $stmt2->execute();
    $stmt2->close();
}

// =========================
// Validate QR token in DB
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
    WHERE k.room_number = ? AND k.qr_code = ?
    LIMIT 1
");
$stmt->bind_param("is", $room, $token);
$stmt->execute();
$guestInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$guestInfo) {
    die('
        <div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
            <h3>❌ Invalid or Expired QR Code</h3>
            <p>Your session has expired. Please contact the front desk.</p>
        </div>
    ');
}

// Save session for continuity
$_SESSION['room_number'] = $guestInfo['room_number'];
$_SESSION['qr_token']    = $guestInfo['qr_code'];

// Extract guest info
$guest_name = $guestInfo['guest_name'] ?? 'Guest';
$room_type  = $guestInfo['room_type'] ?? 'Standard Room';
$check_in   = !empty($guestInfo['start_date']) 
    ? date('F j, Y g:i A', strtotime($guestInfo['start_date'])) 
    : 'N/A';
$check_out  = !empty($guestInfo['end_date']) 
    ? date('F j, Y g:i A', strtotime($guestInfo['end_date'])) 
    : 'N/A';
$status     = ucfirst($guestInfo['booking_status'] ?? 'Pending');

// =========================
// Auto-cancel overdue bookings (simplified)
// =========================
function autoCancelOverdueBookings($conn)
{
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
  <!-- ==================== HEAD SECTION ==================== -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gitarra Apartelle - Guest Order</title>

  <!-- ========== FONTS & ICONS ========== -->
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"/>
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <!-- ========== CSS FRAMEWORK ========== -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- ========== CUSTOM STYLES ========== -->
  <link href="style.css" rel="stylesheet" />
  <!-- ========== JS LIBRARIES ========== -->
  <!-- jsPDF & html2canvas for PDF generation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <!-- SweetAlert2 for alerts -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
          --maroon: #800000;
          --maroon-dark: #5a0000;
          --matte-black: #1c1c1c;
          --text-gray: #6c757d;
          --card-bg: #f8f8f8ff;
          --hover-bg: #f3f3f3ff;
        }

        .text-maroon {
          color: var(--maroon) !important; /* Classic maroon */
        }

        .text-black {
          color: var(--matte-black) !important; /* Classic black */
        }

        body {
            font-family: 'Poppins', sans-serif;
        }

        /* centering the dashboard content */
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

/* ---------- Sticky Navbar-Like Filter Bar ---------- */
.sticky-filter-bar {
  position: sticky;
  top: 0;
  z-index: 1050; /* stays above most elements */
  background-color: #fff;
  border-bottom: 1px solid #dee2e6;
}

/* Smooth transition for sticky effect */
.sticky-filter-bar {
  transition: all 0.3s ease;
}

/* Optional: Add shadow on scroll for visual depth */
body.scrolled .sticky-filter-bar {
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}
      
/* ---------- FILTER MENU STYLING (Animated & Elegant) ---------- */
.btn-filter-toggle {
  background-color: var(--matte-black);
  color: #fff;
  border: none;
  font-weight: 600;
  padding: 8px 18px;
  margin-left: 19px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

.btn-filter-toggle i {
  margin-right: 6px;
  transition: transform 0.3s ease, color 0.3s ease;
}

.btn-filter-toggle:hover i {
  transform: rotate(20deg);
  color: #ffd1d1;
}

.btn-filter-toggle:hover,
.btn-filter-toggle:focus {
  background-color: var(--maroon);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 6px 14px rgba(128, 0, 0, 0.3);
}

/* ---------- Dropdown Menu ---------- */
.dropdown-menu {
  border-radius: 14px;
  background-color: #fff;
  border: none;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  padding: 10px;
  animation: fadeIn 0.3s ease;
  transform-origin: top right;
}

/* ---------- Dropdown Items ---------- */
.dropdown-item {
  border-radius: 8px;
  padding: 8px 14px;
  font-weight: 500;
  color: #1c1c1c;
  transition: all 0.25s ease;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
}

.dropdown-item:hover {
  background-color: var(--maroon);
  color: #fff;
  transform: translateX(6px);
}

.dropdown-item.active {
  background-color: var(--maroon);
  color: #fff;
}

/* ---------- Divider ---------- */
.dropdown-divider {
  margin: 6px 0;
  border-top: 1px solid rgba(0, 0, 0, 0.1);
}

/* ---------- SEARCH BAR (Stylish & Animated) ---------- */
.search-box {
  width: 260px;
  position: relative;
}

.search-box .input-group {
  border-radius: 15px;
  border: solid 1.5px #1c1c1c;
  overflow: hidden;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #fff, #f9f9f9);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
}

.search-box input {
  border: none;
  background: transparent;
  font-size: 0.9rem;
  padding-left: 5px;
  transition: all 0.3s ease;
}

.search-box input:focus {
  outline: none;
  background: #fff7f7;
  box-shadow: none;
}

.search-box .input-group-text {
  background: transparent;
  border: none;
  transition: all 0.3s ease;
}

.search-box .bi-search {
  transition: transform 0.3s ease, color 0.3s ease;
  color: #777;
}

/* 🔍 Animation when focusing */
.search-box input:focus + .input-group-text .bi-search,
.search-box:hover .bi-search {
  color: var(--maroon);
  transform: scale(1.15) rotate(10deg);
}

/* 🌈 Glowing border animation when typing */
.search-box input:focus {
  animation: glowBorder 1s ease-in-out infinite alternate;
}

@keyframes glowBorder {
  from {
    box-shadow: 0 0 6px rgba(128, 0, 0, 0.2);
  }
  to {
    box-shadow: 0 0 10px rgba(128, 0, 0, 0.4);
  }
}

/* 🔥 Smooth fade animation for menu cards and titles */
.menu-category,
.menu-type,
.menu-type h5,
.menu-type .card,
.menu-type .col-md-4,
.menu-type .col-lg-3,
.menu-type .col-sm-6 {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

/* ---------- Cart & View Buttons ---------- */
.btn-cart,
.btn-view {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-weight: 600;
  font-size: 0.9rem;
  padding: 8px 18px;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

/* 🛒 CART BUTTON (Maroon Theme) */
#cartButton {
  position: relative;
  overflow: visible;
  background-color: var(--maroon);
  color: #fff;
  border: 1px solid var(--maroon);
  font-weight: 600;
  padding: 8px 18px;
  transition: all 0.3s ease;
}

#cartButton i {
  transition: transform 0.3s ease, color 0.3s ease;
}

/* Hover Effect Fix */
#cartButton:hover {
  background-color: #fff;
  color: var(--maroon);
  border-color: var(--maroon);
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(128, 0, 0, 0.3);
}

#cartButton:hover i {
  transform: rotate(-15deg) scale(1.2);
  color: var(--maroon);
}

/* 🛍️ Cart Badge */
#cartCount {
  font-size: 0.75rem;
  background-color: #dc3545;
  color: #fff;
  position: absolute;
  top: -6px;
  right: -8px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: none; /* hidden by default */
  align-items: center;
  justify-content: center;
  font-weight: 600;
  border: 2px solid #fff;
  box-shadow: 0 0 4px rgba(0,0,0,0.2);
}

/* Animate when bumping */
#cartCount.bump {
  animation: bump 0.3s ease;
}

@keyframes bump {
  0% { transform: scale(1); }
  50% { transform: scale(1.3); }
  100% { transform: scale(1); }
}

/* ===== CART SIDEBAR ===== */
.cart-sidebar {
  position: fixed;
  top: 0;
  right: -400px;
  width: 350px;
  height: 100%;
  background: #fff;
  box-shadow: -4px 0 12px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  padding: 20px;
  transition: right 0.4s ease;
  z-index: 1050;
}

.cart-sidebar.active {
  right: 0;
}

/* ===== EMPTY CART DESIGN ===== */
.empty-cart {
  display: none;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #888;
}

.empty-cart i {
  font-size: 4rem;
  color: #ccc;
  margin-bottom: 10px;
  transition: transform 0.3s ease;
}

.empty-cart i:hover {
  transform: rotate(-10deg) scale(1.1);
}

/* Hide footer when cart is empty */
.cart-footer.hidden {
  display: none !important;
}

/* Removed Lomi size dropdown styling (.size-select) */

/* Overlay background when cart opens */
.cart-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.4);
  display: none;
  z-index: 1040;
}

.cart-overlay.active {
  display: block;
}

/* Cart items list */
.cart-items {
  flex: 1;
  overflow-y: auto;
  max-height: 75%;
}

/* Individual cart item */
.cart-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f9f9f9;
  border-radius: 12px;
  padding: 10px;
  margin-bottom: 10px;
}

.cart-item img {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  object-fit: cover;
}

.cart-item-info {
  flex: 1;
  margin-left: 10px;
}

.cart-item h6 {
  margin-bottom: 3px;
  font-size: 0.95rem;
}

/* Quantity control */
.qty-control {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: 5px;
}

.qty-control button {
  background: var(--maroon);
  color: #fff;
  border: none;
  width: 25px;
  height: 25px;
  border-radius: 50%;
  line-height: 1;
  transition: background 0.2s ease;
}

.qty-control button:hover {
  background: #a00000;
}

/* Confirm order button */
.btn-confirm {
  background: var(--maroon);
  color: #fff;
  font-weight: 600;
  border-radius: 12px;
  padding: 10px 0;
  transition: background 0.2s ease;
}

.btn-confirm:hover {
  background: #a00000;
}

/* Total section in footer */
.cart-footer .fw-bold {
  font-size: 1.2rem;
  color: var(--matte-black);
}

.cart-footer span#total {
  font-weight: 700;
}

/* 👁️ VIEW ORDER BUTTON (Matte Black Theme) */
.btn-view {
  background-color: var(--matte-black);
  color: #fff;
  margin-right: 19px;
  border-color: var(--matte-black);
}

.btn-view i {
  transition: transform 0.3s ease, color 0.3s ease;
}

.btn-view:hover {
  background-color: #fff;
  border: solid 1.5px var(--matte-black);
  color: var(--matte-black);
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

.btn-view:hover i {
  transform: scale(1.2);
  color: var(--matte-black);
}

/* ---------- Pulse Animation ---------- */
.pulse {
  position: relative;
  overflow: hidden;
}

.pulse::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  transform: translate(-50%, -50%);
  transition: width 0.4s ease, height 0.4s ease;
}

.pulse:active::after {
  width: 200%;
  height: 200%;
}

/* ---------- Fade-in Animation ---------- */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
        
.menu-card {
  border: none;
  border-radius: 16px;
  overflow: hidden;
  background-color: var(--card-bg);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5); /* 🌟 Default soft shadow */
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.menu-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3); /* 🌟 Stronger on hover */
  background-color: var(--hover-bg);
}

    .category-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      background-color: var(--maroon);
      color: #fff;
      font-size: 0.75rem;
      padding: 4px 10px;
      border-radius: 20px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .price {
      color: var(--maroon);
      font-weight: 700;
      font-size: 1.1rem;
      margin: 0;
    }

.btn-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background-color: #fff;
  color: #000; /* Black text */
  border: 2px solid #000; /* Black border */
  border-radius: 50px;
  padding: 6px 14px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

/* Add the + icon before the text */
.btn-add::before {
  content: "+";
  font-size: 1.1rem;
  font-weight: bold;
  color: #000; /* Black icon */
  transition: transform 0.3s ease, color 0.3s ease;
}

/* Hover animation */
.btn-add:hover {
  background-color: #000; /* Black background */
  color: #fff; /* White text */
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Animate the + icon on hover */
.btn-add:hover::before {
  transform: rotate(180deg) scale(1.2);
  color: #fff;
}

/* Optional: small press-down effect */
.btn-add:active {
  transform: translateY(0);
  box-shadow: none;
}

    .card-body {
      padding: 1rem 1.25rem 1.25rem;
    }

    .card-img-top {
      height: 180px;
      object-fit: cover;
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
    <p style="font-size: 14px; color: #f8f8f8ff;">Room <?= htmlspecialchars($room) ?></p>
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
                <h2 class="fw-bold mb-0" >Hotel Menu</h2>
                <p class="text-muted mb-0">Welcome to Gitarra Apartelle</p>
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
        
<!-- ==================== ORDER CARDS ==================== -->
<div class="row mb-4">

  <!-- ==================== FILTER, SEARCH, CART, AND ORDER BUTTON ==================== -->
  <div class="filter-bar sticky-filter-bar d-flex flex-wrap justify-content-between align-items-center gap-3 py-2 px-3 shadow-sm bg-white border-bottom">

        <!-- 🔽 Filter Dropdown (LEFT SIDE) -->
        <div class="dropdown filter-dropdown">
          <button class="btn btn-filter-toggle dropdown-toggle px-4 py-2" type="button" id="filterMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-funnel-fill me-2"></i> 
            <span id="filterMenuLabel">Filter Menu</span>
          </button>

          <ul class="dropdown-menu shadow-sm border-0 p-2" aria-labelledby="filterMenuButton">
            <li><button class="dropdown-item filter-btn active" data-filter="all"><i class="bi bi-grid me-2"></i>All</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item filter-btn" data-filter="food"><i class="bi bi-egg-fried me-2"></i>Food</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="noodles"><i class="bi bi-cup-hot me-2"></i>Noodles</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="ricemeals"><i class="bi bi-bowl-rice me-2"></i>Rice Meals</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="lumpia"><i class="bi bi-bag-fill me-2"></i>Lumpia</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="snacks"><i class="bi bi-cookie me-2"></i>Snacks</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="drinks"><i class="bi bi-cup-straw me-2"></i>Drinks</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item filter-btn" data-filter="non-food"><i class="bi bi-box-seam me-2"></i>Non-Food</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="dental-care"><i class="bi bi-tooth me-2"></i>Dental Care</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="shampoo"><i class="bi bi-droplet-half me-2"></i>Shampoo</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="conditioner"><i class="bi bi-bucket me-2"></i>Conditioner</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="utensils"><i class="bi bi-cup me-2"></i>Disposable Utensils</button></li>
          </ul>
        </div>

        <!-- 🔍 Search, Cart, and View (RIGHT SIDE) -->
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
          <form class="d-flex search-box">
            <div class="input-group shadow-sm overflow-hidden">
              <span class="input-group-text border-0 bg-white ps-3">
                <i class="bi bi-search text-muted"></i>
              </span>
              <input 
                type="text" 
                id="menuSearch" 
                class="form-control border-0" 
                placeholder="Search item..." 
              />
            </div>
          </form>

          <!-- 🛒 Cart Badge -->
          <button class="btn btn-cart pulse position-relative" id="cartButton">
            <i class="bi bi-cart-fill me-1"></i> Cart
            <span id="cartCount" class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle">0</span>
          </button>

          <!-- 🧾 Cart Sidebar -->
          <div id="cartSidebar" class="cart-sidebar">
            <div class="cart-header d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0"><i class="bi bi-cart-fill me-2"></i>Your Cart</h5>
              <button id="closeCart" class="btn-close"></button>
            </div>

            <!-- Empty Cart Message -->
            <div id="emptyCart" class="empty-cart text-center mt-5">
              <i class="bi bi-bag-x fs-1 text-muted mb-3"></i>
              <p class="fw-semibold text-muted mb-1">Your cart is empty</p>
              <p class="small text-secondary">Add some quality items to get started!</p>
            </div>

            <div id="cartItems" class="cart-items mt-3"></div>

            <div class="cart-footer mt-auto">
              <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                <span>Total</span>
                <span id="total">₱0.00</span>
              </div>
              <button class="btn w-100 btn-confirm" id="confirmOrderBtn">
                Confirm Order
              </button>
            </div>
          </div>

          <!-- Cart Overlay -->
          <div id="cartOverlay" class="cart-overlay"></div>

          <button class="btn btn-view pulse" id="viewOrderBtn">
            <i class="bi bi-eye me-1"></i> View Order
          </button>
        </div>
  </div>

  <!-- ==================== MENU CARD ==================== -->
  <div class="card shadow-lg border-0 p-1 my-2">
    <div class="card-body" id="menuContainer">

      <!-- ==================== FOOD MENU ==================== -->
      <div class="menu-category food">
        <div class="container my-4">
          <h3 class="text-center text-maroon mb-4 fw-bold text-uppercase">Food Menu</h3>

          <!-- ==================== NOODLES ==================== -->
          <div class="menu-type noodles">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Noodles</h5>
            <div class="row g-4 mb-4">

              <!-- Mami -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Noodle</span>
                  <img src="image/Mami.png" class="card-img-top" alt="Mami">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Mami</h6>
                    <p class="text-muted small mb-4">Warm comforting soup</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱70</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Nissin Cup (Beef) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Instant Noodle</span>
                  <img src="image/Nissin Beef.png" class="card-img-top" alt="Nissin Cup Beef">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Nissin Cup (Beef)</h6>
                    <p class="text-muted small mb-4">Rich beef-flavored noodles</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Nissin Cup (Chicken) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Instant Noodle</span>
                  <img src="image/Nissin Chicken.png" class="card-img-top" alt="Nissin Cup Chicken">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Nissin Cup (Chicken)</h6>
                    <p class="text-muted small mb-4">Classic chicken broth flavor</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Nissin Cup (Spicy Seafood) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Instant Noodle</span>
                  <img src="image/Nissin Spicy Seafood.png" class="card-img-top" alt="Nissin Cup Spicy Seafood">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Nissin Cup (Spicy Seafood)</h6>
                    <p class="text-muted small mb-4">A bold mix of seafood goodness</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== RICE MEALS ==================== -->
          <div class="menu-type ricemeals">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Rice Meals</h5>
            <div class="row g-4 mb-4">

              <!-- Longganisa -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Longganisa.jpg" class="card-img-top" alt="Longganisa">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Longganisa</h6>
                    <p class="text-muted small mb-4">Savory Filipino sausage</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱100</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Sisig -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Sisig.jpg" class="card-img-top" alt="Sisig">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Sisig</h6>
                    <p class="text-muted small mb-4">Crispy pork bits chili and egg</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱100</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bopis -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Bopis.jpg" class="card-img-top" alt="Bopis">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Bopis</h6>
                    <p class="text-muted small mb-4">Spicy pork lungs</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱100</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tocino -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Tocino.jpg" class="card-img-top" alt="Tocino">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Tocino</h6>
                    <p class="text-muted small mb-4">Sweet cured pork</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱100</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tapa -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Tapa.jpg" class="card-img-top" alt="Tapa">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Tapa</h6>
                    <p class="text-muted small mb-4">Marinated beef slices</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱100</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Hotdog -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Hotdog.jpg" class="card-img-top" alt="Hotdog">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Hotdog</h6>
                    <p class="text-muted small mb-4">Grilled hotdog</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱100</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Dinuguan -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Dinuguan.jpg" class="card-img-top" alt="Dinuguan">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Dinuguan</h6>
                    <p class="text-muted small mb-4">Pork blood stew</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱115</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Chicken Adobo -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Chicken Adobo.jpg" class="card-img-top" alt="Chicken Adobo">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Chicken Adobo</h6>
                    <p class="text-muted small mb-4">Filipino chicken stew</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱120</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bicol Express -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Rice Meal</span>
                  <img src="image/Bicol Express.jpg" class="card-img-top" alt="Bicol Express">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Bicol Express</h6>
                    <p class="text-muted small mb-4">Spicy pork with coconut milk</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱125</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ==================== ADD-ONS ==================== -->
              <!-- Chicharon -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Add-On</span>
                  <img src="image/Chicharon.jpg" class="card-img-top" alt="Chicharon">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Chicharon</h6>
                    <p class="text-muted small mb-4">Crispy fried pork</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱60</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Chicken Skin -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Add-On</span>
                  <img src="image/Chicken Skin.jpg" class="card-img-top" alt="Chicken Skin">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Chicken Skin</h6>
                    <p class="text-muted small mb-4">Crispy golden bites</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱60</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== LUMPIA ==================== -->
          <div class="menu-type lumpia">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Lumpia</h5>
            <div class="row g-4 mb-4">
              <!-- Shanghai -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Lumpia</span>
                  <img src="image/Lumpia Shanghai.jpg" class="card-img-top" alt="Shanghai">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Shanghai (3pcs)</h6>
                    <p class="text-muted small mb-4">Crispy savory rolls</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Gulay -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Lumpia</span>
                  <img src="image/Lumpia Gulay.jpg" class="card-img-top" alt="Gulay">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Gulay (3pcs)</h6>
                    <p class="text-muted small mb-4">Fresh veggie rolls</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Toge -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Lumpia</span>
                  <img src="image/Lumpia Toge.jpg" class="card-img-top" alt="Toge">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Toge (4pcs)</h6>
                    <p class="text-muted small mb-4">Crispy bean delight</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== SNACKS ==================== -->
          <div class="menu-type snacks">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Snacks</h5>
            <div class="row g-4 mb-4">

              <!-- French Fries (BBQ) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/French Fries BBQ.jpg" class="card-img-top" alt="French Fries BBQ">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">French Fries (BBQ)</h6>
                    <p class="text-muted small mb-4">Savory barbecue flavor</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- French Fries (Sour Cream) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/French Fries Sour Cream.jpg" class="card-img-top" alt="French Fries Sour Cream">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">French Fries (Sour Cream)</h6>
                    <p class="text-muted small mb-4">Creamy tangy taste</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- French Fries (Cheese) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/French Fries Cheese.jpg" class="card-img-top" alt="French Fries Cheese">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">French Fries (Cheese)</h6>
                    <p class="text-muted small mb-4">Cheesy salty goodness</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Cheese Sticks (12pcs) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/Cheese Sticks 12pcs.jpg" class="card-img-top" alt="Cheese Sticks">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Cheese Sticks (12pcs)</h6>
                    <p class="text-muted small mb-4">Crispy cheesy delight</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱30</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tinapay (3pcs) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/Tinapay 3pcs.jpg" class="card-img-top" alt="Tinapay">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Tinapay (3pcs)</h6>
                    <p class="text-muted small mb-4">Soft fresh bread</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱20</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tinapay with Spread (3pcs) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/Tinapay with Spread 3pcs.jpg" class="card-img-top" alt="Tinapay with Spread">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Tinapay with Spread (3pcs)</h6>
                    <p class="text-muted small mb-4">Sweet buttery bread</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱30</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Burger Regular -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/Burger Regular.jpg" class="card-img-top" alt="Burger Regular">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Burger Regular</h6>
                    <p class="text-muted small mb-4">Juicy classic patty</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱35</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Burger with Cheese -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/Burger with Cheese.jpg" class="card-img-top" alt="Burger with Cheese">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Burger with Cheese</h6>
                    <p class="text-muted small mb-4">Melty cheesy burger</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱40</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Nagaraya Butter Yellow (Small) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/Nagaraya Butter Yellow Small.jpg" class="card-img-top" alt="Nagaraya Butter Yellow">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Nagaraya Butter Yellow (Small)</h6>
                    <p class="text-muted small mb-4">Crunchy butter nuts</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱20</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Nova Country Cheddar (Small) -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Snack</span>
                  <img src="image/Nova Country Cheddar Small.jpg" class="card-img-top" alt="Nova Country Cheddar">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Nova Country Cheddar (Small)</h6>
                    <p class="text-muted small mb-4">Cheesy crunchy chips</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱25</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- ==================== DRINKS ==================== -->
          <div class="menu-type drinks">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Water</h5>
            <div class="row g-4">
              <!-- Bottled Water -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Water</span>
                  <img src="image/Bottled Water.jpg" class="card-img-top" alt="Bottled Water">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Bottled Water (500ml)</h6>
                    <p class="text-muted small mb-4">Refreshing purified water</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱25</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Purified Hot Water -->
              <div class="col-md-4 col-lg-4">
                <div class="card menu-card position-relative">
                  <span class="category-badge">Water</span>
                  <img src="image/Purified Hot Water Mug.jpg" class="card-img-top" alt="Purified Hot Water">
                  <div class="card-body">
                    <h6 class="fw-bold mb-1">Purified Hot Water Only (Mug)</h6>
                    <p class="text-muted small mb-4">Served hot and ready to sip</p>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="price mb-0">₱10</p>
                      <button class="btn btn-add">Add</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

        <!-- ICE -->
        <h5 class="fw-bold mt-5 mb-3 text-maroon">Ice</h5>
        <div class="row g-4">
          <!-- Ice Bucket -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Ice</span>
              <img src="image/Ice Bucket.jpg" class="card-img-top" alt="Ice Bucket">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Ice Bucket</h6>
                <p class="text-muted small mb-4">Cold refreshing ice</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱40</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- SOFTDRINKS -->
        <h5 class="fw-bold mt-5 mb-3 text-maroon">Softdrinks</h5>
        <div class="row g-4">
          <!-- Coke Mismo -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Softdrink</span>
              <img src="image/Coke Mismo.jpg" class="card-img-top" alt="Coke Mismo">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Coke Mismo</h6>
                <p class="text-muted small mb-4">Classic fizzy cola</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱25</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Royal Mismo -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Softdrink</span>
              <img src="image/Royal Mismo.jpg" class="card-img-top" alt="Royal Mismo">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Royal Mismo</h6>
                <p class="text-muted small mb-4">Sweet orange soda</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱25</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Sting Energy Drink -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Softdrink</span>
              <img src="image/Sting Energy Drink.jpg" class="card-img-top" alt="Sting Energy Drink">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Sting Energy Drink</h6>
                <p class="text-muted small mb-4">Bold energizing flavor</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱30</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- SHAKES -->
        <h5 class="fw-bold mt-5 mb-3 text-maroon">Shakes</h5>
        <div class="row g-4">
          <!-- Dragon Fruit -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Dragon Fruit Shake.jpg" class="card-img-top" alt="Dragon Fruit Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Dragon Fruit</h6>
                <p class="text-muted small mb-4">Fresh tropical blend</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱70</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Mango -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Mango Shake.jpg" class="card-img-top" alt="Mango Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Mango</h6>
                <p class="text-muted small mb-4">Sweet creamy delight</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱70</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Cucumber -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Cucumber Shake.jpg" class="card-img-top" alt="Cucumber Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Cucumber</h6>
                <p class="text-muted small mb-4">Cool refreshing mix</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱70</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Avocado -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Avocado Shake.jpg" class="card-img-top" alt="Avocado Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Avocado</h6>
                <p class="text-muted small mb-4">Rich creamy flavor</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱70</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Chocolate -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Chocolate Shake.jpg" class="card-img-top" alt="Chocolate Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Chocolate</h6>
                <p class="text-muted small mb-4">Smooth cocoa blend</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱40</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Taro -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Taro Shake.jpg" class="card-img-top" alt="Taro Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Taro</h6>
                <p class="text-muted small mb-4">Sweet nutty taste</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱40</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Ube -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Ube Shake.jpg" class="card-img-top" alt="Ube Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Ube</h6>
                <p class="text-muted small mb-4">Creamy purple blend</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱40</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Strawberry -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Shake</span>
              <img src="image/Strawberry Shake.jpg" class="card-img-top" alt="Strawberry Shake">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Strawberry</h6>
                <p class="text-muted small mb-4">Sweet berry flavor</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱40</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- JUICE -->
        <h5 class="fw-bold mt-5 mb-3 text-maroon">Juice</h5>
        <div class="row g-4">
          <!-- Del Monte Pineapple Juice -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Juice</span>
              <img src="image/Pineapple Juice.jpg" class="card-img-top" alt="Del Monte Pineapple Juice">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Pineapple Juice</h6>
                <p class="text-muted small mb-4">Fresh tangy flavor</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱60</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- COFFEE -->
        <h5 class="fw-bold mt-5 mb-3 text-maroon">Coffee</h5>
        <div class="row g-4">
          <!-- Instant Coffee -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Coffee</span>
              <img src="image/Instant Coffee.jpg" class="card-img-top" alt="Instant Coffee">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Instant Coffee</h6>
                <p class="text-muted small mb-4">Quick hot brew</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱25</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Brewed Coffee -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Coffee</span>
              <img src="image/Brewed Coffee.jpg" class="card-img-top" alt="Brewed Coffee">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Brewed Coffee</h6>
                <p class="text-muted small mb-4">Rich aroma flavor</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱45</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TEA -->
        <h5 class="fw-bold mt-5 mb-3 text-maroon">Teas</h5>
        <div class="row g-4">
          <!-- Hot Tea (Green) -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Tea</span>
              <img src="image/Hot Tea Green.jpg" class="card-img-top" alt="Hot Tea Green">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Hot Tea (Green)</h6>
                <p class="text-muted small mb-4">Soothing herbal warmth</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱25</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Hot Tea (Black) -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Tea</span>
              <img src="image/Hot Tea Black.jpg" class="card-img-top" alt="Hot Tea Black">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Hot Tea (Black)</h6>
                <p class="text-muted small mb-4">Bold smooth flavor</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱25</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- MILO -->
        <h5 class="fw-bold mt-5 mb-3 text-maroon">Other Drinks</h5>
        <div class="row g-4">
          <!-- Milo Hot Chocolate Drink -->
          <div class="col-md-4 col-lg-4">
            <div class="card menu-card position-relative">
              <span class="category-badge">Milo</span>
              <img src="image/Milo Hot Chocolate.jpg" class="card-img-top" alt="Milo Hot Chocolate Drink">
              <div class="card-body">
                <h6 class="fw-bold mb-1">Milo Hot Chocolate Drink</h6>
                <p class="text-muted small mb-4">Creamy chocolate energy</p>
                <div class="d-flex justify-content-between align-items-center">
                  <p class="price mb-0">₱25</p>
                  <button class="btn btn-add">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        </div>
        </div>
      </div>

    <!-- ==================== NON FOOD ==================== -->
    <div class="menu-category non-food">
      <div class="container my-4">
        <h3 class="text-center mb-4 fw-bold text-uppercase">Non-Food Menu</h3>

        <!-- Essentials -->
        <div class="menu-type essentials">
          <h5 class="fw-bold mt-5 mb-3 text-black">Essentials</h5>
          <div class="row g-4">
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Essentials</span>
                <img src="image/Face Mask Disposable.jpg" class="card-img-top" alt="Face Mask Disposable">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Face Mask Disposable</h6>
                  <p class="text-muted small mb-4">Protective daily wear</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱5</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Dental Care -->
        <div class="menu-type dental-care">
          <h5 class="fw-bold mt-5 mb-3 text-black">Dental Care</h5>
          <div class="row g-4">
            <!-- Toothbrush with Toothpaste -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Dental</span>
                <img src="image/Toothbrush with Toothpaste.jpg" class="card-img-top" alt="Toothbrush with Toothpaste">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Toothbrush with Toothpaste</h6>
                  <p class="text-muted small mb-4">Fresh morning care</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱25</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Colgate Toothpaste -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Dental</span>
                <img src="image/Colgate Toothpaste.jpg" class="card-img-top" alt="Colgate Toothpaste">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Colgate Toothpaste</h6>
                  <p class="text-muted small mb-4">Minty clean smile</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱20</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Feminine Hygiene -->
        <div class="menu-type feminine-hygiene">
          <h5 class="fw-bold mt-5 mb-3 text-black">Feminine Hygiene</h5>
          <div class="row g-4">
            <!-- Modess All Night Extra Long Pad -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Hygiene</span>
                <img src="image/Modess All Night Extra Long Pad.jpg" class="card-img-top" alt="Modess All Night Extra Long Pad">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Modess All Night Extra Long Pad</h6>
                  <p class="text-muted small mb-4">Comfort overnight protection</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱20</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Shampoo -->
        <div class="menu-type shampoo">
          <h5 class="fw-bold mt-5 mb-3 text-black">Shampoo</h5>
          <div class="row g-4">
            <!-- Sunsilk -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Shampoo</span>
                <img src="image/Sunsilk Shampoo.jpg" class="card-img-top" alt="Sunsilk">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Sunsilk</h6>
                  <p class="text-muted small mb-4">Smooth daily shine</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱15</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Creamsilk -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Shampoo</span>
                <img src="image/Creamsilk Shampoo.jpg" class="card-img-top" alt="Creamsilk">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Creamsilk Shampoo</h6>
                  <p class="text-muted small mb-4">Soft silky finish</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱15</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Palmolive Anti-Dandruff -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Shampoo</span>
                <img src="image/Palmolive Anti-Dandruff Shampoo.jpg" class="card-img-top" alt="Palmolive Anti-Dandruff">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Palmolive Anti-Dandruff</h6>
                  <p class="text-muted small mb-4">Fresh scalp care</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱15</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Dove -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Shampoo</span>
                <img src="image/Dove Shampoo.jpg" class="card-img-top" alt="Dove">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Dove</h6>
                  <p class="text-muted small mb-4">Gentle moisture care</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱15</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Conditioner -->
         <div class="menu-type conditioner">
          <h5 class="fw-bold mt-5 mb-3 text-black">Conditioner</h5>
          <div class="row g-4">
            <!-- Empress Keratin -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Conditioner</span>
                <img src="image/Empress Keratin Conditioner.jpg" class="card-img-top" alt="Empress Keratin">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Empress Keratin</h6>
                  <p class="text-muted small mb-4">Smooth frizz control</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱15</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Creamsilk -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Conditioner</span>
                <img src="image/Creamsilk Conditioner.jpg" class="card-img-top" alt="Creamsilk">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Creamsilk Conditioner</h6>
                  <p class="text-muted small mb-4">Soft silky finish</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱15</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
         </div>

        <!-- Trust Condom -->
         <div class="menu-type personal-protection">
          <h5 class="fw-bold mt-5 mb-3 text-black">Personal Protection</h5>
          <div class="row g-4">
            <!-- Trust Condom (3pcs) -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Personal Care</span>
                <img src="image/Trust Condom Boxed 3pcs.jpg" class="card-img-top" alt="Trust Condom (3pcs)">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Trust Condom (3pcs)</h6>
                  <p class="text-muted small mb-4">Safe reliable protection</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱60</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
         </div>

        <!-- Disposable Utensils -->
         <div class="menu-type utensils">
          <h5 class="fw-bold mt-5 mb-3 text-black">Disposable Utensils</h5>
          <div class="row g-4">
            <!-- Disposable Spoon -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Utensils</span>
                <img src="image/Disposable Spoon.jpg" class="card-img-top" alt="Disposable Spoon">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Disposable Spoon</h6>
                  <p class="text-muted small mb-4">Light easy serve</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱2.50</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Disposable Fork -->
            <div class="col-md-4 col-lg-4">
              <div class="card menu-card position-relative">
                <span class="category-badge">Utensils</span>
                <img src="image/Disposable Fork.jpg" class="card-img-top" alt="Disposable Fork">
                <div class="card-body">
                  <h6 class="fw-bold mb-1">Disposable Fork</h6>
                  <p class="text-muted small mb-4">Durable plastic use</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="price mb-0">₱2.50</p>
                    <button class="btn btn-add">Add</button>
                  </div>
                </div>
              </div>
            </div>
         </div>
        </div>
       </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("scroll", () => {
  if (window.scrollY > 10) {
    document.body.classList.add("scrolled");
  } else {
    document.body.classList.remove("scrolled");
  }
});
</script>

<script>
  // ==================== CLOCK SCRIPT ====================
  function updateClock() {
    const now = new Date();
    const dateEl = document.getElementById("currentDate");
    const timeEl = document.getElementById("currentTime");

    if (dateEl) {
      dateEl.textContent = now.toLocaleDateString("en-US", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    }

    if (timeEl) {
      timeEl.textContent = now.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
      });
    }
  }

  setInterval(updateClock, 1000);
  updateClock();

  // ==================== FILTER MENU SCRIPT ====================
  document.addEventListener("DOMContentLoaded", function () {
    const filterButtons = document.querySelectorAll(".filter-btn");
    const filterLabel = document.getElementById("filterMenuLabel");
    const dropdownBtn = document.getElementById("filterMenuButton");

    // ✅ Safely initialize Bootstrap dropdown (only if available)
    let dropdownMenu = null;
    if (window.bootstrap && dropdownBtn) {
      dropdownMenu = bootstrap.Dropdown.getOrCreateInstance(dropdownBtn);
    }

    // Main sections
    const foodMenu = document.querySelector(".menu-category.food");
    const nonFoodMenu = document.querySelector(".menu-category.non-food");

    // Subtypes
    const foodTypes = document.querySelectorAll(".menu-category.food .menu-type");
    const nonFoodTypes = document.querySelectorAll(".menu-category.non-food .menu-type");

    // Helper functions
    function hideAll() {
      if (foodMenu) foodMenu.style.display = "none";
      if (nonFoodMenu) nonFoodMenu.style.display = "none";
      foodTypes.forEach((type) => (type.style.display = "none"));
      nonFoodTypes.forEach((type) => (type.style.display = "none"));
    }

    function showType(selector) {
      document.querySelectorAll(selector).forEach((sec) => {
        sec.style.display = "block";
      });
    }

    // Default: show everything
    if (foodMenu) foodMenu.style.display = "block";
    if (nonFoodMenu) nonFoodMenu.style.display = "block";
    foodTypes.forEach((type) => (type.style.display = "block"));
    nonFoodTypes.forEach((type) => (type.style.display = "block"));

    // Filter button logic
    filterButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        const filter = btn.dataset.filter;

        // Reset active state
        filterButtons.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");

        // ✅ Update dropdown label text dynamically
        if (filterLabel) {
          filterLabel.textContent = btn.textContent.trim();
        }

        // ✅ Close dropdown (if Bootstrap is available)
        if (dropdownMenu) dropdownMenu.hide();

        // Filter logic
        hideAll();

        switch (filter) {
          case "all":
            if (foodMenu) foodMenu.style.display = "block";
            if (nonFoodMenu) nonFoodMenu.style.display = "block";
            foodTypes.forEach((t) => (t.style.display = "block"));
            nonFoodTypes.forEach((t) => (t.style.display = "block"));
            break;

          case "food":
            if (foodMenu) foodMenu.style.display = "block";
            foodTypes.forEach((t) => (t.style.display = "block"));
            break;

          case "non-food":
            if (nonFoodMenu) nonFoodMenu.style.display = "block";
            nonFoodTypes.forEach((t) => (t.style.display = "block"));
            break;

          // ---- FOOD CATEGORIES ----
          case "noodles":
          case "instant-noodles":
          case "ricemeals":
          case "snacks":
          case "drinks":
          case "lumpia":
            if (foodMenu) foodMenu.style.display = "block";
            showType(`.menu-type.${filter}`);
            break;

          // ---- NON-FOOD CATEGORIES ----
          case "dental-care":
          case "shampoo":
          case "conditioner":
          case "utensils":
            if (nonFoodMenu) nonFoodMenu.style.display = "block";
            showType(`.menu-type.${filter}`);
            break;

          default:
            if (foodMenu) foodMenu.style.display = "block";
            if (nonFoodMenu) nonFoodMenu.style.display = "block";
            foodTypes.forEach((t) => (t.style.display = "block"));
            nonFoodTypes.forEach((t) => (t.style.display = "block"));
        }
      });
    });
  });
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("menuSearch");
  const allCategories = document.querySelectorAll(".menu-category");
  const menuContainer = document.querySelector("#menuContainer") || document.body;

  // --- Add smooth fade animation ---
  const fadeOut = (el) => {
    el.style.transition = "opacity 0.3s ease";
    el.style.opacity = "0";
    setTimeout(() => (el.style.display = "none"), 300);
  };

  const fadeIn = (el) => {
    el.style.display = "";
    el.style.opacity = "0";
    el.style.transition = "opacity 0.3s ease";
    setTimeout(() => (el.style.opacity = "1"), 20);
  };

  searchInput.addEventListener("input", function () {
    const query = this.value.toLowerCase().trim();
    let anyVisible = false;

    // Remove old "No Results"
    const oldMsg = document.getElementById("noResults");
    if (oldMsg) oldMsg.remove();

    // If empty — show everything
    if (!query) {
      allCategories.forEach(category => {
        fadeIn(category);
        category.querySelectorAll(".menu-type").forEach(type => {
          fadeIn(type);
          type.querySelectorAll(".card").forEach(card => {
            const col = card.closest(".col-md-4, .col-lg-3, .col-sm-6");
            fadeIn(col);
            fadeIn(card);
          });
        });
      });
      // ✅ Show all subcategory titles again
      document.querySelectorAll("h5.fw-bold.mt-5.mb-3.text-maroon").forEach(h5 => fadeIn(h5));
      return;
    }

    // Main search logic
    allCategories.forEach(category => {
      const categoryTitle = category.querySelector("h3")?.textContent.toLowerCase() || "";
      const menuTypes = category.querySelectorAll(".menu-type");
      let categoryHasMatch = false;

      // Match "Food" or "Non-Food"
      if (categoryTitle.includes(query)) {
        fadeIn(category);
        menuTypes.forEach(type => {
          fadeIn(type);
          type.querySelectorAll(".card").forEach(card => {
            const col = card.closest(".col-md-4, .col-lg-3, .col-sm-6");
            fadeIn(col);
            fadeIn(card);
          });
        });
        anyVisible = true;
        categoryHasMatch = true;
      } else {
        // Check subcategories and cards
        menuTypes.forEach(type => {
          const typeTitle = type.querySelector("h5")?.textContent.toLowerCase() || "";
          const cards = type.querySelectorAll(".card");
          let typeHasMatch = false;

          // If subcategory title matches query
          if (typeTitle.includes(query)) {
            fadeIn(type);
            cards.forEach(card => {
              const col = card.closest(".col-md-4, .col-lg-3, .col-sm-6");
              fadeIn(col);
              fadeIn(card);
            });
            typeHasMatch = true;
            categoryHasMatch = true;
            anyVisible = true;
          } else {
            // Otherwise, check individual cards
            cards.forEach(card => {
              const name = card.querySelector("h6")?.textContent.toLowerCase() || "";
              const desc = card.querySelector("p")?.textContent.toLowerCase() || "";
              const col = card.closest(".col-md-4, .col-lg-3, .col-sm-6");

              if (name.includes(query) || desc.includes(query)) {
                fadeIn(col);
                fadeIn(card);
                typeHasMatch = true;
                categoryHasMatch = true;
                anyVisible = true;
              } else {
                fadeOut(col);
                fadeOut(card);
              }
            });
          }

          // ✅ Hide subcategory (like “Instant Noodles”) if no visible cards
          if (typeHasMatch) fadeIn(type);
          else fadeOut(type);
        });

        // ✅ Hide category (like “Food Menu” or “Non-Food Menu”) if no visible types
        if (categoryHasMatch) fadeIn(category);
        else fadeOut(category);
      }
    });

    // ✅ Hide "Instant Noodles" and all other subcategory titles if no items under them
    document.querySelectorAll("h5.fw-bold.mt-5.mb-3.text-maroon").forEach(h5 => {
      const title = h5.textContent.toLowerCase().trim();
      let hasVisibleCards = false;

      // Check if there are visible cards under this title
      const nextType = h5.nextElementSibling;
      if (nextType && nextType.querySelectorAll(".card").length > 0) {
        nextType.querySelectorAll(".card").forEach(card => {
          if (card.offsetParent !== null) hasVisibleCards = true;
        });
      }

      if (hasVisibleCards) fadeIn(h5);
      else fadeOut(h5);
    });

    // ✅ Show "No Results" message
    if (!anyVisible) {
      const msg = document.createElement("div");
      msg.id = "noResults";
      msg.innerHTML = `
        <div style="
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          width: 100%;
          padding: 60px 20px;
          background: #f8f8f8;
          border-radius: 12px;
          box-shadow: 0 2px 6px rgba(0,0,0,0.08);
          color: #555;
          font-family: 'Poppins', sans-serif;
          animation: fadeInMsg 0.3s ease;
        ">
          <div style="font-size: 3rem; line-height: 1;">🕵️‍♂️</div>
          <div style="font-size: 1.3rem; font-weight: 600; margin-top: 10px;">No matching items found.</div>
        </div>
      `;

      // Add smooth fade-in
      msg.style.opacity = "0";
      msg.style.transition = "opacity 0.3s ease";
      menuContainer.appendChild(msg);
      setTimeout(() => (msg.style.opacity = "1"), 50);
    }
  });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const addButtons = document.querySelectorAll(".btn-add");
  const cartButton = document.getElementById("cartButton");
  const cartSidebar = document.getElementById("cartSidebar");
  const cartOverlay = document.getElementById("cartOverlay");
  const closeCart = document.getElementById("closeCart");
  const cartCount = document.getElementById("cartCount");
  const cartItemsContainer = document.getElementById("cartItems");
  const totalEl = document.getElementById("total");
  const emptyCartEl = document.getElementById("emptyCart");
  const cartFooter = document.querySelector(".cart-footer");
  const confirmOrderBtn = document.getElementById("confirmOrderBtn");

  let cart = JSON.parse(localStorage.getItem("cartData")) || [];

  // === SIDEBAR TOGGLE ===
  const toggleCart = (show) => {
    if (show) {
      cartSidebar.classList.add("active");
      cartOverlay.classList.add("active");
    } else {
      cartSidebar.classList.remove("active");
      cartOverlay.classList.remove("active");
    }
  };
  cartButton.addEventListener("click", () => toggleCart(true));
  closeCart.addEventListener("click", () => toggleCart(false));
  cartOverlay.addEventListener("click", () => toggleCart(false));

  // === ADD TO CART ===
  addButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      const card = e.target.closest(".card");
      if (!card) return;

      const name = card.querySelector("h5, h6")?.textContent.trim() || "Unnamed";
      const img = card.querySelector("img")?.src || "";
      const priceText = card.querySelector(".price")?.textContent.trim() || "₱0";
      const price = parseFloat(priceText.replace(/[^\d.]/g, "")) || 0;

      const existing = cart.find((i) => i.name === name);
      if (existing) existing.qty++;
      else cart.push({ name, price, qty: 1, img });

      updateCart();
    });
  });

  // === UPDATE CART DISPLAY ===
  function updateCart() {
    cartItemsContainer.innerHTML = "";
    let total = 0, totalQty = 0;

    cart.forEach((item, index) => {
      total += item.price * item.qty;
      totalQty += item.qty;

      const cartItem = document.createElement("div");
      cartItem.classList.add("cart-item");
      cartItem.innerHTML = `
        <img src="${item.img}" alt="${item.name}">
        <div class="cart-item-info">
          <h6>${item.name}</h6>
          <p class="text-muted small mb-1">₱${item.price.toFixed(2)} each</p>
          <p class="fw-bold mb-1">₱${(item.price * item.qty).toFixed(2)}</p>
        </div>
        <div class="qty-control">
          <button class="btn-minus" data-index="${index}">−</button>
          <span>${item.qty}</span>
          <button class="btn-plus" data-index="${index}">+</button>
        </div>
      `;
      cartItemsContainer.appendChild(cartItem);
    });

    totalEl.textContent = `₱${total.toFixed(2)}`;
    cartCount.textContent = totalQty;

    const isEmpty = cart.length === 0;
    emptyCartEl.style.display = isEmpty ? "flex" : "none";
    cartFooter.classList.toggle("hidden", isEmpty);
    cartCount.style.display = totalQty > 0 ? "flex" : "none";

    localStorage.setItem("cartData", JSON.stringify(cart));

    document.querySelectorAll(".btn-minus").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const i = e.target.dataset.index;
        if (cart[i].qty > 1) cart[i].qty--;
        else cart.splice(i, 1);
        updateCart();
      });
    });

    document.querySelectorAll(".btn-plus").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const i = e.target.dataset.index;
        cart[i].qty++;
        updateCart();
      });
    });
  }

  updateCart();

  // === CONFIRM ORDER FLOW (NO PAYMENT OPTION, WITH LOADING SPINNER) ===
  confirmOrderBtn.addEventListener("click", async () => {
    if (cart.length === 0) {
      Swal.fire("Empty Cart", "Please add items before confirming.", "warning");
      return;
    }

    // Step 1: Review Order
    let summaryHtml = `
      <div style="
        font-family:'Poppins',sans-serif;
        color:#333;
        background:#fff;
        border:2px dashed #9c2b27;
        border-radius:10px;
        padding:15px;
        text-align:left;
        max-width:360px;
        margin:0 auto;
      ">
        <div style="text-align:center;border-bottom:2px dashed #ccc;padding-bottom:8px;margin-bottom:10px;">
          <h3 style="margin:0;color:#9c2b27;">Gitarra Apartelle</h3>
          <p style="margin:0;font-size:0.85em;color:#666;">Order List</p>
          <p style="margin:3px 0 0 0;font-size:0.8em;">Date: ${formatDateTime(new Date())}</p>
        </div>
        <ul style="list-style:none;padding-left:0;margin:0;">
    `;

    cart.forEach((i) => {
      summaryHtml += `
        <li style="
          padding:6px 0;
          border-bottom:1px dotted #ccc;
          display:grid;
          grid-template-columns: 1fr auto;
          align-items:flex-start;
          font-size:0.9em;
          gap:8px;
        ">
          <span><b>${i.name}</b> × ${i.qty}</span>
          <span style="text-align:right;font-weight:600;">₱${(i.price * i.qty).toFixed(2)}</span>
        </li>
      `;
    });

    const total = cart.reduce((a,b)=>a+b.price*b.qty,0);
    summaryHtml += `
        </ul>
        <div style="border-top:2px dashed #ccc;padding-top:10px;margin-top:10px;">
          <p style="margin:8px 0 0;font-weight:700;color:#9c2b27;text-align:right;">
            Total: ₱${total.toFixed(2)}
          </p>
        </div>
        <div style="border-top:2px dashed #ccc;margin-top:10px;text-align:center;padding-top:8px;font-size:0.8em;color:#555;">
          <p style="margin:0;">Thank you for ordering with ❤️</p>
          <p style="margin:0;color:#9c2b27;font-weight:600;">Gitarra Apartelle</p>
        </div>
      </div>
    `;

    const confirmRes = await Swal.fire({
      title: "🧾 Confirm Add Order?",
      html: summaryHtml,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes, Proceed",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#9c2b27",
      cancelButtonColor: "#555",
      background: "#fff",
      allowOutsideClick: false
    });

    if (!confirmRes.isConfirmed) return;

    // Step 2: Show Loading Spinner
    Swal.fire({
      title: "Saving Order...",
      text: "Please wait a moment",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    try {
      // Post each item to PHP backend
      await Promise.all(cart.map(item =>
        fetch("guest_add_order.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            item_name: item.name,
            price: item.price,
            quantity: item.qty,
            total: item.price * item.qty
          })
        })
      ));

      // Success toast
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: "Order saved successfully!",
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
      });

      setTimeout(() => {
        localStorage.removeItem("cartData");
        window.location.reload();
      }, 2000);
    } catch (error) {
      Swal.fire("Error", "Failed to save order. Please try again.", "error");
    }
  });

  // 🕒 Format Date and Time
  function formatDateTime(date) {
    const options = { month: "long", day: "numeric", year: "numeric" };
    const formattedDate = date.toLocaleDateString("en-US", options);
    const hours = date.getHours();
    const minutes = date.getMinutes().toString().padStart(2, "0");
    const ampm = hours >= 12 ? "PM" : "AM";
    const hour12 = hours % 12 || 12;
    const formattedTime = `${hour12}:${minutes} ${ampm}`;
    return `${formattedDate} | ${formattedTime}`;
  }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const viewBtn = document.getElementById("viewOrderBtn");

  viewBtn.addEventListener("click", async () => {
    const res = await fetch("guest_fetch_orders.php");
    const data = await res.json();

    if (!data.success) {
      Swal.fire("Error", data.message || "Failed to fetch orders.", "error");
      return;
    }

    let orders = data.orders;
    if (!orders.length) {
      Swal.fire("No Orders", "You have not placed any orders yet.", "info");
      return;
    }

    // ✅ Merge duplicate items
    const mergedOrders = [];
    orders.forEach(o => {
      const existing = mergedOrders.find(m => m.item_name === o.item_name);
      if (existing) {
        existing.quantity += parseInt(o.quantity);
        existing.price += parseFloat(o.price);
      } else {
        mergedOrders.push({
          item_name: o.item_name,
          quantity: parseInt(o.quantity),
          price: parseFloat(o.price)
        });
      }
    });

    const grandTotal = mergedOrders.reduce((sum, o) => sum + o.price, 0);

    // ✅ Show order summary
    Swal.fire({
      title: "🧾 Order Summary",
      html: `
        <div id="orderSummaryWrapper" style="overflow-x:auto;max-height:300px;">
          <table style="width:100%;border-collapse:collapse;font-family:'Poppins',sans-serif;font-size:0.9em;">
            <thead>
              <tr style="background:#222;color:#fff;">
                <th style="padding:8px;text-align:left;">Item</th>
                <th style="padding:8px;text-align:center;">Qty</th>
                <th style="padding:8px;text-align:right;">Price</th>
              </tr>
            </thead>
            <tbody>
              ${mergedOrders.map(o => `
                <tr style="border-bottom:1px solid #ddd;">
                  <td style="padding:6px;text-align:left;">${escapeHtml(o.item_name)}</td>
                  <td style="text-align:center;">${o.quantity}</td>
                  <td style="text-align:right;">₱${o.price.toFixed(2)}</td>
                </tr>
              `).join("")}
            </tbody>
            <tfoot>
              <tr style="border-top:2px solid #000;font-weight:bold;">
                <td colspan="2" style="text-align:right;padding:6px;">Total:</td>
                <td style="text-align:right;padding:6px;">₱${grandTotal.toFixed(2)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      `,
      width: "550px",
      confirmButtonText: "Download Receipt",
      confirmButtonColor: "#9c2b27",
      showCancelButton: true,
      cancelButtonText: "Close",
    }).then(async result => {
      if (result.isConfirmed) {
        const swalContent = Swal.getHtmlContainer();
        const summary = swalContent.querySelector("#orderSummaryWrapper");

        if (!summary) {
          Swal.fire("Error", "Receipt content not found.", "error");
          return;
        }

        await generatePOSPDF(summary);
      }
    });

    // ✅ Generate POS receipt style PDF
    async function generatePOSPDF(summaryElement) {
      Swal.fire({
        title: "Generating Receipt...",
        html: "Please wait while we create your receipt.",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      try {
        const clone = summaryElement.cloneNode(true);
        const wrapper = document.createElement("div");
        wrapper.style.position = "absolute";
        wrapper.style.left = "-9999px";
        wrapper.style.background = "#fff";
        wrapper.style.color = "#000";
        wrapper.style.width = "80mm"; /* ✅ Typical POS width */
        wrapper.style.fontFamily = "monospace";
        wrapper.style.fontSize = "12px";
        wrapper.style.lineHeight = "1.3";
        wrapper.style.padding = "10px";
        wrapper.style.textAlign = "left";

        wrapper.innerHTML = `
          <div style="text-align:center;">
            <h3 style="margin:0;">Gitarra Apartelle</h3>
            <hr style="border:1px dashed #000;margin:8px 0;">
            <p style="font-size:10px;">${formatDateTime(new Date())}</p>
            <hr style="border:1px dashed #000;margin:8px 0;">
          </div>
          ${clone.outerHTML}
          <hr style="border:1px dashed #000;margin:8px 0;">
          <div style="text-align:center;font-size:11px;">
            <p>Thank you for ordering with</p>
            <p>Visit us again soon!</p>
          </div>
        `;

        document.body.appendChild(wrapper);

        const canvas = await html2canvas(wrapper, { scale: 3 });
        const imgData = canvas.toDataURL("image/png");

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
          orientation: "p",
          unit: "mm",
          format: [80, (canvas.height * 80) / canvas.width], // ✅ auto height
        });

        pdf.addImage(imgData, "PNG", 0, 0, 80, (canvas.height * 80) / canvas.width);
        pdf.save(`Receipt_${new Date().toISOString().split("T")[0]}.pdf`);

        document.body.removeChild(wrapper);

        Swal.fire({
          icon: "success",
          title: "Receipt Downloaded!",
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          timer: 1500
        });
      } catch (err) {
        console.error(err);
        Swal.fire("Error", "Failed to generate receipt PDF.", "error");
      } finally {
        Swal.close();
      }
    }

    // ✅ Helper functions
    function escapeHtml(str) {
      return String(str).replace(/[&<>"']/g, s =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[s])
      );
    }

    function formatDateTime(date) {
      const options = { month: "long", day: "numeric", year: "numeric" };
      const formattedDate = date.toLocaleDateString("en-US", options);
      const hours = date.getHours();
      const minutes = date.getMinutes().toString().padStart(2, "0");
      const ampm = hours >= 12 ? "PM" : "AM";
      const hour12 = hours % 12 || 12;
      const formattedTime = `${hour12}:${minutes} ${ampm}`;
      return `${formattedDate} ${formattedTime}`;
    }
  });
});
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>