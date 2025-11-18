<?php
session_start();
require_once 'database.php';

// ✅ Set timezone for PHP and MySQL
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// =========================
// Validate QR access or session
// =========================
$room  = $_GET['room'] ?? ($_SESSION['room_number'] ?? null);
$token = $_GET['token'] ?? ($_SESSION['qr_code'] ?? null);

// Basic validation
if (empty($room) || empty($token)) {
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Access Denied</h3>
        <p>Missing or invalid access token. Please scan your room QR code again.</p>
    </div>');
}

// Sanitize inputs
$room  = htmlspecialchars(trim($room));
$token = htmlspecialchars(trim($token));

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
$stmt->bind_param("ss", $room, $token);
$stmt->execute();
$res  = $stmt->get_result();
$info = $res->fetch_assoc();
$stmt->close();

// Invalid keycard or room
if (!$info) {
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Invalid QR</h3>
        <p>Invalid or missing keycard record. Please contact the front desk.</p>
    </div>');
}

// If room is available → block access
if ($info['room_status'] === 'available') {
    unset($_SESSION['room_number'], $_SESSION['qr_code']);
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Access Denied</h3>
        <p>This room has been checked out. Please contact the front desk.</p>
    </div>');
}

// If room is occupied but keycard is inactive → reactivate automatically
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
        c.guest_name, 
        c.check_in_date, 
        c.check_out_date, 
        r.room_type,
        c.status AS checkin_status
    FROM keycards k
    LEFT JOIN checkins c 
        ON k.room_number = c.room_number 
        AND c.status = 'checked_in'
    LEFT JOIN rooms r 
        ON k.room_number = r.room_number
    WHERE k.room_number = ? 
      AND k.qr_code = ?
    LIMIT 1
");
$stmt->bind_param("ss", $room, $token);
$stmt->execute();
$guestInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Invalid or expired QR
if (!$guestInfo) {
    unset($_SESSION['room_number'], $_SESSION['qr_code']);
    die('<div style="padding:50px;text-align:center;font-family:Poppins,sans-serif;color:red;">
        <h3>❌ Invalid or Expired QR Code</h3>
        <p>Your session has expired. Please contact the front desk.</p>
    </div>');
}

// =========================
// Save session for continuity
// =========================
$_SESSION['room_number'] = $guestInfo['room_number'];
$_SESSION['qr_code']     = $guestInfo['qr_code'];

// =========================
// Extract guest info
// =========================
$guest_name = $guestInfo['guest_name'] ?? 'Guest';
$room_type  = $guestInfo['room_type'] ?? 'Standard Room';
$check_in   = !empty($guestInfo['check_in_date']) 
    ? date('F j, Y g:i A', strtotime($guestInfo['check_in_date'])) 
    : 'N/A';
$check_out  = !empty($guestInfo['check_out_date']) 
    ? date('F j, Y g:i A', strtotime($guestInfo['check_out_date'])) 
    : 'N/A';
$status     = ucfirst($guestInfo['checkin_status'] ?? 'Pending');

// =========================
// Auto-cancel overdue bookings
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
        error_log('Auto-cancel failed: ' . $e->getMessage());
    }
}
autoCancelOverdueBookings($conn);

// =========================
// Fetch announcements
// =========================
$announcements_result = $conn->query("
    SELECT * 
    FROM announcements 
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!$announcements_result) {
    error_log('Failed to fetch announcements: ' . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <!-- ==================== HEAD SECTION ==================== -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gitarra Apartelle - Guest Order</title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">

  <!-- ========== FONTS & ICONS ========== -->
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
          --maroon: #871D2B;
          --maroon-dark: #800000;
          --matte-black: #1c1c1c;
          --text-gray: #6c757d;
          --card-bg: #f8f8f8ff;
          --hover-bg: #f3f3f3ff;
        }

        .text-maroon {
          color: var(--maroon) !important; /* Classic maroon */
        }

        .badge.bg-maroon {
          background-color: var(--maroon); /* Classic maroon */
          color: #fff;
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

/* === Scroll to Top Button Style === */
#scrollTopBtn {
  position: fixed;
  bottom: 25px;
  right: 25px;
  background-color: #9c2b27;
  border: none;
  outline: none;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
  z-index: 999;
}

/* SVG icon */
#scrollTopBtn svg {
  width: 22px;
  height: 22px;
}

/* Show button */
#scrollTopBtn.show {
  opacity: 1;
  visibility: visible;
  animation: floatUp 1.8s ease-in-out infinite;
}

/* Hover effect */
#scrollTopBtn:hover {
  background-color: #b93731;
  transform: scale(1.1);
}

/* Floating Up Animation */
@keyframes floatUp {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
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

.add-btn-wrapper {
    display: inline-block;
    position: relative;
}

.info-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #0dcaf0;
    color: white;
    font-size: 11px;
    font-weight: bold;
    padding: 2px 5px;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 0 3px rgba(0,0,0,0.3);
}

    .card-body {
      padding: 1rem 1.25rem 1.25rem;
    }

    .card-img-top {
      height: 180px;
      object-fit: cover;
    }
    
    /* ============================================
   RESPONSIVE FIXES (MATCHING GUEST DASHBOARD)
   ============================================ */

