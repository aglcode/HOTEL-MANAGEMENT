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
    $additional_hours = intval($_POST['stay_duration'] ?? 0); // This is ADDITIONAL hours
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $change_amount = floatval($_POST['change_amount'] ?? 0);
    $payment_mode = strtolower(trim($_POST['payment_mode'] ?? 'cash'));
    $gcash_reference = trim($_POST['gcash_reference'] ?? '');
    $check_in_date = trim($_POST['check_in_date'] ?? ''); // This is the NEW booking start time
    // Validate required fields
    if (!$guest_id) throw new Exception('Guest ID is required');
    if (!$room_number) throw new Exception('Room number is required');
    if (empty($check_in_date)) throw new Exception('Check-in date is required');
    if (!$additional_hours) throw new Exception('Stay duration is required');
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
    // ============================
    // 🔹 Fetch current guest data
    // ============================
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
    // ✅ KEEP ORIGINAL CHECK-IN TIME
    $original_checkin = new DateTime($currentData['check_in_date'], new DateTimeZone('Asia/Manila'));
    $current_checkout = new DateTime($currentData['check_out_date'], new DateTimeZone('Asia/Manila'));
    $current_total = floatval($currentData['total_price']);
    $current_duration = intval($currentData['stay_duration']);
    $current_status = strtolower(trim($currentData['status']));
    $oldRoomNumber = intval($currentData['room_number']);
    $currentGuestName = trim($currentData['guest_name']);
    $current_amount_paid = floatval($currentData['amount_paid']);
    $existing_prev_charges = floatval($currentData['previous_charges'] ?? 0);
    $was_already_rebooked = ($currentData['is_rebooked'] == 1);
    // ============================
    // 🔹 Get room pricing from database
    // ============================
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
    // ✅ Calculate price for the ADDITIONAL hours only
    if ($additional_hours <= 3) {
        $additional_price = $price_3hrs;
    } elseif ($additional_hours <= 6) {
        $additional_price = $price_6hrs;
    } elseif ($additional_hours <= 12) {
        $additional_price = $price_12hrs;
    } elseif ($additional_hours <= 24) {
        $additional_price = $price_24hrs;
    } else {
        $extra_hours = $additional_hours - 24;
        $additional_price = $price_24hrs + ($extra_hours * $price_ot);
    }
    if ($additional_price <= 0) {
        throw new Exception('Invalid room price calculation');
    }
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    // ============================
    // 🔹 Detect if this is a GAP rebook or EXTENSION
    // ============================
    $new_checkin_requested = new DateTime($check_in_date, new DateTimeZone('Asia/Manila'));
    // Calculate time difference between old checkout and new checkin
    $time_gap_minutes = ($new_checkin_requested->getTimestamp() - $current_checkout->getTimestamp()) / 60;
    error_log("=== REBOOK TYPE DETECTION ===");
    error_log("Current checkout: " . $current_checkout->format('Y-m-d H:i:s'));
    error_log("New checkin requested: " . $new_checkin_requested->format('Y-m-d H:i:s'));
    error_log("Time gap: {$time_gap_minutes} minutes");
    // If gap is MORE than 30 minutes, treat as NEW booking (not extension)
    $is_gap_rebook = ($time_gap_minutes > 30);
    error_log("Rebook type: " . ($is_gap_rebook ? "GAP REBOOK (new booking)" : "CONTINUOUS EXTENSION"));
    error_log("============================");
    if ($is_gap_rebook) {
        // ============================
        // 🔹 GAP REBOOK: Check out old booking, create new one
        // ============================
       
        // New booking starts at requested time
        $original_checkin = clone $new_checkin_requested;
        $new_checkout = clone $new_checkin_requested;
        $new_checkout->modify("+{$additional_hours} hours");
       
        $check_in_mysql = $original_checkin->format('Y-m-d H:i:s');
        $check_out_mysql = $new_checkout->format('Y-m-d H:i:s');
       
        // Set duration to ONLY the new booking hours
        $new_total_duration = $additional_hours;
       
        // Previous charges = full amount of old booking (accumulated)
        $new_previous_charges = $existing_prev_charges + $current_total;
       
        // New charges = price for new booking
        $new_total_price = $additional_price;
       
        // Status: if new checkin is in future, it's scheduled
        if ($original_checkin > $now) {
            $new_status = 'scheduled';
        } elseif ($new_checkout <= $now) {
            $new_status = 'checked_out';
        } else {
            $new_status = 'checked_in';
        }
       
        error_log("=== GAP REBOOK CALCULATION ===");
        error_log("Old booking: " . $currentData['check_in_date'] . " → " . $currentData['check_out_date']);
        error_log("Old charges: ₱{$current_total}");
        error_log("NEW booking: {$check_in_mysql} → {$check_out_mysql}");
        error_log("NEW charges: ₱{$additional_price}");
        error_log("Duration: {$new_total_duration} hours (NEW booking only)");
        error_log("Status: {$new_status}");
        error_log("=============================");
       
    } else {
        // ============================
        // 🔹 CONTINUOUS EXTENSION: Keep original checkin, extend checkout
        // ============================
       
        // Keep original checkin
        // $original_checkin already set to currentData['check_in_date']
       
        // Total duration = current + additional
        $new_total_duration = $current_duration + $additional_hours;
       
        // Extend checkout from original checkin
        $new_checkout = clone $original_checkin;
        $new_checkout->modify("+{$new_total_duration} hours");
       
        $check_in_mysql = $original_checkin->format('Y-m-d H:i:s');
        $check_out_mysql = $new_checkout->format('Y-m-d H:i:s');
       
        // Track previous charges
        if ($was_already_rebooked) {
            $new_previous_charges = $existing_prev_charges + $current_total;
        } else {
            $new_previous_charges = $current_total;
        }
       
        // New charges = additional booking price
        $new_total_price = $additional_price;
       
        // Status determination
        if ($new_checkout <= $now) {
            $new_status = 'checked_out';
        } elseif ($original_checkin > $now) {
            $new_status = 'scheduled';
        } else {
            $new_status = 'checked_in';
        }
       
        error_log("=== CONTINUOUS EXTENSION ===");
        error_log("Original check-in: {$check_in_mysql}");
        error_log("Extended checkout: {$check_out_mysql}");
        error_log("Total duration: {$new_total_duration} hours");
        error_log("Previous charges: ₱{$new_previous_charges}");
        error_log("Additional charges: ₱{$additional_price}");
        error_log("============================");
    }
    // Overall bill calculation (same for both types)
    $overall_bill = $new_previous_charges + $new_total_price;
    // ============================
    // 🔹 Payment calculation
    // ============================
    $new_amount_paid = $current_amount_paid + $amount_paid;
    if ($new_amount_paid >= $overall_bill) {
        $new_change_amount = $new_amount_paid - $overall_bill;
    } else {
        $new_change_amount = 0;
    }
    error_log("=== PAYMENT ===");
    error_log("Overall bill: ₱{$overall_bill}");
    error_log("Total paid: ₱{$new_amount_paid}");
    error_log("Change: ₱{$new_change_amount}");
    error_log("===============");
    // ============================
    // 🔹 Basic time validation
    // ============================
    if ($new_checkout <= $original_checkin) {
        throw new Exception('Invalid checkout time. Checkout must be after check-in time.');
    }
    // ============================
    // 🔹 Check for room conflicts (if room changed)
    // ============================
    if ($room_number != $oldRoomNumber) {
        $room_number_str = strval($room_number);
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
       
        $bookingCheck->bind_param('ssss', $room_number_str, $currentGuestName, $check_out_mysql, $check_in_mysql);
        $bookingCheck->execute();
        $bookingConflicts = intval($bookingCheck->get_result()->fetch_assoc()['conflicts']);
        $bookingCheck->close();
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
       
        $checkinCheck->bind_param('iisss', $room_number, $guest_id, $currentGuestName, $check_out_mysql, $check_in_mysql);
        $checkinCheck->execute();
        $checkinConflicts = intval($checkinCheck->get_result()->fetch_assoc()['conflicts']);
        $checkinCheck->close();
        if ($bookingConflicts > 0 || $checkinConflicts > 0) {
            throw new Exception('Room is not available for the selected time period');
        }
    }
    // Continue with the rest of your existing transaction code...
    // The variables are now correctly set for either gap rebook or extension
    // ============================
    // 🔹 Begin Transaction
    // ============================
    $conn->begin_transaction();
    try {
        // ✅ Update checkins record - KEEP original check-in, extend duration and checkout
        $updateStmt = $conn->prepare("
            UPDATE checkins
            SET guest_name=?, telephone=?, address=?, room_number=?, room_type=?,
                check_in_date=?, check_out_date=?, stay_duration=?, total_price=?,
                amount_paid=?, change_amount=?, payment_mode=?, gcash_reference=?,
                status=?,
                previous_charges=?,
                rebooked_from=?,
                is_rebooked=1,
                last_modified = NOW()
            WHERE id=?
        ");
        if (!$updateStmt) {
            throw new Exception('Failed to prepare update statement: ' . $conn->error);
        }
        $updateStmt->bind_param(
            'sssisssiddssssdii',
            $guest_name, $telephone, $address, $room_number, $room_type,
            $check_in_mysql, $check_out_mysql, $new_total_duration, $new_total_price,
            $new_amount_paid, $new_change_amount, $payment_mode, $gcash_reference,
            $new_status, $new_previous_charges, $guest_id, $guest_id
        );
       
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update checkin: ' . $updateStmt->error);
        }
        $updateStmt->close();
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
        // Update room status
        $room_status_query = match ($new_status) {
            'checked_in' => 'occupied',
            'scheduled' => 'booked',
            default => 'available',
        };
        $updateRoomStmt = $conn->prepare("UPDATE rooms SET status=? WHERE room_number=?");
        if ($updateRoomStmt) {
            $updateRoomStmt->bind_param('si', $room_status_query, $room_number);
            $updateRoomStmt->execute();
            $updateRoomStmt->close();
        }
        // Handle keycards
        if ($oldRoomNumber && $oldRoomNumber != $room_number) {
            $deactivateStmt = $conn->prepare("UPDATE keycards SET status='expired', valid_to=NOW() WHERE room_number=? AND status='active'");
            if ($deactivateStmt) {
                $deactivateStmt->bind_param('i', $oldRoomNumber);
                $deactivateStmt->execute();
                $deactivateStmt->close();
            }
        }
        // Update or create keycard with new checkout time
        $getQr = $conn->prepare("SELECT qr_code FROM keycards WHERE room_number=? AND status='active' LIMIT 1");
        $existing_qr = null;
       
        if ($getQr) {
            $getQr->bind_param('i', $room_number);
            $getQr->execute();
            $getQr->bind_result($existing_qr);
            $getQr->fetch();
            $getQr->close();
        }
        $qr_code = !empty($existing_qr) ? $existing_qr : strtoupper(bin2hex(random_bytes(4)));
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
        $conn->commit();
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Guest successfully rebooked for ' . $additional_hours . ' additional hours',
            'guest_id' => $guest_id,
            'room_number' => $room_number,
            'status' => $new_status,
            'check_in_date' => $check_in_mysql,
            'check_out_date' => $check_out_mysql,
            'total_duration' => $new_total_duration,
            'previous_charges' => $new_previous_charges,
            'new_charges' => $new_total_price,
            'overall_bill' => $overall_bill
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