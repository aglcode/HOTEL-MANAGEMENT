<?php
session_start();
require_once 'database.php';
date_default_timezone_set('Asia/Manila');

// ============================
// Initialize core variables
// ============================
$checkInDate = date("Y-m-d H:i:s");
$checkInDisplay = date("F j, Y h:i A");

// ✅ IMPROVED: Check both GET and POST for booking flag
$from_booking = !empty($_GET['guest_name']) || (!empty($_POST['from_booking']) && $_POST['from_booking'] === 'yes');
$guest_name = $_GET['guest_name'] ?? '';
$checkin = $_GET['checkin'] ?? '';
$checkout = $_GET['checkout'] ?? '';
$num_people = $_GET['num_people'] ?? '';

$room_number = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['room_number'] ?? 0)
    : (int)($_GET['room_number'] ?? 0);

if (!$room_number) {
    echo "Error: Room number is required.";
    exit();
}

// ============================
// Fetch room info
// ============================
$roomQuery = "SELECT * FROM rooms WHERE room_number = ? AND status != 'maintenance'";
$stmt = $conn->prepare($roomQuery);
$stmt->bind_param("i", $room_number);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "
    <style>
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
    </style>
    <div id='toast' class='toast'>Room not found or not available.</div>
    <script>
        let toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(() => window.location.href = 'receptionist-room.php', 2000);
    </script>
    ";
    exit();
}