/* --- TABLETS (≤ 992px) --- */
@media (max-width: 992px) {

  .sidebar {
    width: 230px;
  }

  .content {
    margin-left: 240px;
    padding: 20px;
  }

  .menu-card {
    margin-bottom: 15px;
  }

  .card-img-top {
    height: 160px;
  }

  #cartButton, .btn-view {
    padding: 6px 14px;
    font-size: 0.85rem;
  }
}


/* --- MOBILE (≤ 768px) --- */
@media (max-width: 768px){

  /* Sidebar converts to top bar */
  .sidebar {
    position: fixed;
    width: 100%;
    height: auto;
    flex-direction: row;
    justify-content: space-between;
    padding: 10px 12px;
    z-index: 999;
  }

  .sidebar h4 {
    margin-bottom: 0;
    font-size: 1rem;
  }

  .user-info { display: none; }
  .signout { display: none; }

  .nav-links {
    flex-direction: row;
    padding: 0;
    justify-content: center;
    margin-top: 4px;
  }

  .nav-links a {
    padding: 8px 12px;
    font-size: 13px;
    margin: 0 3px;
  }

  .nav-links a i {
    font-size: 15px;
  }

  /* Content shifts down below sidebar */
  .content {
    margin-left: 0;
    margin-top: 90px;
    padding: 15px;
  }

  /* Search bar responsive */
  .search-box {
    width: 100%;
    margin-bottom: 12px;
  }

  /* Sticky filter bar responsive */
  .btn-filter-toggle {
    padding: 6px 14px;
    font-size: 0.85rem;
  }

  /* Menu cards adjust */
  .card-img-top {
    height: 150px;
  }

  .menu-card {
    border-radius: 14px;
  }

  .card-body {
    padding: 0.9rem;
  }

  .price {
    font-size: 0.95rem;
  }

  .btn-add {
    padding: 5px 12px;
    font-size: 0.8rem;
  }

  /* Cart button responsive */
  #cartButton {
    padding: 6px 14px;
    font-size: 0.8rem;
  }

  #cartCount {
    width: 18px;
    height: 18px;
    font-size: 10px;
  }

  /* Cart sidebar responsive */
  .cart-sidebar {
    width: 100%;
    right: -100%;
  }

  .cart-sidebar.active {
    right: 0;
  }

  .cart-items {
    max-height: 60%;
  }

  .cart-item img {
    width: 50px;
    height: 50px;
  }

  .qty-control button {
    width: 22px;
    height: 22px;
  }

  .btn-confirm {
    padding: 8px 0;
    font-size: 0.9rem;
  }

  #scrollTopBtn {
    bottom: 20px;
    right: 20px;
  }
}


