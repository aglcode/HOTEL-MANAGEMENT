<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
include 'phpqrcode/qrlib.php';

// Example room + token (normally generated per booking + stored in DB)
$room  = 101;
$token = "321A345D"; 

// Local project base URL
$baseUrl = "http://localhost/gitarra_apartelle";

// The URL that will be embedded in the QR code
$url = "{$baseUrl}/api/unlock.php?room={$room}&token={$token}";

// Directory for saving QR images
if (!is_dir("qrcodes")) {
    mkdir("qrcodes", 0777, true);
}

$filePath = "qrcodes/room{$room}.png";

// Generate the QR Code PNG
QRcode::png($url, $filePath, QR_ECLEVEL_L, 6);


$logoPath = "Image/logo.jpg"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Code for Room <?php echo $room; ?></title>
<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f9fafb;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
    }

    .card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        max-width: 420px;
        width: 100%;
        padding: 32px 28px;
        text-align: center;
    }

    .icon-container {
        background: #fef7ec;
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto 16px;
    }

    .icon-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .title {
        color: #b45309;
        font-weight: 600;
        font-size: 18px;
        margin-bottom: 4px;
    }

    h2 {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 4px;
    }

    p {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .qr-container {
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        display: flex;
        justify-content: center;
    }

    .qr-container img {
        width: 200px;
        height: 200px;
    }

    .url-box {
        background: #f3f4f6;
        border-radius: 10px;
        padding: 16px;
        text-align: left;
        margin-bottom: 20px;
    }

    .url-label {
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 6px;
    }

    .url-value {
        font-family: monospace;
        font-size: 13px;
        color: #1d4ed8;
        background: #fff;
        padding: 10px;
        border-radius: 8px;
        display: block;
        overflow-wrap: break-word;
    }

    .footer {
        border-top: 1px solid #e5e7eb;
        padding-top: 10px;
        font-size: 13px;
        color: #374151;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

</style>
</head>
<body>
<div class="card">
    <div class="icon-container">
        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo">
    </div>

    <div class="title">Gitarra Apartelle</div>
    <h2>QR Code generated for Room <?php echo $room; ?></h2>
    <p>Scan this code to open the guest dashboard.</p>

    <div class="qr-container">
        <img src="<?php echo $filePath; ?>" alt="QR Code">
    </div>

    <div class="url-box">
        <div class="url-label">URL inside QR:</div>
        <a class="url-value" href="<?php echo $url; ?>"><?php echo $url; ?></a>
    </div>

    <div class="footer">
        <span>Room Number</span>
        <span><?php echo $room; ?></span>
    </div>
</div>
</body>
</html>
