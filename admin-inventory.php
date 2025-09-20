<?php
session_start();
require_once 'database.php';

// Create stock_logs table if it doesn't exist
try {
  $table_check = $conn->query("SHOW TABLES LIKE 'stock_logs'");
  if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE `stock_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `supply_id` int(11) NOT NULL,
      `action_type` enum('in','out') NOT NULL,
      `quantity` int(11) NOT NULL,
      `reason` text DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `supply_id` (`supply_id`),
      CONSTRAINT `stock_logs_ibfk_1` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($create_table_sql);
  }
} catch (Exception $e) {
  // If there's an error creating the table, just continue
}

// Get supply list with additional information
$result = $conn->query("SELECT * FROM supplies ORDER BY name ASC");
$supplies = [];
while ($row = $result->fetch_assoc()) {
  $supplies[] = $row;
}

// Count total supplies
$total_supplies = count($supplies);

// Count low stock supplies (less than 5 items)
$low_stock_count = 0;
$total_value = 0;

foreach ($supplies as $supply) {
  // Calculate total inventory value
  $total_value += $supply['price'] * $supply['quantity'];
  
  // Count low stock items
  if ($supply['quantity'] < 5) {
    $low_stock_count++;
  }
}

// Get stock logs for the calendar view
$calendar_data = [];
$current_month = date('m');
$current_year = date('Y');

try {
  // Check if stock_logs table exists
  $table_check = $conn->query("SHOW TABLES LIKE 'stock_logs'");
  
  if ($table_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT sl.*, s.name as supply_name 
                         FROM stock_logs sl 
                         JOIN supplies s ON sl.supply_id = s.id 
                         WHERE MONTH(sl.created_at) = ? AND YEAR(sl.created_at) = ? 
                         ORDER BY sl.created_at");
    $stmt->bind_param("ii", $current_month, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($log = $result->fetch_assoc()) {
      $date = date('Y-m-d', strtotime($log['created_at']));
      
      if (!isset($calendar_data[$date])) {
        $calendar_data[$date] = [];
      }
      
      $calendar_data[$date][] = [
        'supply_name' => $log['supply_name'],
        'quantity' => $log['quantity'],
        'action_type' => $log['action_type'],
        'reason' => $log['reason']
      ];
    }
  }
} catch (Exception $e) {
  // If there's an error with the stock_logs table, just continue with an empty calendar
  // This could happen if the table doesn't exist yet
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gitarra Apartelle - Inventory Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .calendar {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
    }
    .calendar-header {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
      margin-bottom: 5px;
    }
    .calendar-day-header {
      text-align: center;
      font-weight: bold;
      padding: 5px;
    }
    .calendar-day {
      border: 1px solid #dee2e6;
      border-radius: 4px;
      min-height: 80px;
      padding: 5px;
      position: relative;
    }
    .calendar-day.today {
      background-color: rgba(13, 110, 253, 0.1);
      border-color: #0d6efd;
    }
    .calendar-day.inactive {
      background-color: #f8f9fa;
      color: #adb5bd;
    }
    .calendar-day-number {
      position: absolute;
      top: 5px;
      right: 5px;
      font-weight: bold;
    }
    .calendar-event {
      font-size: 0.8rem;
      margin-top: 20px;
      padding: 2px 4px;
      border-radius: 3px;
      background-color: rgba(13, 110, 253, 0.1);
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .calendar-event.stock-in {
      background-color: rgba(25, 135, 84, 0.1);
      border-left: 3px solid #198754;
    }
    .calendar-event.stock-out {
      background-color: rgba(220, 53, 69, 0.1);
      border-left: 3px solid #dc3545;
    }
    .avatar-sm {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
  </style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div class="user-info mb-4">
    <i class="fa-solid fa-user-circle mb-2"></i>
    <h5 class="mb-1">Welcome,</h5>
    <p id="user-role" class="mb-0">Admin</p>
  </div>
  <a href="admin-dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
  <a href="admin-user.php"><i class="fa-solid fa-users"></i> Users</a>
  <a href="admin-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
  <a href="admin-report.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
  <a href="admin-supplies.php"><i class="fa-solid fa-boxes-stacked"></i> Supplies</a>
  <a href="admin-inventory.php" class="active"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
  <a href="admin-logout.php" class="mt-auto text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="content p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold mb-0">Inventory Management</h2>
      <p class="text-muted mb-0">Track and manage supply inventory</p>
    </div>
    <div class="clock-box text-end">
      <div id="currentDate" class="fw-semibold"></div>
      <div id="currentTime"></div>
    </div>
  </div>
  
  <?php if (isset($_SESSION['success_msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?= $_SESSION['success_msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['success_msg']); endif; ?>
  
  <?php if (isset($_SESSION['error_msg'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?= $_SESSION['error_msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['error_msg']); endif; ?>

  <!-- Inventory Statistics Cards -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
            <i class="fas fa-boxes-stacked text-white fs-3"></i>
          </div>
          <div>
            <h6 class="text-muted mb-1">Total Supplies</h6>
            <h2 class="mb-0"><?php echo $total_supplies; ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
            <i class="fas fa-exclamation-triangle text-white fs-3"></i>
          </div>
          <div>
            <h6 class="text-muted mb-1">Low Stock Items</h6>
            <h2 class="mb-0"><?php echo $low_stock_count; ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
            <i class="fas fa-peso-sign text-white fs-3"></i>
          </div>
          <div>
            <h6 class="text-muted mb-1">Total Value</h6>
            <h2 class="mb-0">₱<?php echo number_format($total_value, 2); ?></h2>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Inventory Table -->
  <div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
      <h5 class="mb-0">Supply Inventory</h5>
      <div>
        <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#stockModal">
          <i class="fas fa-exchange-alt me-2"></i>Stock In/Out
        </button>
        <a href="admin-supplies.php" class="btn btn-primary btn-sm">
          <i class="fas fa-plus-circle me-2"></i>Manage Supplies
        </a>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Name</th>
              <th>Category</th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Status</th>
              <th>Total Value</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($supplies) > 0): ?>
              <?php foreach ($supplies as $s): ?>
              <tr>
                <td class="ps-3">
                  <div class="d-flex align-items-center">
                    <div class="avatar-sm bg-<?= ($s['category'] == 'Cleaning') ? 'info' : (($s['category'] == 'Maintenance') ? 'warning' : 'success') ?> rounded-circle d-flex align-items-center justify-content-center me-2">
                      <span class="text-white"><?= strtoupper(substr($s['name'], 0, 1)) ?></span>
                    </div>
                    <div>
                      <?= htmlspecialchars($s['name']) ?>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge bg-<?= ($s['category'] == 'Cleaning') ? 'info' : (($s['category'] == 'Maintenance') ? 'warning' : 'success') ?>">
                    <?= htmlspecialchars($s['category']) ?>
                  </span>
                </td>
                <td>₱<?= number_format($s['price'], 2) ?></td>
                <td><?= $s['quantity'] ?></td>
                <td>
                  <?php if ($s['quantity'] < 5): ?>
                    <span class="badge bg-danger">Low Stock</span>
                  <?php elseif ($s['quantity'] < 10): ?>
                    <span class="badge bg-warning">Medium Stock</span>
                  <?php else: ?>
                    <span class="badge bg-success">Good Stock</span>
                  <?php endif; ?>
                </td>
                <td>₱<?= number_format($s['price'] * $s['quantity'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-4">
                  <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                  <p class="mb-0">No supplies found</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Inventory Calendar -->
  <div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
      <h5 class="mb-0">Inventory Calendar - <?php echo date('F Y'); ?></h5>
      <div>
        <span class="badge bg-success me-2"><i class="fas fa-arrow-up me-1"></i>Stock In</span>
        <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i>Stock Out</span>
      </div>
    </div>
    <div class="card-body">
      <div class="calendar-header">
        <?php
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ($days as $day) {
          echo "<div class='calendar-day-header'>$day</div>";
        }
        ?>
      </div>
      <div class="calendar">
        <?php
        $month = date('m');
        $year = date('Y');
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $dayOfWeek = date('w', $firstDay);
        $today = date('j');

        // Add empty cells for days before the first day of the month
        for ($i = 0; $i < $dayOfWeek; $i++) {
          echo "<div class='calendar-day inactive'></div>";
        }

        // Add cells for each day of the month
        for ($day = 1; $day <= $daysInMonth; $day++) {
          $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
          $isToday = ($day == $today);
          $class = $isToday ? 'calendar-day today' : 'calendar-day';
          
          echo "<div class='$class'>";
          echo "<div class='calendar-day-number'>$day</div>";
          
          // Add events for this day
          if (isset($calendar_data[$date])) {
            foreach ($calendar_data[$date] as $event) {
              $eventClass = $event['action_type'] == 'in' ? 'calendar-event stock-in' : 'calendar-event stock-out';
              $actionIcon = $event['action_type'] == 'in' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>';
              echo "<div class='$eventClass' title='{$event['supply_name']}: {$event['quantity']} {$event['action_type']} - {$event['reason']}'>$actionIcon {$event['supply_name']}</div>";
            }
          }
          
          echo "</div>";
        }

        // Add empty cells for days after the last day of the month
        $remainingCells = 7 - (($dayOfWeek + $daysInMonth) % 7);
        if ($remainingCells < 7) {
          for ($i = 0; $i < $remainingCells; $i++) {
            echo "<div class='calendar-day inactive'></div>";
          }
        }
        ?>
      </div>
    </div>
  </div>
</div>

<!-- Stock In/Out Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="stock-action.php">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Stock In/Out</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Supply</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-box"></i></span>
              <select name="supply_id" class="form-select" required>
                <option value="">Select Supply</option>
                <?php foreach ($supplies as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (Qty: <?= $s['quantity'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Action</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-exchange-alt"></i></span>
              <select name="action_type" class="form-select" required>
                <option value="in">Stock In</option>
                <option value="out">Stock Out</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span>
              <input type="number" name="quantity" class="form-control" min="1" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-comment"></i></span>
              <input type="text" name="reason" class="form-control" placeholder="Optional">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Update clock
function updateClock() {
  const now = new Date();
  document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

setInterval(updateClock, 1000);
updateClock();
</script>