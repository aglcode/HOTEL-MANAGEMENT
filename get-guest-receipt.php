<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['guest_id'])) {
    echo json_encode(['success' => false, 'message' => 'Guest ID required']);
    exit;
}

$guest_id = (int)$_GET['guest_id'];

$stmt = $conn->prepare("
    SELECT c.*, r.room_type
    FROM checkins c
    LEFT JOIN rooms r ON c.room_number = r.room_number
    WHERE c.id = ?
");
$stmt->bind_param('i', $guest_id);
$stmt->execute();
$result = $stmt->get_result();
$guest = $result->fetch_assoc();
$stmt->close();

if ($guest) {

    $check_in = new DateTime($guest['check_in_date']);
    $check_out = new DateTime($guest['check_out_date']);
    $interval = $check_in->diff($check_out);
    $stay_duration = ($interval->days * 24) + $interval->h;

    
    $total_price = (float)($guest['total_price'] ?? 0);
    $amount_paid = (float)($guest['amount_paid'] ?? 0);
    $change_amount = $amount_paid - $total_price;

    // ✅ Extension fee detection (₱120/hour after 3 hours)
    $base_hours = 3;
    $extension_hours = max(0, $stay_duration - $base_hours);
    $extension_fee = $extension_hours * 120;
    $base_rate = $total_price - $extension_fee;

    $guest['stay_duration'] = $stay_duration;
    $guest['extended_hours'] = $guest['extended_hours'] ?? 0;
    $guest['extension_fee'] = $extension_fee;
    $guest['base_rate'] = $base_rate;
    $guest['change_amount'] = $change_amount;

    echo json_encode(['success' => true, 'guest' => $guest]);
} else {
    echo json_encode(['success' => false, 'message' => 'Guest not found']);
}
?>
