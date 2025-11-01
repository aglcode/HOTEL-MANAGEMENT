<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

ob_start();

try {
    session_start();
    require_once 'database.php';
    
    ob_clean();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    if (!isset($_POST['action']) || $_POST['action'] !== 'rebook') {
        throw new Exception('Invalid action');
    }

    // ============================
    // 🔹 Sanitize inputs
    // ============================
    $guest_id       = isset($_POST['guest_id']) ? intval($_POST['guest_id']) : 0;
    $guest_name     = trim($_POST['guest_name'] ?? '');
    $telephone      = trim($_POST['telephone'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $room_number    = intval($_POST['room_number'] ?? 0);
    $room_type      = trim($_POST['room_type'] ?? '');
    $stay_duration  = intval($_POST['stay_duration'] ?? 0);
    $total_price    = floatval($_POST['total_price'] ?? 0);
    $amount_paid    = floatval($_POST['amount_paid'] ?? 0);
    $change_amount  = floatval($_POST['change_amount'] ?? 0);
    $payment_mode   = strtolower(trim($_POST['payment_mode'] ?? 'cash'));
    $gcash_reference = trim($_POST['gcash_reference'] ?? '');
    $check_in_date  = str_replace('T', ' ', trim($_POST['check_in_date'] ?? ''));

    // Validate required fields
    if (!$guest_id) throw new Exception('Guest ID is required');
    if (!$room_number) throw new Exception('Room number is required');
    if (empty($check_in_date)) throw new Exception('Check-in date is required');
    if (!$stay_duration) throw new Exception('Stay duration is required');
    if (empty($guest_name)) throw new Exception('Guest name is required');

    // Normalize check-in datetime format (add seconds if missing)
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $check_in_date)) {
        $check_in_date .= ':00';
    }

    // ============================
    // 🔹 Process Dates - SINGLE SOURCE OF TRUTH
    // ============================
    $checkin_dt = new DateTime($check_in_date);
    $check_in_mysql = $checkin_dt->format('Y-m-d H:i:s');

    $checkout_dt = clone $checkin_dt;
    $checkout_dt->modify("+{$stay_duration} hours");
    $check_out_mysql = $checkout_dt->format('Y-m-d H:i:s');

    $now = new DateTime();

    // Get old room and status
    $oldRoomStmt = $conn->prepare("SELECT room_number, status FROM checkins WHERE id = ?");
    $oldRoomStmt->bind_param('i', $guest_id);
    $oldRoomStmt->execute();
    $oldRoomData = $oldRoomStmt->get_result()->fetch_assoc();
    $oldRoomStmt->close();

    if (!$oldRoomData) {
        throw new Exception('Guest not found');
    }

    $oldRoomNumber = intval($oldRoomData['room_number']);
    $oldStatus = strtolower(trim($oldRoomData['status']));

    // Get guest name from DB
    $currentGuestStmt = $conn->prepare("SELECT guest_name FROM checkins WHERE id = ?");
    $currentGuestStmt->bind_param('i', $guest_id);
    $currentGuestStmt->execute();
    $currentGuestData = $currentGuestStmt->get_result()->fetch_assoc();
    $currentGuestName = trim($currentGuestData['guest_name'] ?? '');
    $currentGuestStmt->close();

    // ============================
    // 🔹 Check Conflicts
    // ============================
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
    $checkinCheck->bind_param('iisss', $room_number, $guest_id, $currentGuestName, $check_out_mysql, $check_in_mysql);
    $checkinCheck->execute();
    $checkinConflicts = intval($checkinCheck->get_result()->fetch_assoc()['conflicts']);
    $checkinCheck->close();

    if ($bookingConflicts > 0 || $checkinConflicts > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Room is not available for the selected time period'
        ]);
        ob_end_flush();
        exit;
    }

 // ====================================================
// 🔧 ENHANCED STATUS LOGIC FOR REBOOK
// Replace the status determination in process-rebook.php
// ====================================================

// Get the current status before updating
$currentStatusStmt = $conn->prepare("SELECT status, check_in_date FROM checkins WHERE id = ?");
$currentStatusStmt->bind_param('i', $guest_id);
$currentStatusStmt->execute();
$currentStatusResult = $currentStatusStmt->get_result()->fetch_assoc();
$current_status = $currentStatusResult['status'] ?? 'checked_in';
$original_checkin = $currentStatusResult['check_in_date'] ?? null;
$currentStatusStmt->close();

// ✅ Use server timezone consistently
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$checkin_dt->setTimezone(new DateTimeZone('Asia/Manila'));
$checkout_dt->setTimezone(new DateTimeZone('Asia/Manila'));

// Parse original check-in if available
$original_checkin_dt = null;
if ($original_checkin) {
    $original_checkin_dt = new DateTime($original_checkin, new DateTimeZone('Asia/Manila'));
}

// ============================
// ✅ FIXED: Force correct status for rebooking
// ============================
error_log("=== REBOOK STATUS DEBUG ===");
error_log("Guest ID: $guest_id | Current Status: $current_status");
error_log("Original Check-in: " . ($original_checkin_dt ? $original_checkin_dt->format('Y-m-d H:i:s') : 'N/A'));
error_log("New Check-in: " . $checkin_dt->format('Y-m-d H:i:s'));
error_log("New Check-out: " . $checkout_dt->format('Y-m-d H:i:s'));
error_log("Server Now: " . $now->format('Y-m-d H:i:s'));

