<?php
session_start();
require_once 'database.php';

ob_clean();
header('Content-Type: application/json');

if (!isset($_GET['guest_id'])) {
    echo json_encode(['success' => false, 'message' => 'Guest ID required']);
    exit;
}

$guest_id = intval($_GET['guest_id']);

try {
    // Get guest checkin data with orders
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COALESCE(SUM(o.price * o.quantity), 0) as orders_total,
            c.previous_charges,
            c.is_rebooked
        FROM checkins c
        LEFT JOIN orders o ON c.room_number = CAST(o.room_number AS UNSIGNED) 
            AND o.status IN ('pending', 'served')
        WHERE c.id = ?
        GROUP BY c.id
    ");

    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('i', $guest_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $guest_result = $stmt->get_result();

    if ($guest_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Guest not found']);
        exit;
    }

    $guest = $guest_result->fetch_assoc();
    $stmt->close();

    // Get orders for this guest
    $room_number_str = strval($guest['room_number']);
    $orders_stmt = $conn->prepare("
        SELECT item_name, quantity, price 
        FROM orders 
        WHERE room_number = ? 
        AND status IN ('pending', 'served')
    ");
    
    if ($orders_stmt) {
        $orders_stmt->bind_param('s', $room_number_str);
        $orders_stmt->execute();
        $orders_result = $orders_stmt->get_result();

        $orders = [];
        while ($order = $orders_result->fetch_assoc()) {
            $orders[] = $order;
        }
        $orders_stmt->close();
    } else {
        $orders = [];
    }

    // ✅ FIXED: Calculate charges correctly
    $previous_charges = floatval($guest['previous_charges'] ?? 0);
    $current_charges = floatval($guest['total_price']); // This is the ADDITIONAL charge (for rebook)
    $is_rebooked = ($guest['is_rebooked'] == 1);
    $rebook_info = null;

    // Get rebooking info if rebooked
    if ($is_rebooked && $previous_charges > 0) {
        $rebooked_from_id = intval($guest['rebooked_from'] ?? 0);
        
        if ($rebooked_from_id > 0) {
            $rebook_stmt = $conn->prepare("
                SELECT 
                    id as old_checkin_id,
                    room_number as old_room,
                    total_price as old_total,
                    stay_duration as old_duration,
                    check_in_date as old_checkin,
                    check_out_date as old_checkout,
                    amount_paid as old_paid
                FROM checkins 
                WHERE id = ?
            ");
            
            $rebook_stmt->bind_param('i', $rebooked_from_id);
            $rebook_stmt->execute();
            $rebook_result = $rebook_stmt->get_result();
            
            if ($rebook_result->num_rows > 0) {
                $rebook_info = $rebook_result->fetch_assoc();
            }
            $rebook_stmt->close();
        }
    }

    // ✅ Calculate totals correctly
    $orders_total = floatval($guest['orders_total']);
    
    // If rebooked: room charges = previous + current (additional)
    // If not rebooked: room charges = current only
    if ($is_rebooked && $previous_charges > 0) {
        $total_room_charges = $previous_charges + $current_charges;
        $new_charges = $current_charges; // For display
    } else {
        $total_room_charges = $current_charges;
        $new_charges = 0; // Not rebooked
    }
    
    // Grand total = total room charges + orders
    $grand_total = $total_room_charges + $orders_total;

    error_log("=== RECEIPT CALCULATION ===");
    error_log("Is Rebooked: " . ($is_rebooked ? 'YES' : 'NO'));
    error_log("Previous Charges: ₱" . $previous_charges);
    error_log("Current/Additional Charges: ₱" . $current_charges);
    error_log("Total Room Charges: ₱" . $total_room_charges);
    error_log("Orders Total: ₱" . $orders_total);
    error_log("Grand Total: ₱" . $grand_total);
    error_log("===========================");

    // Format dates
    $checkin_dt = new DateTime($guest['check_in_date']);
    $checkout_dt = new DateTime($guest['check_out_date']);

    $guest['check_in_date'] = $checkin_dt->format('M j, Y g:i A');
    $guest['check_out_date'] = $checkout_dt->format('M j, Y g:i A');
    $guest['orders'] = $orders;
    $guest['orders_total'] = $orders_total;
    $guest['grand_total'] = $grand_total;
    $guest['rebook_info'] = $rebook_info;
    $guest['previous_charges'] = $previous_charges;
    $guest['new_charges'] = $new_charges;
    $guest['total_room_charges'] = $total_room_charges;
    $guest['is_rebooked'] = $is_rebooked;

    echo json_encode([
        'success' => true,
        'guest' => $guest
    ]);

} catch (Exception $e) {
    error_log("Receipt Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>