/* --- EXTRA SMALL (≤ 480px) --- */
@media (max-width: 480px){

  .nav-links a {
    font-size: 11px;
    padding: 6px 8px;
  }

  .btn-filter-toggle {
    font-size: 0.78rem;
    padding: 6px 12px;
  }

  .menu-card {
    border-radius: 12px;
  }

  .card-img-top {
    height: 130px;
  }

  .card-body {
    padding: 0.8rem;
  }

  .btn-add {
    font-size: 0.75rem;
    padding: 5px 12px;
  }

  #cartButton {
    padding: 5px 10px;
    font-size: 0.7rem;
  }

  #cartCount {
    width: 16px;
    height: 16px;
    font-size: 9px;
  }

  .cart-item h6 {
    font-size: 0.8rem;
  }

  .qty-control button {
    width: 20px;
    height: 20px;
  }

  .btn-confirm {
    padding: 7px 0;
    font-size: 0.85rem;
  }

  #scrollTopBtn {
    width: 40px;
    height: 40px;
  }

  #scrollTopBtn svg {
    width: 18px;
    height: 18px;
  }
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
            <li><button class="dropdown-item filter-btn" data-filter="food"><i class="bi bi-box-seam me-2"></i>Food</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="noodles"><i class="bi bi-cup-hot me-2"></i>Noodles</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="ricemeals"><i class="bi bi-egg-fried me-2"></i>Rice Meals</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="lumpia"><i class="bi bi-bag-fill me-2"></i>Lumpia</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="snacks"><i class="bi bi-cookie me-2"></i>Snacks</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="drinks"><i class="bi bi-cup-straw me-2"></i>Drinks</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item filter-btn" data-filter="non-food"><i class="bi bi-box-seam me-2"></i>Non-Food</button></li>
            <li><button class="dropdown-item filter-btn" data-filter="dental-care"><i class="bi bi-heart-pulse me-2"></i>Dental Care</button></li>
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
              <button class="btn w-100 btn-secondary mb-2" id="remarksBtn">
                Add / Edit Remarks
              </button>
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

          <button id="scrollTopBtn" title="Go to top" aria-label="Scroll to top">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="18 15 12 9 6 15" />
            </svg>
          </button>
        </div>
  </div>

  <!-- ==================== MENU CARD ==================== -->
  <div class="card shadow-lg border-0 p-1 my-2">
    <div class="card-body" id="menuContainer">

      <!-- ==================== FOOD MENU ==================== -->
      <div class="menu-category food" id="category-food">
        <div class="container my-4">
          <h3 class="text-center text-maroon mb-4 fw-bold text-uppercase">Food Menu</h3>

          <!-- ==================== NOODLES ==================== -->
          <div class="menu-type noodles" id="type-noodles">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Noodles</h5>
            <div class="row g-4 mb-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Noodles'
              $query = "SELECT * FROM supplies WHERE category='Food' AND type='Noodles' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Noodle items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- ==================== RICE MEALS ==================== -->
          <div class="menu-type ricemeals" id="type-riceMeals">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Rice Meals</h5>
            <div class="row g-4 mb-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Rice Meals'
              $query = "SELECT * FROM supplies WHERE category='Food' AND type='Rice Meals' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Rice Meals items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- ==================== LUMPIA ==================== -->
          <div class="menu-type lumpia" id="type-lumpia">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Lumpia</h5>
            <div class="row g-4 mb-4">
              
              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Lumpia'
              $query = "SELECT * FROM supplies WHERE category='Food' AND type='Lumpia' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Lumpia items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- ==================== SNACKS ==================== -->
          <div class="menu-type snacks" id="type-snacks">
            <h5 class="fw-bold mt-5 mb-3 text-maroon">Snacks</h5>
            <div class="row g-4 mb-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Snacks'
              $query = "SELECT * FROM supplies WHERE category='Food' AND type='Snacks' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Snacks items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- ==================== DRINKS ==================== -->
          <div class="menu-type drinks" id="type-drinks">

            <!-- ==================== WATER ==================== -->
            <div class="menu-type water" id="type-water">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Water</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Water'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Water' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Water items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

            <!-- ==================== ICE ==================== -->
            <div class="menu-type ice" id="type-ice">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Ice</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Ice'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Ice' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Ice items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

            <!-- ==================== SOFTDRINKS ==================== -->
            <div class="menu-type softdrinks" id="type-softdrinks">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Softdrinks</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Softdrinks'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Softdrinks' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Softdrinks items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

            <!-- ==================== SHAKES ==================== -->
            <div class="menu-type shakes" id="type-shakes">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Shakes</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Shakes'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Shakes' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Shakes items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

            <!-- ==================== JUICE ==================== -->
            <div class="menu-type juice" id="type-juice">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Juice</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Juice'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Juice' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Juice items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

            <!-- ==================== COFFEE ==================== -->
            <div class="menu-type coffee" id="type-coffee">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Coffee</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Coffee'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Coffee' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Coffee items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

            <!-- ==================== TEAS ==================== -->
            <div class="menu-type teas" id="type-teas">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Teas</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Teas'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Teas' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Teas items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

            <!-- ==================== OTHER DRINKS ==================== -->
            <div class="menu-type other-drinks" id="type-otherDrinks">
              <h5 class="fw-bold mt-5 mb-3 text-maroon">Other Drinks</h5>
              <div class="row g-4">

                <?php
                // Database connection
                $conn = new mysqli($host, $username, $password, $db_name);
                if ($conn->connect_error) {
                  die("<p class='text-danger text-center'>Database connection failed.</p>");
                }

                // Fetch all Food items of type 'Other Drinks'
                $query = "SELECT * FROM supplies WHERE category='Food' AND type='Other Drinks' AND is_archived = 0 ORDER BY id ASC";
                $result = $conn->query($query);

                if ($result && $result->num_rows > 0):
                  while ($row = $result->fetch_assoc()):
                    $status = strtolower($row['status']);
                    $isAvailable = $status === 'available' && $row['quantity'] > 0;

                    // Add grayscale filter for unavailable images
                    $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
                ?>
                    <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                      <div class="card menu-card position-relative">
                        <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                        <img src="<?= htmlspecialchars($row['image']) ?>" 
                          class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                        style="<?= $imageStyle ?>">

                        <div class="card-body">
                          <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                          <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                            <?php if ($isAvailable): ?>
                              <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                            <?php else: ?>
                              <span class="text-danger small">Out of Stock</span>
                            <?php endif; ?>
                          </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>

                <?php
                  endwhile;
                else:
                  echo "<p class='text-center text-muted'>No Other Drinks items found.</p>";
                endif;

                $conn->close();
                ?>

              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ==================== NON FOOD ==================== -->
      <div class="menu-category non-food" id="category-nonfood">
        <div class="container my-4">
          <h3 class="text-center mb-4 fw-bold text-uppercase">Non-Food Menu</h3>

          <!-- Essentials -->
          <div class="menu-type essentials" id="type-essentials">
            <h5 class="fw-bold mt-5 mb-3 text-black">Essentials</h5>
            <div class="row g-4">
                
              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Essentials'
              $query = "SELECT * FROM supplies WHERE category='Non-Food' AND type='Essentials' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Essentials items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- Dental Care -->
          <div class="menu-type dental-care" id="type-dentalCare">
            <h5 class="fw-bold mt-5 mb-3 text-black">Dental Care</h5>
            <div class="row g-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Dental Care'
              $query = "SELECT * FROM supplies WHERE category='Non-Food' AND type='Dental Care' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Dental Care items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- Feminine Hygiene -->
          <div class="menu-type feminine-hygiene" id="type-feminineHygiene">
            <h5 class="fw-bold mt-5 mb-3 text-black">Feminine Hygiene</h5>
            <div class="row g-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Feminine Hygiene'
              $query = "SELECT * FROM supplies WHERE category='Non-Food' AND type='Feminine Hygiene' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Feminine Hygiene items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- Shampoo -->
          <div class="menu-type shampoo" id="type-shampoo">
            <h5 class="fw-bold mt-5 mb-3 text-black">Shampoo</h5>
            <div class="row g-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Shampoo'
              $query = "SELECT * FROM supplies WHERE category='Non-Food' AND type='Shampoo' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Shampoo items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- Conditioner -->
          <div class="menu-type conditioner" id="type-conditioner">
            <h5 class="fw-bold mt-5 mb-3 text-black">Conditioner</h5>
            <div class="row g-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Conditioner'
              $query = "SELECT * FROM supplies WHERE category='Non-Food' AND type='Conditioner' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Conditioner items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- Trust Condom -->
          <div class="menu-type personal-protection" id="type-personalProtection">
            <h5 class="fw-bold mt-5 mb-3 text-black">Personal Protection</h5>
            <div class="row g-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Personal Protection'
              $query = "SELECT * FROM supplies WHERE category='Non-Food' AND type='Personal Protection' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Personal Protection items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>

          <!-- Disposable Utensils -->
          <div class="menu-type utensils" id="type-utensils">
            <h5 class="fw-bold mt-5 mb-3 text-black">Disposable Utensils</h5>
            <div class="row g-4">

              <?php
              // Database connection
              $conn = new mysqli($host, $username, $password, $db_name);
              if ($conn->connect_error) {
                die("<p class='text-danger text-center'>Database connection failed.</p>");
              }

              // Fetch all Food items of type 'Disposable Utensils'
              $query = "SELECT * FROM supplies WHERE category='Non-Food' AND type='Disposable Utensils' AND is_archived = 0 ORDER BY id ASC";
              $result = $conn->query($query);

              if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                  $status = strtolower($row['status']);
                  $isAvailable = $status === 'available' && $row['quantity'] > 0;

                  // Add grayscale filter for unavailable images
                  $imageStyle = $isAvailable ? '' : 'filter: grayscale(100%) brightness(70%);';
              ?>
                  <div class="col-md-4 col-lg-4" id="item-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $row['name']))) ?>">
                    <div class="card menu-card position-relative">
                      <span class="category-badge"><?= htmlspecialchars($row['type']) ?></span>

                      <img src="<?= htmlspecialchars($row['image']) ?>" 
                        class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>"
                      style="<?= $imageStyle ?>">

                      <div class="card-body">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['name']) ?></h6>

                        <div class="stock-badge mt-2 mb-4 d-flex justify-content-between align-items-center">
                          <?php if ($isAvailable): ?>
                            <span class="text-success small">In Stock (<?= $row['quantity'] ?>)</span>
                          <?php else: ?>
                            <span class="text-danger small">Out of Stock</span>
                          <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                          <p class="price mb-0">₱<?= number_format($row['price'], 2) ?></p>
                          <div class="add-btn-wrapper position-relative">
                            <button 
                              class="btn btn-add" 
                              id="add-btn-<?= $row['id'] ?>" 
                              onclick="addItem(<?= $row['id'] ?>)"
                              <?= $isAvailable ? '' : 'disabled' ?>>
                              Add
                            </button>
                        
                            <!-- Hidden Info Badge -->
                            <span 
                              id="info-badge-<?= $row['id'] ?>" 
                              class="info-badge"
                              style="display:none;"
                              onclick="showPreparingWarning()">
                              <i class="bi bi-info-lg text-white" style="font-size:12px;"></i>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

              <?php
                endwhile;
              else:
                echo "<p class='text-center text-muted'>No Disposable Utensils items found.</p>";
              endif;

              $conn->close();
              ?>

            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ==== ADD 'SCROLLED' CLASS TO BODY WHEN PAGE IS SCROLLED ==== -->
