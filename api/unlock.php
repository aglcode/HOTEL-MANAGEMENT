<?php
require_once '../database.php';
date_default_timezone_set('Asia/Manila');

// ===============================
// Step 1: Read and validate inputs
// ===============================
$room  = isset($_GET['room']) ? intval($_GET['room']) : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($room <= 0 || $token === '') {
    showError("Missing room or token.");
    exit;
}

// ===============================
// Step 2: Fetch keycard & room data
// ===============================
$stmt = $conn->prepare("
    SELECT k.*, r.status AS room_status
    FROM keycards k
    JOIN rooms r ON k.room_number = r.room_number
    WHERE k.qr_code = ? AND k.room_number = ?
    ORDER BY k.id DESC LIMIT 1
");
$stmt->bind_param("si", $token, $room);
$stmt->execute();
$res = $stmt->get_result();
$keycard = $res->fetch_assoc();
$stmt->close();

if (!$keycard) {
    showError("Invalid keycard. Please contact front desk.");
    exit;
}

// ===============================
// Step 3: Room status determines access
// ===============================
if ($keycard['room_status'] === 'available') {
    showError("Room is currently available. No active guest session.");
    exit;
}

// ✅ If room is occupied but keycard expired → reactivate it automatically
if ($keycard['room_status'] !== 'available' && $keycard['status'] !== 'active') {
    $stmt2 = $conn->prepare("UPDATE keycards SET status = 'active' WHERE id = ?");
    $stmt2->bind_param("i", $keycard['id']);
    $stmt2->execute();
    $stmt2->close();
    $keycard['status'] = 'active';
}

// ===============================
// Step 4: Valid room → grant access
// ===============================
session_start();
$_SESSION['qr_token'] = $token;
$_SESSION['room_number'] = $room;

// Optional: fetch guest name for dashboard
$stmt3 = $conn->prepare("
    SELECT guest_name 
    FROM checkins 
    WHERE room_number = ? 
    ORDER BY id DESC LIMIT 1
");
$stmt3->bind_param("i", $room);
$stmt3->execute();
$res = $stmt3->get_result();
if ($res && $res->num_rows > 0) {
    $ci = $res->fetch_assoc();
    $_SESSION['guest_name'] = $ci['guest_name'];
}
$stmt3->close();

// Redirect guest to dashboard
header("Location: /guest-dashboard.php");
exit;

// ===============================
// Helper: display error nicely
// ===============================
function showError($message) {
    echo "
    <div style='
        font-family: Poppins, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        background-color: #f9fafb;
        flex-direction: column;
        margin: 0;
    '>
        <div style=\"
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.08);
            max-width: 600px;
            width: 90%;
            text-align: center;
            position: relative;
            padding: 60px 40px 50px;
            border-top: 6px solid #e53935;
        \">
            <!-- Icon -->
            <div style='
                background-color: #fde8e8;
                display: inline-flex;
                justify-content: center;
                align-items: center;
                border-radius: 50%;
                width: 80px;
                height: 80px;
                margin-top: -100px;
                border: 4px solid #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            '>
                <span style='font-size: 45px; color: #e53935;'>❌</span>
            </div>

            <!-- Title -->
            <h2 style='
                color: #111827;
                font-weight: 700;
                font-size: 24px;
                margin-top: 20px;
                margin-bottom: 10px;
            '>$message</h2>

            <!-- Description -->
            <p style='
                color: #6b7280;
                font-size: 16px;
                margin-bottom: 30px;
            '>
                No active guest session detected. Please verify your booking or return to the home page.
            </p>

            <!-- Button -->
            <a href='/index.php' style='
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background-color: #2563eb;
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 10px;
                font-weight: 500;
                font-size: 16px;
                transition: background-color 0.3s ease;
            ' onmouseover=\"this.style.backgroundColor='#1e40af'\" onmouseout=\"this.style.backgroundColor='#2563eb'\">
              Return to Home
            </a>
        </div>

    </div>
    ";
}
?>
