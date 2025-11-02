<?php
session_start();
require_once 'database.php';
require 'vendor/autoload.php'; // <-- Make sure PHPMailer is installed via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Generate a unique booking token
function generateBookingToken() {
    return 'BK' . date('Ymd') . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
}

// Function to send booking confirmation email using PHPMailer
function sendBookingEmail($email, $guestName, $token, $bookingDetails) {
    $mail = new PHPMailer(true);

    try {
        // === SMTP SETTINGS ===
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'gitarraapartelle@gmail.com';
        $mail->Password = 'pngssmeypubvvhvg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // === SENDER & RECIPIENT ===
        $mail->setFrom('gitarraapartelle@gmail.com', 'Gitarra Apartelle');
        $mail->addAddress($email, $guestName);
        $mail->addReplyTo('gitarraapartelle@gmail.com', 'Gitarra Apartelle Info');

        // === EMAIL CONTENT ===
        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmation - Gitarra Apartelle (Token: $token)";

        // Compute check-out time
        $checkInDateTime = new DateTime($bookingDetails['start_date']);
        $checkOutDateTime = clone $checkInDateTime;
        $checkOutDateTime->add(new DateInterval('PT' . $bookingDetails['duration'] . 'H'));

        // === MODERN STYLED EMAIL BODY ===
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 40px 0;">
          <div style="max-width: 600px; margin: auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">

            <!-- HEADER -->
            <div style="background-color: #7a0a20; padding: 30px; text-align: center;">
              <h1 style="color: #ffffff; margin: 0; font-size: 26px;">Gitarra Apartelle</h1>
              <p style="color: #f1c6c6; margin: 5px 0 0; font-size: 15px;">Booking Information</p>
            </div>

            <!-- BODY -->
            <div style="padding: 30px;">
              <p style="font-size: 15px; color: #333;">Dear <strong>'.$guestName.'</strong>,</p>
              <p style="font-size: 15px; color: #333;">Thank you for booking with us! Below are your booking details:</p>

              <div style="background-color: #fff; border: 1px solid #eee; border-radius: 8px; padding: 20px; margin-top: 10px;">
                <p style="color: #888; font-size: 13px; margin-bottom: 5px;">ROOM</p>
                <p style="font-weight: 600; color: #111; font-size: 16px; margin-top: 0;">'.$bookingDetails['room'].'</p>
                <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">

                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                  <tr>
                    <td style="color: #777;">CHECK-IN</td>
                    <td style="color: #777;">CHECK-OUT</td>
                  </tr>
                  <tr>
                    <td style="font-weight: bold; color: #222;">'.$checkInDateTime->format('Y-m-d').'</td>
                    <td style="font-weight: bold; color: #222;">'.$checkOutDateTime->format('Y-m-d').'</td>
                  </tr>
                </table>

                <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">

                <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                  <tr>
                    <td style="color: #777;">TOTAL PRICE</td>
                    <td style="text-align: right; font-weight: 600;">₱'.$bookingDetails['total_price'].'</td>
                  </tr>
                  <tr>
                    <td style="color: #777;">AMOUNT PAID</td>
                    <td style="text-align: right; font-weight: 600; color: #a10f20;">₱'.$bookingDetails['amount_paid'].'</td>
                  </tr>
                  <tr>
                    <td style="color: #777;">CHANGE</td>
                    <td style="text-align: right; font-weight: 600;">₱'.$bookingDetails['change_amount'].'</td>
                  </tr>
                  <tr>
                    <td style="color: #777;">PAYMENT MODE</td>
                    <td style="text-align: right; font-weight: 600;">'.$bookingDetails['payment_mode'].'</td>
                  </tr>
                </table>
              </div>

              <div style="margin: 25px 0; padding: 15px; border: 1px solid #a10f20; border-left: 5px solid #a10f20; background-color: #fef2f2; border-radius: 5px;">
                <p style="color: #a10f20; margin: 0; text-align: center; font-weight: 600;">
                  Please present this booking token upon check-in.<br>
                  <span style="display: inline-block; margin-top: 8px; background: #7a0a20; color: white; padding: 6px 12px; border-radius: 4px; font-size: 13px;">
                    TOKEN: '.$token.'
                  </span>
                </p>
              </div>

              <p style="text-align: center; color: #444; font-size: 14px;">We look forward to your stay at Gitarra Apartelle!</p>
            </div>

            <!-- FOOTER -->
            <div style="background: #f9f9f9; text-align: center; padding: 20px; border-top: 1px solid #eee;">
              <p style="margin: 0 0 8px;">
                <a href="mailto:gitarraapartelle@gmail.com" style="color: #7a0a20; text-decoration: none; font-size: 14px;">gitarraapartelle@gmail.com</a> |
                <span style="color: #555; font-size: 14px;">+63 912 345 6789</span>
              </p>
              <p style="font-size: 12px; color: #999;">© 2025 Gitarra Apartelle. All rights reserved.</p>
            </div>
          </div>
        </div>
        ';

        $mail->AltBody = 'Booking Confirmation for '.$guestName.' at Gitarra Apartelle. Token: '.$token;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// === FORM PROCESSING ===
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
    
    // Payment will be collected at check-in
    $payment_mode = 'pending';
    $amount_paid = 0.00;
    $reference_number = '';

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
            // Compute total price
            switch ($duration) {
                case 3: $total_price = $room['price_3hrs']; break;
                case 6: $total_price = $room['price_6hrs']; break;
                case 12: $total_price = $room['price_12hrs']; break;
                case 24: $total_price = $room['price_24hrs']; break;
                case 48: $total_price = $room['price_24hrs'] * 2; break;
                default: $total_price = $room['price_ot']; break;
            }

            // Compute dates
            $start_datetime = new DateTime($start_date);
            $end_datetime = clone $start_datetime;
            $end_datetime->add(new DateInterval('PT' . $duration . 'H'));
            $end_date = $end_datetime->format('Y-m-d H:i:s');

            // Check room conflicts
            $conflict_query = $conn->prepare("
                SELECT COUNT(*) as conflicts FROM bookings 
                WHERE room_number = ? 
                AND status IN ('upcoming', 'active', 'pending')
                AND NOT (end_date <= ? OR start_date >= ?)
            ");
            $conflict_query->bind_param("sss", $room_number, $start_date, $end_date);
            $conflict_query->execute();
            $conflicts = $conflict_query->get_result()->fetch_assoc()['conflicts'];

            if ($conflicts > 0) {
                $error = "Room is not available for the selected time period.";
            } else {
                $booking_token = generateBookingToken();
                $change_amount = 0.00;

                // Insert booking with pending payment
                $insert_query = $conn->prepare("
                    INSERT INTO bookings (
                        guest_name, email, telephone, address, age, num_people,
                        room_number, duration, start_date, end_date, total_price,
                        payment_mode, amount_paid, change_amount, reference_number,
                        booking_token, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");

                $insert_query->bind_param(
                    "ssssiisisssddsss",
                    $guest_name, $email, $telephone, $address, $age, $num_people,
                    $room_number, $duration, $start_date, $end_date, $total_price,
                    $payment_mode, $amount_paid, $change_amount, $reference_number, $booking_token
                );

                if ($insert_query->execute()) {
                    $bookingDetails = [
                        'room' => "Room $room_number - {$room['room_type']}",
                        'duration' => $duration,
                        'start_date' => $start_date,
                        'total_price' => number_format($total_price, 2),
                        'telephone' => $telephone,
                        'address' => $address,
                        'age' => $age,
                        'num_people' => $num_people,
                        'payment_mode' => 'Pay at Check-in',
                        'amount_paid' => '0.00',
                        'change_amount' => '0.00'
                    ];

                    $emailSent = sendBookingEmail($email, $guest_name, $booking_token, $bookingDetails);
                    $success = "Booking confirmed! Your booking token is <strong>$booking_token</strong>.";

                    if ($emailSent) {
                        $success .= "<br>A confirmation email has been sent to <strong>$email</strong>.";
                    } else {
                        $success .= "<br>⚠️ Email sending failed. Please save your booking token.";
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
$query = "SELECT * FROM rooms WHERE status != 'maintenance' ORDER BY room_number";
$result = $conn->query($query);
while ($room = $result->fetch_assoc()) {
    $rooms[$room['room_number']] = $room;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Stay - Gitarra Apartelle</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #8b1d2d 0%, #4a0e1a 100%);
            --secondary-gradient: linear-gradient(135deg, #c72c41 0%, #8b1d2d 100%);
            --accent-color: #8b1d2d;
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
            background: linear-gradient(135deg, rgba(199, 44, 65, 0.9) 0%, rgba(139, 29, 45, 0.9) 100%), 
                       url('Image/home.jpg') center/cover no-repeat;
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

.custom-footer {
  background-color: #121212;
  color: #d9d9d9;
  padding: 60px 0 25px;
  font-family: 'Poppins', sans-serif;
}

.footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 50px;
}

.footer-column h5,
.footer-column h6 {
  color: #fff;
  font-weight: 600;
  margin-bottom: 12px;
}

.footer-brand {
  font-size: 20px;
  margin-bottom: 15px;
}

.footer-nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
}

.footer-nav a {
  color: #d9d9d9;
  text-decoration: none;
  font-size: 15px;
  transition: color 0.3s ease;
}

.footer-nav a:hover {
  color: #c72c41;
}

.contact-section {
  margin-bottom: 15px;
}

.contact-section h6 {
  color: #fff;
  font-size: 14px;
  margin-bottom: 5px;
}

.contact-section p,
.contact-section a {
  color: #d9d9d9;
  font-size: 14px;
  text-decoration: none;
  margin: 0;
}

.contact-section a:hover {
  color: #c72c41;
}

.map-container {
  width: 100%;
  overflow: hidden;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.footer-divider {
  border: none;
  border-top: 1px solid #333;
  margin: 40px 0 25px;
}

.footer-bottom {
  text-align: left;
}

.footer-bottom p {
  font-size: 13px;
  color: #aaa;
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .footer-content {
    grid-template-columns: 1fr;
    gap: 40px;
  }

  .map-container iframe {
    height: 200px;
  }
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

        .progress-animate {
        width: 0%;
        animation: progressGrow 2s infinite;
        }
        @keyframes progressGrow {
        0% { width: 0%; }
        50% { width: 70%; }
        100% { width: 100%; }
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
                Gitarra Apartelle
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
                <p class="hero-subtitle">Discover a unique guitar-inspired HOTEL in Brgy Bunggo, Calamba City. Laguna.
This charming place combines a love of music with a clean and secure atmosphere,
perfect for relaxation.</p>
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
                                    <input type="email" name="email" id="email_input" class="form-control" required placeholder="your.email@example.com">
                                    <div class="form-text">We'll send your booking confirmation to this email</div>
                                    <div class="invalid-feedback">Please enter a valid email address (e.g., name@example.com).</div>
                                </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="text" name="telephone" id="telephone_input" class="form-control" required pattern="\d{4}-\d{3}-\d{4}" maxlength="13" placeholder="09XX-XXX-XXXX">
                                        <div class="invalid-feedback">Phone number must be in format 09XX-XXX-XXXX (11 digits).</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Complete Address *</label>
                                        <input type="text" name="address" class="form-control" required placeholder="Street, City, Province">
                                    </div>
                                    <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Age *</label>
                                        <input type="number" name="age" id="age_input" class="form-control" required min="18" placeholder="Must be 18 or older">
                                        <div class="invalid-feedback">Guest must be at least 18 years old.</div>
                                    </div>
                                        <!-- <div class="col-md-6 mb-3">
                                            <label class="form-label">Number of Guests *</label>
                                            <input type="number" name="num_people" class="form-control" required min="1" placeholder="1">
                                        </div> -->
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
    <select name="room_number" id="room_select" class="form-select" onchange="updatePrice(); checkRoomAvailability();" required>
        <option value="">Choose your preferred room</option>
        <?php foreach ($rooms as $room): ?>
            <option value="<?= $room['room_number'] ?>" data-status="<?= $room['status'] ?>">
                Room <?= $room['room_number'] ?> - <?= $room['room_type'] ?>
                <?= $room['status'] == 'booked' ? '(Currently Booked)' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Stay Duration *</label>
    <select name="duration" id="duration_select" class="form-select" onchange="updatePrice(); checkRoomAvailability();" required>
        <option value="3">3 Hours</option>
        <option value="6">6 Hours</option>
        <option value="12">12 Hours (Half Day)</option>
        <option value="24">24 Hours (Full Day)</option>
        <option value="48">48 Hours (2 Days)</option>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Check-in Date & Time *</label>
    <input type="datetime-local" name="start_date" id="checkin_input" class="form-control" onchange="updateCheckout(); checkRoomAvailability();" required>
</div>

<div class="mb-3">
    <label class="form-label">Estimated Check-out</label>
    <input type="text" id="checkout_datetime" class="form-control" readonly placeholder="Select check-in time first">
</div>

<div id="availability-message" class="mb-3"></div>

                                    <!-- <div class="mb-3">
                                        <label class="form-label">Total Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="text" id="total_price" class="form-control" readonly placeholder="0.00">
                                        </div>
                                    </div> -->
                                </div>
                            </div>
                        </div>
                        
<!-- Booking Summary -->
<div class="col-12">
    <div class="payment-card" data-aos="fade-up" data-aos-delay="300">
        <div class="form-card-header">
            <h6><i class="fas fa-info-circle me-2"></i>Booking Summary</h6>
        </div>
        <div class="form-card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Total Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" id="total_price_display" class="form-control" readonly placeholder="0.00">
                    </div>
                    <input type="hidden" name="total_price" id="total_price_hidden">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Stay Duration</label>
                    <input type="text" id="duration_display" class="form-control" readonly placeholder="Not selected">
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Payment Instructions:</strong><br>
                Payment will be collected upon check-in at the front desk. You can pay via Cash or GCash.
            </div>
            
            <div class="booking-details mt-3">
                <h6 class="mb-3">Booking Details Summary:</h6>
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td><strong>Guest Name:</strong></td>
                            <td id="summary_guest">-</td>
                        </tr>
                        <tr>
                            <td><strong>Contact:</strong></td>
                            <td id="summary_contact">-</td>
                        </tr>
                        <tr>
                            <td><strong>Room:</strong></td>
                            <td id="summary_room">-</td>
                        </tr>
                        <tr>
                            <td><strong>Check-in:</strong></td>
                            <td id="summary_checkin">-</td>
                        </tr>
                        <tr>
                            <td><strong>Check-out:</strong></td>
                            <td id="summary_checkout">-</td>
                        </tr>
                        <tr>
                            <td><strong>Total Amount:</strong></td>
                            <td id="summary_total"><strong>₱0.00</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="room_data" value='<?= json_encode($rooms) ?>'>

<!-- Hidden payment fields with default values -->
<input type="hidden" name="payment_mode" value="pending">
<input type="hidden" name="amount_paid" value="0">
<input type="hidden" name="reference_number" value="">
                    
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
                                <p class="text-muted">gitarraapartelle@gmail.com</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h5>Visit Us</h5>
                                <p class="text-muted">
Purok 6 Bunggo road barangay Bunggo, Calamba, Philippines, 4027</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- Footer -->
<footer class="custom-footer">
  <div class="container">
    <div class="footer-content">
      <!-- Column 1: Brand & Navigation -->
      <div class="footer-column">
        <h5 class="footer-brand">Gitarra Apartelle</h5>
        <ul class="footer-nav list-unstyled">
          <li><a href="#hero">Home</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="#booking">Book Now</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>

      <!-- Column 2: Contact Info -->
      <div class="footer-column">
        <div class="contact-section">
          <h6>Call Us</h6>
          <p>+63 912 345 6789</p>
        </div>
        <div class="contact-section">
          <h6>Email Us</h6>
          <p><a href="mailto:gitarraapartelle@gmail.com">gitarraapartelle@gmail.com</a></p>
        </div>
        <div class="contact-section">
          <h6>Visit Us</h6>
          <p>
Purok 6 Bunggo road barangay Bunggo, Calamba, Philippines, 4027</p>
        </div>
      </div>

      <!-- Column 3: Map -->
      <div class="footer-column">
        <h6>Our Location</h6>
        <div class="map-container">
          <iframe 
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.648972775003!2d121.06453207467415!3d14.156723186278718!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd7b43ac989a09%3A0x6ab86b83dce9dcc6!2sGITARRA%20Apartelle%20(HOTEL)!5e0!3m2!1sen!2sph!4v1761133744027!5m2!1sen!2sph"
            width="100%"
            height="180"
            style="border:0; border-radius:8px;"
            allowfullscreen=""
            loading="lazy">
          </iframe>
        </div>
      </div>
    </div>

    <hr class="footer-divider">

    <div class="footer-bottom">
      <p>&copy; 2024 Gitarra Apartelle. All rights reserved.</p>
    </div>
  </div>
</footer>


    <!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="mb-2">
        <i class="fas fa-envelope fa-3x text-primary"></i>
      </div>
      <div class="mb-3">
        <div class="spinner-border text-primary" role="status"></div>
      </div>
      <h5>Sending Confirmation Email</h5>
      <p class="text-muted">
        Please wait while we send your booking confirmation to 
        <span id="loadingEmail"></span>
      </p>
      <div class="progress mt-3" style="height:5px;">
        <div class="progress-bar bg-primary progress-animate" role="progressbar"></div>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="checkmark-container mb-3">
        <div class="checkmark-circle">
          <i class="fas fa-check fa-2x text-success"></i>
        </div>
      </div>
      <h5>Email Sent Successfully!</h5>
      <p class="text-muted">
        Your booking confirmation has been sent to 
        <span id="successEmail"></span>
      </p>
      <p class="text-muted">
        Please check your inbox for booking details and token.
      </p>
      <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">Continue</button>
    </div>
  </div>
</div>


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
            
            const ageInput = document.querySelector('input[name="age"]');
            const age = parseInt(ageInput.value);

            if (age < 18) {
                ageInput.classList.add('is-invalid');
                
                // Show toast notification
                showToast("Guest must be at least 18 years old.", 'danger', 4000);
                
                // Scroll to the age input
                ageInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Focus on the input
                ageInput.focus();
                
                form.classList.remove('loading');
                return false;
            }

            // Remove invalid class if age is valid
            ageInput.classList.remove('is-invalid');

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


        // loading & success modal
        document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('#bookingForm'); // your booking form ID
  const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
  const successModal = new bootstrap.Modal(document.getElementById('successModal'));
  const loadingEmail = document.getElementById('loadingEmail');
  const successEmail = document.getElementById('successEmail');

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(form);
    const email = formData.get('email');
    loadingEmail.textContent = email;
    successEmail.textContent = email;

    // Show loading modal
    loadingModal.show();

    fetch(window.location.href, { 
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(response => {
      // Hide loading and show success
      loadingModal.hide();
      successModal.show();
      
    // Refresh the page after success modal closes (or after few seconds)
    setTimeout(() => {
        location.reload(); 
    }, 3000); // refresh after 3 seconds

    })
    .catch(err => {
      loadingModal.hide();
      alert('An error occurred while processing your booking.');
      console.error(err);
    });
  });
});


// Store booked schedules for each room
const roomSchedules = <?php
    $schedule_query = "SELECT room_number, check_in_date, check_out_date 
                       FROM checkins 
                       WHERE status IN ('scheduled', 'checked_in')";
    $schedule_result = $conn->query($schedule_query);
    $schedules = [];
    while ($schedule = $schedule_result->fetch_assoc()) {
        $room_num = $schedule['room_number'];
        if (!isset($schedules[$room_num])) {
            $schedules[$room_num] = [];
        }
        $schedules[$room_num][] = [
            'check_in' => $schedule['check_in_date'],
            'check_out' => $schedule['check_out_date']
        ];
    }
    echo json_encode($schedules);
?>;

function checkRoomAvailability() {
    const roomSelect = document.getElementById('room_select');
    const checkinInput = document.getElementById('checkin_input');
    const durationSelect = document.getElementById('duration_select');
    const messageDiv = document.getElementById('availability-message');
    
    const roomNumber = roomSelect.value;
    const checkinTime = checkinInput.value;
    const duration = parseInt(durationSelect.value);
    
    if (!roomNumber || !checkinTime || !duration) {
        messageDiv.innerHTML = '';
        return true;
    }
    
    // Calculate checkout time
    const selectedCheckin = new Date(checkinTime);
    const selectedCheckout = new Date(selectedCheckin.getTime() + duration * 60 * 60 * 1000);
    
    const schedules = roomSchedules[roomNumber] || [];
    
    // Check for conflicts
    let isAvailable = true;
    let conflictSchedule = null;
    
    for (let schedule of schedules) {
        const bookedCheckin = new Date(schedule.check_in);
        const bookedCheckout = new Date(schedule.check_out);
        
        // Check for overlap: (start1 < end2) AND (end1 > start2)
        if (selectedCheckin < bookedCheckout && selectedCheckout > bookedCheckin) {
            isAvailable = false;
            conflictSchedule = schedule;
            break;
        }
    }
    
    if (isAvailable) {
        messageDiv.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Room is available for selected time period</div>';
        checkinInput.setCustomValidity('');
        return true;
    } else {
        const bookedCheckin = new Date(conflictSchedule.check_in);
        const bookedCheckout = new Date(conflictSchedule.check_out);
        
        const formattedCheckin = bookedCheckin.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        const formattedCheckout = bookedCheckout.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        messageDiv.innerHTML = `<div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <strong>Room Not Available</strong><br>
            This room is booked from <strong>${formattedCheckin}</strong> to <strong>${formattedCheckout}</strong>.<br>
            Please select a different time or room.
        </div>`;
        
        checkinInput.setCustomValidity('This time slot conflicts with an existing booking');
        return false;
    }
}

// Add this formatDateTime function (to match your checkout format)
function formatDateTime(date) {
    if (!(date instanceof Date) || isNaN(date)) {
        return '-';
    }
    
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    let hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const hoursStr = String(hours).padStart(2, '0');
    
    return `${day}/${month}/${year} ${hoursStr}:${minutes} ${ampm}`;
}

// Update your existing updateCheckout function
function updateCheckout() {
    const checkinInput = document.getElementById('checkin_input');
    const durationSelect = document.getElementById('duration_select');
    const checkoutDisplay = document.getElementById('checkout_datetime');
    
    const checkinTime = checkinInput.value;
    const duration = parseInt(durationSelect.value);
    
    if (checkinTime && duration) {
        const checkin = new Date(checkinInput.value);
        const checkout = new Date(checkin.getTime() + duration * 60 * 60 * 1000);
        
        // Format to match: 22/10/2025 09:32 pm
        const day = String(checkout.getDate()).padStart(2, '0');
        const month = String(checkout.getMonth() + 1).padStart(2, '0');
        const year = checkout.getFullYear();
        
        let hours = checkout.getHours();
        const minutes = String(checkout.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 should be 12
        const hoursStr = String(hours).padStart(2, '0');
        
        const formatted = `${day}/${month}/${year} ${hoursStr}:${minutes} ${ampm}`;
        
        checkoutDisplay.value = formatted;
        
        // Check availability after updating checkout
        checkRoomAvailability();
    } else {
        checkoutDisplay.value = '';
    }
    
    // ADD THIS LINE - Update the summary after setting checkout
    updateBookingSummary();
}

// Update booking summary display
function updateBookingSummary() {
    const guestName = document.querySelector('input[name="guest_name"]')?.value || '-';
    const telephone = document.querySelector('input[name="telephone"]')?.value || '-';
    const roomSelect = document.getElementById('room_select');
    const checkinInput = document.getElementById('checkin_input');
    const checkoutDisplay = document.getElementById('checkout_datetime');
    const totalPrice = document.getElementById('total_price_display')?.value || '0.00';
    
    // Update summary fields
    document.getElementById('summary_guest').textContent = guestName;
    document.getElementById('summary_contact').textContent = telephone;
    
    if (roomSelect && roomSelect.value) {
        const roomText = roomSelect.options[roomSelect.selectedIndex].text;
        document.getElementById('summary_room').textContent = roomText;
    } else {
        document.getElementById('summary_room').textContent = '-';
    }
    
    // Check-in with formatted date
    if (checkinInput && checkinInput.value) {
        const checkin = new Date(checkinInput.value);
        document.getElementById('summary_checkin').textContent = formatDateTime(checkin);
    } else {
        document.getElementById('summary_checkin').textContent = '-';
    }
    
    // Check-out - just use the already formatted value
    if (checkoutDisplay && checkoutDisplay.value && checkoutDisplay.value.trim() !== '') {
        document.getElementById('summary_checkout').textContent = checkoutDisplay.value;
    } else {
        document.getElementById('summary_checkout').textContent = '-';
    }
    
    document.getElementById('summary_total').innerHTML = '<strong>₱' + totalPrice + '</strong>';
}

// Add event listeners to update summary when fields change
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!checkRoomAvailability()) {
                e.preventDefault();
                alert('Please resolve the room availability conflict before booking.');
                return false;
            }
        });
    }

// Real-time age validation - ADD THIS
    const ageInput = document.querySelector('input[name="age"]');
    if (ageInput) {
        ageInput.addEventListener('input', function() {
            const age = parseInt(this.value);
            
            if (this.value && age < 18) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        ageInput.addEventListener('blur', function() {
            const age = parseInt(this.value);
            
            if (this.value && age < 18) {
                this.classList.add('is-invalid');
            }
        });
    }

        // Real-time phone number validation with auto-formatting - REPLACE THIS SECTION
    const telephoneInput = document.querySelector('input[name="telephone"]');
    if (telephoneInput) {
        // Format phone number as user types
        telephoneInput.addEventListener('input', function(e) {
            // Remove all non-digits
            let value = this.value.replace(/\D/g, '');
            
            // Limit to 11 digits
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            
            // Format: 09XX-XXX-XXXX
            let formatted = '';
            if (value.length > 0) {
                formatted = value.slice(0, 4);
                if (value.length > 4) {
                    formatted += '-' + value.slice(4, 7);
                }
                if (value.length > 7) {
                    formatted += '-' + value.slice(7, 11);
                }
            }
            
            this.value = formatted;
            
            // Validate: must be exactly 11 digits (13 chars with dashes)
            const digitsOnly = formatted.replace(/\D/g, '');
            if (digitsOnly.length > 0 && digitsOnly.length !== 11) {
                this.classList.add('is-invalid');
            } else if (digitsOnly.length === 11 && !digitsOnly.startsWith('09')) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        // Validate on blur
        telephoneInput.addEventListener('blur', function() {
            const digitsOnly = this.value.replace(/\D/g, '');
            if (digitsOnly.length > 0 && (digitsOnly.length !== 11 || !digitsOnly.startsWith('09'))) {
                this.classList.add('is-invalid');
            }
        });
        
        // Prevent non-numeric input (except backspace, delete, etc.)
        telephoneInput.addEventListener('keypress', function(e) {
            if (e.key && !/^\d$/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
        // Real-time email validation - ADD THIS
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        // Email validation regex pattern
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        emailInput.addEventListener('input', function() {
            const email = this.value.trim();
            
            if (email.length > 0 && !emailPattern.test(email)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            
            if (email.length > 0 && !emailPattern.test(email)) {
                this.classList.add('is-invalid');
            }
        });
    }
    
    // Add listeners for real-time summary updates
    const guestNameInput = document.querySelector('input[name="guest_name"]');
    const roomSelect = document.getElementById('room_select');
    const checkinInput = document.getElementById('checkin_input');
    const durationSelect = document.getElementById('duration_select');
    
    if (guestNameInput) guestNameInput.addEventListener('input', updateBookingSummary);
    if (telephoneInput) telephoneInput.addEventListener('input', updateBookingSummary);
    if (roomSelect) roomSelect.addEventListener('change', updateBookingSummary);
    if (durationSelect) durationSelect.addEventListener('change', updateBookingSummary);
    // Note: checkinInput already calls updateCheckout() which now calls updateBookingSummary()
});

// Update the existing updatePrice function to also update summary
function updatePrice() {
    const roomSelect = document.getElementById('room_select');
    const durationSelect = document.getElementById('duration_select');
    
    if (!roomSelect || !durationSelect) return;
    
    const roomNumber = roomSelect.value;
    const duration = durationSelect.value;
    
    if (roomNumber && duration) {
        const roomsData = JSON.parse(document.getElementById('room_data').value);
        const room = Object.values(roomsData).find(r => r.room_number == roomNumber);
        
        if (room) {
            let price = 0;
            let durationText = '';
            
            switch(duration) {
                case '3':
                    price = parseFloat(room.price_3hrs);
                    durationText = '3 Hours';
                    break;
                case '6':
                    price = parseFloat(room.price_6hrs);
                    durationText = '6 Hours';
                    break;
                case '12':
                    price = parseFloat(room.price_12hrs);
                    durationText = '12 Hours';
                    break;
                case '24':
                    price = parseFloat(room.price_24hrs);
                    durationText = '24 Hours';
                    break;
                case '48':
                    price = parseFloat(room.price_24hrs) * 2;
                    durationText = '48 Hours (2 Days)';
                    break;
            }
            
            document.getElementById('total_price_display').value = price.toFixed(2);
            document.getElementById('total_price_hidden').value = price.toFixed(2);
            document.getElementById('duration_display').value = durationText;
            
            // Update summary
            updateBookingSummary();
        }
    }
}

// Add event listeners to update summary when fields change
document.addEventListener('DOMContentLoaded', function() {
    const guestNameInput = document.querySelector('input[name="guest_name"]');
    const telephoneInput = document.querySelector('input[name="telephone"]');
    
    if (guestNameInput) {
        guestNameInput.addEventListener('input', updateBookingSummary);
    }
    
    if (telephoneInput) {
        telephoneInput.addEventListener('input', updateBookingSummary);
    }
});
    </script>
</body>
</html>