<script>
document.addEventListener("scroll", () => {
  if (window.scrollY > 10) {
    document.body.classList.add("scrolled");
  } else {
    document.body.classList.remove("scrolled");
  }
});
</script>

<!-- ====== SCROLL-TO-TOP BUTTON WITH SMOOTH WHOOSH EFFECT ====== -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const scrollTopBtn = document.getElementById("scrollTopBtn");

  // Show/hide button on scroll
  window.addEventListener("scroll", () => {
    if (window.scrollY > 300) {
      scrollTopBtn.classList.add("show");
    } else {
      scrollTopBtn.classList.remove("show");
    }
  });

  // === Custom Smooth Scroll Up (Fun "Whoosh" Effect) ===
  function scrollToTop() {
    const start = window.scrollY;
    const duration = 800; // ms
    const startTime = performance.now();

    // Easing function: easeOutCubic (fast then slow)
    function easeOutCubic(t) {
      return 1 - Math.pow(1 - t, 3);
    }

    function animateScroll(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const ease = easeOutCubic(progress);
      window.scrollTo(0, start * (1 - ease));

      if (elapsed < duration) {
        requestAnimationFrame(animateScroll);
      }
    }

    requestAnimationFrame(animateScroll);
  }

  scrollTopBtn.addEventListener("click", scrollToTop);
});
</script>

<!-- ============== CLOCK & FILTER MENU SCRIPT ================== -->
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
          case "lumpia":
            if (foodMenu) foodMenu.style.display = "block";
            showType(`.menu-type.${filter}`);
            break;

          // ✅ IMPROVED DRINKS LOGIC
          case "drinks":
            if (foodMenu) foodMenu.style.display = "block";
            // Show the drinks section and all its subcategories
            const drinksSection = document.querySelector(".menu-type.drinks");
            if (drinksSection) drinksSection.style.display = "block";

            // Show all drink subtypes dynamically (water, ice, softdrinks, shakes)
            const drinkSubtypes = drinksSection.querySelectorAll(".menu-type");
            if (drinkSubtypes.length > 0) {
              drinkSubtypes.forEach((sub) => (sub.style.display = "block"));
            } else {
              // Fallback in case subtypes are direct children
              document.querySelectorAll(".menu-type.drinks .menu-type").forEach((sub) => {
                sub.style.display = "block";
              });
            }
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