// ============================
// ✅ VALIDATION ONLY IF NOT FROM BOOKING SUMMARY
// ============================
if (!$from_booking) {
    // Active check-in conflict
    $activeCheckinQuery = "
        SELECT COUNT(*) as active_count
        FROM checkins
        WHERE room_number = ?
          AND status IN ('checked_in', 'scheduled')
          AND check_in_date <= NOW()
          AND check_out_date > NOW()
    ";
    $stmtActive = $conn->prepare($activeCheckinQuery);
    $stmtActive->bind_param("i", $room_number);
    $stmtActive->execute();
    $activeCount = (int)$stmtActive->get_result()->fetch_assoc()['active_count'];
    $stmtActive->close();

    if ($activeCount > 0) {
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>
            <style>
                body {
                    background: #fff;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                }
                .status-card {
                    width: 60%;
                    max-width: 1100px;
                    background: #fff;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    overflow: hidden;
                }
                .status-header {
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .status-header h1 {
                    font-size: 2rem;
                    font-weight: 700;
                    margin: 0 0 10px 0;
                    letter-spacing: -0.5px;
                }
                .status-header p {
                    margin: 0;
                    opacity: 0.95;
                    font-size: 1.05rem;
                }
                .status-body {
                    padding: 40px 35px;
                    background: #f8f9fa;
                }
                .info-box {
                    background: white;
                    border-radius: 12px;
                    padding: 25px;
                    margin-bottom: 25px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                }
                .info-label {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    color: #6c757d;
                    font-size: 0.85rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                }
                .status-dot {
                    width: 10px;
                    height: 10px;
                    background: #dc3545;
                    border-radius: 50%;
                    display: inline-block;
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0%, 100% {
                        opacity: 1;
                        transform: scale(1);
                    }
                    50% {
                        opacity: 0.5;
                        transform: scale(1.1);
                    }
                }
                .status-text {
                    color: #dc3545;
                    font-size: 0.95rem;
                    font-weight: 600;
                    margin: 0;
                }
                .btn-back {
                    background: linear-gradient(135deg, #8b1f2e 0%, #a82d3e 100%);
                    color: white;
                    border: none;
                    padding: 14px 30px;
                    border-radius: 10px;
                    font-weight: 600;
                    font-size: 1rem;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    transition: all 0.3s ease;
                    text-decoration: none;
                }
                .btn-back:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(139, 31, 46, 0.4);
                    color: white;
                }
            </style>
        </head>
        <body>
            <div class='status-card'>
                <div class='status-header'>
                    <h1>Room Currently Occupied</h1>
                    <p>This room is being used by another guest</p>
                </div>
                <div class='status-body'>
                    <div class='info-box'>
                        <div class='info-label'>
                            <span class='status-dot'></span>
                            ROOM STATUS
                        </div>
                        <p class='status-text'>This room is currently occupied and unavailable for check-in. Please select another room or wait until it becomes available.</p>
                    </div>
                    <a href='receptionist-room.php' class='btn-back'>
                        <i class='fas fa-arrow-left'></i>
                        Back to Rooms
                    </a>
                </div>
            </div>
        </body>
        </html>
        ";
        exit();
    }

    // Booking conflict
    $bookingConflictQuery = "
        SELECT guest_name, start_date, end_date
        FROM bookings
        WHERE room_number = ?
          AND status NOT IN ('cancelled', 'completed')
          AND end_date > NOW()
        ORDER BY start_date ASC
        LIMIT 1
    ";
    $stmtBooking = $conn->prepare($bookingConflictQuery);
    $stmtBooking->bind_param("i", $room_number);
    $stmtBooking->execute();
    $bookingResult = $stmtBooking->get_result();
    $conflictingBooking = $bookingResult->fetch_assoc();
    $stmtBooking->close();

    if ($conflictingBooking) {
        $conflictStart = date('M d, Y', strtotime($conflictingBooking['start_date']));
        $conflictStartTime = date('h:i A', strtotime($conflictingBooking['start_date']));
        $conflictEnd = date('M d, Y', strtotime($conflictingBooking['end_date']));
        $conflictEndTime = date('h:i A', strtotime($conflictingBooking['end_date']));
        
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>
            <style>
                body {
                    background: #fff;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                }
                .status-card {
                    width: 60%;
                    max-width: 1100px;
                    background: #fff;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    overflow: hidden;
                }
                .status-header {
                    background: linear-gradient(135deg, #8b1f2e 0%, #a82d3e 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .status-header h1 {
                    font-size: 2rem;
                    font-weight: 700;
                    margin: 0 0 10px 0;
                    letter-spacing: -0.5px;
                }
                .status-header p {
                    margin: 0;
                    opacity: 0.95;
                    font-size: 1.05rem;
                }
                .status-body {
                    padding: 40px 35px;
                    background: #f8f9fa;
                }
                .info-box {
                    background: white;
                    border-radius: 12px;
                    padding: 25px;
                    margin-bottom: 25px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                }
                .info-label {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    color: #6c757d;
                    font-size: 0.85rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 20px;
                }
                .status-dot {
                    width: 10px;
                    height: 10px;
                    background: #dc3545;
                    border-radius: 50%;
                    display: inline-block;
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0%, 100% {
                        opacity: 1;
                        transform: scale(1);
                    }
                    50% {
                        opacity: 0.5;
                        transform: scale(1.1);
                    }
                }
                .period-section {
                    text-align: center;
                    margin-bottom: 15px;
                }
                .period-label {
                    color: #6c757d;
                    font-size: 0.85rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    margin-bottom: 8px;
                }
                .period-date {
                    color: #212529;
                    font-size: 1.25rem;
                    font-weight: 700;
                    margin-bottom: 3px;
                }
                .period-time {
                    color: #6c757d;
                    font-size: 0.95rem;
                }
                .divider {
                    width: 2px;
                    height: 40px;
                    background: linear-gradient(to bottom, transparent, #dc3545, transparent);
                    margin: 10px auto;
                }
                .btn-back {
                    background: linear-gradient(135deg, #8b1f2e 0%, #a82d3e 100%);
                    color: white;
                    border: none;
                    padding: 14px 30px;
                    border-radius: 10px;
                    font-weight: 600;
                    font-size: 1rem;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    transition: all 0.3s ease;
                    text-decoration: none;
                }
                .btn-back:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(139, 31, 46, 0.4);
                    color: white;
                }
            </style>
        </head>
        <body>
            <div class='status-card'>
                <div class='status-header'>
                    <h1>Room Already Reserved</h1>
                    <p>This room has an upcoming reservation by <strong>" . htmlspecialchars($conflictingBooking['guest_name']) . "</strong></p>
                </div>
                <div class='status-body'>
                    <div class='info-box'>
                        <div class='info-label'>
                            <span class='status-dot'></span>
                            RESERVED PERIOD
                        </div>
                        <div class='period-section'>
                            <div class='period-label'>FROM</div>
                            <div class='period-date'>{$conflictStart}</div>
                            <div class='period-time'>{$conflictStartTime}</div>
                        </div>
                        <div class='divider'></div>
                        <div class='period-section'>
                            <div class='period-label'>TO</div>
                            <div class='period-date'>{$conflictEnd}</div>
                            <div class='period-time'>{$conflictEndTime}</div>
                        </div>
                    </div>
                    <a href='receptionist-room.php' class='btn-back'>
                        <i class='fas fa-arrow-left'></i>
                        Back to Rooms
                    </a>
                </div>
            </div>
        </body>
        </html>
        ";
        exit();
    }
}

// ✅ Make room available if no issues
$conn->query("UPDATE rooms SET status = 'available' WHERE room_number = $room_number AND status != 'maintenance'");

// ============================
// Fetch existing bookings/check-ins for timeline display
// ============================
$upcomingSchedules = [];

// Get active check-ins (only those that have started or are upcoming)
$activeCheckinQuery = "
    SELECT guest_name, check_in_date, check_out_date, status, 'checkin' as type
    FROM checkins
    WHERE room_number = ?
      AND status IN ('checked_in', 'scheduled')
      AND check_out_date > NOW()
    ORDER BY check_in_date ASC
";
$stmtActive = $conn->prepare($activeCheckinQuery);
$stmtActive->bind_param("i", $room_number);
$stmtActive->execute();
$activeResult = $stmtActive->get_result();
while ($row = $activeResult->fetch_assoc()) {
    $upcomingSchedules[] = $row;
}
$stmtActive->close();

// Get upcoming bookings
$upcomingBookingsQuery = "
    SELECT guest_name, start_date as check_in_date, end_date as check_out_date, status, 'booking' as type
    FROM bookings
    WHERE room_number = ?
      AND status NOT IN ('cancelled', 'completed')
      AND end_date > NOW()
    ORDER BY start_date ASC
";
$stmtBooking = $conn->prepare($upcomingBookingsQuery);
$stmtBooking->bind_param("i", $room_number);
$stmtBooking->execute();
$bookingResult = $stmtBooking->get_result();
while ($row = $bookingResult->fetch_assoc()) {
    $upcomingSchedules[] = $row;
}
$stmtBooking->close();

// Sort all schedules by check-in date
usort($upcomingSchedules, function($a, $b) {
    return strtotime($a['check_in_date']) - strtotime($b['check_in_date']);
});

// ============================
// ✅ FORM SUBMISSION WITH SMART VALIDATION
// ============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $guest_name_submitted = htmlspecialchars(trim($_POST['guest_name']));
    $address = htmlspecialchars(trim($_POST['address']));
    $telephone = htmlspecialchars(trim($_POST['telephone']));
    $room_type = htmlspecialchars(trim($_POST['room_type'] ?? $room['room_type']));
    $stay_duration = (int)($_POST['stay_duration']);
    $payment_mode = htmlspecialchars(trim($_POST['payment_mode']));
    $gcash_reference = htmlspecialchars(trim($_POST['gcash_ref_id'] ?? ''));
    $user_id = $_SESSION['user_id'] ?? null;

    if (
        empty($guest_name_submitted) || empty($address) || empty($telephone) ||
        $stay_duration <= 0 || $payment_mode === "select" ||
        ($payment_mode === "gcash" && empty($gcash_reference))
    ) {
        $_SESSION['error_msg'] = "Please fill in all required fields.";
        header("Location: check-in.php?room_number={$room_number}");
        exit();
    }

    $pricing = [
        3 => $room['price_3hrs'],
        6 => $room['price_6hrs'],
        12 => $room['price_12hrs'],
        24 => $room['price_24hrs']
    ];

    if (!isset($pricing[$stay_duration])) {
        $_SESSION['error_msg'] = "Invalid stay duration.";
        header("Location: check-in.php?room_number={$room_number}");
        exit();
    }

    $total_price = floatval($pricing[$stay_duration]);
    $amount_paid = isset($_POST['amount_paid']) && is_numeric($_POST['amount_paid'])
        ? floatval($_POST['amount_paid'])
        : 0.00;
    $change = max(0, $amount_paid - $total_price);

    $check_in_datetime = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $check_out_datetime = clone $check_in_datetime;
    $check_out_datetime->modify("+{$stay_duration} hours");

    $check_in_mysql = $check_in_datetime->format('Y-m-d H:i:s');
    $check_out_mysql = $check_out_datetime->format('Y-m-d H:i:s');

    // ============================
    // ✅ CONFLICT CHECKS (ONLY IF NOT FROM BOOKING)
    // ============================
    if (!$from_booking) {
        // Check for overlapping check-ins
        $overlapCheckinQuery = "
            SELECT guest_name, check_in_date, check_out_date
            FROM checkins
            WHERE room_number = ?
              AND status IN ('checked_in', 'scheduled')
              AND (
                  (check_in_date < ? AND check_out_date > ?) OR
                  (check_in_date < ? AND check_out_date > ?) OR
                  (check_in_date >= ? AND check_out_date <= ?)
              )
            LIMIT 1
        ";
        $stmtOverlap = $conn->prepare($overlapCheckinQuery);
        $stmtOverlap->bind_param(
            'issssss',
            $room_number,
            $check_out_mysql, $check_in_mysql,
            $check_out_mysql, $check_in_mysql,
            $check_in_mysql, $check_out_mysql
        );
        $stmtOverlap->execute();
        $overlapResult = $stmtOverlap->get_result();
        $overlappingCheckin = $overlapResult->fetch_assoc();
        $stmtOverlap->close();

        if ($overlappingCheckin) {
            $_SESSION['error_msg'] = "Time conflict detected! Room already booked by " .
                                     htmlspecialchars($overlappingCheckin['guest_name']) . 
                                     " during this time period.";
            header("Location: check-in.php?room_number={$room_number}");
            exit();
        }

        // Check for overlapping bookings
        $overlapBookingQuery = "
            SELECT guest_name, start_date, end_date
            FROM bookings
            WHERE room_number = ?
              AND status NOT IN ('cancelled', 'completed')
              AND (
                  (start_date < ? AND end_date > ?) OR
                  (start_date < ? AND end_date > ?) OR
                  (start_date >= ? AND end_date <= ?)
              )
            LIMIT 1
        ";
        $stmtBookingOverlap = $conn->prepare($overlapBookingQuery);
        $stmtBookingOverlap->bind_param(
            'issssss',
            $room_number,
            $check_out_mysql, $check_in_mysql,
            $check_out_mysql, $check_in_mysql,
            $check_in_mysql, $check_out_mysql
        );
        $stmtBookingOverlap->execute();
        $bookingOverlapResult = $stmtBookingOverlap->get_result();
        $overlappingBooking = $bookingOverlapResult->fetch_assoc();
        $stmtBookingOverlap->close();

        if ($overlappingBooking) {
            $_SESSION['error_msg'] = "Time conflict detected! Room reserved by " .
                                     htmlspecialchars($overlappingBooking['guest_name']) . 
                                     " during this time period.";
            header("Location: check-in.php?room_number={$room_number}");
            exit();
        }
    }

    // ============================
    // ✅ Insert check-in record
    // ============================
    $sql = "INSERT INTO checkins 
            (guest_name, address, telephone, room_number, room_type, stay_duration,
             total_price, amount_paid, change_amount, payment_mode, gcash_reference,
             check_in_date, check_out_date, status, receptionist_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'checked_in', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssisiddsssssi",
        $guest_name_submitted, $address, $telephone,
        $room_number, $room_type, $stay_duration,
        $total_price, $amount_paid, $change,
        $payment_mode, $gcash_reference,
        $check_in_mysql, $check_out_mysql,
        $user_id
    );

    if ($stmt->execute()) {
        // Update room status
        $updateRoomStatusQuery = "UPDATE rooms SET status = 'booked' WHERE room_number = ?";
        $updateStmt = $conn->prepare($updateRoomStatusQuery);
        $updateStmt->bind_param("i", $room_number);
        $updateStmt->execute();
        $updateStmt->close();

        // ✅ Update booking status to completed if from booking
        if ($from_booking) {
            $updateBookingQuery = "
                UPDATE bookings 
                SET status = 'completed' 
                WHERE guest_name = ? 
                  AND room_number = ? 
                  AND status NOT IN ('cancelled', 'completed')
                ORDER BY start_date DESC 
                LIMIT 1
            ";
            $updateBookingStmt = $conn->prepare($updateBookingQuery);
            $updateBookingStmt->bind_param("si", $guest_name_submitted, $room_number);
            $updateBookingStmt->execute();
            $updateBookingStmt->close();
        }

        $_SESSION['guest_name'] = $guest_name_submitted;
        $_SESSION['room_number'] = $room_number;
        $_SESSION['room_type'] = $room_type;
        $_SESSION['check_in_date'] = $check_in_datetime->format('F j, Y h:i A');
        $_SESSION['check_out_date'] = $check_out_datetime->format('F j, Y h:i A');
        $_SESSION['total_price'] = $total_price;

        header("Location: receptionist-guest.php?success=checkedin");
        exit();
    } else {
        $_SESSION['error_msg'] = "Check-in failed: " . $stmt->error;
        header("Location: check-in.php?room_number={$room_number}");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>


<!-- HTML FORM -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In | Gitarra Apartelle</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        gcash: '#0063F7',
                    },
                },
            },
        }
    </script>

    <style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>

</head>

<body class="bg-gray-50 font-sans">
    <div class="container mx-auto py-8 px-4">
        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden transition-all duration-300 hover:shadow-xl mb-6">
                <div class="text-white px-6 py-4 flex justify-between items-center" style="background-color: #8b1d2d;">
                    <h4 class="text-xl font-semibold flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>Guest Check-In
                        <?php if ($from_booking): ?>
                            <span class="ml-3 text-sm bg-green-500 px-3 py-1 rounded-full">From Booking</span>
                        <?php endif; ?>
                    </h4>
                    <div id="currentTime" class="text-white text-sm" style="color: white !important;"></div>
                </div>
                
                <div class="p-6">
                    <form method="post" id="checkInForm" onsubmit="return validateForm();">
                        <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room_number); ?>">
                        <!-- ✅ Hidden field to track if from booking -->
                        <input type="hidden" name="from_booking" value="<?php echo $from_booking ? 'yes' : 'no'; ?>">

                        <div class="mb-8">
                            <div class="border-l-4 p-4 rounded-lg mb-6"
                                style="background-color: #f8e8ea; border-left-color: #8b1d2d; color: #8b1d2d;">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <p>
                                        You are checking in a guest for
                                        <span class="font-semibold text-[#8b1d2d]">
                                            Room <?php echo $room['room_number']; ?> (<?php echo ucfirst($room['room_type']); ?>)
                                        </span>
                                    </p>
                                </div>
                            </div>

<!-- Occupied Time Slots Display -->
<?php if (!empty($upcomingSchedules)): ?>
<div class="mb-6">
    <div class="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg">
        <div class="bg-yellow-50 px-4 py-3 border-b border-yellow-200">
            <h5 class="font-medium flex items-center text-yellow-800">
                <i class="fas fa-calendar-alt mr-2"></i>
                Occupied Time Slots for Room <?php echo $room['room_number']; ?>
            </h5>
        </div>
        <div class="p-5">
            <div class="space-y-3">
                <?php 
                foreach ($upcomingSchedules as $schedule): 
                    $checkInTime = strtotime($schedule['check_in_date']);
                    $checkOutTime = strtotime($schedule['check_out_date']);
                    $currentTime = time();
                    
                    // ✅ Currently occupied = check-in has started AND hasn't ended yet
                    $isCurrentlyOccupied = ($checkInTime <= $currentTime) && ($checkOutTime > $currentTime);
                    
                    // ✅ Upcoming = hasn't started yet
                    $isUpcoming = $checkInTime > $currentTime;
                    
                    $borderColor = $schedule['type'] === 'checkin' ? 'border-blue-500' : 'border-purple-500';
                    $bgColor = $schedule['type'] === 'checkin' ? 'bg-blue-50' : 'bg-purple-50';
                    $iconColor = $schedule['type'] === 'checkin' ? 'text-blue-600' : 'text-purple-600';
                    $typeLabel = $schedule['type'] === 'checkin' ? 'Check-in' : 'Booking';
                    
                    // Determine status label
                    if ($isCurrentlyOccupied) {
                        $statusLabel = 'Currently Occupied';
                        $statusClass = 'bg-red-200 text-red-800';
                        $statusIcon = 'fa-circle';
                    } elseif ($isUpcoming) {
                        $statusLabel = 'Upcoming';
                        $statusClass = 'bg-yellow-200 text-yellow-800';
                        $statusIcon = 'fa-clock';
                    } else {
                        $statusLabel = 'Past';
                        $statusClass = 'bg-gray-200 text-gray-800';
                        $statusIcon = 'fa-history';
                    }
                ?>
                <div class="border-l-4 <?php echo $borderColor; ?> <?php echo $bgColor; ?> p-4 rounded-lg">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2 flex-wrap gap-2">
                                <i class="fas fa-user <?php echo $iconColor; ?> mr-1"></i>
                                <span class="font-semibold <?php echo $iconColor; ?>">
                                    <?php echo htmlspecialchars($schedule['guest_name']); ?>
                                </span>
                                <span class="text-xs px-2 py-1 rounded-full <?php echo $schedule['type'] === 'checkin' ? 'bg-blue-200 text-blue-800' : 'bg-purple-200 text-purple-800'; ?>">
                                    <?php echo $typeLabel; ?>
                                </span>
                                <span class="text-xs px-2 py-1 rounded-full <?php echo $statusClass; ?> <?php echo $isCurrentlyOccupied ? 'animate-pulse' : ''; ?>">
                                    <i class="fas <?php echo $statusIcon; ?> mr-1"></i><?php echo $statusLabel; ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-sign-in-alt text-green-600 mr-2 w-4"></i>
                                    <span class="font-medium">Check-in:</span>
                                    <span class="ml-2"><?php echo date('M d, Y h:i A', $checkInTime); ?></span>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-sign-out-alt text-red-600 mr-2 w-4"></i>
                                    <span class="font-medium">Check-out:</span>
                                    <span class="ml-2"><?php echo date('M d, Y h:i A', $checkOutTime); ?></span>
                                </div>
                            </div>
                            <?php 
                            $duration = ($checkOutTime - $checkInTime) / 3600;
                            $remainingHours = max(0, ($checkOutTime - $currentTime) / 3600);
                            $hoursUntilStart = max(0, ($checkInTime - $currentTime) / 3600);
                            ?>
                            <div class="mt-2 text-xs text-gray-600">
                                <i class="fas fa-clock mr-1"></i>
                                Duration: <?php echo number_format($duration, 1); ?> hours
                                <?php if ($isCurrentlyOccupied && $remainingHours > 0): ?>
                                    • <span class="text-orange-600 font-medium"><?php echo number_format($remainingHours, 1); ?> hours remaining</span>
                                <?php elseif ($isUpcoming && $hoursUntilStart > 0): ?>
                                    • <span class="text-blue-600 font-medium">Starts in <?php echo number_format($hoursUntilStart, 1); ?> hours</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($from_booking): ?>
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800 flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1"></i>
                        <span><strong>Ready to Check In:</strong> You're checking in a guest from their reservation. The booking details are pre-filled for convenience. Please verify the information and complete the check-in process.</span>
                    </p>
                </div>
            <?php else: ?>
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800 flex items-start">
                        <i class="fas fa-info-circle mr-2 mt-1"></i>
                        <span><strong>Important:</strong> Please select a check-in time and duration that doesn't conflict with the occupied slots above. The system will automatically validate your selection.</span>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-white rounded-xl shadow-md overflow-hidden h-full transition-all duration-300 hover:shadow-lg">
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                    <h5 class="font-medium flex items-center" style="color: #8b1d2d;">
                                    <i class="fas fa-user mr-2" style="color: #8b1d2d;"></i>Guest Information
                                    </h5>
                                    </div>
                                    <div class="p-5">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Guest Name</label>
                                            <input type="text" name="guest_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors" value="<?= htmlspecialchars($guest_name) ?>" required oninput="this.value = this.value.replace(/[0-9]/g, '')">
                                        </div>
                                        <div class="flex flex-col mb-5">
                                        <label for="telephone" class="mb-1 font-medium text-gray-700">Contact Number</label>

                                        <div class="flex">
                                            <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                            <i class="fas fa-mobile"></i>
                                            </span>
                                            <input
                                            type="text"
                                            id="telephone"
                                            name="telephone"
                                            class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors"
                                            placeholder="+63 9xx-xxx-xxxx"
                                            required
                                            oninput="formatPhilippineNumber(this)"
                                            onblur="checkPhoneValidity(this)"
                                            >
                                        </div>

                                            <div id="phone-error" class="text-red-500 text-sm mb-1 hidden">
                                            Please enter a valid mobile number (e.g. +63 9XX-XXX-XXXX).
                                        </div>

                                        </div>

                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                                            <div class="flex">
                                                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </span>
                                                <input type="text" name="address" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-white rounded-xl shadow-md overflow-hidden h-full transition-all duration-300 hover:shadow-lg">
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                        <h5 class="font-medium flex items-center" style="color: #8b1d2d;"><i class="fas fa-calendar-alt mr-2" style="color: #8b1d2d;"></i>Stay Information</h5>
                                    </div>
                                    <div class="p-5">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Check-in Date & Time</label>
                                            <div class="flex">
                                                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                                    <i class="fas fa-clock"></i>
                                                </span>
                                                <input type="text" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" value="<?php echo $checkInDisplay; ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Stay Duration</label>
                                            <select name="stay_duration" id="stay_duration" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors" required onchange="updateSummary();">
                                                <option value="">Select Duration</option>
                                                <option value="3">3 Hours - ₱<?php echo number_format($room['price_3hrs'], 2); ?></option>
                                                <option value="6">6 Hours - ₱<?php echo number_format($room['price_6hrs'], 2); ?></option>
                                                <option value="12">12 Hours - ₱<?php echo number_format($room['price_12hrs'], 2); ?></option>
                                                <option value="24">24 Hours - ₱<?php echo number_format($room['price_24hrs'], 2); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Estimated Check-out</label>
                                            <div class="flex">
                                                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                                    <i class="fas fa-calendar-check"></i>
                                                </span>
                                                <input type="text" id="checkout_datetime" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-8">
                            <div class="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                    <h5 class="font-medium flex items-center" style="color: #8b1d2d;"><i class="fas fa-credit-card mr-2" style="color: #8b1d2d;"></i>Payment Method</h5>
                                </div>
                                <div class="p-5">
                                    <input type="hidden" name="payment_mode" id="payment_mode" value="select">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-all duration-300 hover:border-gray-400 hover:-translate-y-1" id="cash_option" onclick="selectPayment('cash')">
                                            <div class="flex items-center">
                                                <div class="text-2xl text-green-500 mr-3">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </div>
                                                <div>
                                                    <h5 class="font-medium mb-1">Cash Payment</h5>
                                                    <p class="text-sm text-gray-500">Pay with cash upon check-in</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-all duration-300 hover:border-gray-400 hover:-translate-y-1" id="gcash_option" onclick="selectPayment('gcash')">
                                            <div class="flex items-center">
                                                <div class="text-2xl text-[#0063F7] mr-3">
                                                    <i class="fas fa-mobile-alt"></i>
                                                </div>
                                                <div>
                                                    <h5 class="font-medium mb-1">GCash Payment</h5>
                                                    <p class="text-sm text-gray-500">Pay using GCash mobile wallet</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
<!-- CASH PAYMENT SECTION -->
<div id="cash_section" class="hidden mt-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-700 text-sm font-medium mb-2">Amount Paid</label>
            <div class="flex">
                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">₱</span>
                <input type="number" name="amount_paid" id="cash_amount_paid"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors"
                    oninput="calculateChange(); validateCashAmount();">
            </div>
            <p id="cash_error" class="text-red-500 text-sm mt-1 hidden">Amount paid must be at least equal to the total price.</p>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-medium mb-2">Change</label>
            <div class="flex">
                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">₱</span>
                <input type="text" name="change" id="change"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" readonly>
            </div>
        </div>
    </div>
</div>

<!-- GCASH PAYMENT SECTION -->
<div id="gcash_section" class="hidden mt-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                <h5 class="font-medium text-blue-700 flex items-center mb-3">
                    <i class="fas fa-info-circle mr-2"></i>GCash Payment Instructions
                </h5>
                <ol class="list-decimal pl-5 text-blue-700 text-sm space-y-2">
                    <li>Open your GCash app</li>
                    <li>Send payment to: <span class="font-semibold">09123456789</span></li>
                    <li>Enter the exact amount: <span class="font-semibold" id="gcash_amount_display">₱0.00</span></li>
                    <li>Complete the payment and note your reference number</li>
                </ol>
            </div>
        </div>

        <div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-2">Amount Paid via GCash</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">₱</span>
                    <input type="number" id="gcash_amount_paid"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" readonly>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-2">GCash Reference Number</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                        <i class="fas fa-hashtag"></i>
                    </span>
                    <input type="text" 
                        name="gcash_ref_id" 
                        id="gcash_ref_id"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors"
                        placeholder="Enter your GCash reference number"
                        maxlength="13"
                        oninput="limitGCashRef(this); validateGCashRef();">
                </div>
                <p id="gcash_error" class="text-red-500 text-sm mt-1 hidden">Please enter the GCash reference number.</p>
                <p id="gcash_digit_error" class="text-red-500 text-sm mt-1 hidden">Reference number must contain digits only.</p>
                <p class="text-xs text-gray-500 mt-1">Enter the reference number from your GCash transaction</p>
            </div>

        </div>
    </div>
</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                    <h5 class="font-medium flex items-center" style="color: #8b1d2d;"><i class="fas fa-receipt mr-2" style="color: #8b1d2d;"></i>Booking Summary</h5>
                                </div>
                                <div class="p-5">
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Room Number:</span>
                                            <span class="font-medium"><?php echo $room['room_number']; ?></span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Room Type:</span>
                                            <span class="font-medium"><?php echo ucfirst($room['room_type']); ?></span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Duration:</span>
                                            <span class="font-medium" id="summary_duration">-</span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Check-in:</span>
                                            <span class="font-medium"><?php echo $checkInDisplay; ?></span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Check-out:</span>
                                            <span class="font-medium" id="summary_checkout">-</span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Payment Method:</span>
                                            <span class="font-medium" id="summary_payment">-</span>
                                        </div>
                                        <div class="flex justify-between pt-4 mt-2 border-t border-gray-200">
                                            <span class="text-gray-800 font-medium">Total Amount:</span>
                                            <span class="font-bold" style="color: #8b1d2d;" id="summary_total">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col justify-end">
                            <button type="submit"
                                class="w-full text-white font-medium py-3 px-4 rounded-lg transition-colors duration-300 flex items-center justify-center mb-3"
                                style="background-color: #8b1d2d;"
                                onmouseover="this.style.backgroundColor='#6b1422';"
                                onmouseout="this.style.backgroundColor='#8b1d2d';">
                                <i class="fas fa-check-circle mr-2"></i>Confirm Check-In
                            </button>
                                <a href="receptionist-room.php" class="w-full bg-white hover:bg-gray-100 text-gray-700 font-medium py-3 px-4 rounded-lg border border-gray-300 transition-colors duration-300 flex items-center justify-center">
                                    <i class="fas fa-arrow-left mr-2"></i>Back to Rooms
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to update the estimated check-out date and total price based on stay duration
        const priceMap = {
            3: <?php echo (float)$room['price_3hrs']; ?>,
            6: <?php echo (float)$room['price_6hrs']; ?>,
            12: <?php echo (float)$room['price_12hrs']; ?>,
            24: <?php echo (float)$room['price_24hrs']; ?>,
        };


        function updateSummary() {
            const duration = parseInt(document.getElementById("stay_duration").value);
            if (!duration || !priceMap[duration]) {
                document.getElementById("checkout_datetime").value = '';
                document.getElementById("summary_duration").textContent = '-';
                document.getElementById("summary_checkout").textContent = '-';
                document.getElementById("summary_total").textContent = '₱0.00';
                document.getElementById("gcash_amount_display").textContent = '₱0.00';
                if (document.getElementById("gcash_amount_paid")) {
                    document.getElementById("gcash_amount_paid").value = '';
                }
                return;
            }
            
            const now = new Date();
            const checkoutTime = new Date(now.getTime() + duration * 60 * 60 * 1000);
            const formattedCheckout = checkoutTime.toLocaleString();
            
            document.getElementById("checkout_datetime").value = formattedCheckout;
            document.getElementById("summary_duration").textContent = `${duration} hours`;
            document.getElementById("summary_checkout").textContent = formattedCheckout;
            
            const totalPrice = priceMap[duration];
            document.getElementById("summary_total").textContent = `₱${totalPrice.toFixed(2)}`;
            document.getElementById("gcash_amount_display").textContent = `₱${totalPrice.toFixed(2)}`;
            
            if (document.getElementById("gcash_amount_paid")) {
                document.getElementById("gcash_amount_paid").value = totalPrice.toFixed(2);
            }
        }

function selectPayment(mode) {
    // Update hidden input
    document.getElementById("payment_mode").value = mode;

    // ✅ Ensure only one active input with name="amount_paid"
    document.getElementById("cash_amount_paid").removeAttribute("name");
    document.getElementById("gcash_amount_paid").removeAttribute("name");
    if (mode === "cash") {
        document.getElementById("cash_amount_paid").setAttribute("name", "amount_paid");
    } else if (mode === "gcash") {
        document.getElementById("gcash_amount_paid").setAttribute("name", "amount_paid");
    }

    // Update UI
    document.getElementById("cash_option").classList.remove("ring-2", "ring-primary-500", "border-primary-500", "bg-primary-50");
    document.getElementById("gcash_option").classList.remove("ring-2", "ring-primary-500", "border-primary-500", "bg-primary-50");
    document.getElementById(`${mode}_option`).classList.add("ring-2", "ring-primary-500", "border-primary-500", "bg-primary-50");

    // Show/hide appropriate sections
    document.getElementById("cash_section").classList.add("hidden");
    document.getElementById("gcash_section").classList.add("hidden");
    document.getElementById(`${mode}_section`).classList.remove("hidden");

    // Update summary
    document.getElementById("summary_payment").textContent = mode === 'cash' ? 'Cash' : 'GCash';

    // Set required fields
    const gcashRefInput = document.getElementById("gcash_ref_id");
    if (gcashRefInput) {
        gcashRefInput.required = mode === "gcash";
    }

    // Update amount fields
    const duration = parseInt(document.getElementById("stay_duration").value);
    if (duration && priceMap[duration]) {
        const totalPrice = priceMap[duration];
        if (mode === "gcash") {
            document.getElementById("gcash_amount_paid").value = totalPrice.toFixed(2);
        }
    }
}


        function calculateChange() {
            const total = parseFloat(document.getElementById("summary_total").textContent.replace('₱', '').replace(',', '')) || 0;
            const paid = parseFloat(document.getElementById("cash_amount_paid").value) || 0;
            const changeInput = document.getElementById("change");

            if (paid > total) {
                // ✅ Show change only when overpaid
                changeInput.value = (paid - total).toFixed(2);
            } else {
                // ✅ Hide change if exact or underpaid
                changeInput.value = '';
            }
        }
         
            // ---- FORM VALIDATION ----
            function validateForm() {
                const paymentMode = document.getElementById("payment_mode").value;
                const duration = parseInt(document.getElementById("stay_duration").value);
                const totalPrice = priceMap[duration];
                const paid = parseFloat(document.getElementById("cash_amount_paid").value) || 0;

                document.getElementById("cash_error").classList.add("hidden");
                document.getElementById("gcash_error").classList.add("hidden");
                document.getElementById("gcash_digit_error").classList.add("hidden");

                if (!duration || !priceMap[duration]) {
                    alert("Please select a stay duration.");
                    return false;
                }

                if (paymentMode === "select") {
                    alert("Please select a payment method.");
                    return false;
                }

                if (paymentMode === "cash") {
                    if (paid < totalPrice) {
                        document.getElementById("cash_error").classList.remove("hidden");
                        return false;
                    }
                } else if (paymentMode === "gcash") {
                    const refNumber = document.getElementById("gcash_ref_id").value.trim();
                    if (!refNumber) {
                        document.getElementById("gcash_error").classList.remove("hidden");
                        return false;
                    } else if (!/^\d+$/.test(refNumber)) {
                        document.getElementById("gcash_digit_error").classList.remove("hidden");
                        return false;
                    }
                }

                return true;
            }

            // ---- LIVE VALIDATION ----
            function validateCashAmount() {
                const duration = parseInt(document.getElementById("stay_duration").value);
                const totalPrice = priceMap[duration];
                const paid = parseFloat(document.getElementById("cash_amount_paid").value) || 0;
                const error = document.getElementById("cash_error");

                if (paid >= totalPrice) {
                    // ✅ Valid if exact or more
                    error.classList.add("hidden");
                } else {
                    // ❌ Not enough payment
                    error.classList.remove("hidden");
                }
            }
            
            // GCash reference number validation
           // Restrict input to digits only and force 13-digit max
            function limitGCashRef(input) {
                // Remove non-digits
                input.value = input.value.replace(/\D/g, '');
                // Limit to 13 digits
                if (input.value.length > 13) {
                    input.value = input.value.slice(0, 13);
                }
            }

            // Validate input
            function validateGCashRef() {
                const refInput = document.getElementById("gcash_ref_id");
                const errorEmpty = document.getElementById("gcash_error");
                const errorDigit = document.getElementById("gcash_digit_error");

                let errorLength = document.getElementById("gcash_length_error");
                if (!errorLength) {
                    errorLength = document.createElement("p");
                    errorLength.id = "gcash_length_error";
                    errorLength.className = "text-red-500 text-sm mt-1 hidden";
                    errorLength.textContent = "Reference number must be exactly 13 digits.";
                    refInput.parentNode.parentNode.appendChild(errorLength);
                }

                const value = refInput.value.trim();

                // Reset all errors
                errorEmpty.classList.add("hidden");
                errorDigit.classList.add("hidden");
                errorLength.classList.add("hidden");
                refInput.classList.remove("border-red-500");

                if (value === "") {
                    errorEmpty.classList.remove("hidden");
                    refInput.classList.add("border-red-500");
                    return false;
                }

                const onlyDigits = /^[0-9]+$/;
                if (!onlyDigits.test(value)) {
                    errorDigit.classList.remove("hidden");
                    refInput.classList.add("border-red-500");
                    return false;
                }

                if (value.length !== 13) {
                    errorLength.classList.remove("hidden");
                    refInput.classList.add("border-red-500");
                    return false;
                }

                return true;
            }


// Occupied time slots data
const occupiedSlots = <?php echo json_encode(array_map(function($schedule) {
    return [
        'guest_name' => $schedule['guest_name'],
        'check_in' => strtotime($schedule['check_in_date']) * 1000,
        'check_out' => strtotime($schedule['check_out_date']) * 1000,
        'type' => $schedule['type']
    ];
}, $upcomingSchedules)); ?>;

// ✅ Check if this is from a booking
const fromBooking = <?php echo $from_booking ? 'true' : 'false'; ?>;

// Function to check if selected time conflicts with existing schedules
function checkTimeConflict() {
    // ✅ Skip conflict check if checking in from booking
    if (fromBooking) {
        hideConflictWarning();
        return;
    }

    const duration = parseInt(document.getElementById("stay_duration").value);
    if (!duration) return;
    
    const now = new Date().getTime();
    const selectedCheckout = now + (duration * 60 * 60 * 1000);
    
    let hasConflict = false;
    let conflictDetails = '';
    
    occupiedSlots.forEach(slot => {
        if ((now < slot.check_out && selectedCheckout > slot.check_in) ||
            (now >= slot.check_in && selectedCheckout <= slot.check_out)) {
            hasConflict = true;
            const checkInDate = new Date(slot.check_in).toLocaleString();
            const checkOutDate = new Date(slot.check_out).toLocaleString();
            conflictDetails += `\n• ${slot.guest_name} (${slot.type === 'checkin' ? 'Check-in' : 'Booking'}): ${checkInDate} - ${checkOutDate}`;
        }
    });
    
    if (hasConflict) {
        showConflictWarning(conflictDetails);
    } else {
        hideConflictWarning();
    }
}

function showConflictWarning(details) {
    let warningDiv = document.getElementById('conflict-warning');
    if (!warningDiv) {
        warningDiv = document.createElement('div');
        warningDiv.id = 'conflict-warning';
        warningDiv.className = 'mt-3 p-3 bg-red-50 border-l-4 border-red-500 rounded-lg';
        document.getElementById('stay_duration').parentNode.appendChild(warningDiv);
    }
    warningDiv.innerHTML = `
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-red-600 mr-2 mt-1"></i>
            <div>
                <p class="text-sm font-semibold text-red-800 mb-1">Time Conflict Detected!</p>
                <p class="text-xs text-red-700">Your selected duration conflicts with:${details}</p>
            </div>
        </div>
    `;
    warningDiv.classList.remove('hidden');
}

function hideConflictWarning() {
    const warningDiv = document.getElementById('conflict-warning');
    if (warningDiv) {
        warningDiv.classList.add('hidden');
    }
}

// Add event listener to stay duration
document.getElementById('stay_duration').addEventListener('change', checkTimeConflict);

        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
        }

        setInterval(updateClock, 1000);
        updateClock();

        // validate contact number
        function formatPhilippineNumber(input) {
        let value = input.value.replace(/[^\d+]/g, "");

        if (!value.startsWith("+63")) {
            value = "+63" + value.replace(/^\+?63?/, "");
        }

        const digits = value.replace(/\D/g, "").substring(2, 12);

        let formatted = "+63";
        if (digits.length > 0) formatted += " " + digits.substring(0, 1);
        if (digits.length > 1) formatted += digits.substring(1, 3);
        if (digits.length > 3) formatted += "-" + digits.substring(3, 6);
        if (digits.length > 6) formatted += "-" + digits.substring(6, 10);

        input.value = formatted;
        }

        function checkPhoneValidity(input) {
        const pattern = /^\+63 9\d{2}-\d{3}-\d{4}$/;
        const feedback = document.getElementById("phone-error");

        if (!pattern.test(input.value)) {
            input.classList.add("is-invalid");
            feedback.classList.remove("hidden");
        } else {
            input.classList.remove("is-invalid");
            feedback.classList.add("hidden");
        }
        }

        // Clean up before submitting (remove spaces and dashes)
        document.querySelector("form")?.addEventListener("submit", function () {
        const input = document.getElementById("telephone");
        input.value = input.value.replace(/[\s-]/g, ""); // e.g. +639123456789
        });
    </script>
</body>

</html>