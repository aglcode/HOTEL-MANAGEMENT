<?php
require_once 'database.php';

if (isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    
    $query = "SELECT b.*, r.room_type 
              FROM bookings b 
              LEFT JOIN rooms r ON b.room_number = r.room_number 
              WHERE b.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Personal Information</h6>';
        echo '<table class="table table-borderless">';
        echo '<tr><td><strong>Full Name:</strong></td><td>' . htmlspecialchars($booking['guest_name']) . '</td></tr>';
        echo '<tr><td><strong>Age:</strong></td><td>' . $booking['age'] . ' years old</td></tr>';
        echo '<tr><td><strong>Email:</strong></td><td>' . (empty($booking['email']) ? 'Not provided' : htmlspecialchars($booking['email'])) . '</td></tr>';
        echo '<tr><td><strong>Phone:</strong></td><td>' . htmlspecialchars($booking['telephone']) . '</td></tr>';
        echo '<tr><td><strong>Address:</strong></td><td>' . htmlspecialchars($booking['address']) . '</td></tr>';
        // echo '<tr><td><strong>Number of Guests:</strong></td><td>' . $booking['num_people'] . ' person(s)</td></tr>';
        echo '</table>';
        echo '</div>';
        
        echo '<div class="col-md-6">';
        echo '<h6 class="text-success mb-3"><i class="fas fa-bed me-2"></i>Booking Information</h6>';
        echo '<table class="table table-borderless">';
        echo '<tr><td><strong>Room Number:</strong></td><td>Room ' . htmlspecialchars($booking['room_number']) . '</td></tr>';
        echo '<tr><td><strong>Room Type:</strong></td><td>' . htmlspecialchars($booking['room_type'] ?? 'Standard') . '</td></tr>';
        echo '<tr><td><strong>Duration:</strong></td><td>' . $booking['duration'] . ' hours</td></tr>';
        echo '<tr><td><strong>Check-in:</strong></td><td>' . date('M j, Y g:i A', strtotime($booking['start_date'])) . '</td></tr>';
        echo '<tr><td><strong>Check-out:</strong></td><td>' . date('M j, Y g:i A', strtotime($booking['end_date'])) . '</td></tr>';
        echo '<tr><td><strong>Status:</strong></td><td>';
        
        $now = new DateTime();
        $start = new DateTime($booking['start_date']);
        $end = new DateTime($booking['end_date']);
        
        if ($booking['status'] === 'cancelled') {
            echo '<span class="badge bg-danger">Cancelled</span>';
            if (!empty($booking['cancellation_reason'])) {
                echo '<br><small class="text-muted">Reason: ' . htmlspecialchars($booking['cancellation_reason']) . '</small>';
            }
        } elseif ($now < $start) {
            echo '<span class="badge bg-info">Upcoming</span>';
        } elseif ($now >= $start && $now <= $end) {
            echo '<span class="badge bg-success">In Use</span>';
        } else {
            echo '<span class="badge bg-secondary">Completed</span>';
        }
        
        echo '</td></tr>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        
        echo '<hr>';
        echo '<h6 class="text-warning mb-3"><i class="fas fa-credit-card me-2"></i>Payment Information</h6>';
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<table class="table table-borderless">';
        echo '<tr><td><strong>Total Price:</strong></td><td>₱' . number_format($booking['total_price'], 2) . '</td></tr>';
        echo '<tr><td><strong>Amount Paid:</strong></td><td>₱' . number_format($booking['amount_paid'], 2) . '</td></tr>';
        echo '<tr><td><strong>Change:</strong></td><td>₱' . number_format($booking['change_amount'], 2) . '</td></tr>';
        echo '</table>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<table class="table table-borderless">';
        // echo '<tr><td><strong>Payment Mode:</strong></td><td>' . htmlspecialchars($booking['payment_mode']) . '</td></tr>';
        if ($booking['payment_mode'] === 'GCash' && !empty($booking['reference_number'])) {
            echo '<tr><td><strong>GCash Reference:</strong></td><td>' . htmlspecialchars($booking['reference_number']) . '</td></tr>';
        }
        if (!empty($booking['booking_token'])) {
            echo '<tr><td><strong>Booking Token:</strong></td><td><span class="badge bg-success">' . htmlspecialchars($booking['booking_token']) . '</span></td></tr>';
        }
        echo '<tr><td><strong>Booking Date:</strong></td><td>' . date('M j, Y g:i A', strtotime($booking['created_at'])) . '</td></tr>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        
    } else {
        echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Booking not found.</div>';
    }
} else {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Invalid booking ID.</div>';
}
?>