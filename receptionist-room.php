<?php
session_start();

require_once 'database.php';
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// Update room status based on current checkins
// This ensures rooms with active/scheduled guests show correct status
$conn->query("
    UPDATE rooms r
    LEFT JOIN (
        SELECT room_number, check_out_date, status
        FROM (
            SELECT room_number, check_out_date, status,
                   ROW_NUMBER() OVER (
                       PARTITION BY room_number 
                       ORDER BY 
                           CASE 
                               WHEN status = 'checked_in' AND check_in_date <= NOW() AND check_out_date > NOW() THEN 1
                               WHEN status = 'scheduled' AND check_in_date <= NOW() AND check_out_date > NOW() THEN 2
                               ELSE 3
                           END,
                           check_in_date ASC
                   ) as rn
            FROM checkins
            WHERE status IN ('checked_in', 'scheduled')
              AND check_in_date <= NOW() 
              AND check_out_date > NOW()
        ) ranked
        WHERE rn = 1
    ) c ON r.room_number = c.room_number
    SET r.status = CASE
        WHEN c.status = 'checked_in' THEN 'booked'
        WHEN c.status = 'scheduled' THEN 'booked'
        ELSE 'available'
    END
    WHERE r.status != 'maintenance'
");

// auto checkout with cleaning time ---------------------------------------------

//  Auto-checkout expired bookings and track rooms for cleaning
$expiredCheckinsResult = $conn->query("
    SELECT id, room_number 
    FROM checkins 
    WHERE status IN ('checked_in', 'scheduled')
      AND check_out_date <= NOW()
      AND id NOT IN (
          SELECT id FROM (
              SELECT MIN(id) as id 
              FROM checkins 
              WHERE status = 'scheduled' 
                AND check_in_date > NOW()
              GROUP BY room_number, guest_name
          ) future_bookings
      )
");

$autoCheckedOutRooms = [];
while ($expiredRow = $expiredCheckinsResult->fetch_assoc()) {
    $autoCheckedOutRooms[] = (int)$expiredRow['room_number'];
}

$conn->query("
    UPDATE checkins 
    SET status = 'checked_out'
    WHERE status IN ('checked_in', 'scheduled')
      AND check_out_date <= NOW()
      AND id NOT IN (
          SELECT id FROM (
              SELECT MIN(id) as id 
              FROM checkins 
              WHERE status = 'scheduled' 
                AND check_in_date > NOW()
              GROUP BY room_number, guest_name
          ) future_bookings
      )
");

// ------------------------------------------------------------------

// Free rooms with no active bookings
$expiredRooms = $conn->query("
    SELECT r.room_number
    FROM rooms r
    LEFT JOIN (
        SELECT room_number
        FROM checkins
        WHERE status IN ('checked_in', 'scheduled')
          AND check_in_date <= NOW()
          AND check_out_date > NOW()
        GROUP BY room_number
    ) c ON r.room_number = c.room_number
    WHERE r.status = 'booked' 
      AND c.room_number IS NULL
");

while ($room = $expiredRooms->fetch_assoc()) {
    $room_number = (int)$room['room_number'];
    $conn->query("UPDATE rooms SET status = 'available' WHERE room_number = $room_number");
}

$bookedRooms = $conn->query("SELECT COUNT(*) AS booked FROM rooms WHERE status = 'booked'")->fetch_assoc()['booked'] ?? 0;
$maintenanceRooms = $conn->query("SELECT COUNT(*) AS maintenance FROM rooms WHERE status = 'maintenance'")->fetch_assoc()['maintenance'] ?? 0;
$totalRooms = $conn->query("SELECT COUNT(*) AS total FROM rooms")->fetch_assoc()['total'] ?? 0;
$availableRooms = $conn->query("SELECT COUNT(*) AS available FROM rooms WHERE status = 'available'")->fetch_assoc()['available'] ?? 0;

$booking_count_result = $conn->query("SELECT COUNT(*) AS total FROM bookings");
$booking_count_row = $booking_count_result->fetch_assoc();
$booking_count = $booking_count_row['total'] ?? 0;

$bookings_result = $conn->query("SELECT * FROM bookings ORDER BY start_date DESC");

// Get count of new bookings created in last 24 hours
$newBookingsQuery = $conn->query("
    SELECT COUNT(*) as new_count 
    FROM bookings 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND status NOT IN ('cancelled', 'completed')
");
$newBookingsCount = $newBookingsQuery->fetch_assoc()['new_count'] ?? 0;

// Get count of upcoming bookings (check-in within next 24 hours)
$upcomingBookingsQuery = $conn->query("
    SELECT COUNT(*) as upcoming_count 
    FROM bookings 
    WHERE start_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
      AND status NOT IN ('cancelled', 'completed')
");
$upcomingBookingsCount = $upcomingBookingsQuery->fetch_assoc()['upcoming_count'] ?? 0;

// Total notification count
$totalNotifications = $newBookingsCount + $upcomingBookingsCount;

// Query rooms with ONLY current active booking (not future ones)
// FIXED: Always fetch the latest check_out_date for active bookings
$allRoomsQuery = "
    SELECT r.room_number, r.room_type, r.status,
        c.check_out_date,
        c.checkin_status,
        c.guest_name,
        c.checkin_id,
        c.stay_duration,
        c.last_modified,
        c.tapped_at
    FROM rooms r
    LEFT JOIN (
        SELECT 
            id as checkin_id,
            room_number, 
            check_out_date, 
            status as checkin_status,
            guest_name,
            stay_duration,
            last_modified,
            tapped_at,
            ROW_NUMBER() OVER (
                PARTITION BY room_number 
                ORDER BY 
                    CASE 
                        WHEN status = 'checked_in' AND check_in_date <= NOW() AND check_out_date > NOW() THEN 1
                        WHEN status = 'scheduled' AND check_in_date <= NOW() AND check_out_date > NOW() THEN 2
                        ELSE 3
                    END,
                    last_modified DESC,
                    check_in_date ASC
            ) as rn
        FROM checkins
        WHERE status IN ('checked_in', 'scheduled')
          AND check_in_date <= NOW()
          AND check_out_date > NOW()
    ) c ON r.room_number = c.room_number AND c.rn = 1
    ORDER BY r.room_number ASC
";
$resultRooms = $conn->query($allRoomsQuery);

$roomOrders = [];
$orderResult = $conn->query("
    SELECT room_number, item_name, status, created_at FROM orders ORDER BY created_at DESC
");
while ($order = $orderResult->fetch_assoc()) {
    $roomOrders[$order['room_number']][] = $order;
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


// IMPROVED CHECKOUT ACTION - Handles Gap Rebookings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_number'])) {
    $room_number = (int)$_POST['room_number'];

    // EXTEND ACTION
    if (isset($_POST['extend'])) {
        $stmt = $conn->prepare("
            SELECT id, check_out_date, total_price 
            FROM checkins 
            WHERE room_number = ? 
              AND status = 'checked_in'
              AND check_in_date <= NOW()
              AND check_out_date > NOW()
            ORDER BY check_in_date DESC LIMIT 1
        ");
        $stmt->bind_param('i', $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $checkin = $result->fetch_assoc();
        $stmt->close();

        if ($checkin) {
            $checkin_id = $checkin['id'];
            $check_out_date = $checkin['check_out_date'];
            $new_check_out_date = date('Y-m-d H:i:s', strtotime($check_out_date . ' +1 hour'));

            $extension_fee = 120;
            $new_total_price = $checkin['total_price'] + $extension_fee;

            $stmt_update = $conn->prepare("
                UPDATE checkins 
                SET check_out_date = ?, total_price = ?, status = 'checked_in'
                WHERE id = ?
            ");
            $stmt_update->bind_param('sdi', $new_check_out_date, $new_total_price, $checkin_id);
            $stmt_update->execute();
            $stmt_update->close();

            $stmt_k = $conn->prepare("
                UPDATE keycards 
                SET valid_to = ?, status = 'active' 
                WHERE room_number = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt_k->bind_param("si", $new_check_out_date, $room_number);
            $stmt_k->execute();
            $stmt_k->close();

            header("Location: receptionist-room.php?success=extended");
            exit;
        } else {
            header("Location: receptionist-room.php?error=no_active_checkin");
            exit;
        }
    }

    // Only checks out CURRENT active booking
    if (isset($_POST['checkout'])) {
        $stmt = $conn->prepare("
            SELECT id, total_price, amount_paid, guest_name 
            FROM checkins 
            WHERE room_number = ? 
              AND status = 'checked_in'
              AND check_in_date <= NOW()
              AND check_out_date > NOW()
            ORDER BY check_in_date DESC LIMIT 1
        ");
        $stmt->bind_param('i', $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $checkin = $result->fetch_assoc();
        $stmt->close();

        if ($checkin) {
            $total_price = (float)$checkin['total_price'];
            $amount_paid = (float)$checkin['amount_paid'];
            $guest_name = $checkin['guest_name'];
            $balance = $total_price - $amount_paid;

            if ($balance > 0) {
                header("Location: receptionist-room.php?error=unpaid&balance=" . $balance);
                exit;
            }

            // Check if there's a future scheduled booking for this room
            $futureCheck = $conn->prepare("
                SELECT COUNT(*) as has_future
                FROM checkins
                WHERE room_number = ?
                  AND status = 'scheduled'
                  AND check_in_date > NOW()
                LIMIT 1
            ");
            $futureCheck->bind_param('i', $room_number);
            $futureCheck->execute();
            $hasFuture = (int)$futureCheck->get_result()->fetch_assoc()['has_future'];
            $futureCheck->close();

            // Only set room to available if no future bookings
            if ($hasFuture === 0) {
                $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE room_number = ?");
                $stmt->bind_param('i', $room_number);
                $stmt->execute();
                $stmt->close();
            }

            // Check out current booking
            $stmt_update2 = $conn->prepare("
                UPDATE checkins 
                SET check_out_date = NOW(), status = 'checked_out' 
                WHERE id = ?
            ");
            $stmt_update2->bind_param('i', $checkin['id']);
            $stmt_update2->execute();
            $stmt_update2->close();

            // Update related booking status
            $bkSel = $conn->prepare("
                SELECT id FROM bookings 
                WHERE guest_name = ? AND room_number = ? 
                AND status NOT IN ('cancelled','completed') 
                ORDER BY start_date DESC LIMIT 1
            ");
            $bkSel->bind_param("si", $guest_name, $room_number);
            $bkSel->execute();
            $bkRes = $bkSel->get_result();
            if ($bkRes && $bkRow = $bkRes->fetch_assoc()) {
                $booking_id = (int)$bkRow['id'];
                $bkSel->close();
                $bkUpd = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                $bkUpd->bind_param('i', $booking_id);
                $bkUpd->execute();
                $bkUpd->close();
            } else {
                $bkSel->close();
            }

            // Expire keycard
            $stmt_k2 = $conn->prepare("
                UPDATE keycards 
                SET status='expired' 
                WHERE room_number = ? AND status = 'active'
            ");
            $stmt_k2->bind_param("i", $room_number);
            $stmt_k2->execute();
            $stmt_k2->close();

            // Delete orders
            $del = $conn->prepare("
                DELETE FROM orders 
                WHERE room_number = ? 
                AND status IN ('pending','served')
            ");
            $room_number_str = (string)$room_number;
            $del->bind_param('s', $room_number_str);
            $del->execute();
            $deleted_orders = $del->affected_rows;
            $del->close();

            header("Location: receptionist-room.php?success=checked_out&deleted={$deleted_orders}");
            exit;

        } else {
            header("Location: receptionist-room.php?error=no_active_guest");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Room Management</title>
    <link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">

    <style>
/* Sidebar styles */
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
  font-family: 'Poppins', sans-serif;
}

.sidebar h4 {
  text-align: center;
  font-weight: 700;
  color: #111827;
  margin-bottom: 30px;
}

.user-info {
  text-align: center;
  background: #f9fafb;
  border-radius: 10px;
  padding: 15px;
  margin: 0 20px 25px 20px;
}

.user-info i {
  font-size: 40px;
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

.nav-links a:hover {
  background: #f3f4f6;
  color: #111827;
}

.nav-links a:hover i {
  color: #111827;
}

.nav-links a.active {
  background: #871D2B;
  color: #fff;
}

.nav-links a.active i {
  color: #fff;
}

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

.signout a:hover {
  background: #f3f4f6;
  color: #dc2626;
}

.signout a:hover i {
  color: #dc2626;
}

.content {
  margin-left: 270px;
  padding: 30px;
  max-width: 1400px;
}

    .stat-card {
        border-radius: 12px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: #fff;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
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

    .stat-title {
        font-size: 14px;
        font-weight: 600;
        color: #555;
        margin: 0;
    }

    .stat-change {
        font-size: 13px;
        margin-top: 6px;
    }

    .stat-change span {
        font-size: 12px;
        color: #888;
    }

    .card {
        border: none;
    }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-available {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .status-booked {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .status-maintenance {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .countdown-timer {
            font-weight: 600;
        }
        
        
    .sidebar {
      width: 250px;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
    }
    .content { margin-left: 265px; padding: 20px; }
    .card { border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    table th { background: #f8f9fa; }
    table td, table th { padding: 12px; }

    .toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #dc3545;
    color: white;
    padding: 12px 20px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.4s, transform 0.4s;
    transform: translateY(-20px);
    z-index: 9999;
}
.toast.show {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}

.table thead th {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  padding: 0.75rem;
  font-size: 0.75rem;
  letter-spacing: 0.05em;
}

.table th.sorting {
  cursor: pointer;
  position: relative;
}

.table th.sorting_asc::after,
.table th.sorting_desc::after {
  content: '';
  position: absolute;
  right: 0.5rem;
  font-size: 0.7em;
  color: #6c757d;
}

.table th.sorting_asc::after { content: '↑'; }
.table th.sorting_desc::after { content: '↓'; }

.table td {
  padding: 0.75rem;
  vertical-align: middle;
  font-size: 0.875rem;
  color: #4a5568;
}

.table .badge {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  border: 1px solid;
  transition: all 0.2s ease;
}

.bg-blue-100 { background-color: #ebf8ff; }
.text-blue-800 { color: #2b6cb0; }
.border-blue-200 { border-color: #bee3f8; }
.bg-info-100 { background-color: #e6f7ff; }
.text-info-800 { color: #2b6cb0; }
.border-info-200 { border-color: #bee3f8; }
.bg-gray-100 { background-color: #f7fafc; }
.text-gray-800 { color: #2d3748; }
.border-gray-200 { border-color: #edf2f7; }
.bg-green-100 { background-color: #f0fff4; }
.text-green-800 { color: #2f855a; }
.border-green-200 { border-color: #c6f6d5; }
.bg-amber-100 { background-color: #fffaf0; }
.text-amber-800 { color: #975a16; }
.border-amber-200 { border-color: #fed7aa; }

.table-hover tbody tr:hover {
  background-color: #f8f9fa;
  transition: background-color 0.15s ease;
}

/* ============================================
   CLEANING STATUS STYLES
   Add these to your existing <style> section
   ============================================ */

/* Cleaning status badge */
/* ============================================
   CLEANING STATUS STYLES
   Add these to your existing <style> section
   ============================================ */

/* Cleaning status badge */
.status-cleaning {
    background-color: rgba(13, 202, 240, 0.1);
    color: #0dcaf0;
    border: 1px solid rgba(13, 202, 240, 0.3);
}

/* Cleaning room card styling */
.room-card.cleaning {
    border-left: 4px solid #0dcaf0;
    background: linear-gradient(135deg, rgba(13, 202, 240, 0.02) 0%, rgba(255, 255, 255, 1) 100%);
    cursor: not-allowed;
}

.room-card.cleaning .card-header {
    background-color: rgba(13, 202, 240, 0.05);
    border-bottom: 1px solid rgba(13, 202, 240, 0.2);
}

/* Cleaning countdown timer */
.cleaning-countdown {
    font-size: 1.1rem;
    color: #0dcaf0;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-shadow: 0 1px 2px rgba(13, 202, 240, 0.2);
}

/* Cleaning timer container */
.cleaning-timer-container {
    background: rgba(13, 202, 240, 0.05);
    border: 1px solid rgba(13, 202, 240, 0.15);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
}

/* Broom icon animation */
.cleaning-timer-container .fa-broom {
    animation: sweep 2s ease-in-out infinite;
    display: inline-block;
}

@keyframes sweep {
    0%, 100% {
        transform: rotate(0deg);
    }
    25% {
        transform: rotate(-15deg);
    }
    75% {
        transform: rotate(15deg);
    }
}

/* Hover effect for cleaning cards */
.room-card.cleaning:hover {
    box-shadow: 0 4px 15px rgba(13, 202, 240, 0.2);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

/* Cleaning status in room list (if you have a table view) */
.badge.bg-cleaning {
    background-color: #0dcaf0 !important;
    color: #fff;
}

/* Pulse animation for cleaning badge */
.status-cleaning {
    animation: pulse-cleaning 2s ease-in-out infinite;
}

@keyframes pulse-cleaning {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Disabled check-in button during cleaning */
.btn-disabled-cleaning {
    cursor: not-allowed !important;
    opacity: 1 !important;
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    color: #fff !important;
}

.btn-disabled-cleaning:hover {
    background-color: #5a6268 !important;
    border-color: #545b62 !important;
}

/* Prevent card click during cleaning */
.room-card.cleaning * {
    pointer-events: none;
}

/* Allow finish cleaning button to be clickable */
.btn-finish-cleaning {
    pointer-events: auto !important;
    cursor: pointer !important;
}

.btn-finish-cleaning:hover {
    background-color: #157347 !important;
    border-color: #146c43 !important;
}

/* Cleaning button container */
.cleaning-disabled-btn-container {
    gap: 8px;
}

/* Notification Badge Styles */
.notification-badge {
  position: absolute;
  top: 2px;     /* move higher up */
  right: 10px;  /* slightly tighter alignment */
  background: #dc3545;
  color: white;
  border-radius: 50%;
  min-width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 5px;
  animation: pulse-badge 2s infinite;
  box-shadow: 0 2px 4px rgba(220, 53, 69, 0.4);
}


@keyframes pulse-badge {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.8;
  }
}

/* Make sure nav-links has position relative */
.nav-links a {
  position: relative;
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

/* New booking row highlight */
.new-booking-row {
  background-color: #fff3cd !important;
  border-left: 4px solid #ffc107;
}

.new-booking-row:hover {
  background-color: #ffe69c !important;
}

/* Badge for new bookings */
.badge-new-slim {
  display: inline-block;
  font-size: 9px;
  background: #e6f4ea;
  color: #2e7d32;
  border-radius: 6px;
  padding: 0px 4px 1px 4px;
  font-weight: 600;
  margin-left: 4px;
  position: relative;
  top: -4px; /* lifts slightly like a square root */
  line-height: 1;
}

@keyframes glow {
  0%, 100% {
    box-shadow: 0 0 5px rgba(102, 126, 234, 0.5);
  }
  50% {
    box-shadow: 0 0 15px rgba(102, 126, 234, 0.8);
  }
}

/* Upcoming booking indicator */
.upcoming-indicator {
  display: inline-block;
  width: 8px;
  height: 8px;
  background: #28a745;
  border-radius: 50%;
  margin-right: 6px;
  animation: blink 1.5s infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

/* Tapped status color coding */
.text-success {
    color: #28a745 !important;
}

.text-info {
    color: #17a2b8 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.text-muted {
    color: #6c757d !important;
}

/* Pulsing animation for "Just now" taps */
@keyframes pulse-tap {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.fw-bold.text-success {
    animation: pulse-tap 2s ease-in-out infinite;
}

/* Truncate long guest names */
.text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    <h6>Receptionist</h6>
  </div>

  <div class="nav-links">
    <a href="receptionist-dash.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-dash.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-gauge"></i> Dashboard
    </a>
    <a href="receptionist-room.php"
      class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-room.php' ? 'active' : ''; ?> position-relative">
      <i class="fa-solid fa-bed"></i> Rooms
      <span class="notification-badge" style="display: none;">0</span>
    </a>
    <a href="receptionist-guest.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-guest.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-users"></i> Guests
    </a>
    <a href="receptionist-booking.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-booking.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-calendar-check"></i> Booking
    </a>
    <a href="receptionist-payment.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'receptionist-payment.php' ? 'active' : ''; ?>">
      <i class="fa-solid fa-money-check"></i> Payment
    </a>
  </div>

  <div class="signout">
    <a href="signin.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
  </div>
</div>

<div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Room Management</h2>
            <p class="text-muted mb-0">Manage rooms and check-in/check-out guests</p>
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

<!-- STATISTICS CARDS -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Total Rooms</p>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-door-closed"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $totalRooms ?></h3>
            <p class="stat-change text-muted">Updated Today</p>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Available Rooms</p>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $availableRooms ?></h3>
            <p class="stat-change text-success">+3% <span>from last week</span></p>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Booked Rooms</p>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-key"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $bookedRooms ?></h3>
            <p class="stat-change text-danger">-1% <span>this week</span></p>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Under Maintenance</p>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $maintenanceRooms ?></h3>
            <p class="stat-change text-muted">Scheduled repairs</p>
        </div>
    </div>
</div>

    <!-- Room List -->
    <div class="card mb-4">
        <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #871D2B;">
            <h5 class="mb-0">Room Status</h5>
            <i class="fas fa-bed"></i>
        </div>
        <div class="card-body">
<div class="row">
    <?php while ($room = $resultRooms->fetch_assoc()): 
        $orderCountQuery = $conn->prepare("
            SELECT COUNT(*) AS pending_orders 
            FROM orders 
            WHERE room_number = ? AND status = 'pending'
        ");
        $orderCountQuery->bind_param('i', $room['room_number']);
        $orderCountQuery->execute();
        $orderResult = $orderCountQuery->get_result();
        $orderCount = $orderResult->fetch_assoc()['pending_orders'] ?? 0;
        $orderCountQuery->close();
        
        $hasActiveCheckin = !empty($room['check_out_date']) && !empty($room['checkin_status']);
        
        // Format tapped_at time - FIXED VERSION
        $tappedAtDisplay = 'Never';
        $tappedAtClass = 'text-muted';
        
        if (!empty($room['tapped_at'])) {
            // Use Unix timestamps for accurate calculation
            $tappedTimestamp = strtotime($room['tapped_at']);
            $nowTimestamp = time();
            $secondsDiff = $nowTimestamp - $tappedTimestamp;
            
            // Debugging (remove after testing)
            echo "<!-- Room {$room['room_number']}: tapped_at={$room['tapped_at']}, diff={$secondsDiff}s -->";
            
            if ($secondsDiff < 0) {
                // Future time - something is wrong
                $tappedAtDisplay = 'Error';
                $tappedAtClass = 'text-danger';
            } elseif ($secondsDiff < 60) {
                // Less than 1 minute
                $tappedAtDisplay = 'Just now';
                $tappedAtClass = 'text-success fw-bold';
            } elseif ($secondsDiff < 3600) {
                // Less than 1 hour
                $minutes = floor($secondsDiff / 60);
                $tappedAtDisplay = $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
                $tappedAtClass = 'text-success';
            } elseif ($secondsDiff < 86400) {
                // Less than 1 day
                $hours = floor($secondsDiff / 3600);
                $tappedAtDisplay = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                $tappedAtClass = 'text-info';
            } else {
                // 1 day or more
                $days = floor($secondsDiff / 86400);
                $tappedAtDisplay = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                $tappedAtClass = 'text-warning';
            }
        }
    ?>
        
    <div class="col-md-4 mb-3">
        <div class="card room-card <?= $room['status'] ?>"
            onclick="cardClicked(event, <?= $room['room_number']; ?>, '<?= $room['status'] ?>')">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex flex-column">
                    <span>Room #<?= htmlspecialchars($room['room_number']); ?></span>
                </div>
                <span class="status-badge status-<?= $room['status'] ?>"><?= ucfirst($room['status']) ?></span>
            </div>

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted"><i class="fas fa-tag me-2"></i>Type:</span>
                    <span class="fw-semibold"><?= ucfirst($room['room_type']) ?></span>
                </div>
                
                <?php if ($hasActiveCheckin): ?>
                    <!-- Guest Name -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted"><i class="fas fa-user me-2"></i>Guest:</span>
                        <span class="fw-semibold text-truncate" style="max-width: 150px;" 
                              title="<?= htmlspecialchars($room['guest_name']) ?>">
                            <?= htmlspecialchars($room['guest_name']) ?>
                        </span>
                    </div>
                    
                    <!-- Last Tapped -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">
                            <i class="fas fa-fingerprint me-2"></i>Last Tap:
                        </span>
                        <span class="<?= $tappedAtClass ?> fw-semibold">
                            <?= $tappedAtDisplay ?>
                        </span>
                    </div>
                    
                    <!-- Time Left -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted"><i class="fas fa-clock me-2"></i>Time Left:</span>
                        <span class="countdown-timer" 
                              data-room="<?= $room['room_number']; ?>" 
                              data-checkout="<?= $room['check_out_date']; ?>">
                            Loading...
                        </span>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between mt-3">
                        <form method="POST" action="receptionist-room.php" class="d-inline extend-form">
                            <input type="hidden" name="room_number" value="<?= $room['room_number']; ?>">
                            <input type="hidden" name="extend" value="1">
                            <button type="submit" class="btn btn-sm btn-warning">
                                <i class="fas fa-clock me-1"></i> Extend
                            </button>
                        </form>

                        <form method="POST" action="receptionist-room.php" class="d-inline checkout-form">
                            <input type="hidden" name="room_number" value="<?= $room['room_number']; ?>">
                            <input type="hidden" name="checkout" value="1">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-sign-out-alt me-1"></i> Check Out
                            </button>
                        </form>
                    </div>
                <?php elseif ($room['status'] === 'available'): ?>
                    <div class="d-flex justify-content-center mt-3">
                        <a href="check-in.php?room_number=<?= $room['room_number']; ?>" 
                           class="btn btn-sm btn-success">
                            <i class="fas fa-sign-in-alt me-1"></i> Check In
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
  <?php if (isset($_GET['success'])): ?>
    <div id="roomToastSuccess" class="toast fade text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          <?php
            if ($_GET['success'] === 'extended') echo "Room extended successfully!";
            elseif ($_GET['success'] === 'checked_out') echo "Guest checked out successfully!";
          ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error'])): ?>
    <div id="roomToastError" class="toast fade text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <?php
            if ($_GET['error'] === 'no_checkin') echo "No active check-in found for this room.";
            else echo "Action failed. Please try again.";
          ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Booking Summary Table -->
<div class="card mb-4">
    <div class="card-header text-white bg-dark d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Booking Summary</h5>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div id="customBookingLengthMenu"></div>
            <input id="bookingSearchInput" type="text" class="form-control form-control-sm" placeholder="Search bookings..." style="width: 200px;">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="bookingSummaryTable" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Guest Name</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Room #</th>
                        <th>Duration</th>
                        <th>Action/Status</th>
                    </tr>
                </thead>
<tbody>
<?php
    // prioritize active 'Check In' bookings, then latest ones
    $summary_result = $conn->query("
        SELECT 
            b.guest_name, 
            b.start_date, 
            b.end_date, 
            b.room_number, 
            b.duration, 
            b.num_people, 
            b.status, 
            b.created_at,
            r.status AS room_status,
            CASE 
                WHEN b.status NOT IN ('cancelled','completed') 
                     AND r.status = 'available' THEN 0 -- highest priority (for check-in)
                ELSE 1
            END AS checkin_priority
        FROM bookings b
        LEFT JOIN rooms r ON b.room_number = r.room_number
        ORDER BY 
            checkin_priority ASC,  -- show 'Check In' first
            b.created_at DESC       -- then latest to oldest
    ");

    if ($summary_result->num_rows > 0):
        while ($booking = $summary_result->fetch_assoc()):
            $room_number = (int)$booking['room_number'];
            $guest_name = $booking['guest_name'];
            $booking_status = $booking['status'];

            $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $booking_start_dt = new DateTime($booking['start_date'], new DateTimeZone('Asia/Manila'));
            $booking_end_dt = new DateTime($booking['end_date'], new DateTimeZone('Asia/Manila'));
            $created_dt = new DateTime($booking['created_at'], new DateTimeZone('Asia/Manila'));

            $booking_finished = $now >= $booking_end_dt;
            $is_cancelled = ($booking_status === 'cancelled');

            // Determine if expired (booking time passed, never checked in)
            $is_expired = false;

            // Check active occupancy or checkin status
            $already_checked_in = false;
            $occupied_by_other = false;
            $checked_out_for_booking = false;

            if (!$is_cancelled) {
                $currStmt = $conn->prepare("
                    SELECT guest_name, check_out_date 
                    FROM checkins 
                    WHERE room_number = ? 
                      AND check_in_date <= NOW() 
                      AND check_out_date > NOW() 
                    ORDER BY check_in_date DESC 
                    LIMIT 1
                ");
                $currStmt->bind_param("i", $room_number);
                $currStmt->execute();
                $currRes = $currStmt->get_result();
                if ($currRes && $rowCurr = $currRes->fetch_assoc()) {
                    $current_occupant = $rowCurr['guest_name'];
                    if ($current_occupant === $guest_name) {
                        $already_checked_in = true;
                    } else {
                        $occupied_by_other = true;
                    }
                }
                $currStmt->close();

                if (!$already_checked_in) {
                    $coStmt = $conn->prepare("
                        SELECT id 
                        FROM checkins 
                        WHERE room_number = ? 
                          AND guest_name = ? 
                          AND check_out_date <= NOW()
                        ORDER BY check_out_date DESC
                        LIMIT 1
                    ");
                    $coStmt->bind_param("is", $room_number, $guest_name);
                    $coStmt->execute();
                    $coRes = $coStmt->get_result();
                    if ($coRes && $coRes->num_rows > 0) {
                        $checked_out_for_booking = true;
                    }
                    $coStmt->close();
                }
            }

            // Mark expired only if booking end time has passed and not checked in/out
            if (!$already_checked_in && !$checked_out_for_booking && !$is_cancelled && $now > $booking_end_dt) {
                $is_expired = true;
            }

            // Mark as NEW only if created < 24hrs, not cancelled, not expired
            $hours_since_created = ($now->getTimestamp() - $created_dt->getTimestamp()) / 3600;
            $is_new = ($hours_since_created <= 24 && !$is_cancelled && !$is_expired);

            // Add row class for new bookings
            $row_class = $is_new ? 'new-booking-row' : '';
?>
<tr class="<?= $row_class ?>">
    <td class="align-middle">
        <?= htmlspecialchars($guest_name) ?>
        <?php if ($is_new): ?>
            <span class="badge-new-slim">New</span>
        <?php endif; ?>
    </td>
    <td class="align-middle">
        <?= date("M d, Y h:i A", strtotime($booking['start_date'])) ?>
    </td>
    <td class="align-middle"><?= date("M d, Y h:i A", strtotime($booking['end_date'])) ?></td>
    <td class="align-middle"><?= $booking['room_number'] ?></td>
    <td class="align-middle"><?= $booking['duration'] ?> hrs</td>
    <td class="align-middle">
        <?php if ($is_cancelled): ?>
            <span class="badge bg-danger">Cancelled</span>
        <?php elseif ($is_expired): ?>
            <span class="badge bg-warning">Expired</span>
        <?php elseif ($already_checked_in): ?>
            <span class="badge bg-success">In Use</span>
        <?php elseif ($checked_out_for_booking || $booking_finished): ?>
            <span class="badge bg-secondary">Checked Out</span>
        <?php elseif ($occupied_by_other): ?>
            <span class="badge bg-warning text-dark">Room Unavailable</span>
        <?php else: ?>
            <?php
                $room_check = $conn->prepare("SELECT status FROM rooms WHERE room_number = ?");
                $room_check->bind_param("i", $room_number);
                $room_check->execute();
                $room_result = $room_check->get_result();
                $room = $room_result->fetch_assoc();
                $room_check->close();

                if ($room && $room['status'] === 'available'):
                    $guest = urlencode($guest_name);
                    $checkin = urlencode($booking['start_date']);
                    $checkout = urlencode($booking['end_date']);
                    $num_people = (int)$booking['num_people'];
            ?>
                <a href="check-in.php?room_number=<?= $room_number; ?>&guest_name=<?= $guest; ?>&checkin=<?= $checkin; ?>&checkout=<?= $checkout; ?>&num_people=<?= $num_people; ?>" 
                   class="btn btn-sm btn-success">
                    <i class="fas fa-sign-in-alt me-1"></i> Check In
                </a>
            <?php else: ?>
                <span class="badge bg-secondary">Room Unavailable</span>
            <?php endif; ?>
        <?php endif; ?>
    </td>
</tr>
<?php 
        endwhile;
    else: 
?>
    <tr><td colspan="6" class="text-center py-4 text-muted">No bookings found.</td></tr>
<?php endif; ?>
</tbody>

            </table>
        </div>
    </div>
</div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>

// ============================================
// ROOM CLEANING STATUS SYSTEM - ENHANCED FRONTEND VERSION
// Adds 20-minute cleaning countdown after checkout
// Handles manual 'Finish Cleaning' as frontend-only
// ============================================

// Storage keys
const CLEANING_STORAGE_KEY = 'rooms_in_cleaning';
const FORCE_AVAILABLE_KEY = 'rooms_force_available';

// ==========================
// Local Storage Utilities
// ==========================

function getCleaningRooms() {
  try {
    const data = localStorage.getItem(CLEANING_STORAGE_KEY);
    return data ? JSON.parse(data) : {};
  } catch (e) {
    console.error('Error reading cleaning rooms:', e);
    return {};
  }
}

function saveCleaningRooms(rooms) {
  try {
    localStorage.setItem(CLEANING_STORAGE_KEY, JSON.stringify(rooms));
  } catch (e) {
    console.error('Error saving cleaning rooms:', e);
  }
}

function getForceAvailableRooms() {
  try {
    const data = localStorage.getItem(FORCE_AVAILABLE_KEY);
    return data ? JSON.parse(data) : [];
  } catch (e) {
    console.error('Error reading force available rooms:', e);
    return [];
  }
}

function saveForceAvailableRooms(rooms) {
  try {
    localStorage.setItem(FORCE_AVAILABLE_KEY, JSON.stringify([...new Set(rooms)]));
  } catch (e) {
    console.error('Error saving force available rooms:', e);
  }
}

function addForceAvailableRoom(roomNumber) {
  let rooms = getForceAvailableRooms();
  if (!rooms.includes(roomNumber)) {
    rooms.push(roomNumber);
    saveForceAvailableRooms(rooms);
  }
}

function removeForceAvailableRoom(roomNumber) {
  let rooms = getForceAvailableRooms();
  rooms = rooms.filter(r => r !== roomNumber);
  saveForceAvailableRooms(rooms);
}

// ==========================
// Cleaning State Functions
// ==========================

function setRoomToCleaning(roomNumber) {
  const cleaningRooms = getCleaningRooms();
  const cleaningEndTime = Date.now() + (20 * 60 * 1000); // 20 minutes from now
  
  cleaningRooms[roomNumber] = {
    startTime: Date.now(),
    endTime: cleaningEndTime
  };
  
  saveCleaningRooms(cleaningRooms);
  removeForceAvailableRoom(roomNumber); // remove from force-available if re-cleaned
  
  console.log(`🧹 Room ${roomNumber} set to cleaning until ${new Date(cleaningEndTime).toLocaleTimeString()}`);
}

function removeRoomFromCleaning(roomNumber) {
  const cleaningRooms = getCleaningRooms();
  delete cleaningRooms[roomNumber];
  saveCleaningRooms(cleaningRooms);
  console.log(`✅ Room ${roomNumber} removed from cleaning status`);
}

function isRoomCleaning(roomNumber) {
  const cleaningRooms = getCleaningRooms();
  const roomData = cleaningRooms[roomNumber];
  
  if (!roomData) return false;
  
  // Check if cleaning period has expired
  if (Date.now() >= roomData.endTime) {
    removeRoomFromCleaning(roomNumber);
    return false;
  }
  
  return true;
}

function getCleaningTimeRemaining(roomNumber) {
  const cleaningRooms = getCleaningRooms();
  const roomData = cleaningRooms[roomNumber];
  
  if (!roomData) return 0;
  
  const remaining = roomData.endTime - Date.now();
  return remaining > 0 ? remaining : 0;
}

function formatCleaningTime(ms) {
  const totalSeconds = Math.floor(ms / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes}m ${seconds.toString().padStart(2, '0')}s`;
}

// ==========================
// Helper function to find room card - MUST BE BEFORE UI FUNCTIONS
// ==========================

function findRoomCard(roomNumber) {
  let roomCard = document.querySelector(`.room-card[onclick*="${roomNumber}"]`) ||
                 document.querySelector(`[data-room-number="${roomNumber}"]`);

  if (!roomCard) {
    const allCards = document.querySelectorAll('.room-card');
    for (let card of allCards) {
      const roomNumEl = card.querySelector('.card-title, h5, h6, span');
      if (roomNumEl && roomNumEl.textContent.includes(`#${roomNumber}`)) {
        roomCard = card;
        break;
      }
    }
  }
  
  return roomCard;
}

// ==========================
// UI Update Functions
// ==========================

function updateRoomCardForCleaning(roomNumber) {
  let roomCard = findRoomCard(roomNumber);

  if (!roomCard) return console.warn(`⚠️ Room card not found for room ${roomNumber}`);

  const statusBadge = roomCard.querySelector('.status-badge');
  const cardBody = roomCard.querySelector('.card-body');
  if (!statusBadge || !cardBody) return;

  console.log(`🔧 Updating UI for cleaning room ${roomNumber}`);
  
  roomCard.classList.remove('available', 'booked', 'maintenance');
  roomCard.classList.add('cleaning');
  roomCard.style.pointerEvents = 'none';
  roomCard.style.opacity = '0.9';

  statusBadge.className = 'status-badge status-cleaning';
  statusBadge.textContent = 'Cleaning';

  // Remove ALL existing timers and buttons first
  cardBody.querySelectorAll('.countdown-timer, .cleaning-disabled-btn-container, .cleaning-timer-container, .btn-success, .d-flex.justify-content-between.mt-3, .d-flex.justify-content-center.mt-3').forEach(el => el.remove());

  // Create cleaning timer
  let timerDiv = document.createElement('div');
  timerDiv.className = 'cleaning-timer-container';
  timerDiv.innerHTML = `
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted"><i class="fas fa-broom me-2"></i>Cleaning:</span>
      <span class="cleaning-countdown fw-bold text-info" data-room="${roomNumber}">Calculating...</span>
    </div>
  `;
  cardBody.insertBefore(timerDiv, cardBody.firstChild);

  const countdownEl = timerDiv.querySelector('.cleaning-countdown');
  if (countdownEl) countdownEl.textContent = formatCleaningTime(getCleaningTimeRemaining(roomNumber));

  // Add buttons
  const disabledButtonDiv = document.createElement('div');
  disabledButtonDiv.className = 'd-flex flex-column align-items-center gap-2 mt-3 cleaning-disabled-btn-container';
  disabledButtonDiv.innerHTML = `
    <button class="btn btn-sm btn-success btn-finish-cleaning" data-room="${roomNumber}" style="width: 100%;">
      <i class="fas fa-check me-1"></i> Finish Cleaning
    </button>
    <button class="btn btn-sm btn-secondary btn-disabled-cleaning" disabled style="width: 100%;">
      <i class="fas fa-lock me-1"></i> Room Being Cleaned
    </button>
  `;
  cardBody.appendChild(disabledButtonDiv);

  const finishBtn = disabledButtonDiv.querySelector('.btn-finish-cleaning');
  finishBtn.addEventListener('click', e => {
    e.stopPropagation();
    const room = finishBtn.getAttribute('data-room');

    const finishAction = () => {
      removeRoomFromCleaning(room);
      removeForceAvailableRoom(room); // Clear from localStorage
      
      // Show success message before reload
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Cleaning Finished!',
          text: `Room #${room} is now available for check-in.`,
          icon: 'success',
          confirmButtonColor: '#198754',
          background: '#1a1a1a',
          color: '#fff',
          timer: 2000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => {
          // Reload without query params to avoid toast
          window.location.href = 'receptionist-room.php';
        });
      } else {
        // Fallback without SweetAlert
        window.location.href = 'receptionist-room.php';
      }
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Finish Cleaning?',
        text: `Room #${room} will be marked as available immediately.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, finish cleaning',
        cancelButtonText: 'Cancel',
        background: '#1a1a1a',
        color: '#fff'
      }).then(result => result.isConfirmed && finishAction());
    } else {
      if (confirm(`Finish cleaning Room #${room}?`)) finishAction();
    }
  });
}

function restoreRoomCardToAvailable(roomNumber) {
  let roomCard = findRoomCard(roomNumber);

  if (!roomCard) return;

  console.log(`♻️ Restoring room ${roomNumber} to available status`);
  
  const statusBadge = roomCard.querySelector('.status-badge');
  const cardBody = roomCard.querySelector('.card-body');

  roomCard.classList.remove('cleaning');
  roomCard.classList.add('available');
  roomCard.style.pointerEvents = 'auto';
  roomCard.style.opacity = '1';

  if (statusBadge) {
    statusBadge.className = 'status-badge status-available';
    statusBadge.textContent = 'Available';
  }

  cardBody?.querySelectorAll('.cleaning-timer-container, .cleaning-disabled-btn-container').forEach(el => el.remove());

  if (cardBody && !cardBody.querySelector('.btn-success')) {
    const buttonDiv = document.createElement('div');
    buttonDiv.className = 'd-flex justify-content-center mt-3';
    buttonDiv.innerHTML = `
      <a href="check-in.php?room_number=${roomNumber}" class="btn btn-sm btn-success">
        <i class="fas fa-sign-in-alt me-1"></i> Check In
      </a>
    `;
    cardBody.appendChild(buttonDiv);
  }

  // DON'T add to force available here - let the checkout handler do it
}

// ==========================
// Countdown + Init
// ==========================

function updateAllCleaningCountdowns() {
  const countdowns = document.querySelectorAll('.cleaning-countdown');
  countdowns.forEach(countdown => {
    const roomNumber = countdown.getAttribute('data-room');
    if (!roomNumber) return;
    
    if (isRoomCleaning(roomNumber)) {
      const remaining = getCleaningTimeRemaining(roomNumber);
      countdown.textContent = formatCleaningTime(remaining);
      
      if (remaining <= 0) {
        console.log(`⏰ Cleaning time expired for room ${roomNumber}`);
        removeRoomFromCleaning(roomNumber);
        
        // Show SweetAlert for automatic completion
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Cleaning Complete!',
            text: `Room #${roomNumber} cleaning time has expired. Room is now available.`,
            icon: 'info',
            confirmButtonColor: '#198754',
            background: '#1a1a1a',
            color: '#fff',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        } else {
          window.location.reload();
        }
      }
    }
  });
}

let cleaningCountdownInterval = null;

function initializeCleaningStatus() {
  console.log('🚀 Initializing cleaning status system...');
  if (cleaningCountdownInterval) clearInterval(cleaningCountdownInterval);

  // First, update any rooms currently in cleaning
  const cleaningRooms = getCleaningRooms();
  Object.keys(cleaningRooms).forEach(roomNumber => {
    if (isRoomCleaning(roomNumber)) {
      console.log(`🧹 Room ${roomNumber} is in cleaning mode`);
      updateRoomCardForCleaning(roomNumber);
    } else {
      console.log(`⏰ Room ${roomNumber} cleaning expired, removing`);
      removeRoomFromCleaning(roomNumber);
    }
  });

  // Then check force-available rooms ONLY if they're truly available
  const forceAvailableRooms = getForceAvailableRooms();
  forceAvailableRooms.forEach(roomNumber => {
    const roomCard = findRoomCard(roomNumber);
    if (roomCard) {
      const statusBadge = roomCard.querySelector('.status-badge');
      const currentStatus = statusBadge?.textContent.toLowerCase().trim();
      
      // Only restore if room is truly available (no active guest)
      if (currentStatus === 'available') {
        console.log(`🔁 Restoring forced available room ${roomNumber}`);
        restoreRoomCardToAvailable(roomNumber);
      } else {
        // Room is occupied - remove from force available list
        console.log(`⚠️ Room ${roomNumber} is ${currentStatus}, removing from force available list`);
        removeForceAvailableRoom(roomNumber);
      }
    }
  });

  // Start countdown interval
  cleaningCountdownInterval = setInterval(updateAllCleaningCountdowns, 1000);
  setTimeout(updateAllCleaningCountdowns, 100);
  
  console.log('✅ Cleaning status system initialized');
}

// ==========================
// Export Public Functions
// ==========================

window.RoomCleaningSystem = {
  setRoomToCleaning,
  removeRoomFromCleaning,
  isRoomCleaning,
  getCleaningTimeRemaining,
  initializeCleaningStatus,
  updateRoomCardForCleaning,
  restoreRoomCardToAvailable,
  triggerCleaningAfterCheckout(roomNumber) {
    if (!roomNumber) return console.error('Room number is required');
    setRoomToCleaning(roomNumber);
    setTimeout(() => updateRoomCardForCleaning(roomNumber), 100);
  },
  refreshCleaningStatus: initializeCleaningStatus
};

// ==========================
// Auto-Initialize
// ==========================

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeCleaningStatus);
} else {
  initializeCleaningStatus();
}


let previousNotifCount = 0;

// 🔔 Check pending orders count
async function checkOrderNotifications() {
  const notifBadge = document.getElementById("orderNotifCount");
  if (!notifBadge) return;

  try {
    const res = await fetch("fetch_pending_orders.php");
    const data = await res.json();

    let pendingCount = 0;
    if (data && Object.keys(data).length > 0) {
      for (const orders of Object.values(data)) {
        pendingCount += orders.filter(o => o.status === "pending").length;
      }
    }

    // 🔴 Update the badge
    if (pendingCount > 0) {
      notifBadge.textContent = pendingCount;
      notifBadge.classList.remove("d-none");

      // 🌀 Animate if the number increased
      if (pendingCount > previousNotifCount) {
        notifBadge.classList.add("animate__animated", "animate__bounceIn");
        setTimeout(() => notifBadge.classList.remove("animate__animated", "animate__bounceIn"), 1000);
      }

    } else {
      notifBadge.classList.add("d-none");
    }

    previousNotifCount = pendingCount;

  } catch (error) {
    console.error("Failed to fetch order notifications:", error);
  }
}

// Run every 10 seconds
checkOrderNotifications();
setInterval(checkOrderNotifications, 10000);

function updateRoomNotifications() {
  fetch('get_booking_notifications.php')
    .then(res => res.json())
    .then(data => {
      const badge = document.querySelector('.notification-badge');
      if (!badge) return;

      // Update badge
      if (data.success && data.count > 0) {
        badge.textContent = data.count;
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
    })
    .catch(err => console.error('Notification fetch error:', err));
}

// Run on page load
updateRoomNotifications();

// Refresh every 30 seconds
setInterval(updateRoomNotifications, 30000);

// Auto-refresh booking table every 60 seconds (only if DataTable exists)
function refreshBookingTable() {
  if ($.fn.DataTable.isDataTable('#bookingSummaryTable')) {
    $('#bookingSummaryTable').DataTable().ajax.reload(null, false);
  }
}


// Smooth scroll to new bookings when page loads
$(document).ready(function() {
  const newBookingRows = $('.new-booking-row');
  if (newBookingRows.length > 0) {
    // Scroll to first new booking
    $('html, body').animate({
      scrollTop: newBookingRows.first().offset().top - 150
    }, 1000);
  }
});

document.addEventListener("DOMContentLoaded", function() {
  const successToast = document.getElementById("roomToastSuccess");
  const errorToast = document.getElementById("roomToastError");

  if (successToast) new bootstrap.Toast(successToast, { delay: 3000 }).show();
  if (errorToast) new bootstrap.Toast(errorToast, { delay: 3000 }).show();
});

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleIcon = document.getElementById('sidebar-toggle');
    sidebar.classList.toggle('active');
    toggleIcon.classList.toggle('open');
    toggleIcon.setAttribute('aria-expanded', sidebar.classList.contains('active'));
}

document.querySelectorAll('.countdown-timer').forEach(function (timer) {
    const checkOutTime = new Date(timer.getAttribute('data-checkout')).getTime();
    const roomNumber = timer.getAttribute('data-room');

    const interval = setInterval(() => {
        const now = new Date().getTime();
        const distance = checkOutTime - now;

        if (distance <= 0) {
            clearInterval(interval);
            timer.textContent = "Expired";

            console.log(`⏰ TIMER EXPIRED for room ${roomNumber}`);
            console.log(`🧹 Setting room ${roomNumber} to cleaning status...`);
            
            // Set room to cleaning BEFORE checkout
            setRoomToCleaning(roomNumber);
            
            // Verify it was set
            const cleaningRooms = getCleaningRooms();
            console.log(`✅ Cleaning rooms after setting:`, cleaningRooms);
            
            // Small delay to ensure localStorage is written
            setTimeout(() => {
                console.log(`📤 Sending checkout request for room ${roomNumber}...`);
                
                fetch('receptionist-room.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `room_number=${roomNumber}&checkout=1`
                }).then(response => {
                    console.log(`✅ Checkout response received for room ${roomNumber}`);
                    return response.text();
                }).then(() => {
                    console.log(`🔄 Reloading page...`);
                    location.reload();
                }).catch(error => {
                    console.error(`❌ Checkout error for room ${roomNumber}:`, error);
                    location.reload();
                });
            }, 200);
        } else {
            const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((distance % (1000 * 60)) / 1000);
            timer.textContent = `${h}h ${m}m ${s}s`;
        }
    }, 1000);
});

document.querySelectorAll('.extend-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Extend Stay?',
            text: "Do you want to extend this stay by 1 hour?",
            icon: 'question',
            showCancelButton: true,
            background: '#1a1a1a',
            color: '#fff',
            confirmButtonColor: '#8b1d2d', 
            cancelButtonColor: '#555',
            confirmButtonText: 'Yes, extend',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

document.querySelectorAll('.checkout-form').forEach(form => {
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const roomNumber = form.querySelector('input[name="room_number"]').value;

    fetch("receptionist-guest.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=check_payment_status&room_number=${roomNumber}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.payment_required) {
        const amountDueRaw = Number(data.amount_due) || 0;
        const amountDueDisplay = amountDueRaw.toFixed(2);

        Swal.fire({
          title: '<span style="font-weight: 600; color: #fff;">Complete Payment</span>',
          html: `
            <div style="background: #111; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: inset 0 0 6px rgba(255,255,255,0.05);">
              <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333;">
                <span style="color: #bbb;">Guest:</span>
                <span style="color: #fff; font-weight: 500;">${data.guest_name || ''}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333;">
                <span style="color: #bbb;">Room:</span>
                <span style="color: #fff; font-weight: 500;">#${data.room_number || ''}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                <span style="color: #dc3545; font-weight: 600;">Balance Due:</span>
                <span style="color: #dc3545; font-weight: 700;">₱${amountDueDisplay}</span>
              </div>
            </div>

            <div style="text-align: left; margin-bottom: 15px;">
              <label style="display: block; color: #ccc; font-size: 14px; margin-bottom: 6px; font-weight: 500;">Enter Payment:</label>
              <input type="number" id="payment_amount" placeholder="0.00" min="0" step="0.01"
                style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 6px; font-size: 15px; background: #222; color: #eee; box-sizing: border-box;" />
            </div>

            <div style="text-align: left;">
              <label style="display: block; color: #ccc; font-size: 14px; margin-bottom: 6px; font-weight: 500;">Payment Method:</label>
              <select id="payment_mode" style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 6px; font-size: 15px; background: #222; color: #eee;">
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
              </select>
            </div>

            <div id="gcash_ref_wrapper" style="display:none; margin-top: 15px; text-align: left;">
              <label style="display: block; color: #ccc; font-size: 14px; margin-bottom: 6px; font-weight: 500;">GCash Reference (13 digits):</label>
              <input id="gcash_reference" placeholder="Enter 13-digit reference number" maxlength="13"
                style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 6px; font-size: 15px; background: #222; color: #eee; box-sizing: border-box;" />
              <small style="color: #888; font-size: 12px; display: block; margin-top: 4px;">Only numbers allowed (exactly 13 digits)</small>
            </div>
          `,
          background: '#1a1a1a',
          color: '#fff',
          showCancelButton: true,
          confirmButtonText: 'Submit Payment',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#8b1d2d',
          cancelButtonColor: '#555',
          width: '500px',
          padding: '1.5rem',
          didOpen: () => {
            const modeSelect = document.getElementById('payment_mode');
            const gcashWrapper = document.getElementById('gcash_ref_wrapper');
            const gcashInput = document.getElementById('gcash_reference');

            modeSelect.addEventListener('change', () => {
              gcashWrapper.style.display = modeSelect.value === 'gcash' ? 'block' : 'none';
            });

            gcashInput.addEventListener('input', (e) => {
              e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
          },
          preConfirm: () => {
            const amount = parseFloat(document.getElementById("payment_amount").value);
            const mode = document.getElementById("payment_mode").value;
            const gcash_reference = document.getElementById("gcash_reference")?.value.trim() || '';

            if (!amount || amount <= 0) {
              Swal.showValidationMessage("Please enter a valid amount.");
              return false;
            }

            if (mode === "gcash") {
              if (!gcash_reference) {
                Swal.showValidationMessage("Please enter a GCash reference number.");
                return false;
              }

              if (!/^[0-9]+$/.test(gcash_reference)) {
                Swal.showValidationMessage("GCash reference must contain only numbers.");
                return false;
              }

              if (gcash_reference.length !== 13) {
                Swal.showValidationMessage("GCash reference must be exactly 13 digits.");
                return false;
              }
            }

            return { amount, mode, gcash_reference };
          }
        }).then(result => {
          if (result.isConfirmed) {
            const payload = new URLSearchParams();
            payload.append('action', 'add_payment');
            payload.append('guest_id', data.guest_id);
            payload.append('additional_amount', result.value.amount);
            payload.append('payment_mode', result.value.mode);
            payload.append('gcash_reference', result.value.gcash_reference);

            fetch("receptionist-guest.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: payload.toString()
            })
            .then(res => res.json())
            .then(payData => {
              if (payData.success) {
                Swal.fire({
                  title: "Payment Successful",
                  text: payData.message || "Payment recorded successfully.",
                  icon: "success",
                  background: '#1a1a1a',
                  confirmButtonColor: "#8b1d2d"
                }).then(() => {
                  console.log(`🧹 Starting cleaning for room ${roomNumber} after payment`);
                  
                  if (typeof window.RoomCleaningSystem !== 'undefined') {
                    window.RoomCleaningSystem.setRoomToCleaning(roomNumber);
                  } else {
                    setRoomToCleaning(roomNumber);
                  }
                  
                  setTimeout(() => {
                    form.removeEventListener('submit', arguments.callee);
                    form.submit();
                  }, 100);
                });
              } else {
                Swal.fire("Error", payData.message || "Payment failed.", "error");
              }
            })
            .catch(err => {
              console.error("Payment Error:", err);
              Swal.fire("Error", "An error occurred while processing payment.", "error");
            });
          }
        });

      } else {
        Swal.fire({
          title: 'Are you sure?',
          text: "Do you really want to check out this guest?",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#8b1d2d',
          cancelButtonColor: '#555',
          confirmButtonText: 'Yes, check out',
          cancelButtonText: 'Cancel',
          background: '#1a1a1a',
          color: '#fff'
        }).then((result) => {
          if (result.isConfirmed) {
            console.log(`🧹 Starting cleaning for room ${roomNumber}`);
            
            if (typeof window.RoomCleaningSystem !== 'undefined') {
              window.RoomCleaningSystem.setRoomToCleaning(roomNumber);
            } else {
              setRoomToCleaning(roomNumber);
            }
            
            setTimeout(() => {
              form.removeEventListener('submit', arguments.callee);
              form.submit();
            }, 100);
          }
        });
      }
    })
    .catch(err => {
      console.error("Error:", err);
      Swal.fire("Error", "Unable to verify payment status.", "error");
    });
  });
});


function cardClicked(event, roomNumber, status) {
    if (event.target.tagName.toLowerCase() === 'button' || event.target.closest('form')) {
        return;
    }

    if (status === 'booked') {
        let toast = document.getElementById('roomToast');
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
        event.preventDefault();
        return false;
    }

    window.location.href = `check-in.php?room_number=${roomNumber}`;
}

function updateClock() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    const timeStr = now.toLocaleTimeString('en-US');

    document.getElementById('currentDate').innerText = dateStr;
    document.getElementById('currentTime').innerText = timeStr;
}

setInterval(updateClock, 1000);
updateClock();

function refreshOrderBadges() {
  fetch('fetch_pending_orders.php')
    .then(res => res.json())
    .then(data => {
      for (const [room, count] of Object.entries(data)) {
        const badge = document.querySelector(`#order-badge-${room}`);
        if (badge) {
          badge.textContent = count > 0
            ? `${count} New ${count > 1 ? 'Orders' : 'Order'}`
            : 'No Orders';
          badge.className = count > 0
            ? 'badge bg-danger mt-1'
            : 'badge bg-secondary mt-1';
        }
      }
    });
}
setInterval(refreshOrderBadges, 5000);

$(document).ready(function() {
  var bookingSummary = $('#bookingSummaryTable').DataTable({
    order: [[5, 'asc']],
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: true,
    order: [],
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50, 100],
    dom: 'rt<"row mt-3"<"col-sm-5"i><"col-sm-7"p>>',
    language: {
      emptyTable: "<i class='fas fa-calendar-times fa-3x text-muted mb-3'></i><p class='mb-0'>No bookings found</p>",
      info: "Showing _START_ to _END_ of _TOTAL_ bookings",
      infoEmpty: "No entries available",
      infoFiltered: "(filtered from _MAX_ total bookings)",
      lengthMenu: "Show _MENU_ bookings",
      paginate: {
        first: "«",
        last: "»",
        next: "›",
        previous: "‹"
      }
    }
  });

  bookingSummary.on('init', function () {
    var lengthSelect = $('#bookingSummaryTable_length select')
      .addClass('form-select form-select-sm')
      .css('width', '80px');

    $('#customBookingLengthMenu').html(
      '<label class="d-flex align-items-center gap-2 mb-0 text-white">' +
        '<span>Show</span>' +
        lengthSelect.prop('outerHTML') +
        '<span>bookings</span>' +
      '</label>'
    );

    $('#bookingSummaryTable_length').hide();
  });

  $('#bookingSearchInput').on('keyup', function() {
    bookingSummary.search(this.value).draw();
  });

  bookingSummary.on('order.dt', function() {
    $('th.sorting', bookingSummary.table().header()).removeClass('sorting_asc sorting_desc');
    bookingSummary.columns().every(function(index) {
      var order = bookingSummary.order()[0];
      if (order[0] === index) {
        $('th:eq(' + index + ')', bookingSummary.table().header())
          .addClass(order[1] === 'asc' ? 'sorting_asc' : 'sorting_desc');
      }
    });
  });
});

</script>
</body>
</html>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    if (@$conn->ping()) {
        $conn->close();
    }
}
?>