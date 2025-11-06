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
  // Skip if quantity is 999 (N/A)
  if ($supply['quantity'] != 999) {
    // Calculate total inventory value
    $total_value += $supply['price'] * $supply['quantity'];

    // Count low stock items (less than 5)
    if ($supply['quantity'] < 5) {
      $low_stock_count++;
    }
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
      <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>

    .stat-card {
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
    background: #fff;
}

.stat-card:hover {
    transform: translateY(-4px);
}

.stat-title {
    font-size: 14px;
    font-weight: 600;
    color: #555;
    margin: 0;
}

.stat-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 18px;
}

.stat-change {
    font-size: 13px;
    margin-top: 6px;
}

.stat-change span {
    font-size: 12px;
    color: #888;
}


    .table-top {
     padding: 0 1rem; /* match card padding */
    }
    .dataTables_filter {
      margin: 0; /* remove default margin */
    }

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

    .calendar-sidebar {
    position: fixed;
    top: 0;
    right: -400px; 
    width: 400px;
    height: 100%;
    background: #fff;
    box-shadow: -2px 0 8px rgba(0,0,0,0.1); 
    overflow-y: auto;
    transition: right 0.3s ease;
    z-index: 1050;
  }
  .calendar-sidebar.active {
    right: 0;
  }
    .avatar-sm {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /*  Data table  */

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 0.75rem;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.table th.sorting {
    cursor: pointer;
    position: relative;
}

.table th.sorting_asc::after,
.table th.sorting_desc::after {
    content: '';
    position: absolute;
    right: 0.5rem;
    font-size: 0.7em;
    color: #6c757d;
}

.table th.sorting_asc::after {
    content: '↑';
}

.table th.sorting_desc::after {
    content: '↓';
}

.table td {
    padding: 0.75rem;
    vertical-align: middle;
    font-size: 0.875rem;
    color: #4a5568;
}

.table .badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border: 1px solid;
    transition: all 0.2s ease;
}

