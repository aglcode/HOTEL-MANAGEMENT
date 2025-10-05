<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
include 'phpqrcode/qrlib.php';

// Example room + token (normally generated per booking + stored in DB)
$room  = 101;
$token = "321A345D"; 

// Local project base URL
$baseUrl = "http://localhost/HOTEL-MANAGEMENT";

// The URL that will be embedded in the QR code
$url = "{$baseUrl}/api/unlock.php?room={$room}&token={$token}";

// Directory for saving QR images
if (!is_dir("qrcodes")) {
    mkdir("qrcodes", 0777, true);
}

$filePath = "qrcodes/room{$room}.png";

// Generate the QR Code PNG
QRcode::png($url, $filePath, QR_ECLEVEL_L, 6);

echo "<h3>QR Code generated for Room {$room}</h3>";
echo "<p>Scan this code to open the guest dashboard.</p>";
echo "<img src='$filePath' style='width:200px;'>";
echo "<br><br>URL inside QR: <a href='$url'>$url</a>";
?>
