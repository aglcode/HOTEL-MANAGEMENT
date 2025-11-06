<?php
// Disable all output buffering and error display
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'database.php';
    ob_clean();
   
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
   
    if (!isset($_POST['action']) || $_POST['action'] !== 'rebook') {
        throw new Exception('Invalid action');
    }

    // Sanitize inputs
    $guest_id = intval($_POST['guest_id'] ?? 0);
    $guest_name = trim($_POST['guest_name'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $room_number = intval($_POST['room_number'] ?? 0);
    $room_type = trim($_POST['room_type'] ?? '');
    $new_duration = intval($_POST['stay_duration'] ?? 0);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $change_amount = floatval($_POST['change_amount'] ?? 0);
    $payment_mode = strtolower(trim($_POST['payment_mode'] ?? 'cash'));
    $gcash_reference = trim($_POST['gcash_reference'] ?? '');
    $check_in_date = trim($_POST['check_in_date'] ?? '');

    // Validate required fields
    if (!$guest_id) throw new Exception('Guest ID is required');
    if (!$room_number) throw new Exception('Room number is required');
    if (empty($check_in_date)) throw new Exception('Check-in date is required');
    if (!$new_duration) throw new Exception('Stay duration is required');
    if (empty($guest_name)) throw new Exception('Guest name is required');
    if ($amount_paid < 0) throw new Exception('Invalid amount paid');

    // Normalize check-in datetime
    $check_in_date = str_replace('T', ' ', $check_in_date);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $check_in_date)) {
        $check_in_date .= ':00';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $check_in_date)) {
        throw new Exception('Invalid check-in date format');
    }

    if (!in_array($payment_mode, ['cash', 'gcash'], true)) {
        $payment_mode = 'cash';
    }

    // Fetch current guest data
    $currentDataStmt = $conn->prepare("
        SELECT check_in_date, check_out_date, status, guest_name, room_number,
               total_price, previous_charges, stay_duration, amount_paid, is_rebooked
        FROM checkins
        WHERE id = ?
    ");
   
    if (!$currentDataStmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
   
    $currentDataStmt->bind_param('i', $guest_id);
   
    if (!$currentDataStmt->execute()) {
        throw new Exception('Failed to fetch guest data: ' . $currentDataStmt->error);
    }
   
    $currentData = $currentDataStmt->get_result()->fetch_assoc();
    $currentDataStmt->close();

    if (!$currentData) {
        throw new Exception('Guest not found');
    }

    $original_checkin = new DateTime($currentData['check_in_date'], new DateTimeZone('Asia/Manila'));
    $current_checkout = new DateTime($currentData['check_out_date'], new DateTimeZone('Asia/Manila'));
    $current_total = floatval($currentData['total_price']);
    $current_duration = intval($currentData['stay_duration']);
    $current_status = strtolower(trim($currentData['status']));
    $oldRoomNumber = intval($currentData['room_number']);
    $currentGuestName = trim($currentData['guest_name']);
    $current_amount_paid = floatval($currentData['amount_paid']);

    // Get room pricing
    $roomPriceStmt = $conn->prepare("
        SELECT price_3hrs, price_6hrs, price_12hrs, price_24hrs, price_ot
        FROM rooms
        WHERE room_number = ?
    ");
   
    if (!$roomPriceStmt) {
        throw new Exception('Failed to prepare room price query: ' . $conn->error);
    }
   
    $roomPriceStmt->bind_param('i', $room_number);
    $roomPriceStmt->execute();
    $roomPricing = $roomPriceStmt->get_result()->fetch_assoc();
    $roomPriceStmt->close();

    if (!$roomPricing) {
        throw new Exception('Room pricing not found');
    }

    $price_3hrs = floatval($roomPricing['price_3hrs']);
    $price_6hrs = floatval($roomPricing['price_6hrs']);
    $price_12hrs = floatval($roomPricing['price_12hrs']);
    $price_24hrs = floatval($roomPricing['price_24hrs']);
    $price_ot = floatval($roomPricing['price_ot']);

    // Calculate price for new duration
    if ($new_duration <= 3) {
        $new_price = $price_3hrs;
    } elseif ($new_duration <= 6) {
        $new_price = $price_6hrs;
    } elseif ($new_duration <= 12) {
        $new_price = $price_12hrs;
    } elseif ($new_duration <= 24) {
        $new_price = $price_24hrs;
    } else {
        $extra_hours = $new_duration - 24;
        $new_price = $price_24hrs + ($extra_hours * $price_ot);
    }

    if ($new_price <= 0) {
        throw new Exception('Invalid room price calculation');
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $new_checkin_requested = new DateTime($check_in_date, new DateTimeZone('Asia/Manila'));

    // ============================
    // 🔹 GAP DETECTION: 30+ minutes = separate booking
    // ============================
    $time_gap_minutes = ($new_checkin_requested->getTimestamp() - $current_checkout->getTimestamp()) / 60;
    $is_gap_rebook = ($time_gap_minutes > 30);

    error_log("=== REBOOK TYPE DETECTION ===");
    error_log("Current checkout: " . $current_checkout->format('Y-m-d H:i:s'));
    error_log("New checkin requested: " . $new_checkin_requested->format('Y-m-d H:i:s'));
    error_log("Time gap: {$time_gap_minutes} minutes");
    error_log("Rebook type: " . ($is_gap_rebook ? "GAP REBOOK (separate booking)" : "CONTINUOUS EXTENSION"));
    error_log("============================");

    // Check for room conflicts (excluding current guest)
    if ($room_number != $oldRoomNumber || $is_gap_rebook) {
        $room_number_str = strval($room_number);
        $new_checkout = clone $new_checkin_requested;
        $new_checkout->modify("+{$new_duration} hours");
        $new_checkout_str = $new_checkout->format('Y-m-d H:i:s');

        // Check bookings
        $bookingCheck = $conn->prepare("
            SELECT COUNT(*) as conflicts
            FROM bookings
            WHERE room_number = ?
            AND status NOT IN ('cancelled', 'completed')
            AND guest_name != ?
            AND start_date < ?
            AND end_date > ?
        ");
       
        if (!$bookingCheck) {
            throw new Exception('Failed to prepare booking check: ' . $conn->error);
        }
       
        $bookingCheck->bind_param('ssss', $room_number_str, $currentGuestName, $new_checkout_str, $check_in_date);
        $bookingCheck->execute();
        $bookingConflicts = intval($bookingCheck->get_result()->fetch_assoc()['conflicts']);
        $bookingCheck->close();

        // Check checkins
        $checkinCheck = $conn->prepare("
            SELECT COUNT(*) as conflicts
            FROM checkins
            WHERE room_number = ?
            AND status IN ('scheduled', 'checked_in')
            AND id != ?
            AND guest_name != ?
            AND check_in_date < ?
            AND check_out_date > ?
        ");
       
        if (!$checkinCheck) {
            throw new Exception('Failed to prepare checkin check: ' . $conn->error);
        }
       
        $checkinCheck->bind_param('iisss', $room_number, $guest_id, $currentGuestName, $new_checkout_str, $check_in_date);
        $checkinCheck->execute();
        $checkinConflicts = intval($checkinCheck->get_result()->fetch_assoc()['conflicts']);
        $checkinCheck->close();

        if ($bookingConflicts > 0 || $checkinConflicts > 0) {
            throw new Exception('Room is not available for the selected time period');
        }
    }

    // ============================
    // 🔹 Begin Transaction
    // ============================
    $conn->begin_transaction();

    try {
        if ($is_gap_rebook) {
            // ============================
            // 🎯 GAP REBOOK: Keep old booking active, create new scheduled booking
            // ============================
            
            error_log("Gap rebook: Keeping old booking (ID {$guest_id}) active until {$current_checkout->format('Y-m-d H:i:s')}");

            // Create new checkin record
            $new_checkout = clone $new_checkin_requested;
            $new_checkout->modify("+{$new_duration} hours");
            
            $check_in_mysql = $new_checkin_requested->format('Y-m-d H:i:s');
            $check_out_mysql = $new_checkout->format('Y-m-d H:i:s');

            // Determine status for new booking
            if ($new_checkin_requested > $now) {
                $new_status = 'scheduled';
            } elseif ($new_checkout <= $now) {
                $new_status = 'checked_out';
            } else {
                $new_status = 'checked_in';
            }

            // Calculate payment for new booking
            $new_amount_paid = $amount_paid;
            $new_change_amount = $new_amount_paid >= $new_price ? ($new_amount_paid - $new_price) : 0;

            $insertStmt = $conn->prepare("
                INSERT INTO checkins 
                (guest_name, telephone, address, room_number, room_type, 
                 check_in_date, check_out_date, stay_duration, total_price,
                 amount_paid, change_amount, payment_mode, gcash_reference,
                 status, previous_charges, rebooked_from, is_rebooked, last_modified)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 0, NOW())
            ");

            if (!$insertStmt) {
                throw new Exception('Failed to prepare insert: ' . $conn->error);
            }

            $insertStmt->bind_param(
                'sssisssiddssssi',
                $guest_name, $telephone, $address, $room_number, $room_type,
                $check_in_mysql, $check_out_mysql, $new_duration, $new_price,
                $new_amount_paid, $new_change_amount, $payment_mode, $gcash_reference,
                $new_status, $guest_id
            );

            if (!$insertStmt->execute()) {
                throw new Exception('Failed to create new booking: ' . $insertStmt->error);
            }

            $new_booking_id = $conn->insert_id;
            $insertStmt->close();

            error_log("=== GAP REBOOK COMPLETED ===");
            error_log("Old booking (ID {$guest_id}): Still active");
            error_log("New booking (ID {$new_booking_id}): CREATED");
            error_log("Time range: {$check_in_mysql} → {$check_out_mysql}");
            error_log("Duration: {$new_duration} hours");
            error_log("Price: ₱{$new_price}");
            error_log("Status: {$new_status}");
            error_log("===========================");

            $result_guest_id = $new_booking_id;
            $result_message = "New booking created for {$new_duration} hours starting at " . $new_checkin_requested->format('M d, Y h:i A');


} else {
    // ============================
    // 🎯 CONTINUOUS EXTENSION: Extend current booking
    // ============================
    
    $existing_prev_charges = floatval($currentData['previous_charges'] ?? 0);
    $was_already_rebooked = ($currentData['is_rebooked'] == 1);

    // Keep original check-in, extend duration and checkout
    $new_total_duration = $current_duration + $new_duration;
    $new_checkout = clone $original_checkin;
    $new_checkout->modify("+{$new_total_duration} hours");
    
    $check_in_mysql = $original_checkin->format('Y-m-d H:i:s');
    $check_out_mysql = $new_checkout->format('Y-m-d H:i:s');

    // ✅ Track previous charges correctly
    if ($was_already_rebooked) {
        // Already has previous charges, add current total to them
        $new_previous_charges = $existing_prev_charges + $current_total;
    } else {
        // First rebook, current total becomes "previous"
        $new_previous_charges = $current_total;
    }

    // ✅ The "total_price" column should ONLY store the ADDITIONAL charge
    $new_total_price = $new_price; // Just the new extension cost

    // Determine status
    if ($new_checkout <= $now) {
        $new_status = 'checked_out';
    } elseif ($original_checkin > $now) {
        $new_status = 'scheduled';
    } else {
        $new_status = 'checked_in';
    }

    // ✅ Calculate payment correctly
    // Overall bill = all previous charges + new additional charge
    $overall_bill = $new_previous_charges + $new_total_price;
    
    // Total paid = what was already paid + new payment
    $new_amount_paid = $current_amount_paid + $amount_paid;
    
    // Calculate change/due
    $balance = $new_amount_paid - $overall_bill;
    $new_change_amount = $balance > 0 ? $balance : 0;

    error_log("=== CONTINUOUS EXTENSION CALCULATION ===");
    error_log("Was already rebooked: " . ($was_already_rebooked ? 'YES' : 'NO'));
    error_log("Existing previous charges: ₱{$existing_prev_charges}");
    error_log("Current total (becoming previous): ₱{$current_total}");
    error_log("New previous charges: ₱{$new_previous_charges}");
    error_log("New additional charge: ₱{$new_total_price}");
    error_log("Overall bill: ₱{$overall_bill}");
    error_log("Current paid: ₱{$current_amount_paid}");
    error_log("New payment: ₱{$amount_paid}");
    error_log("Total paid: ₱{$new_amount_paid}");
    error_log("Balance: ₱{$balance}");
    error_log("=====================================");

    // ✅ CRITICAL FIX: Update with correct column mapping
    $updateStmt = $conn->prepare("
        UPDATE checkins
        SET check_out_date = ?,
            stay_duration = ?,
            total_price = ?,
            amount_paid = ?,
            change_amount = ?,
            payment_mode = ?,
            gcash_reference = ?,
            status = ?,
            previous_charges = ?,
            is_rebooked = 1,
            last_modified = NOW()
        WHERE id = ?
    ");

    if (!$updateStmt) {
        throw new Exception('Failed to prepare update: ' . $conn->error);
    }

    // ✅ Bind parameters in correct order
    $updateStmt->bind_param(
        'sidddsssdi',
        $check_out_mysql,           // s - checkout date
        $new_total_duration,        // i - total duration
        $new_total_price,           // d - NEW charge only (additional)
        $new_amount_paid,           // d - total amount paid
        $new_change_amount,         // d - change amount
        $payment_mode,              // s - payment mode
        $gcash_reference,           // s - gcash reference
        $new_status,                // s - status
        $new_previous_charges,      // d - accumulated previous charges
        $guest_id                   // i - guest ID
    );

    if (!$updateStmt->execute()) {
        $error_msg = $updateStmt->error;
        $updateStmt->close();
        throw new Exception('Failed to extend booking: ' . $error_msg);
    }
    
    $affected_rows = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($affected_rows === 0) {
        error_log("WARNING: No rows affected by update for guest ID {$guest_id}");
    }

    error_log("=== CONTINUOUS EXTENSION COMPLETED ===");
    error_log("Guest ID: {$guest_id}");
    error_log("Original check-in: {$check_in_mysql}");
    error_log("Extended checkout: {$check_out_mysql}");
    error_log("Total duration: {$new_total_duration} hours");
    error_log("Previous charges: ₱{$new_previous_charges}");
    error_log("Additional charge: ₱{$new_total_price}");
    error_log("Total paid: ₱{$new_amount_paid}");
    error_log("Status: {$new_status}");
    error_log("Affected rows: {$affected_rows}");
    error_log("====================================");

    $result_guest_id = $guest_id;
    $result_message = "Stay extended by {$new_duration} hours (total: {$new_total_duration} hours)";
    $result_is_extension = true; // Flag for UI display
}

        // Free old room if changed
        if ($oldRoomNumber && $oldRoomNumber != $room_number) {
            $checkOldRoom = $conn->prepare("
                SELECT COUNT(*) as active_guests
                FROM checkins
                WHERE room_number = ?
                AND status IN ('checked_in', 'scheduled')
                AND id != ?
            ");
           
            if ($checkOldRoom) {
                $checkOldRoom->bind_param('ii', $oldRoomNumber, $guest_id);
                $checkOldRoom->execute();
                $active_guests = intval($checkOldRoom->get_result()->fetch_assoc()['active_guests']);
                $checkOldRoom->close();

                if ($active_guests === 0) {
                    $freeOldRoom = $conn->prepare("UPDATE rooms SET status='available' WHERE room_number=?");
                    if ($freeOldRoom) {
                        $freeOldRoom->bind_param('i', $oldRoomNumber);
                        $freeOldRoom->execute();
                        $freeOldRoom->close();
                    }
                }
            }
        }

        // Update room status only if the new booking is currently active
        if ($new_status === 'checked_in') {
            $updateRoomStmt = $conn->prepare("UPDATE rooms SET status='booked' WHERE room_number=?");
        } else {
            // For scheduled bookings, keep room available until check-in time
            $updateRoomStmt = $conn->prepare("UPDATE rooms SET status='available' WHERE room_number=?");
        }
        
        if ($updateRoomStmt) {
            $updateRoomStmt->bind_param('i', $room_number);
            $updateRoomStmt->execute();
            $updateRoomStmt->close();
        }

        // Handle keycards
        if ($is_gap_rebook || ($oldRoomNumber && $oldRoomNumber != $room_number)) {
            $deactivateStmt = $conn->prepare("UPDATE keycards SET status='expired', valid_to=NOW() WHERE room_number=? AND status='active'");
            if ($deactivateStmt) {
                $deactivateStmt->bind_param('i', $oldRoomNumber);
                $deactivateStmt->execute();
                $deactivateStmt->close();
            }
        }

        // Create/update keycard only if booking is currently active
        if ($new_status === 'checked_in') {
            $qr_code = strtoupper(bin2hex(random_bytes(4)));
            $keycardStmt = $conn->prepare("
                INSERT INTO keycards (room_number, qr_code, valid_from, valid_to, status)
                VALUES (?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE valid_from=VALUES(valid_from), valid_to=VALUES(valid_to), status='active'
            ");
           
            if ($keycardStmt) {
                $keycardStmt->bind_param('isss', $room_number, $qr_code, $check_in_mysql, $check_out_mysql);
                $keycardStmt->execute();
                $keycardStmt->close();
            }
        }

        $conn->commit();

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $result_message,
            'guest_id' => $result_guest_id,
            'is_gap_rebook' => $is_gap_rebook,
            'room_number' => $room_number,
            'status' => $new_status
        ]);
        ob_end_flush();
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    if (ob_get_level()) ob_clean();
   
    error_log("Rebook Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
   
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
   
    if (ob_get_level()) ob_end_flush();
    exit;
}
?>