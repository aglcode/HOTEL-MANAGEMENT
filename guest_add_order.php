<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connection.php'; // make sure this file contains your $conn connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $room_number  = mysqli_real_escape_string($conn, $_POST['room_number'] ?? '');
    $category     = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
    $item_name    = mysqli_real_escape_string($conn, $_POST['item_name'] ?? '');
    $size         = mysqli_real_escape_string($conn, $_POST['size'] ?? '');
    $price        = floatval($_POST['price'] ?? 0);
    $quantity     = intval($_POST['quantity'] ?? 1);
    $mode_payment = strtolower(trim($_POST['mode_payment'] ?? 'cash')); // fix for ENUM lowercase
    $ref_number   = mysqli_real_escape_string($conn, $_POST['ref_number'] ?? '');

    // Make sure all required fields are present
    if (empty($room_number) || empty($category) || empty($item_name) || $price <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order data.']);
        exit;
    }

    // Insert order into database
    $sql = "INSERT INTO orders (room_number, category, item_name, size, price, quantity, mode_payment, ref_number, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssssdiis", $room_number, $category, $item_name, $size, $price, $quantity, $mode_payment, $ref_number);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Order placed successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database insert failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    }

    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
