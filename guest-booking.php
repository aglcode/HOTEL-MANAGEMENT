<?php
session_start();
require_once 'database.php';

// Generate a unique booking token
function generateBookingToken() {
    return 'BK' . date('Ymd') . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
}

// Enhanced email function with comprehensive booking details
function sendBookingEmail($email, $guestName, $token, $bookingDetails) {
    $subject = "🏨 Booking Confirmation - Gitarra Apartelle (Token: $token)";
    
    // Calculate check-out time
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
            .detail-label { font-weight: bold; color: #555; }
            .detail-value { color: #333; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            .important { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
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
                
                <div class='important'>
                    <strong>⚠️ IMPORTANT:</strong> Please save this booking token and present it during check-in.
                </div>
                
                <div class='details'>
                    <h4>📋 Booking Details</h4>
                    <div class='detail-row'>
                        <span class='detail-label'>Guest Name:</span>
                        <span class='detail-value'>{$guestName}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Email:</span>
                        <span class='detail-value'>{$email}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Phone:</span>
                        <span class='detail-value'>{$bookingDetails['telephone']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Address:</span>
                        <span class='detail-value'>{$bookingDetails['address']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Age:</span>
                        <span class='detail-value'>{$bookingDetails['age']} years old</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Number of Guests:</span>
                        <span class='detail-value'>{$bookingDetails['num_people']} person(s)</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Room Number:</span>
                        <span class='detail-value'>Room {$bookingDetails['room']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Duration:</span>
                        <span class='detail-value'>{$bookingDetails['duration']} hours</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Check-in:</span>
                        <span class='detail-value'>" . $checkInDateTime->format('F j, Y - g:i A') . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Estimated Check-out:</span>
                        <span class='detail-value'>" . $checkOutDateTime->format('F j, Y - g:i A') . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Payment Method:</span>
                        <span class='detail-value'>{$bookingDetails['payment_mode']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Amount Paid:</span>
                        <span class='detail-value'>₱{$bookingDetails['amount_paid']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Change:</span>
                        <span class='detail-value'>₱{$bookingDetails['change_amount']}</span>
                    </div>
                    <div class='detail-row' style='border-bottom: 3px solid #4CAF50; font-size: 18px;'>
                        <span class='detail-label'>💰 TOTAL PRICE:</span>
                        <span class='detail-value' style='color: #4CAF50; font-weight: bold;'>₱{$bookingDetails['total_price']}</span>
                    </div>
                </div>
                
                <div class='important'>
                    <h4>📍 Check-in Instructions:</h4>
                    <ul>
                        <li>Present your booking token: <strong>{$token}</strong></li>
                        <li>Arrive at your scheduled check-in time</li>
                        <li>Bring a valid ID for verification</li>
                        <li>Contact us if you need to modify your booking</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p><strong>Gitarra Apartelle</strong><br>
                    📧 Email: info@gitarraapartelle.com<br>
                    📞 Phone: (Your phone number)<br>
                    📍 Address: (Your address)</p>
                    
                    <p><em>Thank you for choosing Gitarra Apartelle. We look forward to hosting you!</em></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Enhanced headers for better email delivery
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Gitarra Apartelle <noreply@gitarraapartelle.com>',
        'Reply-To: info@gitarraapartelle.com',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1',
        'Importance: High'
    );
    
    $headers_string = implode("\r\n", $headers);
    
    // Attempt to send email
    $result = mail($email, $subject, $message, $headers_string);
    
    // Log email attempt (optional - for debugging)
    error_log("Email sent to $email - Result: " . ($result ? 'Success' : 'Failed'));
    
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_name = $_POST['guest_name'];
    $email = $_POST['email'];
    $telephone = $_POST['telephone'];
    $address = $_POST['address'];
    $age = (int)$_POST['age'];
    $num_people = (int)$_POST['num_people'];
    $room_number = $_POST['room_number'];
    $duration = (int)$_POST['duration'];
    $start_date = $_POST['start_date'];
    $payment_mode = $_POST['payment_mode'];
    $amount_paid = (float)$_POST['amount_paid'];
    $reference_number = $_POST['reference_number'] ?? '';
    
    // Validation
    if ($age < 18) {
        $error = "Guest must be at least 18 years old.";
    } else {
        // Get room details
        $room_query = $conn->prepare("SELECT * FROM rooms WHERE room_number = ?");
        $room_query->bind_param("s", $room_number);
        $room_query->execute();
        $room_result = $room_query->get_result();
        $room = $room_result->fetch_assoc();
        
        if (!$room) {
            $error = "Selected room is not available.";
        } else {
            // Calculate price
            switch ($duration) {
                case 3: $total_price = $room['price_3hrs']; break;
                case 6: $total_price = $room['price_6hrs']; break;
                case 12: $total_price = $room['price_12hrs']; break;
                case 24: $total_price = $room['price_24hrs']; break;
                default: $total_price = $room['price_ot']; break;
            }
            
            // Calculate end date
            $start_datetime = new DateTime($start_date);
            $end_datetime = clone $start_datetime;
            $end_datetime->add(new DateInterval('PT' . $duration . 'H'));
            $end_date = $end_datetime->format('Y-m-d H:i:s');
            
            // Check for conflicts
            $conflict_query = $conn->prepare("
                SELECT COUNT(*) as conflicts FROM bookings 
                WHERE room_number = ? 
                AND status IN ('upcoming', 'active')
                AND NOT (end_date <= ? OR start_date >= ?)
            ");
            $conflict_query->bind_param("sss", $room_number, $start_date, $end_date);
            $conflict_query->execute();
            $conflict_result = $conflict_query->get_result();
            $conflicts = $conflict_result->fetch_assoc()['conflicts'];
            
            if ($conflicts > 0) {
                $error = "Room is not available for the selected time period.";
            } else {
                // Generate booking token
                $booking_token = generateBookingToken();
                
                // Calculate change
                $change_amount = max(0, $amount_paid - $total_price);
                
                // Insert booking
                $insert_query = $conn->prepare("
                    INSERT INTO bookings (guest_name, email, telephone, address, age, num_people, 
                                        room_number, duration, start_date, end_date, total_price, 
                                        payment_mode, amount_paid, change_amount, reference_number, 
                                        booking_token, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $insert_query->bind_param("ssssiisisssddsss", 
                    $guest_name, $email, $telephone, $address, $age, $num_people,
                    $room_number, $duration, $start_date, $end_date, $total_price,
                    $payment_mode, $amount_paid, $change_amount, $reference_number, $booking_token
                );
                
                if ($insert_query->execute()) {
                    // Send confirmation email
                    $bookingDetails = [
                        'room' => "Room $room_number - {$room['room_type']}",
                        'duration' => $duration,
                        'start_date' => $start_date,
                        'total_price' => number_format($total_price, 2),
                        'telephone' => $telephone,
                        'address' => $address,
                        'age' => $age,
                        'num_people' => $num_people,
                        'payment_mode' => $payment_mode,
                        'amount_paid' => number_format($amount_paid, 2),
                        'change_amount' => number_format($change_amount, 2)
                    ];
                    
                    $emailSent = sendBookingEmail($email, $guest_name, $booking_token, $bookingDetails);
                    
                    $success = "Booking confirmed! Your booking token is: <strong>$booking_token</strong>";
                    if ($emailSent) {
                        $success .= "<br>A confirmation email has been sent to $email";
                    } else {
                        $success .= "<br>Please save your booking token as email delivery failed.";
                    }
                } else {
                    $error = "Failed to create booking. Please try again.";
                }
            }
        }
    }
}

// Get available rooms
$rooms = [];
$room_query = $conn->query("SELECT * FROM rooms WHERE status = 'available'");
while ($room = $room_query->fetch_assoc()) {
    $rooms[$room['room_number']] = $room;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Stay - Gitarra Apartelle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-color: #667eea;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-radius: 15px;
            --shadow-light: 0 5px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 10px 30px rgba(0,0,0,0.15);
            --shadow-heavy: 0 20px 40px rgba(0,0,0,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            padding: 1rem 0;
        }

        .navbar.scrolled {
            background: rgba(255,255,255,0.98) !important;
            box-shadow: var(--shadow-medium);
            padding: 0.5rem 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-link {
            font-weight: 500;
            color: var(--text-dark) !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            color: var(--accent-color) !important;
            transform: translateY(-2px);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 50%;
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%), 
                        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80') center/cover;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-subtitle {
            font-size: 1.5rem;
            font-weight: 300;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .hero-btn:hover {
            background: white;
            color: var(--accent-color);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        /* About Section */
        .about-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            position: relative;
        }

        .about-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to bottom, white, transparent);
        }

        .section-title {
            font-size: 3rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: var(--text-light);
            text-align: center;
            margin-bottom: 4rem;
        }

        .about-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-dark);
            margin-bottom: 3rem;
        }

        .feature-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-medium);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .feature-description {
            color: var(--text-light);
            line-height: 1.6;
        }

        /* Booking Section */
        .booking-section {
            padding: 100px 0;
            background: white;
            position: relative;
        }

        .booking-container {
            background: white;
            border-radius: 25px;
            box-shadow: var(--shadow-heavy);
            padding: 3rem;
            margin-top: -150px;
            position: relative;
            z-index: 10;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .booking-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .booking-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .booking-subtitle {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        /* Form Styling */
        .form-card {
            background: #f8f9fa;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .form-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }

        .form-card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            border: none;
        }

        .form-card-header h6 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .form-card-body {
            padding: 2rem;
            background: white;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }

        .form-text {
            color: var(--text-light);
            font-size: 0.875rem;
        }

        .input-group-text {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
        }

        /* Payment Section */
        .payment-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .gcash-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 1rem;
            border: 2px solid rgba(102, 126, 234, 0.1);
        }

        .gcash-info {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-light);
        }

        .gcash-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
            margin: 0.5rem 0;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-lg {
            padding: 18px 40px;
            font-size: 1.2rem;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #cce7ff 0%, #b3d9ff 100%);
            color: #004085;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.2rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .booking-container {
                margin-top: -100px;
                padding: 2rem;
            }

            .booking-title {
                font-size: 2rem;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .btn-primary {
            position: relative;
        }

        .loading .btn-primary::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Smooth Scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-gradient);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-building me-2"></i>Gitarra Apartelle
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="#hero">Home</a>
                    <a class="nav-link" href="#about">About</a>
                    <a class="nav-link" href="#booking">Book Now</a>
                    <a class="nav-link" href="#contact">Contact</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="hero" class="hero-section">
        <div class="container">
            <div class="hero-content" data-aos="fade-up" data-aos-duration="1000">
                <h1 class="hero-title">Welcome to Gitarra Apartelle</h1>
                <p class="hero-subtitle">Experience luxury, comfort, and exceptional service in the heart of the city</p>
                <a href="#booking" class="btn hero-btn">
                    <i class="fas fa-calendar-check me-2"></i>Book Your Stay Now
                </a>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto" data-aos="fade-up">
                    <h2 class="section-title">About Gitarra Apartelle</h2>
                    <p class="section-subtitle">Your home away from home</p>
                    
                    <div class="about-content">
                        <p>Welcome to Gitarra Apartelle, where comfort meets elegance in the heart of the city. Our establishment has been providing exceptional hospitality services for over a decade, creating memorable experiences for travelers from around the world.</p>
                        
                        <p>Located in a prime area with easy access to major attractions, shopping centers, and business districts, Gitarra Apartelle offers the perfect blend of convenience and tranquility. Our modern facilities and personalized service ensure that every guest feels valued and comfortable throughout their stay.</p>
                        
                        <p>Whether you're here for business or leisure, our dedicated team is committed to making your stay exceptional. From our well-appointed rooms to our attentive service, every detail is designed with your comfort in mind.</p>
                    </div>
                </div>
            </div>
            
            <div class="row g-4 mt-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <h4 class="feature-title">Free High-Speed WiFi</h4>
                        <p class="feature-description">Stay connected with complimentary high-speed internet access throughout the property.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <h4 class="feature-title">Free Parking</h4>
                        <p class="feature-description">Secure and convenient parking space available for all our guests at no additional cost.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-concierge-bell"></i>
                        </div>
                        <h4 class="feature-title">24/7 Service</h4>
                        <p class="feature-description">Round-the-clock reception and assistance to ensure your needs are met anytime.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tv"></i>
                        </div>
                        <h4 class="feature-title">Modern Amenities</h4>
                        <p class="feature-description">Each room features modern amenities including flat-screen TV, air conditioning, and more.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4 class="feature-title">Prime Location</h4>
                        <p class="feature-description">Strategically located near major attractions, shopping centers, and business districts.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="feature-title">Safe & Secure</h4>
                        <p class="feature-description">Your safety is our priority with 24/7 security and secure access systems.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Section -->
    <section id="booking" class="booking-section">
        <div class="container">
            <div class="booking-container" data-aos="fade-up" data-aos-duration="800">
                <div class="booking-header">
                    <h2 class="booking-title">Reserve Your Room</h2>
                    <p class="booking-subtitle">Complete the form below to secure your perfect stay with us</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" data-aos="fade-in">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success" data-aos="fade-in">
                        <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="bookingForm" onsubmit="return validateForm();">
                    <div class="row g-4">
                        <!-- Guest Information -->
                        <div class="col-lg-6">
                            <div class="form-card h-100" data-aos="fade-right" data-aos-delay="100">
                                <div class="form-card-header">
                                    <h6><i class="fas fa-user me-2"></i>Guest Information</h6>
                                </div>
                                <div class="form-card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="guest_name" class="form-control" required placeholder="Enter your full name">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" name="email" class="form-control" required placeholder="your.email@example.com">
                                        <div class="form-text">We'll send your booking confirmation to this email</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="text" name="telephone" class="form-control" required pattern="\d{10,11}" placeholder="09123456789">
                                        <div class="form-text">Enter a valid 10-11 digit phone number</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Complete Address *</label>
                                        <input type="text" name="address" class="form-control" required placeholder="Street, City, Province">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Age *</label>
                                            <input type="number" name="age" class="form-control" required min="18" placeholder="18">
                                            <div class="form-text">Must be 18 or older</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Number of Guests *</label>
                                            <input type="number" name="num_people" class="form-control" required min="1" placeholder="1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Booking Details -->
                        <div class="col-lg-6">
                            <div class="form-card h-100" data-aos="fade-left" data-aos-delay="200">
                                <div class="form-card-header">
                                    <h6><i class="fas fa-calendar-alt me-2"></i>Booking Details</h6>
                                </div>
                                <div class="form-card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Select Room *</label>
                                        <select name="room_number" class="form-select" onchange="updatePrice()" required>
                                            <option value="">Choose your preferred room</option>
                                            <?php foreach ($rooms as $room): ?>
                                                <option value="<?= $room['room_number'] ?>">
                                                    Room <?= $room['room_number'] ?> - <?= $room['room_type'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Stay Duration *</label>
                                        <select name="duration" class="form-select" onchange="updatePrice()" required>
                                            <option value="3">3 Hours</option>
                                            <option value="6">6 Hours</option>
                                            <option value="12">12 Hours (Half Day)</option>
                                            <option value="24">24 Hours (Full Day)</option>
                                            <option value="48">48 Hours (2 Days)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Check-in Date & Time *</label>
                                        <input type="datetime-local" name="start_date" class="form-control" onchange="updateCheckout()" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estimated Check-out</label>
                                        <input type="text" id="checkout_datetime" class="form-control" readonly placeholder="Select check-in time first">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Total Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="text" id="total_price" class="form-control" readonly placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Information -->
                        <div class="col-12">
                            <div class="payment-card" data-aos="fade-up" data-aos-delay="300">
                                <div class="form-card-header">
                                    <h6><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
                                </div>
                                <div class="form-card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Payment Method *</label>
                                            <select name="payment_mode" class="form-select" onchange="togglePaymentFields(this.value)" required>
                                                <option value="">Select payment method</option>
                                                <option value="Cash">💵 Cash Payment</option>
                                                <option value="GCash">📱 GCash</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Amount to Pay *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" name="amount_paid" class="form-control" step="0.01" oninput="calculateChange()" required placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3" id="change_field" style="display: none;">
                                            <label class="form-label">Change</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="text" id="change_display" class="form-control" readonly placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- GCash Section -->
                                    <div id="gcash_section" class="gcash-section" style="display: none;">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <div class="alert alert-info mb-3">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>GCash Payment Instructions:</strong><br>
                                                    1. Send your payment to the GCash number provided<br>
                                                    2. Take a screenshot of the transaction<br>
                                                    3. Enter the 13-digit reference number below
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">GCash Reference Number *</label>
                                                    <input type="text" name="reference_number" class="form-control" placeholder="Enter 13-digit reference number" maxlength="13">
                                                    <div class="form-text">This can be found in your GCash transaction receipt</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="gcash-info">
                                                    <div class="mb-2">
                                                        <i class="fab fa-google-pay fa-2x text-primary mb-2"></i>
                                                    </div>
                                                    <p class="mb-1"><strong>GCash Number:</strong></p>
                                                    <p class="gcash-number">09123456789</p>
                                                    <p class="mb-1"><strong>Account Name:</strong></p>
                                                    <p class="text-muted mb-0">Gitarra Apartelle</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="room_data" value='<?= json_encode($rooms) ?>'>
                    
                    <div class="d-grid gap-2 mt-4" data-aos="fade-up" data-aos-delay="400">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar-check me-2"></i>Confirm Booking & Reserve Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
                    <h2 class="section-title mb-4">Get in Touch</h2>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <h5>Call Us</h5>
                                <p class="text-muted">+63 912 345 6789</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h5>Email Us</h5>
                                <p class="text-muted">info@gitarraapartelle.com</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h5>Visit Us</h5>
                                <p class="text-muted">123 Main Street, City Center</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4" style="background: var(--text-dark); color: white;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2024 Gitarra Apartelle. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Made with <i class="fas fa-heart text-danger"></i> for our guests</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Form validation
        function validateForm() {
            const form = document.getElementById('bookingForm');
            form.classList.add('loading');
            
            const age = parseInt(document.querySelector('input[name="age"]').value);
            if (age < 18) {
                alert("Guest must be at least 18 years old.");
                form.classList.remove('loading');
                return false;
            }

            const paymentMode = document.querySelector('select[name="payment_mode"]').value;
            const amountPaid = parseFloat(document.querySelector('input[name="amount_paid"]').value) || 0;
            const totalPrice = parseFloat(document.getElementById('total_price').value) || 0;

            if (amountPaid <= 0) {
                alert("Please enter a valid payment amount.");
                form.classList.remove('loading');
                return false;
            }

            if (paymentMode === "Cash" && amountPaid < totalPrice) {
                alert("For cash payments, amount paid must be greater than or equal to the total price.");
                form.classList.remove('loading');
                return false;
            }

            if (paymentMode === "GCash") {
                const referenceNumber = document.querySelector('input[name="reference_number"]').value.trim();
                if (referenceNumber === "" || referenceNumber.length !== 13) {
                    alert("Please enter a valid 13-digit GCash reference number.");
                    form.classList.remove('loading');
                    return false;
                }
            }

            return true;
        }

        // Payment method toggle
        function togglePaymentFields(mode) {
            const gcashSection = document.getElementById("gcash_section");
            const changeField = document.getElementById("change_field");
            const refField = document.querySelector('input[name="reference_number"]');
            
            if (mode === "GCash") {
                gcashSection.style.display = "block";
                changeField.style.display = "none";
                refField.required = true;
            } else if (mode === "Cash") {
                gcashSection.style.display = "none";
                changeField.style.display = "block";
                refField.required = false;
                calculateChange();
            } else {
                gcashSection.style.display = "none";
                changeField.style.display = "none";
                refField.required = false;
            }
        }

        // Price calculation
        function updatePrice() {
            const roomSelect = document.querySelector('select[name="room_number"]');
            const durationSelect = document.querySelector('select[name="duration"]');
            const totalPriceField = document.getElementById('total_price');
            const amountPaidField = document.querySelector('input[name="amount_paid"]');
            
            if (roomSelect.value && durationSelect.value) {
                const rooms = JSON.parse(document.getElementById('room_data').value);
                const selectedRoom = rooms[roomSelect.value];
                const duration = durationSelect.value;
                
                let price = 0;
                switch (duration) {
                    case '3': price = selectedRoom.price_3hrs; break;
                    case '6': price = selectedRoom.price_6hrs; break;
                    case '12': price = selectedRoom.price_12hrs; break;
                    case '24': price = selectedRoom.price_24hrs; break;
                    default: price = selectedRoom.price_ot; break;
                }
                
                totalPriceField.value = parseFloat(price).toFixed(2);
                amountPaidField.value = parseFloat(price).toFixed(2);
                calculateChange();
            }
        }

        // Calculate change for cash payments
        function calculateChange() {
            const amountPaid = parseFloat(document.querySelector('input[name="amount_paid"]').value) || 0;
            const totalPrice = parseFloat(document.getElementById('total_price').value) || 0;
            const changeDisplay = document.getElementById('change_display');
            
            if (amountPaid >= totalPrice) {
                const change = amountPaid - totalPrice;
                changeDisplay.value = change.toFixed(2);
            } else {
                changeDisplay.value = '0.00';
            }
        }

        // Update checkout time
        function updateCheckout() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const duration = document.querySelector('select[name="duration"]').value;
            const checkoutField = document.getElementById('checkout_datetime');
            
            if (startDate && duration) {
                const start = new Date(startDate);
                const end = new Date(start.getTime() + (parseInt(duration) * 60 * 60 * 1000));
                
                const options = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                
                checkoutField.value = end.toLocaleDateString('en-US', options);
            }
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Form field animations
        document.querySelectorAll('.form-control, .form-select').forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.querySelector('input[name="start_date"]');
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            dateInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
        });
    </script>
</body>
</html>