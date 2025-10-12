<?php
require_once 'database.php';

$query = "SELECT guest_name, room_number, start_date, cancellation_reason, cancelled_at 
          FROM bookings 
          WHERE status = 'cancelled' 
          ORDER BY cancelled_at DESC";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Guest Name</th><th>Room</th><th>Original Date</th><th>Reason</th><th>Cancelled Date</th></tr></thead>';
    echo '<tbody>';
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['guest_name']) . '</td>';
        echo '<td>Room ' . htmlspecialchars($row['room_number']) . '</td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($row['start_date'])) . '</td>';
        echo '<td>' . htmlspecialchars($row['cancellation_reason'] ?? 'No reason provided') . '</td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($row['cancelled_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
} else {
    echo '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No cancelled bookings found.</div>';
}
?>