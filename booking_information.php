<?php
session_start();
require_once 'database.php';

// Generate booking token function
function generateBookingToken() {
    return 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Send email with booking token
function sendBookingEmail($email, $guestName, $token, $bookingDetails) {
    $subject = "🏨 Booking Confirmation - Gitarra Apartelle (Token: $token)";
    
    $checkInDateTime = new DateTime($bookingDetails['start_date']);
    $checkOutDateTime = clone $checkInDateTime;
    $checkOutDateTime->add(new DateInterval('PT' . $bookingDetails['duration'] . 'H'));
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .token { background: #4CAF50; color: white; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; border-radius: 5px; margin: 20px 0; }
            .details { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏨 Gitarra Apartelle</h1>
                <h2>Booking Confirmation</h2>
            </div>
            <div class='content'>
                <h3>Dear {$guestName},</h3>
                <p>Thank you for choosing Gitarra Apartelle! Your booking has been confirmed.</p>
                
                <div class='token'>
                    📋 BOOKING TOKEN: {$token}
                </div>
                
                <div class='details'>
                    <h4>📋 Booking Details</h4>
                    <div class='detail-row'>
                        <span>Guest Name:</span>
                        <span>{$guestName}</span>
                    </div>
                    <div class='detail-row'>
                        <span>Room Number:</span>
                        <span>Room {$bookingDetails['room']}</span>
                    </div>
                    <div class='detail-row'>
                        <span>Duration:</span>
                        <span>{$bookingDetails['duration']} hours</span>
                    </div>
                    <div class='detail-row'>
                        <span>Check-in:</span>
                        <span>" . $checkInDateTime->format('F j, Y - g:i A') . "</span>
                    </div>
                    <div class='detail-row'>
                        <span>Estimated Check-out:</span>
                        <span>" . $checkOutDateTime->format('F j, Y - g:i A') . "</span>
                    </div>
                    <div class='detail-row'>
                        <span>Total Price:</span>
                        <span>₱{$bookingDetails['total_price']}</span>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Gitarra Apartelle <noreply@gitarraapartelle.com>',
        'Reply-To: info@gitarraapartelle.com'
    );
    
    return mail($email, $subject, $message, implode("\r\n", $headers));
}

// Get booking statistics
$total_bookings = 0;
$upcoming_bookings = 0;
$active_bookings = 0;
$total_revenue = 0;

try {
    // Total bookings
    $result = $conn->query("SELECT COUNT(*) AS total FROM bookings");
    if ($result && $row = $result->fetch_assoc()) {
        $total_bookings = $row['total'];
    }
    
    // Upcoming bookings (start date is in the future)
    $result = $conn->query("SELECT COUNT(*) AS upcoming FROM bookings WHERE start_date > NOW()");
    if ($result && $row = $result->fetch_assoc()) {
        $upcoming_bookings = $row['upcoming'];
    }
    
    // Active bookings (currently ongoing)
    $result = $conn->query("SELECT COUNT(*) AS active FROM bookings WHERE NOW() BETWEEN start_date AND end_date");
    if ($result && $row = $result->fetch_assoc()) {
        $active_bookings = $row['active'];
    }
    
    // Total revenue
    $result = $conn->query("SELECT SUM(total_price) AS revenue FROM bookings");
    if ($result && $row = $result->fetch_assoc()) {
        $total_revenue = $row['revenue'] ?: 0;
    }
} catch (Exception $e) {
    // Handle error silently
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Booking deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Error deleting booking.";
    }
    header("Location: receptionist-booking.php");
    exit();
}

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $guest_name = trim($_POST['guest_name']);
    $guest_email = trim($_POST['guest_email']);
    $guest_phone = trim($_POST['guest_phone']);
    $guest_address = trim($_POST['guest_address']);
    $guest_age = intval($_POST['guest_age']);
    $num_people = intval($_POST['num_people']);
    $room_number = intval($_POST['room_number']);
    $duration = intval($_POST['duration']);
    $start_date = $_POST['start_date'];
    $payment_method = $_POST['payment_method'];
    $amount_paid = floatval($_POST['amount_paid']);
    
    // Calculate end date
    $start_datetime = new DateTime($start_date);
    $end_datetime = clone $start_datetime;
    $end_datetime->add(new DateInterval('PT' . $duration . 'H'));
    $end_date = $end_datetime->format('Y-m-d H:i:s');
    
    // Get room price
    $room_query = $conn->prepare("SELECT price FROM rooms WHERE room_number = ?");
    $room_query->bind_param("i", $room_number);
    $room_query->execute();
    $room_result = $room_query->get_result();
    
    if ($room_result->num_rows > 0) {
        $room_data = $room_result->fetch_assoc();
        $room_price = $room_data['price'];
        $total_price = $room_price * $duration;
        $change = $amount_paid - $total_price;
        
        // Generate booking token
        $booking_token = generateBookingToken();
        
        // Check for conflicts
        $conflict_query = $conn->prepare("
            SELECT id FROM bookings 
            WHERE room_number = ? 
            AND ((start_date <= ? AND end_date > ?) OR (start_date < ? AND end_date >= ?))
        ");
        $conflict_query->bind_param("issss", $room_number, $start_date, $start_date, $end_date, $end_date);
        $conflict_query->execute();
        $conflict_result = $conflict_query->get_result();
        
        if ($conflict_result->num_rows > 0) {
            $_SESSION['error_msg'] = "Room is already booked for the selected time period!";
        } else {
            // Insert booking
            $stmt = $conn->prepare("
                INSERT INTO bookings (guest_name, guest_email, guest_phone, guest_address, guest_age, num_people, 
                room_number, duration, start_date, end_date, payment_method, amount_paid, change_amount, 
                total_price, booking_token, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param(
                "ssssiiiisssdds",
                $guest_name, $guest_email, $guest_phone, $guest_address, $guest_age, $num_people,
                $room_number, $duration, $start_date, $end_date, $payment_method, $amount_paid,
                $change, $total_price, $booking_token
            );
            
            if ($stmt->execute()) {
                // Send email with booking details
                $bookingDetails = [
                    'room' => $room_number,
                    'duration' => $duration,
                    'start_date' => $start_date,
                    'total_price' => number_format($total_price, 2)
                ];
                
                if (sendBookingEmail($guest_email, $guest_name, $booking_token, $bookingDetails)) {
                    $_SESSION['success_msg'] = "Booking created successfully! Confirmation email sent to guest with token: $booking_token";
                } else {
                    $_SESSION['success_msg'] = "Booking created successfully! Token: $booking_token (Email sending failed)";
                }
            } else {
                $_SESSION['error_msg'] = "Error creating booking: " . $conn->error;
            }
        }
    } else {
        $_SESSION['error_msg'] = "Invalid room number!";
    }
    
    header("Location: receptionist-booking.php");
    exit();
}

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(guest_name LIKE ? OR guest_email LIKE ? OR room_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($status_filter)) {
    if ($status_filter === 'upcoming') {
        $where_conditions[] = "start_date > NOW()";
    } elseif ($status_filter === 'active') {
        $where_conditions[] = "NOW() BETWEEN start_date AND end_date";
    } elseif ($status_filter === 'completed') {
        $where_conditions[] = "end_date < NOW()";
    }
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(start_date) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM bookings $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $limit);

// Get bookings
$bookings_query = "SELECT * FROM bookings $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
if (!empty($params)) {
    $bookings_stmt = $conn->prepare($bookings_query);
    $bookings_stmt->bind_param($types, ...$params);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
} else {
    $bookings_result = $conn->query($bookings_query);
}

// Get rooms for dropdown
$rooms_result = $conn->query("SELECT room_number, price FROM rooms ORDER BY room_number");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Booking Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
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
        
        .booking-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .booking-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }
        
        .booking-table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f0f0f0;
        }
        
        .booking-table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .status-upcoming {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }
        
        .status-active {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #388e3c;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            color: #616161;
        }
        
        .search-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            font-weight: bold;
            margin-right: 10px;
        }
        
        .booking-token {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="user-info mb-4">
            <i class="fa-solid fa-user-circle mb-2" style="font-size: 60px;"></i>
            <h5 class="mb-1">Welcome,</h5>
            <p id="user-role" class="mb-0">Receptionist</p>
        </div>

        <a href="receptionist-dash.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="receptionist-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
        <a href="receptionist-guest.php"><i class="fa-solid fa-users"></i> Guest</a>
        <a href="receptionist-booking.php" class="active"><i class="fa-solid fa-calendar-check"></i> Booking</a>
        <a href="#"><i class="fa-solid fa-money-check"></i> Payment</a>
        <a href="signin.php" class="mt-auto text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <!-- Content -->
    <div class="content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Booking Management</h2>
                <p class="text-muted mb-0">Manage guest bookings and reservations</p>
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
            <!-- Total Bookings Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?= $total_bookings ?></h3>
                            <p class="text-muted mb-0">Total Bookings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Bookings Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?= $upcoming_bookings ?></h3>
                            <p class="text-muted mb-0">Upcoming Bookings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Bookings Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1"><?= $active_bookings ?></h3>
                            <p class="text-muted mb-0">Active Bookings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Card -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1">₱<?= number_format($total_revenue, 2) ?></h3>
                            <p class="text-muted mb-0">Total Revenue</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Card -->
        <div class="card search-card mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Search & Filter Bookings</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                    <i class="fas fa-plus me-2"></i>New Booking
                </button>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Guest name, email, room...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                            <a href="receptionist-booking.php" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Bookings Table -->
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Booking List</h5>
                <span class="badge bg-light text-dark"><?= $total_records ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table booking-table mb-0">
                        <thead>
                            <tr>
                                <th>Guest Details</th>
                                <th>Room</th>
                                <th>Duration</th>
                                <th>Check-in</th>
                                <th>Status</th>
                                <th>Token</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $bookings_result->fetch_assoc()): 
                                $now = new DateTime();
                                $start_date = new DateTime($booking['start_date']);
                                $end_date = new DateTime($booking['end_date']);
                                
                                if ($now < $start_date) {
                                    $status = 'upcoming';
                                    $status_text = 'Upcoming';
                                } elseif ($now >= $start_date && $now <= $end_date) {
                                    $status = 'active';
                                    $status_text = 'Active';
                                } else {
                                    $status = 'completed';
                                    $status_text = 'Completed';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="guest-avatar">
                                            <?= strtoupper(substr($booking['guest_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($booking['guest_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($booking['guest_email']) ?></small><br>
                                            <small class="text-muted"><?= htmlspecialchars($booking['guest_phone']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold">Room <?= $booking['room_number'] ?></span><br>
                                    <small class="text-muted"><?= $booking['num_people'] ?> guest(s)</small>
                                </td>
                                <td><?= $booking['duration'] ?> hours</td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($booking['start_date'])) ?></div>
                                    <small class="text-muted"><?= date('g:i A', strtotime($booking['start_date'])) ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $status ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <span class="booking-token"><?= htmlspecialchars($booking['booking_token']) ?></span>
                                </td>
                                <td class="fw-bold">₱<?= number_format($booking['total_price'], 2) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-info" onclick="viewBooking(<?= $booking['id'] ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="editBooking(<?= $booking['id'] ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?= $booking['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this booking?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Booking pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date=<?= urlencode($date_filter) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date=<?= urlencode($date_filter) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date=<?= urlencode($date_filter) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5>No bookings found</h5>
                    <p class="text-muted">Try adjusting your search criteria or create a new booking.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                        <i class="fas fa-plus me-2"></i>Create New Booking
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Booking Modal -->
    <div class="modal fade" id="addBookingModal" tabindex="-1" aria-labelledby="addBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addBookingModalLabel">
                        <i class="fas fa-calendar-plus me-2"></i>New Booking
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Guest Information -->
                            <div class="col-12">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-user me-2"></i>Guest Information
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="guest_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="guest_email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="guest_phone" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Age *</label>
                                <input type="number" class="form-control" name="guest_age" min="18" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address *</label>
                                <textarea class="form-control" name="guest_address" rows="2" required></textarea>
                            </div>
                            
                            <!-- Booking Information -->
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-bed me-2"></i>Booking Information
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Room Number *</label>
                                <select class="form-select" name="room_number" required>
                                    <option value="">Select Room</option>
                                    <?php 
                                    $rooms_result->data_seek(0);
                                    while ($room = $rooms_result->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $room['room_number'] ?>" data-price="<?= $room['price'] ?>">
                                        Room <?= $room['room_number'] ?> - ₱<?= number_format($room['price'], 2) ?>/hour
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Number of Guests *</label>
                                <input type="number" class="form-control" name="num_people" min="1" max="10" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (hours) *</label>
                                <input type="number" class="form-control" name="duration" min="1" max="24" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Check-in Date & Time *</label>
                                <input type="datetime-local" class="form-control" name="start_date" required>
                            </div>
                            
                            <!-- Payment Information -->
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-credit-card me-2"></i>Payment Information
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method *</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="card">Credit/Debit Card</option>
                                    <option value="gcash">GCash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Amount Paid *</label>
                                <input type="number" class="form-control" name="amount_paid" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="submit_booking" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update clock
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
        
        // View booking details
        function viewBooking(id) {
            // Implementation for viewing booking details
            alert('View booking details for ID: ' + id);
        }
        
        // Edit booking
        function editBooking(id) {
            // Implementation for editing booking
            alert('Edit booking for ID: ' + id);
        }
        
        // Calculate total price when room or duration changes
        document.addEventListener('DOMContentLoaded', function() {
            const roomSelect = document.querySelector('select[name="room_number"]');
            const durationInput = document.querySelector('input[name="duration"]');
            const amountInput = document.querySelector('input[name="amount_paid"]');
            
            function calculateTotal() {
                const selectedOption = roomSelect.options[roomSelect.selectedIndex];
                const price = selectedOption.getAttribute('data-price');
                const duration = durationInput.value;
                
                if (price && duration) {
                    const total = parseFloat(price) * parseInt(duration);
                    amountInput.value = total.toFixed(2);
                }
            }
            
            roomSelect.addEventListener('change', calculateTotal);
            durationInput.addEventListener('input', calculateTotal);q
        });
    </script>
</body>
</html>