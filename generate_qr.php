<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
require_once 'database.php';
include 'phpqrcode/qrlib.php';
date_default_timezone_set('Asia/Manila');

// ===============================
// 1️⃣ Choose room number to generate QR for
// ===============================
$room = isset($_GET['room']) ? intval($_GET['room']) : 0;

if ($room <= 0) {
    die("<div style='font-family:Poppins,sans-serif;color:red;padding:40px;'>
        ❌ Invalid or missing room number.
    </div>");
}

// ===============================
// 2️⃣ Check if a permanent token already exists
// ===============================
$stmt = $conn->prepare("SELECT qr_code FROM keycards WHERE room_number = ? LIMIT 1");
$stmt->bind_param("i", $room);
$stmt->execute();
$res = $stmt->get_result();
$keycard = $res->fetch_assoc();
$stmt->close();

// ===============================
// 3️⃣ Generate a permanent token only once
// ===============================
if ($keycard && !empty($keycard['qr_code'])) {
    // Reuse existing token
    $token = $keycard['qr_code'];
} else {
    // Generate a new permanent token (once)
    $token = strtoupper(bin2hex(random_bytes(4)));

    // Insert or update into keycards table
    $stmt2 = $conn->prepare("
        INSERT INTO keycards (room_number, qr_code, valid_from, valid_to, status)
        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 YEAR), 'active')
        ON DUPLICATE KEY UPDATE qr_code = VALUES(qr_code)
    ");
    $stmt2->bind_param("is", $room, $token);
    $stmt2->execute();
    $stmt2->close();
}

// ===============================
// 4️⃣ Generate the QR code (only one per room)
// ===============================
$baseUrl = "http://localhost/HOTEL-MANAGEMENT";
$url = "{$baseUrl}/api/unlock.php?room={$room}&token={$token}";

if (!is_dir("qrcodes")) {
    mkdir("qrcodes", 0777, true);
}

$filePath = "qrcodes/room{$room}.png";

// Create the QR image only if it doesn't exist yet
if (!file_exists($filePath)) {
    QRcode::png($url, $filePath, QR_ECLEVEL_L, 6);
}

// ===============================
// 5️⃣ Display QR info
// ===============================
echo "<div style='font-family:Poppins,sans-serif;text-align:center;padding:40px;'>";
echo "<h2>🏨 Permanent QR Code for Room {$room}</h2>";
echo "<p>This QR code will never change. It will automatically activate only when the room is occupied.</p>";
echo "<img src='{$filePath}' alt='QR Code' style='width:250px;border:4px solid #ccc;border-radius:10px;'><br><br>";
echo "<p><b>Scan to access:</b><br><a href='{$url}' target='_blank'>{$url}</a></p>";
echo "</div>";
?>
