<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
include 'phpqrcode/qrlib.php';

// Example room + token (normally generated per booking + stored in DB)
$room  = 101;
$token = "ABC123"; 

// Base domain for your project
$baseUrl = "http://hotel-management-c.test";

// The URL encoded inside QR
$url = $baseUrl . "/api/unlock.php?room={$room}&token={$token}";

// File path to save QR
if (!is_dir("qrcodes")) {
    mkdir("qrcodes", 0777, true);
}
$filePath = "qrcodes/room{$room}.png";

// Generate PNG
QRcode::png($url, $filePath, QR_ECLEVEL_L, 6);

echo "QR code generated for Room {$room}: <br>";
echo "<img src='$filePath' />";
?>
