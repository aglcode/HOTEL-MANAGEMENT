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
            COALESCE(SUM(o.price), 0) as orders_total,
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

    // Calculate charges correctly
    $previous_charges = floatval($guest['previous_charges'] ?? 0);
    $current_charges = floatval($guest['total_price']);
    $is_rebooked = ($guest['is_rebooked'] == 1);
    $rebook_info = null;
    $extension_info = null;

    // ✅ NEW: Detect if this is an extension (continuous rebook)
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
                
                // Detect time gap between bookings
                $old_checkout = new DateTime($rebook_info['old_checkout']);
                $current_checkin = new DateTime($guest['check_in_date']);
                
                $has_gap = ($current_checkin > $old_checkout);
                
                if ($has_gap) {
                    // Gap rebook (separate booking)
                    $gap_duration = $old_checkout->diff($current_checkin);
                    $gap_hours = ($gap_duration->days * 24) + $gap_duration->h;
                    $gap_minutes = $gap_duration->i;
                    
                    $rebook_info['has_gap'] = true;
                    $rebook_info['gap_hours'] = $gap_hours;
                    $rebook_info['gap_minutes'] = $gap_minutes;
                } else {
                    // ✅ Continuous extension
                    $rebook_info['has_gap'] = false;
                    
                    // Calculate extension details
                    $original_duration = intval($rebook_info['old_duration']);
                    $total_duration = intval($guest['stay_duration']);
                    $extended_hours = $total_duration - $original_duration;
                    
                    // Count how many times extended (based on previous_charges accumulation)
                    $total_extensions = 1;
                    if ($previous_charges > floatval($rebook_info['old_total'])) {
                        // Multiple extensions
                        $total_extensions = ceil($previous_charges / floatval($rebook_info['old_total']));
                    }
                    
                    $extension_info = [
                        'is_extension' => true,
                        'original_duration' => $original_duration,
                        'extended_hours' => $extended_hours,
                        'total_duration' => $total_duration,
                        'original_checkout' => date('M j, Y g:i A', strtotime($rebook_info['old_checkout'])),
                        'extended_checkout' => date('M j, Y g:i A', strtotime($guest['check_out_date'])),
                        'total_extensions' => $total_extensions
                    ];
                    
                    error_log("=== EXTENSION DETECTED ===");
                    error_log("Original: {$original_duration}h → Extended: +{$extended_hours}h → Total: {$total_duration}h");
                    error_log("Extensions count: {$total_extensions}");
                    error_log("========================");
                }
            }
            $rebook_stmt->close();
        }
    }
    
    // Calculate totals correctly
    $orders_total = floatval($guest['orders_total']);
    
    // If rebooked: room charges = previous + current (additional)
    if ($is_rebooked && $previous_charges > 0) {
        $total_room_charges = $previous_charges + $current_charges;
        $new_charges = $current_charges;
    } else {
        $total_room_charges = $current_charges;
        $new_charges = 0;
    }
    
    // Grand total = total room charges + orders
    $grand_total = $total_room_charges + $orders_total;

    error_log("=== RECEIPT CALCULATION ===");
    error_log("Is Rebooked: " . ($is_rebooked ? 'YES' : 'NO'));
    error_log("Is Extension: " . ($extension_info ? 'YES' : 'NO'));
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
    $guest['extension_info'] = $extension_info; 
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