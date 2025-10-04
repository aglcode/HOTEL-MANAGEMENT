<?php
session_start();

require_once 'database.php'; // Include your database connection settings

// Auto-cancel overdue bookings function
function autoCancelOverdueBookings($conn) {
    try {
        // Calculate the cutoff time (30 minutes ago)
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        
        // Update bookings that are overdue and not checked in
        $updateQuery = "
            UPDATE bookings 
            SET status = 'cancelled' 
            WHERE status = 'upcoming' 
            AND start_date <= '$cutoffTime'
            AND id NOT IN (
                SELECT DISTINCT booking_id 
                FROM checkins 
                WHERE booking_id IS NOT NULL
            )
        ";
        
        $conn->query($updateQuery);
    } catch (Exception $e) {
        // Handle error silently
    }
}

// Run auto-cancellation logic
autoCancelOverdueBookings($conn);

// Get check-in statistics
$current_checkins = 0;
$total_bookings = 0;
$available_rooms_count = 0;

try {
    // Current check-ins - Use checkins table instead of bookings
    $result = $conn->query("SELECT COUNT(*) AS current_checkins FROM checkins WHERE NOW() BETWEEN check_in_date AND check_out_date");
    if ($result && $row = $result->fetch_assoc()) {
        $current_checkins = $row['current_checkins'];
    }
    
    // Total bookings - Use checkins table for actual check-ins
    $result = $conn->query("SELECT COUNT(*) AS total_bookings FROM checkins");
    if ($result && $row = $result->fetch_assoc()) {
        $total_bookings = $row['total_bookings'];
    }
    
    // Available rooms count
    $result = $conn->query("SELECT COUNT(*) AS available_rooms FROM rooms WHERE status = 'available'");
    if ($result && $row = $result->fetch_assoc()) {
        $available_rooms_count = $row['available_rooms'];
    }
} catch (Exception $e) {
    // Handle error silently
}

// Fetch announcements
$announcements_result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcement_count = $announcements_result ? $announcements_result->num_rows : 0;

// Fetch available rooms details
$available_rooms_result = $conn->query("SELECT room_number, room_type FROM rooms WHERE status = 'available' ORDER BY room_number");

// Fetch upcoming bookings (next 7 days) - exclude completed and cancelled bookings
$upcoming_bookings_result = $conn->query("
    SELECT b.*, r.room_type 
    FROM bookings b 
    LEFT JOIN rooms r ON b.room_number = r.room_number 
    WHERE DATE(b.start_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
    AND b.status NOT IN ('completed', 'cancelled')
    ORDER BY b.start_date ASC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Receptionist Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
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
    <p id="user-role" class="mb-0">Receptionist</p>
  </div>

  <a href="receptionist-dash.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-dash.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-gauge"></i> Dashboard
  </a>
  <a href="receptionist-room.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-room.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-bed"></i> Rooms
  </a>
  <a href="receptionist-guest.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-guest.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-users"></i> Guest
  </a>
  <a href="receptionist-booking.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-booking.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-calendar-check"></i> Booking
  </a>
  <a href="receptionist-payment.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-payment.php' ? 'active' : ''; ?>">
    <i class="fa-solid fa-money-check"></i> Payment
  </a>
  <a href="signin.php" class="text-danger">
    <i class="fa-solid fa-right-from-bracket"></i> Logout
  </a>
</div>


    <!-- Content -->
    <div class="content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div style="margin-left: 20px;">
                <h2 class="fw-bold mb-0" >Dashboard</h2>
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
            <!-- Current Check-ins Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?= $current_checkins ?></h3>
                            <p class="text-muted mb-0">Current Check-ins</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Bookings Card -->
            <div class="col-md-3 mb-3">
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
            
            <!-- Available Rooms Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100" data-bs-toggle="collapse" data-bs-target="#availableRoomsList" style="cursor: pointer;">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="fas fa-bed"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?= $available_rooms_count ?></h3>
                            <p class="text-muted mb-0">Available Rooms</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Announcements Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100" data-bs-toggle="collapse" data-bs-target="#announcementList" style="cursor: pointer;">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?= $announcement_count ?></h3>
                            <p class="text-muted mb-0">Announcements</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Collapsible Available Rooms List -->
        <div class="collapse mb-4" id="availableRoomsList">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Available Rooms</h5>
                    <i class="fas fa-bed"></i>
                </div>
                <div class="card-body p-0">
                    <?php if ($available_rooms_result && $available_rooms_result->num_rows > 0): ?>
                        <?php while ($room = $available_rooms_result->fetch_assoc()): ?>
                            <div class="room-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Room <?= htmlspecialchars($room['room_number']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($room['room_type']) ?></small>
                                </div>
                                <span class="badge bg-success">Available</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                            <p>No available rooms at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Collapsible Announcement List -->
        <div class="collapse mb-4" id="announcementList">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Announcements</h5>
                    <i class="fas fa-list-ul"></i>
                </div>
                <div class="card-body p-0">
                    <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                        <?php while ($row = $announcements_result->fetch_assoc()): ?>
                            <div class="announcement-item p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="mb-0"><?= htmlspecialchars($row['title']) ?></h5>
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
                            <p>No announcements yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Bookings Section -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Bookings (Next 7 Days)</h5>
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming_bookings_result && $upcoming_bookings_result->num_rows > 0): ?>
                            <div class="row g-3">
                                <?php while ($booking = $upcoming_bookings_result->fetch_assoc()): ?>
                                    <?php 
                                        $checkInDate = new DateTime($booking['start_date']);
                                        $checkOutDate = clone $checkInDate;
                                        $checkOutDate->add(new DateInterval('PT' . $booking['duration'] . 'H'));
                                        $guestInitial = strtoupper(substr($booking['guest_name'], 0, 1));
                                    ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card booking-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="guest-avatar me-3">
                                                        <?= $guestInitial ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($booking['guest_name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($booking['email']) ?></small>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted"><i class="fas fa-bed me-1"></i>Room:</span>
                                                        <span class="fw-semibold"><?= htmlspecialchars($booking['room_number']) ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted"><i class="fas fa-home me-1"></i>Type:</span>
                                                        <span><?= htmlspecialchars($booking['room_type'] ?? 'N/A') ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted"><i class="fas fa-clock me-1"></i>Duration:</span>
                                                        <span><?= $booking['duration'] ?> hours</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted"><i class="fas fa-users me-1"></i>Guests:</span>
                                                        <span><?= $booking['num_people'] ?> people</span>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted"><i class="fas fa-sign-in-alt me-1"></i>Check-in:</span>
                                                        <span class="fw-semibold"><?= $checkInDate->format('M j, g:i A') ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted"><i class="fas fa-sign-out-alt me-1"></i>Check-out:</span>
                                                        <span><?= $checkOutDate->format('M j, g:i A') ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="text-muted"><i class="fas fa-money-bill me-1"></i>Total:</span>
                                                        <span class="fw-bold text-success">₱<?= number_format($booking['total_price'], 2) ?></span>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($booking['booking_token']): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">Token: <code><?= htmlspecialchars($booking['booking_token']) ?></code></small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Upcoming Bookings</h5>
                                <p class="text-muted">There are no bookings scheduled for the next 7 days.</p>
                            </div>
                        <?php endif; ?>
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
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