<!-- ==================== SEARCH MENU SCRIPT ==================== -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("menuSearch");
  const allItems = document.querySelectorAll(".menu-type .col-md-4, .menu-type .col-lg-4");
  const menuContainer = document.getElementById("menuContainer");

  // === Utility: Normalize text ===
  const normalize = (text) => text.toLowerCase().trim();

  // === Utility: Simple Levenshtein distance ===
  function levenshtein(a, b) {
    const m = [];
    for (let i = 0; i <= b.length; i++) m[i] = [i];
    for (let j = 0; j <= a.length; j++) m[0][j] = j;
    for (let i = 1; i <= b.length; i++) {
      for (let j = 1; j <= a.length; j++) {
        m[i][j] = b[i - 1] === a[j - 1]
          ? m[i - 1][j - 1]
          : Math.min(m[i - 1][j - 1] + 1, m[i][j - 1] + 1, m[i - 1][j] + 1);
      }
    }
    return m[b.length][a.length];
  }

  // === Utility: Fuzzy match threshold ===
  const isFuzzyMatch = (query, text) => {
    if (!query || !text) return false;
    query = normalize(query);
    text = normalize(text);
    if (text.includes(query)) return true;
    const distance = levenshtein(query, text);
    const tolerance = Math.max(1, Math.floor(text.length * 0.25)); // 25% typo tolerance
    return distance <= tolerance;
  };

  // === Main search handler ===
  searchInput.addEventListener("input", () => {
    const query = normalize(searchInput.value);
    let anyVisible = false;

    // === Reset everything if empty ===
    if (!query) {
      document.querySelectorAll(".menu-category, .menu-type, .menu-type h5, .col-md-4, .col-lg-4")
        .forEach((el) => (el.style.display = "block"));
      const noResult = document.getElementById("noResults");
      if (noResult) noResult.remove();
      return;
    }

    // === Filter items ===
    allItems.forEach((item) => {
      const itemName = normalize(item.querySelector("h6")?.textContent || "");
      const typeId = normalize(item.closest(".menu-type")?.id || "");
      const categoryId = normalize(item.closest(".menu-category")?.id || "");
      const combinedText = `${itemName} ${typeId} ${categoryId}`;

      if (isFuzzyMatch(query, combinedText)) {
        item.style.display = "block";
        anyVisible = true;
      } else {
        item.style.display = "none";
      }
    });

    // === Update each type block visibility ===
    document.querySelectorAll(".menu-type").forEach((typeBlock) => {
      const visibleItems = typeBlock.querySelectorAll(
        '.col-md-4[style*="display: block"], .col-lg-4[style*="display: block"]'
      );
      const sectionTitles = typeBlock.querySelectorAll("h5");

      if (visibleItems.length > 0) {
        typeBlock.style.display = "block";
        sectionTitles.forEach((t) => (t.style.display = "block"));
      } else {
        typeBlock.style.display = "none";
        sectionTitles.forEach((t) => (t.style.display = "none"));
      }
    });

    // === Update category (Food / Non-Food) visibility ===
    document.querySelectorAll(".menu-category").forEach((catBlock) => {
      const visibleTypes = catBlock.querySelectorAll('.menu-type[style*="display: block"]');
      catBlock.style.display = visibleTypes.length ? "block" : "none";
    });

    // === Handle "No Results" message ===
    let noResultMsg = document.getElementById("noResults");
    if (!anyVisible) {
      if (!noResultMsg) {
        noResultMsg = document.createElement("div");
        noResultMsg.id = "noResults";
        noResultMsg.innerHTML = `
          <div style="
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            width:100%;
            padding:60px 20px;
            background:#f8f8f8;
            border-radius:12px;
            box-shadow:0 4px 10px rgba(0,0,0,0.05);
          ">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="#999" viewBox="0 0 24 24">
              <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8.009 8.009 0 0 1-8 8Zm0-5a2.996 2.996 0 0 0-2.816 2H8a4 4 0 0 1 8 0h-1.184A2.996 2.996 0 0 0 12 15Zm-3-5a1 1 0 1 1-1-1 1 1 0 0 1 1 1Zm6 0a1 1 0 1 1-1-1 1 1 0 0 1 1 1Z"/>
            </svg>
            <p style="color:#777;font-weight:500;margin-top:10px;font-size:1.1rem;">
              No matching items found.
            </p>
          </div>
        `;
        menuContainer.appendChild(noResultMsg);
      }
    } else if (noResultMsg) {
      noResultMsg.remove();
    }
  });
});
</script>

