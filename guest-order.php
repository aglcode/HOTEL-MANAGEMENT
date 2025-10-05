<?php
session_start();
require_once 'database.php';

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
    WHERE k.room_number = ? 
      AND k.qr_code = ? 
      AND k.status = 'active'
      AND NOW() BETWEEN k.valid_from AND k.valid_to
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
$_SESSION['qr_token']    = $guestInfo['qr_code'];

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
    <title>Gitarra Apartelle - Guest Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        /* centering the dashboard content */
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
        
.card {
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.card:hover {
  transform: translateY(-6px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
.card-title {
  font-weight: 600;
}
.btn-warning:hover {
  background-color: #e89f00 !important;
}
.btn-success:hover {
  background-color: #1b8a4b !important;
}
        
        .announcement-item {
            transition: background-color 0.2s ease;
        }
        
        .announcement-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .room-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .room-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .room-item:last-child {
            border-bottom: none;
        }
        
        .booking-card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-2px);
        }
        
        .guest-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
    </style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="user-info mb-4 text-center">
    <i class="fa-solid fa-user-circle mb-2" style="font-size: 60px;"></i>
    <h5 class="mb-1">Welcome,</h5>
    <p id="user-role" class="mb-0">Guest</p>
  </div>

  <a href="guest-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guest-dashboard.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-gauge"></i> Dashboard
  </a>
  <a href="guest-order.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guest-order.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-bowl-food"></i> Order
  </a>
  <a href="signin.php" class="text-danger">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </a>

    <!-- Logo at the bottom -->
  <div class="sidebar-logo text-center mt-4">
    <img src="image/logo-dark.png" alt="Gitarra Apartelle Logo" style="width: 130px; opacity: 0.9;">
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

  <!-- ==================== FILTER MENU ==================== -->
  <h6 class="fw-bold mb-3 text-uppercase text-dark d-flex flex-wrap justify-content-center">Filter Menu</h6>
  <div class="d-flex flex-wrap justify-content-center gap-2 mb-2">
    <button class="btn btn-outline-dark active filter-btn" data-filter="all">All</button>
    <button class="btn btn-outline-dark filter-btn" data-filter="food">Food</button>
    <button class="btn btn-outline-warning filter-btn" data-filter="noodles">Noodles</button>
    <button class="btn btn-outline-warning filter-btn" data-filter="ricemeals">Rice Meals</button>
    <button class="btn btn-outline-warning filter-btn" data-filter="lumpia">Lumpia</button>
    <button class="btn btn-outline-warning filter-btn" data-filter="snacks">Snacks</button>
    <button class="btn btn-outline-warning filter-btn" data-filter="drinks">Drinks</button>
  </div>
  <div class="d-flex flex-wrap justify-content-center gap-2">
    <button class="btn btn-outline-dark filter-btn" data-filter="non-food">Non-Food</button>
    <button class="btn btn-outline-primary filter-btn" data-filter="dental-care">Dental Care</button>
    <button class="btn btn-outline-primary filter-btn" data-filter="shampoo">Shampoo</button>
    <button class="btn btn-outline-primary filter-btn" data-filter="conditioner">Conditioner</button>
    <button class="btn btn-outline-primary filter-btn" data-filter="utensils">Disposable Utensils</button>
  </div>

  <!-- ==================== MENU CARD ==================== -->
  <div class="card shadow-lg border-0 p-1 my-4">
    <div class="card-body">

      <!-- ==================== FOOD MENU ==================== -->
      <div class="menu-category food">
        <div class="container my-4">
          <h3 class="text-center mb-4 fw-bold text-uppercase">🍴 Food Menu</h3>

          <!-- ==================== NOODLES ==================== -->
          <div class="menu-type noodles">
            <h5 class="fw-bold mt-4 mb-3 text-warning">🍜 Noodles</h5>

            <div class="row g-4">
              <!-- Lomi -->
              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="image/Lomi.jpg" class="card-img-top" alt="Lomi">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Lomi</h5>
                    <p class="text-muted small">Small ₱60 | Medium ₱70 | Large ₱80</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>

              <!-- Mami -->
              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="image/Mami.jpg" class="card-img-top" alt="Mami">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Mami</h5>
                    <p class="text-muted small">₱70</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- INSTANT NOODLES -->
            <h5 class="fw-bold mt-5 mb-3 text-warning">🍲 Instant Noodles</h5>
            <div class="row g-4">
              <!-- Nissin Cup (Beef) -->
              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="image/Nissin Beef.png" class="card-img-top" alt="Nissin Cup Beef">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Nissin Cup (Beef)</h5>
                    <p class="text-muted small">₱40</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>

              <!-- Nissin Cup (Chicken) -->
              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="image/Nissin Chicken.png" class="card-img-top" alt="Nissin Cup Chicken">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Nissin Cup (Chicken)</h5>
                    <p class="text-muted small">₱40</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>

              <!-- Nissin Cup (Spicy Seafood) -->
              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="image/Nissin Spicy Seafood.png" class="card-img-top" alt="Spicy Seafood">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Nissin Cup (Spicy Seafood)</h5>
                    <p class="text-muted small">₱40</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== RICE MEALS ==================== -->
          <div class="menu-type ricemeals">
            <h5 class="fw-bold mt-5 mb-3 text-warning">🍛 Rice Meals</h5>
            <div class="row g-4">
              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="images/default-food.jpg" class="card-img-top" alt="Longganisa">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Longganisa</h5>
                    <p class="text-muted small">₱100</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>
            <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                    <img src="images/default-food.jpg" class="card-img-top" alt="Sisig">
                    <div class="card-body text-center d-flex flex-column justify-content-between">
                        <h5 class="card-title">Sisig</h5>
                        <p class="text-muted small">₱100</p>
                        <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Bopis</h5><p class="text-muted small">₱100</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Tocino</h5><p class="text-muted small">₱100</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Tapa</h5><p class="text-muted small">₱100</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Hotdog</h5><p class="text-muted small">₱100</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Dinuguan</h5><p class="text-muted small">₱115</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Chicken Adobo</h5><p class="text-muted small">₱120</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Bicol Express</h5><p class="text-muted small">₱125</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>


            </div>

            <!-- ==================== ADD-ONS ==================== -->
            <h5 class="fw-bold mt-5 mb-3 text-warning">🍗 Add-Ons</h5>
            <div class="row g-4">
              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="images/default-food.jpg" class="card-img-top" alt="Chicharon">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Chicharon</h5>
                    <p class="text-muted small">₱60</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>

              <div class="col-md-4 col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                  <img src="images/default-food.jpg" class="card-img-top" alt="Chicken Skin">
                  <div class="card-body text-center d-flex flex-column justify-content-between">
                    <h5 class="card-title">Chicken Skin</h5>
                    <p class="text-muted small">₱60</p>
                    <button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== LUMPIA ==================== -->
          <div class="menu-type lumpia">
            <h5 class="fw-bold mt-5 mb-3 text-warning">🥟 Lumpia</h5>
            <div class="row g-4">
              <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Shanghai (3pcs)</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
              <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Gulay (3pcs)</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
              <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5 class="card-title">Toge (4pcs)</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            </div>
          </div>

          <!-- ==================== SNACKS ==================== -->
          <div class="menu-type snacks">
            <h5 class="fw-bold mt-5 mb-3 text-warning">🍟 Snacks</h5>
            <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>French Fries (BBQ)</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>French Fries (Sour Cream)</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>French Fries (Cheese)</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Cheese Sticks (12pcs)</h5><p class="text-muted small">₱30</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Tinapay (3pcs)</h5><p class="text-muted small">₱20</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Tinapay with Spread (3pcs)</h5><p class="text-muted small">₱30</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Burger Regular</h5><p class="text-muted small">₱35</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Burger with Cheese</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Nagaraya Butter Yellow (Small)</h5><p class="text-muted small">₱20</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Nova Country Cheddar (Small)</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            </div>
          </div>

          <!-- ==================== DRINKS ==================== -->
          <div class="menu-type drinks">
            <h5 class="fw-bold mt-5 mb-3 text-warning">💧 Water</h5>
            <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Bottled Water (500ml)</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Purified Hot Water Only (Mug)</h5><p class="text-muted small">₱10</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            </div>


        <!-- ICE -->
        <h5 class="fw-bold mt-5 mb-3 text-warning">🧊 Ice</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Ice Bucket</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
        </div>

        <!-- SOFTDRINKS -->
        <h5 class="fw-bold mt-5 mb-3 text-warning">🥤 Softdrinks</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Coke Mismo</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Royal Mismo</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Sting Energy Drink</h5><p class="text-muted small">₱30</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
        </div>

        <!-- SHAKES -->
        <h5 class="fw-bold mt-5 mb-3 text-warning">🥤 Shakes</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Dragon Fruit</h5><p class="text-muted small">₱70</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Mango</h5><p class="text-muted small">₱70</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Cucumber</h5><p class="text-muted small">₱70</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Avocado</h5><p class="text-muted small">₱70</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Chocolate</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Taro</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Ube</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Strawberry</h5><p class="text-muted small">₱40</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
        </div>

        <!-- JUICE -->
        <h5 class="fw-bold mt-5 mb-3 text-warning">🍹 Juice</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Del Monte Pineapple Juice</h5><p class="text-muted small">₱60</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
        </div>

        <!-- COFFEE -->
        <h5 class="fw-bold mt-5 mb-3 text-warning">☕ Coffee</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Instant Coffee</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Brewed Coffee</h5><p class="text-muted small">₱45</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
        </div>

        <!-- TEA -->
        <h5 class="fw-bold mt-5 mb-3 text-warning">🍵 Tea</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Hot Tea (Green)</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Hot Tea (Black)</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
        </div>

        <!-- MILO -->
        <h5 class="fw-bold mt-5 mb-3 text-warning">🍫 Other Drinks</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-food.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Milo Hot Chocolate Drink</h5><p class="text-muted small">₱25</p><button class="btn btn-warning text-white rounded-pill px-4 mt-auto">Add to Order</button></div></div></div>
        </div>
        </div>
        </div>
      </div>

<!-- ==================== NON FOOD ==================== -->
<div class="menu-category non-food">
  <div class="container my-4">
    <h3 class="text-center mb-4 fw-bold text-uppercase">🧺 Non-Food Items</h3>

    <!-- Essentials -->
    <div class="menu-type essentials">
      <h5 class="fw-bold mt-4 mb-3 text-primary">😷 Essentials</h5>
      <div class="row g-4">
        <div class="col-md-4 col-lg-3">
          <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
            <img src="images/default-nonfood.jpg" class="card-img-top" alt="Face Mask">
            <div class="card-body text-center d-flex flex-column justify-content-between">
              <h5>Face Mask Disposable</h5>
              <p class="text-muted small">₱5</p>
              <button class="btn btn-primary rounded-pill px-4 mt-auto">Add to Order</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Dental Care -->
    <div class="menu-type dental-care">
      <h5 class="fw-bold mt-5 mb-3 text-primary">🦷 Dental Care</h5>
      <div class="row g-4">
        <div class="col-md-4 col-lg-3">
          <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
            <img src="images/default-nonfood.jpg" class="card-img-top">
            <div class="card-body text-center d-flex flex-column justify-content-between">
              <h5>Toothbrush with Toothpaste</h5>
              <p class="text-muted small">₱25</p>
              <button class="btn btn-primary rounded-pill px-4">Add to Order</button>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-lg-3">
          <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
            <img src="images/default-nonfood.jpg" class="card-img-top">
            <div class="card-body text-center d-flex flex-column justify-content-between">
              <h5>Colgate Toothpaste</h5>
              <p class="text-muted small">₱20</p>
              <button class="btn btn-primary rounded-pill px-4">Add to Order</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Feminine Hygiene -->
    <div class="menu-type feminine-hygiene">
      <h5 class="fw-bold mt-5 mb-3 text-primary">🚺 Feminine Hygiene</h5>
      <div class="row g-4">
        <div class="col-md-4 col-lg-3">
          <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
            <img src="images/default-nonfood.jpg" class="card-img-top">
            <div class="card-body text-center d-flex flex-column justify-content-between">
              <h5>Modess All Night Extra Long Pad</h5>
              <p class="text-muted small">₱20</p>
              <button class="btn btn-primary rounded-pill px-4">Add to Order</button>
            </div>
          </div>
        </div>
      </div>
    </div>

        <!-- Shampoo -->
         <div class="menu-type shampoo">
        <h5 class="fw-bold mt-5 mb-3 text-primary">🧴 Shampoo</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Sunsilk</h5><p class="text-muted small">₱15</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Creamsilk</h5><p class="text-muted small">₱15</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Palmolive Anti-Dandruff</h5><p class="text-muted small">₱15</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Dove</h5><p class="text-muted small">₱15</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
        </div>
         </div>

        <!-- Conditioner -->
         <div class="menu-type conditioner">
        <h5 class="fw-bold mt-5 mb-3 text-primary">💆 Conditioner</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Empress Keratin</h5><p class="text-muted small">₱15</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Creamsilk</h5><p class="text-muted small">₱15</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
        </div>
         </div>

        <!-- Trust Condom -->
         <div class="menu-type personal-protection">
                    <h5 class="fw-bold mt-5 mb-3 text-primary">🩺 Personal Protection</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Trust Condom (3pcs)</h5><p class="text-muted small">₱60</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
        </div>
         </div>

        <!-- Disposable Utensils -->
         <div class="menu-type utensils">
        <h5 class="fw-bold mt-5 mb-3 text-primary">🥄 Disposable Utensils</h5>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Disposable Spoon</h5><p class="text-muted small">₱2.50</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
            <div class="col-md-4 col-lg-3"><div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100"><img src="images/default-nonfood.jpg" class="card-img-top"><div class="card-body text-center d-flex flex-column justify-content-between"><h5>Disposable Fork</h5><p class="text-muted small">₱2.50</p><button class="btn btn-primary rounded-pill px-4">Add to Order</button></div></div></div>
        </div>
         </div>
        </div>
       </div>
    </div>
  </div>
</div>

    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }

        setInterval(updateClock, 1000);
        updateClock();