.bg-blue-100 { background-color: #ebf8ff; }
.text-blue-800 { color: #2b6cb0; }
.border-blue-200 { border-color: #bee3f8; }
.bg-info-100 { background-color: #e6f7ff; }
.text-info-800 { color: #2b6cb0; }
.border-info-200 { border-color: #bee3f8; }
.bg-gray-100 { background-color: #f7fafc; }
.text-gray-800 { color: #2d3748; }
.border-gray-200 { border-color: #edf2f7; }
.bg-green-100 { background-color: #f0fff4; }
.text-green-800 { color: #2f855a; }
.border-green-200 { border-color: #c6f6d5; }
.bg-amber-100 { background-color: #fffaf0; }
.text-amber-800 { color: #975a16; }
.border-amber-200 { border-color: #fed7aa; }
.bg-yellow-100 { background-color: #fef9c3; }
.text-yellow-800 { color: #854d0e; }
.border-yellow-200 { border-color: #fef08a; }

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    transition: background-color 0.15s ease;
}

.card-footer,
.bg-gray-50 {
    background-color: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.dataTables_wrapper .dataTables_paginate .pagination {
    margin: 0;
}

.dataTables_wrapper .dataTables_info {
    padding: 0.75rem;
}

.dataTables_wrapper .dataTables_paginate {
    padding-right: 15px; 
}

.user-actions .action-btn {
  color: #9b9da2ff;                
  transition: color .15s ease;   
  text-decoration: none;
  cursor: pointer;
}

.user-actions .action-btn.edit:hover {
  color: #2563eb;
}

.user-actions .action-btn.delete:hover {
  color: #dc2626; 
}


.dataTables_wrapper .row {
  margin: 0 !important;
}

.dataTables_wrapper .col-sm-12,
.dataTables_wrapper .col-md-6 {
  padding: 0 !important;
}

  #customSearch input {
    height: 31px;       
    padding: 4px 10px;
    font-size: 0.875rem;
  }


  /* === Sidebar Navigation === */
  /* === Sidebar Container === */
.sidebar {
  width: 260px;
  height: 100vh;
  background: #fff;
  border-right: 1px solid #e5e7eb;
  position: fixed;
  top: 0;
  left: 0;
  display: flex;
  flex-direction: column;
  padding: 20px 0;
  font-family: 'Inter', sans-serif;
}

/* === Logo / Header === */
.sidebar h4 {
  text-align: center;
  font-weight: 700;
  color: #111827;
  margin-bottom: 30px;
}

/* === User Info Section === */
.user-info {
  text-align: center;
  background: #f9fafb;
  border-radius: 10px;
  padding: 15px;
  margin: 0 20px 25px 20px;
}

.user-info i {
  font-size: 30px;
  color: #6b7280;
  margin-bottom: 5px;
}

.user-info p {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
}

.user-info h6 {
  margin: 0;
  font-weight: 600;
  color: #111827;
}

  .nav-links {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    padding: 0 10px;
  }

  .nav-links a {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 16px;
    font-weight: 500;
    color: #374151;
    text-decoration: none;
    padding: 12px 18px;
    border-radius: 8px;
    margin: 4px 10px;
    transition: all 0.2s ease;
  }

  .nav-links a i {
    font-size: 19px;
    color: #374151;
    transition: color 0.2s ease;
  }

  /* Hover state — icon & text both turn black */
  .nav-links a:hover {
    background: #f3f4f6;
    color: #111827;
  }

  .nav-links a:hover i {
    color: #111827;
  }

  /* Active state — white text & icon on dark background */
  .nav-links a.active {
    background: #871D2B;
    color: #fff;
  }

  .nav-links a.active i {
    color: #fff;
  }

  /* === Sign Out === */
  .signout {
    border-top: 1px solid #e5e7eb;
    padding: 15px 20px 0;
  }

  .signout a {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #dc2626;
    text-decoration: none;
    font-weight: 500;
    font-size: 15px;
    padding: 10px 15px;
    border-radius: 8px;
    transition: all 0.2s ease;
  }

  /* Hover effect — same feel as the other links */
  .signout a:hover {
    background: #f3f4f6;
    color: #dc2626;
  }

  .signout a:hover i {
    color: #dc2626;
  }

  /* === Main Content Offset === */
  .content {
    margin-left: 270px;
    padding: 30px;
    max-width: 1400px;
  }

@media (max-width: 768px) {
    .table-responsive {
        display: block;
        overflow-x: auto;
    }
}
  </style>
</head>
<body>
   <!-- Sidebar -->
  <div class="sidebar">
    <h4>Gitarra Apartelle</h4>

    <div class="user-info">
      <i class="fa-solid fa-user-circle"></i>
      <p>Welcome Admin</p>
      <h6>Admin</h6>
    </div>

    <div class="nav-links">
      <a href="admin-dashboard.php"><i class="fa-solid fa-border-all"></i> Dashboard</a>
      <a href="admin-user.php"><i class="fa-solid fa-users"></i> Users</a>
      <a href="admin-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
      <a href="admin-report.php"><i class="fa-solid fa-file-lines"></i> Reports</a>
      <a href="admin-supplies.php"><i class="fa-solid fa-cube"></i> Supplies</a>
      <a href="admin-inventory.php" class="active"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
      <a href="admin-archive.php"><i class="fa-solid fa-archive"></i> Archived</a>
    </div>

    <div class="signout">
      <a href="admin-logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
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
    <!-- Total Supplies -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Total Supplies</p>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?php echo $total_supplies; ?></h3>
            <p class="stat-change text-success">+4% <span>from last month</span></p>
        </div>
    </div>

    <!-- Low Stock Items -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Low Stock Items</p>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?php echo $low_stock_count; ?></h3>
            <p class="stat-change text-danger">-2% <span>from last month</span></p>
        </div>
    </div>

    <!-- Total Value -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Total Value</p>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-peso-sign"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1">₱<?php echo number_format($total_value, 2); ?></h3>
            <p class="stat-change text-success">+5% <span>from last month</span></p>
        </div>
    </div>
</div>

<!-- Inventory Table Card -->
<div class="card mb-4">
  <div class="card-header bg-dark d-flex justify-content-between align-items-center py-3">
    <h5 class="mb-0 text-white">Supply Inventory</h5>
    <div class="d-flex align-items-center gap-2">
      <!-- Calendar Toggle -->
      <button id="toggleCalendar" class="btn btn-outline-light btn-sm">
        <i class="fas fa-calendar-alt"></i>
      </button>

      <!-- Stock In/Out -->
      <button type="button" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#stockModal">
        <i class="fas fa-exchange-alt me-2"></i>Stock In/Out
      </button>

      <!-- Manage Supplies -->
      <a href="admin-supplies.php" class="btn btn-dark btn-sm">
        <i class="fas fa-plus-circle me-2"></i>Manage Supplies
      </a>

      <!-- Search -->
      <div id="customSearch" class="ms-2"></div>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="inventoryTable" class="table table-hover mb-0">
        <thead>
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
            <!-- Name + Avatar/Image -->
            <td class="ps-3">
              <div class="d-flex align-items-center">
                <?php if (!empty($s['image']) && file_exists($s['image'])): ?>
                  <img src="<?= htmlspecialchars($s['image']) ?>" 
                      alt="<?= htmlspecialchars($s['name']) ?>" 
                      class="rounded me-2" 
                      style="width: 40px; height: 40px; object-fit: cover;">
                <?php else: ?>
                  <div class="avatar-sm 
                      <?php if ($s['category'] == 'Food'): ?>
                        bg-green-100 text-green-800 border-green-200
                      <?php else: ?>
                        bg-info-100 text-info-800 border-info-200
                      <?php endif; ?>
                      rounded-circle d-flex align-items-center justify-content-center me-2" 
                      style="width: 40px; height: 40px; border:1px solid;">
                    <span><?= strtoupper(substr($s['name'], 0, 1)) ?></span>
                  </div>
                <?php endif; ?>
                <div><?= htmlspecialchars($s['name']) ?></div>
              </div>
            </td>

            <!-- Category -->
            <td>
              <span class="badge 
                <?php if ($s['category'] == 'Food'): ?>
                  bg-green-100 text-green-800 border-green-200
                <?php else: ?>
                  bg-blue-100 text-blue-800 border-blue-200
                <?php endif; ?>
              ">
                <?= htmlspecialchars($s['category']) ?>
              </span>
            </td>

            <!-- Price -->
            <td>₱<?= number_format($s['price'], 2) ?></td>
            
            <!-- Quantity -->
            <td><?= $s['quantity'] == 999 ? 'N/A' : (int)$s['quantity'] ?></td>
            
            <!-- Stock Status -->
            <td>
              <?php if ($s['quantity'] == 999): ?>
                <?php if ($s['status'] == 'available'): ?>
                  <span class="badge bg-green-100 text-green-800 border-green-200">Available</span>
                <?php else: ?>
                  <span class="badge bg-gray-100 text-gray-800 border-gray-200">Unavailable</span>
                <?php endif; ?>
              <?php else: ?>
                <?php if ($s['quantity'] < 5): ?>
                  <span class="badge bg-amber-100 text-amber-800 border-amber-200">Low Stock</span>
                <?php elseif ($s['quantity'] < 10): ?>
                  <span class="badge bg-yellow-100 text-yellow-800 border-yellow-200">Medium Stock</span>
                <?php else: ?>
                  <span class="badge bg-green-100 text-green-800 border-green-200">Good Stock</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>

            <!-- Total Value -->
            <td>
              <?php if ($s['quantity'] == 999): ?>
                N/A
              <?php else: ?>
                ₱<?= number_format($s['price'] * $s['quantity'], 2) ?>
              <?php endif; ?>
            </td>
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


 <!-- Calendar Sidebar -->
<div id="calendarSidebar" class="calendar-sidebar">
  <div class="calendar-header-bar d-flex justify-content-between align-items-center p-3 border-bottom">
    <h5 class="mb-0">Inventory Calendar - <?php echo date('F Y'); ?></h5>
    <button id="closeCalendar" class="btn btn-sm btn-light">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <div class="p-3">
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

      // Add empty cells for days before the first day
      for ($i = 0; $i < $dayOfWeek; $i++) {
        echo "<div class='calendar-day inactive'></div>";
      }

      // Days of month
      for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
        $isToday = ($day == $today);
        $class = $isToday ? 'calendar-day today' : 'calendar-day';

        echo "<div class='$class'>";
        echo "<div class='calendar-day-number'>$day</div>";

        if (isset($calendar_data[$date])) {
          foreach ($calendar_data[$date] as $event) {
            $eventClass = $event['action_type'] == 'in' ? 'calendar-event stock-in' : 'calendar-event stock-out';
            $actionIcon = $event['action_type'] == 'in' ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>';
            echo "<div class='$eventClass' title='{$event['supply_name']}: {$event['quantity']} {$event['action_type']} - {$event['reason']}'>$actionIcon {$event['supply_name']}</div>";
          }
        }

        echo "</div>";
      }

      // Empty cells after last day
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

<!-- Stock In/Out Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="stock-action.php">
        <div class="modal-header bg-dark text-white">
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
                  <option value="<?= $s['id'] ?>">
                    <?= htmlspecialchars($s['name']) ?> 
                    (Qty: <?= $s['quantity'] == 999 ? 'N/A' : (int)$s['quantity'] ?>)
                  </option>
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
          <button type="submit" class="btn btn-dark">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>

    document.getElementById("toggleCalendar").addEventListener("click", function () {
    document.getElementById("calendarSidebar").classList.add("active");
  });

  document.getElementById("closeCalendar").addEventListener("click", function () {
    document.getElementById("calendarSidebar").classList.remove("active");
  });

// Update clock
function updateClock() {
  const now = new Date();
  document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

setInterval(updateClock, 1000);
updateClock();

$('#inventoryTable').DataTable({
  paging: true,
  searching: true,
  ordering: true,
  info: true,
  autoWidth: false,
  responsive: true,
  pageLength: 5,
  lengthMenu: [5, 10, 25, 50, 100],
  dom: 't<"d-flex justify-content-between align-items-center mt-3"ip>', 
  language: {
    paginate: {
      first: '<<',
      previous: '<',
      next: '>',
      last: '>>'
    }
  },
  initComplete: function () {
    var searchInput = $('<input type="search" class="form-control form-control-sm" placeholder="Search inventory...">');
    searchInput.on('keyup', function () {
      $('#inventoryTable').DataTable().search(this.value).draw();
    });
    $('#customSearch').append(searchInput);
  }
});



</script>