<!-- ============== FETCH AND DISPLAY STOCK BADGES ============== -->
<script>
document.addEventListener("DOMContentLoaded", async () => {
  try {
    const res = await fetch("guest_fetch_supplies.php");
    const stockData = await res.json();

    document.querySelectorAll(".menu-card").forEach((card) => {
      const name = card.querySelector("h5, h6")?.textContent.trim().toLowerCase();
      if (!name || !stockData[name]) return;

      const { quantity, status } = stockData[name];
      const badgeContainer = card.querySelector(".stock-badge");

      let qtyBadge = "";
      let statusBadge = "";

      // ✅ Show maroon quantity badge only if not infinite (999)
      if (quantity !== 999) {
        qtyBadge = `<span class="badge bg-maroon">Quantity: ${quantity ?? 0}</span>`;
      }

      // ✅ Always right-aligned status badge
      if (status === "unavailable") {
        statusBadge = `<span class="badge bg-secondary">Unavailable</span>`;
      } else if (quantity === 0) {
        statusBadge = `<span class="badge bg-danger">Out of Stock</span>`;
      } else if (quantity > 0 && quantity <= 5) {
        statusBadge = `<span class="badge bg-warning text-dark">Low Stock</span>`;
      } else if (quantity >= 6 || quantity === 999) {
        statusBadge = `<span class="badge bg-success">Available</span>`;
      }

      // ✅ Align quantity on the left, status on the right
      badgeContainer.innerHTML = `
        <div class="d-flex justify-content-between align-items-center w-100">
          <div>${qtyBadge}</div>
          <div>${statusBadge}</div>
        </div>
      `;
    });
  } catch (err) {
    console.error("Error fetching supplies:", err);
  }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {

    fetch("guest_fetch_orders.php")
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            const orders = data.orders;

            orders.forEach(o => {

                if (o.status.toLowerCase() === "preparing") {
                    const itemId = o.supply_id;

                    let addBtn = document.getElementById("add-btn-" + itemId);
                    let badge = document.getElementById("info-badge-" + itemId);

                    if (addBtn && badge) {
                        addBtn.disabled = true;
                        badge.style.display = "block";
                    }
                }
            });
        });
});

function addItem(itemId) {
    fetch("guest_fetch_orders.php")
    .then(response => response.json())
    .then(data => {

        const existing = data.orders.find(o =>
            Number(o.supply_id) === Number(itemId) &&
            o.status.toLowerCase() === "preparing"
        );

        if (existing) {
            showPreparingWarning();
            return;
        }

        // Allowed → proceed
        addToOrders(itemId);
    });
}

function showPreparingWarning() {
    Swal.fire({
        icon: "info",
        title: "Item Already Preparing",
        text: "You can add this item after it is prepared.",
        confirmButtonColor: "#8b0000"
    });
}
</script>

<!-- ================ CART FUNCTIONALITY SCRIPT ================ -->
<script>
document.addEventListener("DOMContentLoaded", async () => {
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
  let stockData = {};

  // ✅ Fetch item quantity + status from PHP
  try {
    const res = await fetch("guest_fetch_supplies.php");
    stockData = await res.json(); // { "Mami": { quantity: 5, status: "available" }, ... }
  } catch (e) {
    console.error("Error fetching stock data:", e);
    stockData = {};
  }

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

  // ✅ Add button event
  addButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      const card = e.target.closest(".card");
      if (!card) return;

      const name = card.querySelector("h5, h6")?.textContent.trim() || "Unnamed";
      const img = card.querySelector("img")?.src || "";
      const priceText = card.querySelector(".price")?.textContent.trim() || "₱0";
      const price = parseFloat(priceText.replace(/[^\d.]/g, "")) || 0;
      const key = name.toLowerCase().trim();

      // ✅ Get stock data
      const itemData = stockData[name] || stockData[key];
      const quantity = itemData?.quantity;
      const status = itemData?.status ?? "available";

      // ✅ Handle unavailable cases
      if (status === "unavailable") {
        if (quantity == 999) {
          Swal.fire({
            icon: "error",
            title: "Unavailable",
            text: `"${name}" is currently unavailable.`,
            confirmButtonColor: "#9c2b27"
          });
          return;
        } else if (quantity == 0) {
          Swal.fire({
            icon: "error",
            title: "Out of Stock",
            text: `"${name}" is currently out of stock.`,
            confirmButtonColor: "#9c2b27"
          });
          return;
        }
      }

      // ✅ Infinite stock condition
      const isInfinite = quantity == 999;

      // ✅ Validate stock only if not infinite
      if (!isInfinite && quantity !== null && quantity !== undefined) {
        const stock = parseInt(quantity, 10);

        if (stock <= 0) {
          Swal.fire({
            icon: "error",
            title: "Out of Stock",
            text: `"${name}" is currently out of stock.`,
            confirmButtonColor: "#9c2b27"
          });
          return;
        }

        // ✅ Check if cart already reached stock limit
        const existing = cart.find((i) => i.name === name);
        if (existing && existing.qty >= stock) {
          Swal.fire({
            icon: "warning",
            title: "Stock Limit Reached",
            text: `You can only order up to ${stock} of "${name}".`,
            confirmButtonColor: "#9c2b27"
          });
          return;
        }
      }

      // ✅ Passed validation — Add or increment item in cart
      const existing = cart.find((i) => i.name === name);
      if (existing) {
        existing.qty++;
      } else {
        cart.push({
          name,
          price,
          qty: 1,
          img,
          stock: isInfinite ? Infinity : (quantity ?? 0),
          limited: !isInfinite
        });
      }

      updateCart();
    });
  });

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
        const key = cart[i].name.toLowerCase().trim();
        const stock = stockData[cart[i].name]?.quantity ?? stockData[key]?.quantity ?? 999;
        if (cart[i].qty >= stock) {
          Swal.fire({
            icon: "warning",
            title: "Stock Limit Reached",
            text: `You can only order up to ${stock} of "${cart[i].name}".`,
            confirmButtonColor: "#9c2b27"
          });
          return;
        }
        cart[i].qty++;
        updateCart();
      });
    });
  }

  updateCart();
  