// Filter buttons
document.addEventListener("DOMContentLoaded", function () {
  const filterButtons = document.querySelectorAll(".filter-btn");

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

    foodTypes.forEach(type => (type.style.display = "none"));
    nonFoodTypes.forEach(type => (type.style.display = "none"));
  }

  function showType(selector) {
    document.querySelectorAll(selector).forEach(sec => {
      sec.style.display = "block";
    });
  }

  // Default: show everything
  if (foodMenu) foodMenu.style.display = "block";
  if (nonFoodMenu) nonFoodMenu.style.display = "block";
  foodTypes.forEach(type => (type.style.display = "block"));
  nonFoodTypes.forEach(type => (type.style.display = "block"));

  // Add button filter logic
  filterButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const filter = btn.dataset.filter;

      // Reset active state
      filterButtons.forEach(b => b.classList.remove("active"));
      btn.classList.add("active");

      hideAll();

      switch (filter) {
        case "all":
          if (foodMenu) foodMenu.style.display = "block";
          if (nonFoodMenu) nonFoodMenu.style.display = "block";
          foodTypes.forEach(type => (type.style.display = "block"));
          nonFoodTypes.forEach(type => (type.style.display = "block"));
          break;

        case "food":
          if (foodMenu) foodMenu.style.display = "block";
          foodTypes.forEach(type => (type.style.display = "block"));
          break;

        case "non-food":
          if (nonFoodMenu) nonFoodMenu.style.display = "block";
          // Show all non-food, including Essentials, Hygiene, Protection
          nonFoodTypes.forEach(type => (type.style.display = "block"));
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
          // Show everything as fallback
          if (foodMenu) foodMenu.style.display = "block";
          if (nonFoodMenu) nonFoodMenu.style.display = "block";
          foodTypes.forEach(type => (type.style.display = "block"));
          nonFoodTypes.forEach(type => (type.style.display = "block"));
      }
    });
  });
});
    </script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const addButtons = document.querySelectorAll(".card .btn-warning, .card .btn-primary");

  addButtons.forEach(button => {
    button.addEventListener("click", () => {
      const card = button.closest(".card");
      if (!card) return;

      const titleEl = card.querySelector("h5.card-title, .card-title, h5");
      const itemName = titleEl ? titleEl.textContent.trim() : "Item";

      const priceEl = card.querySelector("p.text-muted, .card-body p");
      const priceText = priceEl ? priceEl.textContent.trim() : "";

      const rawSegments = priceText.split(/\||\n|,/).map(s => s.trim()).filter(Boolean);
      const options = [];
      const priceRegex = /(?:([A-Za-z0-9().\s\-]+?)\s*)?₱\s*([\d,]+(?:\.\d+)?)/;

      rawSegments.forEach(seg => {
        const m = seg.match(priceRegex);
        if (m) {
          options.push({ label: (m[1] || "Unit").trim(), price: parseFloat(m[2].replace(/,/g, "")) });
        }
      });
      if (options.length === 0) options.push({ label: "Unit", price: 0 });

      // ================= SWEETALERT FORM =================
      let html = `
        <div style="text-align:left;display:flex;flex-direction:column;gap:15px;">

          <!-- Size / Variant -->
          ${options.length > 1 ? `
            <div>
              <label style="font-weight:600;display:block;margin-bottom:5px;">⚙️ Size / Variant</label>
              <select id="sizeSelect" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;">
                ${options.map((opt, i) => `<option value="${i}">${escapeHtml(opt.label)} — ₱${opt.price.toFixed(2)}</option>`).join("")}
              </select>
            </div>` 
          : `<p><strong>Price:</strong> ₱${options[0].price.toFixed(2)}</p>`}

          <!-- Quantity -->
          <div>
            <label style="font-weight:600;display:block;margin-bottom:5px;">🔢 Quantity</label>
            <input id="qty" type="number" min="1" value="1" 
              style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;">
          </div>

          <div id="totalDisplay" 
               style="font-weight:bold;background:#f8f9fa;padding:10px;border-radius:8px;text-align:center;">
            Total: ₱${options[0].price.toFixed(2)}
          </div>

          <!-- Mode of Payment -->
          <div>
            <label style="font-weight:600;display:block;margin-bottom:5px;">💳 Mode of Payment</label>
            <select id="modePayment" style="width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;">
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
            </select>
          </div>

          <!-- GCash Info -->
          <div id="gcashDetails" 
               style="display:none;background:#222222;color:white;padding:15px;border-radius:10px;margin-top:5px;">
            <p style="margin:0 0 10px 0;font-weight:600;">📱 GCash Payment Information</p>
            <p style="margin:0 0 5px 0;">📞 <strong>Account Number:</strong> 09171234567</p>
            <p style="margin:0 0 10px 0;">👤 <strong>Account Name:</strong> Juan Dela Cruz</p>

            <label for="refNumber" style="font-weight:600;display:block;margin-bottom:5px;">🔢 Enter Reference Number</label>
            <input id="refNumber" type="text" 
              placeholder="Enter 12–14 digit Ref No."
              maxlength="14"
              style="width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;">
          </div>
        </div>
      `;

      Swal.fire({
        title: `Order ${escapeHtml(itemName)}`,
        html,
        showCancelButton: true,
        confirmButtonText: "Confirm Order",
        confirmButtonColor: "#222222",
        cancelButtonText: "Cancel",
        cancelButtonColor: "#dc3545",
        focusConfirm: false,
        customClass: {
          popup: "swal2-rounded swal2-large"
        },
        didOpen: () => {
          const qtyInput = document.getElementById("qty");
          const sizeSelect = document.getElementById("sizeSelect");
          const totalDisplay = document.getElementById("totalDisplay");
          const modePayment = document.getElementById("modePayment");
          const gcashDetails = document.getElementById("gcashDetails");

          const updateTotal = () => {
            const qty = Math.max(1, parseInt(qtyInput.value) || 1);
            const idx = sizeSelect ? parseInt(sizeSelect.value) : 0;
            const price = options[idx].price;
            totalDisplay.textContent = `Total: ₱${(qty * price).toFixed(2)}`;
          };

          qtyInput.addEventListener("input", updateTotal);
          if (sizeSelect) sizeSelect.addEventListener("change", updateTotal);

          modePayment.addEventListener("change", () => {
            gcashDetails.style.display = modePayment.value === "gcash" ? "block" : "none";
          });
        },
preConfirm: () => {
  const qty = Math.max(1, parseInt(document.getElementById("qty").value) || 1);
  const idx = document.getElementById("sizeSelect") ? parseInt(document.getElementById("sizeSelect").value) : 0;
  const mode_payment = document.getElementById("modePayment").value;
  const ref_number = document.getElementById("refNumber") ? document.getElementById("refNumber").value.trim() : null;
  const chosen = options[idx];
  const total = qty * chosen.price;

  if (mode_payment === "gcash" && !/^[0-9]{12,14}$/.test(ref_number)) {
    Swal.showValidationMessage("Please enter a valid 12–14 digit GCash reference number.");
    return false;
  }

  return {
    item_name: itemName,
    category: "Food",
    size: chosen.label,
    // ✅ SAVE the TOTAL price (price × quantity)
    price: total,
    quantity: qty,
    total: total, // optional (if you want separate field)
    mode_payment: mode_payment,
    ref_number: mode_payment === "gcash" ? ref_number : null
  };
}
      }).then(result => {
        if (result.isConfirmed && result.value) {
          const orderData = result.value;
          fetch("guest_add_order.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                room_number: roomNumber,
                category: category,
                item_name: itemName,
                size: size,
                price: price,
                quantity: quantity,
                mode_payment: paymentMode, // should be 'cash' or 'gcash'
                ref_number: referenceNumber
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                Swal.fire("Success", data.message, "success");
            } else {
                Swal.fire("Error", data.message, "error");
            }
        })
        .catch(err => Swal.fire("Error", "Server error. Please try again later.", "error"));
        }
      });
    });
  });

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
});
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>