// ✅ KEY LOGIC: If guest is currently checked in OR was checked in, keep them checked in
// This prevents "scheduled" status during same-day rebooking
if ($checkout_dt <= $now) {
    // Checkout has already passed
    error_log("⚠️ WARNING: Rebooked with past checkout time!");
    $new_status = 'checked_out';
    
} elseif ($current_status === 'checked_in') {
    // ✅ CRITICAL: Guest is currently active - ALWAYS keep as checked_in
    $new_status = 'checked_in';
    error_log("✅ Guest is currently active - maintaining checked_in status");
    
} elseif ($original_checkin_dt && $original_checkin_dt <= $now && $checkout_dt > $now) {
    // ✅ Original check-in has passed, new checkout is future
    // This handles rebooking where guest extended their stay
    $new_status = 'checked_in';
    error_log("✅ Guest already checked in (original time passed) - setting checked_in");
    
} elseif ($checkin_dt <= $now && $checkout_dt > $now) {
    // ✅ New check-in has passed, checkout is in future
    $new_status = 'checked_in';
    error_log("✅ Check-in time reached, checkout future - setting checked_in");
    
} elseif ($checkin_dt > $now) {
    // Check-in is still in the future
    $new_status = 'scheduled';
    error_log("✅ Check-in is future - setting scheduled");
    
} else {
    // Fallback - preserve current status if possible
    $new_status = ($current_status === 'checked_out') ? 'checked_out' : 'checked_in';
    error_log("⚠️ Fallback logic - using: $new_status");
}

error_log("📊 Final Status: $new_status");
error_log("========================");

    if (!in_array($payment_mode, ['cash', 'gcash'], true)) {
        $payment_mode = 'cash';
    }

    // ============================
    // 🔹 Begin Transaction
    // ============================
    $conn->begin_transaction();

    try {
        // ============================
        // 🔹 Get current total_price BEFORE updating (for previous_charges tracking)
        // ============================
        $getPrevChargeStmt = $conn->prepare("SELECT total_price, previous_charges FROM checkins WHERE id = ?");
        $getPrevChargeStmt->bind_param('i', $guest_id);
        $getPrevChargeStmt->execute();
        $prevData = $getPrevChargeStmt->get_result()->fetch_assoc();
        $getPrevChargeStmt->close();

        $current_total = floatval($prevData['total_price'] ?? 0);
        $existing_prev_charges = floatval($prevData['previous_charges'] ?? 0);

        // Calculate new previous_charges
        // If already has previous charges, add current to it; otherwise, current becomes previous
        $new_previous_charges = $existing_prev_charges > 0 
            ? $existing_prev_charges + $current_total 
            : $current_total;

        error_log("💰 Previous Charges Calculation:");
        error_log("   Current Total: $current_total");
        error_log("   Existing Previous: $existing_prev_charges");
        error_log("   New Previous: $new_previous_charges");
     
        // Update checkins WITH previous_charges tracking
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

        $updateStmt->bind_param(
            'sssisssiddssssdii',
            $guest_name, $telephone, $address, $room_number, $room_type,
            $check_in_mysql, $check_out_mysql, $stay_duration, $total_price,
            $amount_paid, $change_amount, $payment_mode, $gcash_reference,
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
            $checkOldRoom->bind_param('ii', $oldRoomNumber, $guest_id);
            $checkOldRoom->execute();
            $active_guests = intval($checkOldRoom->get_result()->fetch_assoc()['active_guests']);
            $checkOldRoom->close();

            if ($active_guests === 0) {
                $freeOldRoom = $conn->prepare("UPDATE rooms SET status='available' WHERE room_number=?");
                $freeOldRoom->bind_param('i', $oldRoomNumber);
                $freeOldRoom->execute();
                $freeOldRoom->close();
            }
        }

        // Sync room status with guest status
        $room_status_query = match ($new_status) {
            'checked_in' => 'occupied',
            'scheduled'  => 'booked',
            default      => 'available',
        };

        $updateRoomStmt = $conn->prepare("UPDATE rooms SET status=? WHERE room_number=?");
        $updateRoomStmt->bind_param('si', $room_status_query, $room_number);
        $updateRoomStmt->execute();
        $updateRoomStmt->close();

        // 🔹 Keycard Handling
        if ($oldRoomNumber && $oldRoomNumber != $room_number) {
            $deactivateStmt = $conn->prepare("UPDATE keycards SET status='expired', valid_to=NOW() WHERE room_number=? AND status='active'");
            $deactivateStmt->bind_param('i', $oldRoomNumber);
            $deactivateStmt->execute();
            $deactivateStmt->close();
        }

        // Update or create keycard
        $getQr = $conn->prepare("SELECT qr_code FROM keycards WHERE room_number=? AND status='active' LIMIT 1");
        $getQr->bind_param('i', $room_number);
        $getQr->execute();
        $getQr->bind_result($existing_qr);
        $getQr->fetch();
        $getQr->close();

        $qr_code = !empty($existing_qr) ? $existing_qr : strtoupper(bin2hex(random_bytes(4)));

        $keycardStmt = $conn->prepare("
            INSERT INTO keycards (room_number, qr_code, valid_from, valid_to, status)
            VALUES (?, ?, NOW(), ?, 'active')
            ON DUPLICATE KEY UPDATE valid_from=NOW(), valid_to=VALUES(valid_to), status='active'
        ");
        $keycardStmt->bind_param('iss', $room_number, $qr_code, $check_out_mysql);
        $keycardStmt->execute();
        $keycardStmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Guest successfully rebooked',
            'guest_id' => $guest_id,
            'room_number' => $room_number,
            'status' => $new_status,
            'check_in_date' => $check_in_mysql,
            'check_out_date' => $check_out_mysql,
            'previous_charges' => $new_previous_charges
        ]);
        ob_end_flush();
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Rebook Error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    ob_end_flush();
    exit;
}
?>