// ===============================
// REMARKS FUNCTION
// ===============================
const remarksBtn = document.getElementById("remarksBtn");

if (remarksBtn) {
  remarksBtn.addEventListener("click", () => {
    openRemarksModal();
  });
}

async function openRemarksModal() {
  try {
    const fetchRes = await fetch("guest_fetch_remarks.php");
    const remarksData = await fetchRes.json();
    const existingNotes = remarksData.success ? remarksData.notes : "";

    const { value: notes } = await Swal.fire({
      title: "📝 Add / Edit Remarks",
      input: "textarea",
      inputLabel: "Enter remarks for your order",
      inputPlaceholder: "Write your remarks here...",
      inputValue: existingNotes,
      inputAttributes: { maxlength: 300 },
      showCancelButton: true,
      confirmButtonText: "Save",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#9c2b27",
      preConfirm: async (value) => {
        try {
          const res = await fetch("guest_add_remarks.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ notes: value.trim() })
          });
          const result = await res.json();
          if (!result.success) throw new Error(result.message);
          return result.message;
        } catch (err) {
          Swal.showValidationMessage(`Error: ${err.message}`);
          return false;
        }
      }
    });

    if (notes !== undefined) {
      Swal.fire({
        icon: "success",
        title: "Success",
        text: "Your remarks have been updated.",
        timer: 1500,
        showConfirmButton: false
      });
    }
  } catch (err) {
    Swal.fire("Error", "Failed to load or save remarks.", "error");
  }
}

