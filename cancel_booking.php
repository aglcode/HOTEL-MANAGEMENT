<?php
session_start();
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = intval($_POST['booking_id']);
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    if (empty($cancellation_reason)) {
        $_SESSION['error_msg'] = 'Cancellation reason is required.';
        header('Location: receptionist-booking.php');
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $cancellation_reason, $booking_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = 'Booking has been cancelled successfully.';
    } else {
        $_SESSION['error_msg'] = 'Error cancelling booking. Please try again.';
    }
} else {
    $_SESSION['error_msg'] = 'Invalid request.';
}

header('Location: receptionist-booking.php');
exit();
?>