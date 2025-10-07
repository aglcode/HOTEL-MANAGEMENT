<?php
require_once 'database.php';
date_default_timezone_set('Asia/Manila');

// ===============================
// Step 1: Read and validate inputs
// ===============================
$room  = isset($_GET['room']) ? intval($_GET['room']) : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($room <= 0 || $token === '') {
    showError("❌ Missing room or token.");
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
    showError("❌ Invalid keycard. Please contact front desk.");
    exit;
}

// ===============================
// Step 3: Room status determines access
// ===============================
if ($keycard['room_status'] === 'available') {
    showError("❌ Room is currently available. No active guest session.");
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
header("Location: /HOTEL-MANAGEMENT/guest-dashboard.php");
exit;

// ===============================
// Helper: display error nicely
// ===============================
function showError($message) {
    echo "<div style='
        font-family:Poppins, sans-serif;
        text-align:center;
        color:red;
        padding:50px;
    '>
        <h2>$message</h2>
        <a href='/HOTEL-MANAGEMENT/index.php' style='color:#007bff;text-decoration:none;'>Return to Home</a>
    </div>";
}
?>
