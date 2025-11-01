<?php
session_start();
require_once 'database.php';

// Clear any previous output
ob_clean();

header('Content-Type: application/json');

if (!isset($_GET['guest_id'])) {
    echo json_encode(['success' => false, 'message' => 'Guest ID required']);
    exit;
}

$guest_id = intval($_GET['guest_id']);

try {
    // Get guest checkin data - FIXED: Calculate orders total properly
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COALESCE(SUM(o.price * o.quantity), 0) as orders_total,
            c.previous_charges
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

    // ✅ ENHANCED: Get complete rebooking history with charges breakdown
    $previous_charges = floatval($guest['previous_charges'] ?? 0);
    $rebook_info = null;

    // If this is a rebooked guest, get the original booking info
    if ($guest['is_rebooked'] == 1 || $previous_charges > 0) {
        // Try to get info from rebooked_from reference
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
        
        // If still no info, try to find most recent previous booking by same guest
        if (!$rebook_info) {
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
                WHERE guest_name = ? 
                AND id < ?
                AND DATE(check_in_date) = DATE(?)
                ORDER BY id DESC
                LIMIT 1
            ");

            if ($rebook_stmt) {
                $rebook_stmt->bind_param('sis', $guest['guest_name'], $guest_id, $guest['check_in_date']);
                $rebook_stmt->execute();
                $rebook_result = $rebook_stmt->get_result();

                if ($rebook_result->num_rows > 0) {
                    $rebook_info = $rebook_result->fetch_assoc();
                }
                $rebook_stmt->close();
            }
        }
    }

    // Calculate totals
    $room_charge = floatval($guest['total_price']);
    $orders_total = floatval($guest['orders_total']);
    $grand_total = $room_charge + $orders_total;
    
    // Calculate new charges (current total minus previous charges)
    $new_charges = $room_charge - $previous_charges;

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
    $guest['is_rebooked'] = $previous_charges > 0;

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