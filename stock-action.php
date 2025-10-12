<?php
session_start();
require_once 'database.php';

// Handle stock in/out form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supply_id'], $_POST['action_type'], $_POST['quantity'])) {
  $supply_id = $_POST['supply_id'];
  $action_type = $_POST['action_type'];
  $quantity = (int)$_POST['quantity'];
  $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
  
  // Get current quantity
  $stmt = $conn->prepare("SELECT quantity FROM supplies WHERE id = ?");
  $stmt->bind_param("i", $supply_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $supply = $result->fetch_assoc();
  
  if ($supply) {
    $current_qty = $supply['quantity'];
    $new_qty = $action_type === 'in' ? $current_qty + $quantity : $current_qty - $quantity;
    
    // Ensure quantity doesn't go below 0
    if ($new_qty < 0) {
      $new_qty = 0;
    }
    
    // Update supply quantity
    $stmt = $conn->prepare("UPDATE supplies SET quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_qty, $supply_id);
    $stmt->execute();
    
    // Log the transaction
    $stmt = $conn->prepare("INSERT INTO stock_logs (supply_id, action_type, quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isis", $supply_id, $action_type, $quantity, $reason);
    $stmt->execute();
    
    // Set success message
    $_SESSION['success_msg'] = "Stock {$action_type} operation completed successfully.";
  } else {
    // Set error message
    $_SESSION['error_msg'] = "Supply not found.";
  }
}

// Redirect back to inventory page
header("Location: admin-inventory.php");
exit();
?>