confirmOrderBtn.addEventListener("click", async () => {
  if (cart.length === 0) {
    Swal.fire("Empty Cart", "Please add items before confirming.", "warning");
    return;
  }

  // ==============================
  // ✅ FETCH REMARKS (Compact Version)
  // ==============================
  let remarksText = "";
  try {
    const r = await fetch("guest_fetch_remarks.php");
    const d = await r.json();
    if (d.success && d.notes?.trim()) remarksText = d.notes.trim();
  } catch (err) {
    console.warn("Failed to fetch remarks:", err);
  }

  // ==============================
  // BUILD ORDER SUMMARY
  // ==============================
  let summaryHtml = `
    <div style="
      font-family:'Poppins',sans-serif;
      color:#333;
      background:#fff;
      border:2px dashed #9c2b27;
      border-radius:10px;
      padding:15px;
      max-width:360px;
      margin:0 auto;
    ">
      <div style="
        text-align:center;
        border-bottom:2px dashed #ccc;
        padding-bottom:8px;
        margin-bottom:10px;
      ">
        <h3 style="margin:0;color:#9c2b27;">Gitarra Apartelle</h3>
        <p style="margin:0;font-size:0.85em;color:#666;">Order List</p>
        <p style="margin:3px 0 0;font-size:0.8em;">Date: ${formatDateTime(new Date())}</p>
      </div>

      <ul style="list-style:none;padding:0;margin:0;">
  `;

  cart.forEach((i) => {
    summaryHtml += `
      <li style="
        padding:5px 0;
        border-bottom:1px dotted #ccc;
        display:grid;
        grid-template-columns:1fr auto;
        font-size:0.9em;
        gap:6px;
      ">
        <span><b>${i.name}</b> × ${i.qty}</span>
        <span style="text-align:right;font-weight:600;">₱${(i.price * i.qty).toFixed(2)}</span>
      </li>
    `;
  });

  const total = cart.reduce((a, b) => a + b.price * b.qty, 0);

  summaryHtml += `
      </ul>

      <div style="border-top:2px dashed #ccc;padding-top:8px;margin-top:10px;">
        <p style="margin:5px 0 0;font-weight:700;color:#9c2b27;text-align:right;">
          Total: ₱${total.toFixed(2)}
        </p>
      </div>
  `;

  // ==============================
  // ✅ ULTRA-COMPACT REMARKS BOX
  // ==============================
  if (remarksText) {
    summaryHtml += `
      <div style="
        margin-top:8px;
        padding:6px 8px;
        background:#f9f9f9;
        border-left:3px solid #9c2b27;
        border-radius:4px;
        font-size:0.75em;
        color:#444;
        line-height:1.15em;
      ">
        <b style="color:#9c2b27;">Remarks:</b>
        <div style="margin-top:2px;">
          ${remarksText.replace(/</g, "&lt;")}
        </div>
      </div>
    `;
  }

  summaryHtml += `
      <div style="
        border-top:2px dashed #ccc;
        margin-top:10px;
        text-align:center;
        padding-top:6px;
        font-size:0.8em;
        color:#555;
      ">
        <p style="margin:0;">Thank you for ordering with ❤️</p>
        <p style="margin:0;color:#9c2b27;font-weight:600;">Gitarra Apartelle</p>
      </div>
    </div>
  `;

  // ==============================
  // CONFIRM POPUP
  // ==============================
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

  Swal.fire({
    title: "Saving Order...",
    text: "Please wait a moment",
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  // ==============================
  // SAVE ORDER
  // ==============================
  try {
    await Promise.all(
      cart.map(item =>
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
      )
    );

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

  function formatDateTime(date) {
    const options = { month: "long", day: "numeric", year: "numeric" };
    const formattedDate = date.toLocaleDateString("en-US", options);
    const hours = date.getHours();
    const minutes = date.getMinutes().toString().padStart(2, "0");
    const ampm = hours >= 12 ? "PM" : "AM";
    const hour12 = hours % 12 || 12;
    return `${formattedDate} | ${hour12}:${minutes} ${ampm}`;
  }
});
</script>

<!-- ============ VIEW ORDERS AND DOWNLOAD RECEIPT ============= -->
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

    // ✅ DELIVERY FEE LOGIC
    const DELIVERY_FEE = 50;
    const hasDeliveryItem = orders.some(o => parseInt(o.supply_quantity) === 999);

    let grandTotal = mergedOrders.reduce((sum, o) => sum + o.price, 0);
    if (hasDeliveryItem) grandTotal += DELIVERY_FEE;

    // ✅ Fetch existing remarks (if any)
    let remarksText = "";
    try {
      const remarksRes = await fetch("guest_fetch_remarks.php");
      const remarksData = await remarksRes.json();
      if (remarksData.success && remarksData.notes?.trim()) {
        remarksText = remarksData.notes.trim();
      }
    } catch (err) {
      console.warn("Remarks fetch failed:", err);
    }

    // ✅ Show order summary (with remarks displayed only if not blank)
    Swal.fire({
    title: "🧾 Order Summary",
      html: `
        <div id="orderSummaryWrapper" style="overflow-x:auto;">
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
          </table>
    
          <!-- Delivery Fee outside the table -->
          ${
            hasDeliveryItem
              ? `
                <div style="margin-top:10px;padding:8px;text-align:left;border-radius:6px;background:#f3f3f3;border:1px solid #ddd;">
                  <strong>Delivery Fee:</strong>
                  <span style="float:right;">₱${DELIVERY_FEE.toFixed(2)}</span>
                </div>
              `
              : ""
          }
    
          <!-- TOTAL -->
          <div style="margin-top:10px;padding:10px;text-align:left;border-top:2px solid #000;font-weight:bold;font-size:1.1em;">
            Total:
            <span style="float:right;">₱${grandTotal.toFixed(2)}</span>
          </div>
    
          ${remarksText
            ? `
              <div id="remarksSection" style="margin-top:10px;padding:8px;border-radius:6px;background:#f8f8f8;border:1px solid #ddd;">
                <strong>📝 Remarks:</strong>
                <p style="margin:4px 0 0 0;white-space:pre-wrap;">${escapeHtml(remarksText)}</p>
              </div>
            `
            : ""
          }
        </div>
      `,
      width: "550px",
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: "Download Receipt",
      denyButtonText: "Add Remarks",
      cancelButtonText: "Close",
      confirmButtonColor: "#9c2b27",
      denyButtonColor: "#444"
    }).then(async result => {
      const swalContent = Swal.getHtmlContainer();
      const summary = swalContent.querySelector("#orderSummaryWrapper");

      if (result.isConfirmed && summary) {
        await generatePOSPDF(summary);
      } else if (result.isDenied) {
        openRemarksModal();
      }
    });

    // ===============================
    // REMARKS MODAL (unchanged)
    // ===============================
    async function openRemarksModal() {
      try {
        const fetchRes = await fetch("guest_fetch_remarks.php");
        const remarksData = await fetchRes.json();
        const existingNotes = remarksData.success ? remarksData.notes : "";

        const { value: notes } = await Swal.fire({
          title: "📝 Add / Edit Remarks",
          input: "textarea",
          inputLabel: "Enter remarks for your order",
          inputPlaceholder: "Write your remarks here...",
          inputValue: existingNotes,
          inputAttributes: { maxlength: 300 },
          showCancelButton: true,
          confirmButtonText: "Save",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#9c2b27",
          preConfirm: async (value) => {
            try {
              const res = await fetch("guest_add_remarks.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ notes: value.trim() })
              });
              const result = await res.json();
              if (!result.success) throw new Error(result.message);
              return result.message;
            } catch (err) {
              Swal.showValidationMessage(`Error: ${err.message}`);
              return false;
            }
          }
        });

        if (notes !== undefined) {
          Swal.fire({
            icon: "success",
            title: "Success",
            text: "Your remarks have been updated.",
            timer: 1500,
            showConfirmButton: false
          });
        }
      } catch (err) {
        Swal.fire("Error", "Failed to load or save remarks.", "error");
      }
    }

    // ===============================
    // GENERATE PDF (unchanged)
    // ===============================
    async function generatePOSPDF(summaryElement) {
      Swal.fire({
        title: "Generating Receipt...",
        html: "Please wait while we create your receipt.",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      try {
        const clone = summaryElement.cloneNode(true);
        clone.style.maxHeight = "none";
        clone.style.overflow = "visible";
        const wrapper = document.createElement("div");
        wrapper.style.position = "absolute";
        wrapper.style.left = "-9999px";
        wrapper.style.background = "#fff";
        wrapper.style.color = "#000";
        wrapper.style.width = "80mm";
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
          format: [80, (canvas.height * 80) / canvas.width],
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

    // Helper functions
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