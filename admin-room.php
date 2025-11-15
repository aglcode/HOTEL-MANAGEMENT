<?php 
session_start();

require_once 'database.php'; // Include your database connection settings

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: signin.php");
    exit();
}

// Handle Add Room
if (isset($_POST['add_room'])) {
    $room_number = $_POST['room_number'];
    $room_type = $_POST['room_type'];
    $status = $_POST['status'];
    $price_3hrs = ($room_type == 'twin') ? 0 : $_POST['price_3hrs'];
    $price_6hrs = ($room_type == 'twin') ? 0 : $_POST['price_6hrs'];
    $price_12hrs = $_POST['price_12hrs'];
    $price_24hrs = $_POST['price_24hrs'];
    $price_ot = $_POST['price_ot'];

    $query = "INSERT INTO rooms 
    (room_number, room_type, status, price_3hrs, price_6hrs, price_12hrs, price_24hrs, price_ot)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issddddd", $room_number, $room_type, $status, $price_3hrs, $price_6hrs, $price_12hrs, $price_24hrs, $price_ot);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-room.php?success=added");
    exit();
}

// Handle Edit Room
if (isset($_POST['edit_room'])) {
    $room_id = $_POST['room_id'];
    $room_number = $_POST['room_number'];
    $room_type = $_POST['room_type'];
    $status = $_POST['status'];
    $price_3hrs = ($room_type == 'twin') ? 0 : $_POST['price_3hrs'];
    $price_6hrs = ($room_type == 'twin') ? 0 : $_POST['price_6hrs'];
    $price_12hrs = $_POST['price_12hrs'];
    $price_24hrs = $_POST['price_24hrs'];
    $price_ot = $_POST['price_ot'];

    $query = "UPDATE rooms SET room_number = ?, room_type = ?, status = ?, 
              price_3hrs = ?, price_6hrs = ?, price_12hrs = ?, price_24hrs = ?, price_ot = ?
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssdddddi", $room_number, $room_type, $status, $price_3hrs, $price_6hrs, $price_12hrs, $price_24hrs, $price_ot, $room_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-room.php?success=edited");
    exit();
}

// Handle Archive
if (isset($_GET['action']) && $_GET['action'] == 'archive' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    
    // Archive the room instead of deleting
    $query = "UPDATE rooms SET is_archived = 1, archived_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-room.php?success=archived");
    exit();
}

// Handle Add Card
if (isset($_POST['add_card'])) {
    $room_id = (int)$_POST['room_id'];
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        header("Location: admin-room.php?error=empty_code");
        exit();
    }
    
    $query = "INSERT INTO cards (room_id, code) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $room_id, $code);
    
    if ($stmt->execute()) {
        header("Location: admin-room.php?success=card_added#addCardForm");
    } else {
        header("Location: admin-room.php?error=card_add_failed");
    }
    $stmt->close();
    exit();
}

// Handle Edit Card
if (isset($_POST['edit_card'])) {
    $card_id = (int)$_POST['card_id'];
    $room_id = (int)$_POST['room_id'];
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        header("Location: admin-room.php?error=empty_code");
        exit();
    }
    
    $query = "UPDATE cards SET room_id = ?, code = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isi", $room_id, $code, $card_id);
    
    if ($stmt->execute()) {
        header("Location: admin-room.php?success=card_edited#addCardForm");
    } else {
        header("Location: admin-room.php?error=card_edit_failed");
    }
    $stmt->close();
    exit();
}

// Handle Delete Card
if (isset($_GET['action']) && $_GET['action'] == 'delete_card' && isset($_GET['card_id'])) {
    $card_id = (int)$_GET['card_id'];
    
    $query = "DELETE FROM cards WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $card_id);
    
    if ($stmt->execute()) {
        header("Location: admin-room.php?success=card_deleted");
    } else {
        header("Location: admin-room.php?error=card_delete_failed");
    }
    $stmt->close();
    exit();
}

// Fetch room data
$result = $conn->query("SELECT * FROM rooms WHERE is_archived = 0 ORDER BY room_number ASC");

// Total rooms
$total_rooms = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE is_archived = 0")->fetch_assoc()['total'];

// Count maintenance rooms
$maintenance_rooms = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'maintenance' AND is_archived = 0")->fetch_assoc()['total'];

// Count available rooms
$available_rooms = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'available' AND is_archived = 0")->fetch_assoc()['total'];

// Room to edit
$room_to_edit = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit') {
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $_GET['room_id']);
    $stmt->execute();
    $room_to_edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Room Management</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">

    <style>
      .user-actions .action-btn.archive:hover {
  color: #f59e0b; /* amber/orange color */
}
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
  color: #2563eb; /* blue-600 */
}

.user-actions .action-btn.delete:hover {
  color: #dc2626; /* red-600 */
}

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

  /* === Sidebar Navigation === */
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

        /* Card styling */
#addRoomForm.card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    background: #fff;
}

/* Header */
#addRoomForm .card-header {
    background: #fff;
    border-bottom: none;
    padding: 1.25rem 1.5rem;
}

#addRoomForm .card-header h5 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1a202c;
}

#addRoomForm .card-header .btn-close {
    font-size: 0.9rem;
}

/* Labels */
#addRoomForm .form-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

/* Inputs and selects */
#addRoomForm .form-control,
#addRoomForm .form-select {
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 0.625rem 0.75rem;
    font-size: 0.95rem;
    color: #111827;
    background-color: #fff;
    box-shadow: none;
    transition: border-color 0.2s ease;
}

#addRoomForm .form-control:focus,
#addRoomForm .form-select:focus {
    border-color: #2563eb; /* blue-600 */
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

/* Section titles */
#addRoomForm h6 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #111827;
}

/* Buttons */
#addRoomForm .btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.55rem 1.25rem;
    font-size: 0.95rem;
}

#addRoomForm .btn-light {
    border: 1px solid #e5e7eb;
    color: #374151;
    background: #fff;
}

#addRoomForm .btn-light:hover {
    background: #f9fafb;
}

#addRoomForm .btn-primary {
    background: #2563eb;
    border: none;
}

#addRoomForm .btn-primary:hover {
    background: #1e40af;
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
      <a href="admin-room.php" class="active"><i class="fa-solid fa-bed"></i> Rooms</a>
      <a href="admin-report.php"><i class="fa-solid fa-file-lines"></i> Reports</a>
      <a href="admin-supplies.php"><i class="fa-solid fa-cube"></i> Supplies</a>
      <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
      <a href="admin-archive.php"><i class="fa-solid fa-archive"></i> Archived</a>
    </div>

    <div class="signout">
      <a href="admin-logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
  </div>

    <div class="content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Room Management</h2>
                <p class="text-muted mb-0">Manage room information and pricing</p>
            </div>
            <div class="clock-box text-end">
                <div id="currentDate" class="fw-semibold"></div>
                <div id="currentTime"></div>
            </div>
        </div>

        <!---- success message --->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
  <?php if (isset($_GET['success'])): ?>
    <div id="serverToastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          <?php
            if ($_GET['success'] == 'added') echo "Room added successfully!";
            if ($_GET['success'] == 'edited') echo "Room edited successfully!";
            if ($_GET['success'] == 'archived') echo "Room archived successfully!";
            if ($_GET['success'] == 'card_added') echo "Keycard added successfully!";
            if ($_GET['success'] == 'card_edited') echo "Keycard updated successfully!";
            if ($_GET['success'] == 'card_deleted') echo "Keycard deleted successfully!";
          ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'foreign_key_violation'): ?>
    <div id="serverToastError" class="toast align-items-center text-bg-danger border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-exclamation-triangle me-2"></i>
          Cannot delete this room because it has related check-in records.
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
</div>


<script>
document.addEventListener("DOMContentLoaded", () => {
  const serverToastSuccess = document.getElementById("serverToastSuccess");
  const serverToastError = document.getElementById("serverToastError");

  if (serverToastSuccess) {
    new bootstrap.Toast(serverToastSuccess, { delay: 4000 }).show();
  }
  if (serverToastError) {
    new bootstrap.Toast(serverToastError, { delay: 4000 }).show();
  }
});
</script>


<!-- Room Statistics Cards -->
<div class="row mb-4">
    <!-- Total Rooms -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Total Rooms</p>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-door-open"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?php echo $total_rooms; ?></h3>
            <p class="stat-change text-success">+4% <span>from last month</span></p>
        </div>
    </div>

    <!-- Available Rooms -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Available Rooms</p>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?php echo $available_rooms; ?></h3>
            <p class="stat-change text-success">+7% <span>from last month</span></p>
        </div>
    </div>

    <!-- Maintenance Rooms -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Maintenance Rooms</p>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?php echo $maintenance_rooms; ?></h3>
            <p class="stat-change text-danger">-1% <span>from last month</span></p>
        </div>
    </div>
</div>


<!-- Add/Edit Room Form -->
<div class="card shadow-sm border-0 mb-4" id="addRoomForm">
  <div class="card-header border-0 d-flex justify-content-between align-items-center" style="background-color: #871D2B;">
    <h5 class="mb-0 fw-semibold text-white">
      <i class="fas fa-bed text-white me-2"></i>
      <?php echo isset($room_to_edit) ? "Edit Room" : "Add New Room"; ?>
    </h5>
  </div>

  <div class="card-body">
    <form method="POST" action="admin-room.php" class="row g-4">
      <input type="hidden" name="room_id" value="<?php echo isset($room_to_edit) ? $room_to_edit['id'] : ''; ?>">

      <!-- Room Number -->
      <div class="col-12">
        <label for="room_number" class="form-label fw-semibold">Room Number *</label>
        <input type="text" id="room_number" name="room_number" class="form-control" 
               value="<?php echo isset($room_to_edit) ? $room_to_edit['room_number'] : ''; ?>" required>
      </div>

      <!-- Room Type + Status -->
      <div class="col-md-6">
        <label for="room_type" class="form-label fw-semibold">Room Type *</label>
        <select id="room_type" name="room_type" class="form-select" required>
          <!-- your PHP options untouched -->
          <option value="single" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'single' ? 'selected' : ''; ?>>Single Room</option>
          <option value="presidential_suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'presidential_suite' ? 'selected' : ''; ?>>Presidential Suite</option> 
          <option value="connecting_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'connecting_rooms' ? 'selected' : ''; ?>>Connecting Rooms</option> 
          <option value="quad_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'quad_room' ? 'selected' : ''; ?>>Quad Room</option> 
          <option value="deluxe_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'deluxe_room' ? 'selected' : ''; ?>>Deluxe Room</option> 
          <option value="double_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'double_room' ? 'selected' : ''; ?>>Double Room</option> 
          <option value="triple_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'triple_room' ? 'selected' : ''; ?>>Triple Room</option> 
          <option value="twin_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'twin_room' ? 'selected' : ''; ?>>Twin Room</option> 
          <option value="standard_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'standard_room' ? 'selected' : ''; ?>>Standard Room</option> 
          <option value="studio" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'studio' ? 'selected' : ''; ?>>Studio</option> <option value="suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'suite' ? 'selected' : ''; ?>>Suite</option> 
          <option value="queen_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'queen_room' ? 'selected' : ''; ?>>Queen Room</option> 
          <option value="executive_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'executive_room' ? 'selected' : ''; ?>>Executive Room</option> 
          <option value="suites" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'suites' ? 'selected' : ''; ?>>Suites</option> 
          <option value="accessible_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'accessible_room' ? 'selected' : ''; ?>>Accessible Room</option> 
          <option value="hollywood_twin_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'hollywood_twin_room' ? 'selected' : ''; ?>>Hollywood Twin Room</option> 
          <option value="king_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'king_room' ? 'selected' : ''; ?>>King Room</option> 
          <option value="studio_hotel_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'studio_hotel_rooms' ? 'selected' : ''; ?>>Studio Hotel Rooms</option> <option value="villa" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'villa' ? 'selected' : ''; ?>>Villa</option> 
          <option value="double_hotel_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'double_hotel_rooms' ? 'selected' : ''; ?>>Double Hotel Rooms</option> 
          <option value="honeymoon_suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'honeymoon_suite' ? 'selected' : ''; ?>>Honeymoon Suite</option> 
          <option value="penthouse_suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'penthouse_suite' ? 'selected' : ''; ?>>Penthouse Suite</option> 
          <option value="single_hotel_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'single_hotel_rooms' ? 'selected' : ''; ?>>Single Hotel Rooms</option> 
          <option value="adjoining_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'adjoining_room' ? 'selected' : ''; ?>>Adjoining Room</option>
          </select>
      </div>

      <div class="col-md-6">
        <label for="status" class="form-label fw-semibold">Status *</label>
        <select id="status" name="status" class="form-select" required>
          <option value="available" <?php echo isset($room_to_edit) && $room_to_edit['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
          <option value="maintenance" <?php echo isset($room_to_edit) && $room_to_edit['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
        </select>
      </div>

      <!-- Pricing Section -->
      <div class="col-12">
        <h6 class="fw-bold mt-3">
          <i class="fas fa-peso-sign me-2 text-success"></i>Pricing Information
        </h6>
      </div>

      <div class="col-md-3">
        <label for="price_3hrs" class="form-label">Price for 3 Hours *</label>
        <input type="number" step="0.01" id="price_3hrs" name="price_3hrs" class="form-control" 
              value="<?php echo isset($room_to_edit) ? $room_to_edit['price_3hrs'] : ''; ?>" required>
        <div class="invalid-feedback">Price must be at least 400</div>
      </div>

      <div class="col-md-3">
        <label for="price_6hrs" class="form-label">Price for 6 Hours *</label>
        <input type="number" step="0.01" id="price_6hrs" name="price_6hrs" class="form-control" 
              value="<?php echo isset($room_to_edit) ? $room_to_edit['price_6hrs'] : ''; ?>" required>
        <div class="invalid-feedback">Price must be at least 400</div>
      </div>

      <div class="col-md-3">
        <label for="price_12hrs" class="form-label">Price for 12 Hours *</label>
        <input type="number" step="0.01" id="price_12hrs" name="price_12hrs" class="form-control" 
              value="<?php echo isset($room_to_edit) ? $room_to_edit['price_12hrs'] : ''; ?>" required>
        <div class="invalid-feedback">Price must be at least 400</div>
      </div>

      <div class="col-md-3">
        <label for="price_24hrs" class="form-label">Price for 24 Hours *</label>
        <input type="number" step="0.01" id="price_24hrs" name="price_24hrs" class="form-control" 
              value="<?php echo isset($room_to_edit) ? $room_to_edit['price_24hrs'] : ''; ?>" required>
        <div class="invalid-feedback">Price must be at least 400</div>
      </div>

      <div class="col-md-6">
        <label for="price_ot" class="form-label">Overtime Price (per hour) *</label>
        <input type="number" step="0.01" id="price_ot" name="price_ot" class="form-control" 
              value="<?php echo isset($room_to_edit) ? $room_to_edit['price_ot'] : ''; ?>" required>
        <div class="invalid-feedback">Overtime Price must be at least 120</div>
      </div>


      <!-- Buttons -->
      <div class="col-12 d-flex justify-content-end gap-2 mt-3">
        <button type="submit" name="<?php echo isset($room_to_edit) ? 'edit_room' : 'add_room'; ?>" 
                class="btn text-white" style="background-color: #871D2B;">
          <?php echo isset($room_to_edit) ? 'Update Room' : 'Add Room'; ?>
        </button>
      </div>
    </form>
  </div>
</div>


<!-- Room Table -->
<div class="card">
  <div class="card-header bg-dark d-flex justify-content-between align-items-center p-3">
    <div>
      <h2 class="h5 mb-0 text-white">Room List</h2>
      <p class="text-sm text-white mt-1"><?php echo $result->num_rows; ?> total rooms</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <!-- Show Rooms -->
      <div id="customRoomLengthMenu"></div>

      <!-- Search -->
      <div class="position-relative">
        <input type="text" class="form-control ps-4" id="roomSearchInput" placeholder="Search rooms..." style="width: 200px;">
        <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-2 text-gray-400"></i>
      </div>

      <!-- Filter by status -->
      <select class="form-select" id="roomFilterSelect" style="width: 120px;">
        <option value="">Filter</option>
        <option value="available">Available</option>
        <option value="occupied">Occupied</option>
        <option value="maintenance">Maintenance</option>
      </select>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="roomTable" class="table table-hover align-middle mb-0" style="width:100%;">
        <thead class="bg-gray-50 border-bottom border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">Room Number</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">Room Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">Status</th>
            <th class="px-4 py-3 sorting">3hrs</th>
            <th class="px-4 py-3 sorting">6hrs</th>
            <th class="px-4 py-3 sorting">12hrs</th>
            <th class="px-4 py-3 sorting">24hrs</th>
            <th class="px-4 py-3 sorting">OT</th>
            <th class="px-4 py-3 text-center sorting">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($room = $result->fetch_assoc()): ?>
            <tr>
              <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($room['room_number']) ?></td>
              <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($room['room_type']) ?></td>
              <td class="px-4 py-3 text-sm">
                <span class="badge 
                  bg-<?= ($room['status'] == 'available') ? 'green-100' : (($room['status'] == 'occupied') ? 'amber-100' : 'gray-100') ?> 
                  text-<?= ($room['status'] == 'available') ? 'green-800' : (($room['status'] == 'occupied') ? 'amber-800' : 'gray-800') ?> 
                  border-<?= ($room['status'] == 'available') ? 'green-200' : (($room['status'] == 'occupied') ? 'amber-200' : 'gray-200') ?> 
                  rounded-pill px-2.5 py-0.5 text-xs font-medium">
                  <?= ucfirst($room['status']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-sm"><?= number_format($room['price_3hrs'], 2) ?></td>
              <td class="px-4 py-3 text-sm"><?= number_format($room['price_6hrs'], 2) ?></td>
              <td class="px-4 py-3 text-sm"><?= number_format($room['price_12hrs'], 2) ?></td>
              <td class="px-4 py-3 text-sm"><?= number_format($room['price_24hrs'], 2) ?></td>
              <td class="px-4 py-3 text-sm"><?= number_format($room['price_ot'], 2) ?></td>
              <td class="px-4 py-3 text-center">
                <div class="d-flex gap-2 justify-content-center user-actions">
                <a href="#" 
                  class="p-1 action-btn edit" 
                  title="Edit"
                  data-id="<?= $room['id'] ?>"
                  data-room_number="<?= htmlspecialchars($room['room_number']) ?>"
                  data-room_type="<?= htmlspecialchars($room['room_type']) ?>"
                  data-status="<?= htmlspecialchars($room['status']) ?>"
                  data-price_3hrs="<?= $room['price_3hrs'] ?>"
                  data-price_6hrs="<?= $room['price_6hrs'] ?>"
                  data-price_12hrs="<?= $room['price_12hrs'] ?>"
                  data-price_24hrs="<?= $room['price_24hrs'] ?>"
                  data-price_ot="<?= $room['price_ot'] ?>">
                  <i class="fas fa-edit"></i>
                </a>

<a href="javascript:void(0)" 
   onclick="confirmArchive(<?= $room['id'] ?>, '<?= htmlspecialchars($room['room_number']) ?>')"
   class="p-1 action-btn archive" title="Archive">
   <i class="fas fa-archive"></i>
</a>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Divider Section -->
<div class="my-5">
    <div class="d-flex align-items-center">
        <hr class="flex-grow-1 border-2">
        <span class="px-4 text-muted fw-semibold text-uppercase" style="font-size: 0.875rem; letter-spacing: 0.5px;">
            <i class="fas fa-grip-lines-vertical me-2"></i>
            Room Keycards
            <i class="fas fa-grip-lines-vertical ms-2"></i>
        </span>
        <hr class="flex-grow-1 border-2">
    </div>
</div>

<!-- KEYCARD MANAGEMENT SECTION -->
<div class="mt-5">
    <h3 class="fw-bold mb-4">Keycard Management</h3>
    
    <?php
    // Fetch all cards with room information
    $cardsQuery = "
        SELECT c.id, c.room_id, c.code, c.created_at, 
               r.room_number, r.room_type, r.status
        FROM cards c
        LEFT JOIN rooms r ON c.room_id = r.id
        WHERE r.is_archived = 0
        ORDER BY c.created_at DESC
    ";
    $cardsResult = $conn->query($cardsQuery);

    // Fetch all rooms for dropdown
    $roomsQuery = "SELECT id, room_number, room_type, status FROM rooms WHERE is_archived = 0 ORDER BY room_number ASC";
    $roomsResult = $conn->query($roomsQuery);
    $rooms = [];
    while ($room = $roomsResult->fetch_assoc()) {
        $rooms[] = $room;
    }

    // Keycard Statistics
    $totalCards = $conn->query("SELECT COUNT(*) as total FROM cards c JOIN rooms r ON c.room_id = r.id WHERE r.is_archived = 0")->fetch_assoc()['total'] ?? 0;
    $activeRooms = $conn->query("SELECT COUNT(DISTINCT room_id) as active FROM cards c JOIN rooms r ON c.room_id = r.id WHERE r.is_archived = 0")->fetch_assoc()['active'] ?? 0;
    $availableCards = $conn->query("SELECT COUNT(*) as available FROM cards c JOIN rooms r ON c.room_id = r.id WHERE r.status = 'available' AND r.is_archived = 0")->fetch_assoc()['available'] ?? 0;

    // Card to edit
    $card_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit_card') {
        $stmt = $conn->prepare("SELECT * FROM cards WHERE id = ?");
        $stmt->bind_param("i", $_GET['card_id']);
        $stmt->execute();
        $card_to_edit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    ?>

    <!-- Keycard Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Total Cards</p>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1"><?php echo $totalCards; ?></h3>
                <p class="stat-change text-success">+2% <span>from last month</span></p>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Rooms with Cards</p>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-door-open"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1"><?php echo $activeRooms; ?></h3>
                <p class="stat-change text-success">+5% <span>from last month</span></p>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="stat-title">Available Cards</p>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-1"><?php echo $availableCards; ?></h3>
                <p class="stat-change text-info">Ready to use</p>
            </div>
        </div>
    </div>

    <!-- Add/Edit Card Form -->
    <div class="card shadow-sm border-0 mb-4" id="addCardForm">
        <div class="card-header border-0 d-flex justify-content-between align-items-center" style="background-color: #871D2B;">
            <h5 class="mb-0 fw-semibold text-white">
                <i class="fas fa-id-card text-white me-2"></i>
                <?php echo isset($card_to_edit) ? "Edit Keycard" : "Add New Keycard"; ?>
            </h5>
        </div>

        <div class="card-body">
            <form method="POST" action="admin-room.php" class="row g-4">
                <input type="hidden" name="card_id" value="<?php echo isset($card_to_edit) ? $card_to_edit['id'] : ''; ?>">

                <div class="col-md-6">
                    <label for="room_id" class="form-label fw-semibold">Select Room *</label>
                    <select id="room_id" name="room_id" class="form-select" required>
                        <option value="">Choose a room...</option>
                        <?php foreach ($rooms as $room): ?>
                        <option value="<?= $room['id'] ?>" <?php echo isset($card_to_edit) && $card_to_edit['room_id'] == $room['id'] ? 'selected' : ''; ?>>
                            Room #<?= $room['room_number'] ?> - <?= ucwords(str_replace('_', ' ', $room['room_type'])) ?>
                            (<?= ucfirst($room['status']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="card_code" class="form-label fw-semibold">Card Code *</label>
                    <input type="text" id="card_code" name="code" class="form-control" 
                           placeholder="Enter card access code" 
                           value="<?php echo isset($card_to_edit) ? htmlspecialchars($card_to_edit['code']) : ''; ?>" 
                           required>
                    <small class="text-muted">This code will be used to access the room</small>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                    <?php if (isset($card_to_edit)): ?>
                    <a href="admin-room.php" class="btn btn-light">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" name="<?php echo isset($card_to_edit) ? 'edit_card' : 'add_card'; ?>" 
                            class="btn text-white" style="background-color: #871D2B;">
                        <?php echo isset($card_to_edit) ? 'Update Card' : 'Add Card'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards Table -->
    <div class="card">
        <div class="card-header bg-dark d-flex justify-content-between align-items-center p-3">
            <div>
                <h2 class="h5 mb-0 text-white">Keycard List</h2>
                <p class="text-sm text-white mt-1"><?php echo $cardsResult->num_rows; ?> total cards</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div id="customCardLengthMenu"></div>
                <div class="position-relative">
                    <input type="text" class="form-control ps-4" id="cardSearchInput" placeholder="Search cards..." style="width: 200px;">
                    <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-2 text-gray-400"></i>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="cardTable" class="table table-hover align-middle mb-0" style="width:100%;">
                    <thead class="bg-gray-50 border-bottom border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">Room Number</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">Room Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">Card Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting">Room Status</th>
                            <th class="px-4 py-3 sorting">Created At</th>
                            <th class="px-4 py-3 text-center sorting">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($card = $cardsResult->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900"><?= $card['id'] ?></td>
                            <td class="px-4 py-3 text-sm"><strong>#<?= htmlspecialchars($card['room_number']) ?></strong></td>
                            <td class="px-4 py-3 text-sm text-gray-900"><?= ucwords(str_replace('_', ' ', $card['room_type'])) ?></td>
                            <td class="px-4 py-3 text-sm"><span style="font-family: 'Courier New', monospace; font-weight: 600; background: #f8f9fa; padding: 4px 8px; border-radius: 4px;"><?= htmlspecialchars($card['code']) ?></span></td>
                            <td class="px-4 py-3 text-sm">
                                <span class="badge 
                                    bg-<?= ($card['status'] == 'available') ? 'green-100' : (($card['status'] == 'booked') ? 'amber-100' : 'gray-100') ?> 
                                    text-<?= ($card['status'] == 'available') ? 'green-800' : (($card['status'] == 'booked') ? 'amber-800' : 'gray-800') ?> 
                                    border-<?= ($card['status'] == 'available') ? 'green-200' : (($card['status'] == 'booked') ? 'amber-200' : 'gray-200') ?> 
                                    rounded-pill px-2.5 py-0.5 text-xs font-medium">
                                    <?= ucfirst($card['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm"><?= date('M d, Y h:i A', strtotime($card['created_at'])) ?></td>
                            <td class="px-4 py-3 text-center">
                                <div class="d-flex gap-2 justify-content-center user-actions">
                                  <a href="#" 
                                    class="p-1 action-btn edit" 
                                    title="Edit"
                                    data-card-id="<?= $card['id'] ?>"
                                    data-room-id="<?= $card['room_id'] ?>"
                                    data-room-number="<?= htmlspecialchars($card['room_number']) ?>"
                                    data-room-type="<?= ucwords(str_replace('_', ' ', $card['room_type'])) ?>"
                                    data-card-code="<?= htmlspecialchars($card['code']) ?>">
                                      <i class="fas fa-edit"></i>
                                  </a>
                                    <a href="javascript:void(0)" 
                                       onclick="confirmDeleteCard(<?= $card['id'] ?>, '<?= htmlspecialchars($card['room_number']) ?>')"
                                       class="p-1 action-btn delete" 
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Card Confirmation Modal -->
<div class="modal fade" id="deleteCardModal" tabindex="-1" aria-labelledby="deleteCardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title fw-bold text-danger" id="deleteCardModalLabel">
                    <i class="fas fa-trash me-2"></i>Delete Keycard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <hr class="my-0">
            
            <div class="modal-body text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="fas fa-trash text-danger fa-2x"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-2">Delete this keycard?</h5>
                <p class="text-muted mb-0">
                    Are you sure you want to delete the keycard for room <span id="cardToDelete" class="fw-semibold text-dark"></span>?<br>
                    This action cannot be undone.
                </p>
            </div>
            <hr class="my-0">
            
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteCardBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> Delete Card
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Edit Keycard Modal -->
<div class="modal fade" id="editCardModal" tabindex="-1" aria-labelledby="editCardModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      
      <!-- Header -->
      <div class="modal-header border-bottom">
        <div class="d-flex align-items-center">
          <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-2" style="width:40px; height:40px;">
            <i class="fas fa-id-card text-primary"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold mb-0" id="editCardModalLabel">Edit Keycard</h5>
            <small class="text-muted">Update card access code</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <form method="POST" action="admin-room.php" id="editCardForm">
          <input type="hidden" name="card_id" id="edit_card_id">
          <input type="hidden" name="room_id" id="edit_card_room_id">

          <!-- Room Number (Read-only) -->
          <div class="mb-3">
            <label for="edit_card_room_number" class="form-label fw-semibold">
              <i class="fas fa-door-open me-1 text-muted"></i> Room Number
            </label>
            <input type="text" id="edit_card_room_number" class="form-control bg-light" readonly>
            <small class="text-muted">This field cannot be modified</small>
          </div>

          <!-- Room Type (Read-only) -->
          <div class="mb-3">
            <label for="edit_card_room_type" class="form-label fw-semibold">
              <i class="fas fa-bed me-1 text-muted"></i> Room Type
            </label>
            <input type="text" id="edit_card_room_type" class="form-control bg-light" readonly>
            <small class="text-muted">This field cannot be modified</small>
          </div>

          <!-- Card Code (Editable) -->
          <div class="mb-3">
            <label for="edit_card_code" class="form-label fw-semibold">
              <i class="fas fa-key me-1 text-primary"></i> Card Code *
            </label>
            <input type="text" name="code" id="edit_card_code" class="form-control" 
                   placeholder="Enter new card access code" required>
            <small class="text-muted">This code will be used to access the room</small>
          </div>

          <!-- Info Alert -->
          <div class="alert alert-info d-flex align-items-start mb-0" role="alert">
            <i class="fas fa-info-circle me-2 mt-1"></i>
            <div>
              <strong>Note:</strong> Only the card code can be modified. To change the room assignment, please delete this card and create a new one.
            </div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="editCardForm" name="edit_card" class="btn text-white" style="background-color: #871D2B;">
          <i class="fas fa-save me-1"></i> Update Card
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="validationToast" class="toast align-items-center text-bg-danger border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body">
        Please fix pricing errors before submitting.
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1" aria-labelledby="editRoomModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      
      <!-- Header -->
      <div class="modal-header border-bottom">
        <div class="d-flex align-items-center">
          <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center me-2" style="width:40px; height:40px;">
            <i class="fas fa-bed text-dark"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold mb-0" id="editRoomModalLabel">Edit Room</h5>
            <small class="text-muted">Update room information</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <form method="POST" action="admin-room.php" id="editRoomForm" class="row g-4">
          <input type="hidden" name="room_id" id="edit_room_id">

          <!-- Room Number -->
          <div class="col-12">
            <label for="edit_room_number" class="form-label fw-semibold">Room Number *</label>
            <input type="text" name="room_number" id="edit_room_number" class="form-control" placeholder="e.g., 101, A-205" required>
          </div>

          <!-- Room Type & Status -->
          <div class="col-md-6">
            <label for="edit_room_type" class="form-label fw-semibold">Room Type *</label>
            <select name="room_type" id="edit_room_type" class="form-select" required>
              <option value="standard_room">Standard Room</option>
              <option value="single">Single Room</option>
              <option value="double_room">Double Room</option>
              <option value="deluxe_room">Deluxe Room</option>
              <option value="suite">Suite</option>
            </select>
          </div>

          <div class="col-md-6">
            <label for="edit_status" class="form-label fw-semibold">Status *</label>
            <select name="status" id="edit_status" class="form-select" required>
              <option value="available">Available</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>

          <!-- Pricing Section -->
          <div class="col-12">
            <h6 class="fw-bold mt-3">
              <i class="fas fa-dollar-sign text-success me-2"></i> Pricing Information
            </h6>
          </div>

          <div class="col-md-3">
            <label for="edit_price_3hrs" class="form-label">Price for 3 Hours *</label>
            <input type="number" step="0.01" id="edit_price_3hrs" name="price_3hrs" class="form-control" required>
            <div class="invalid-feedback">Minimum: 400</div>
          </div>

          <div class="col-md-3">
            <label for="edit_price_6hrs" class="form-label">Price for 6 Hours *</label>
            <input type="number" step="0.01" id="edit_price_6hrs" name="price_6hrs" class="form-control" required>
            <div class="invalid-feedback">Minimum: 400</div>
          </div>

          <div class="col-md-3">
            <label for="edit_price_12hrs" class="form-label">Price for 12 Hours *</label>
            <input type="number" step="0.01" id="edit_price_12hrs" name="price_12hrs" class="form-control" required>
            <div class="invalid-feedback">Minimum: 400</div>
          </div>

          <div class="col-md-3">
            <label for="edit_price_24hrs" class="form-label">Price for 24 Hours *</label>
            <input type="number" step="0.01" id="edit_price_24hrs" name="price_24hrs" class="form-control" required>
            <div class="invalid-feedback">Minimum: 400</div>
          </div>

          <div class="col-md-6">
            <label for="edit_price_ot" class="form-label">Overtime Price (per hour) *</label>
            <input type="number" step="0.01" id="edit_price_ot" name="price_ot" class="form-control" required>
            <div class="invalid-feedback">Minimum: 120</div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="editRoomForm" name="edit_room" class="btn btn-dark text-white">
          <i class="fas fa-save me-1"></i> Update Room
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;"> <!-- Reduced width -->
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Header -->
<div class="modal-header border-0 pb-2">
  <h5 class="modal-title fw-bold text-warning" id="deleteModalLabel">
    <i class="fas fa-archive me-2"></i>Archive Room
  </h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
      <hr class="my-0"> <!-- Line after header -->
      
      <!-- Body -->
<div class="modal-body text-center">
  <div class="mb-3">
    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px; height:60px;">
      <i class="fas fa-archive text-warning fa-2x"></i>
    </div>
  </div>
  <h5 class="fw-bold mb-2">Archive this room?</h5>
  <p class="text-muted mb-0">
    Are you sure you want to archive room <span id="roomToArchive" class="fw-semibold text-dark"></span>?<br>
    This room will be moved to the archive. You can restore or permanently delete it from the archive page.
  </p>
</div>
      <hr class="my-0"> <!-- Line before buttons -->
      
      <!-- Footer -->
<div class="modal-footer border-0 justify-content-center">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
  <a href="#" id="confirmArchiveBtn" class="btn btn-warning">
    <i class="fas fa-archive me-1"></i> Archive Room
  </a>
</div>
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

        function togglePriceFields() {
            const roomType = document.querySelector('[name="room_type"]').value;
            const disable = roomType === 'twin';
            document.querySelector('[name="price_3hrs"]').disabled = disable;
            document.querySelector('[name="price_6hrs"]').disabled = disable;

            if (disable) {
                document.querySelector('[name="price_3hrs"]').value = 0;
                document.querySelector('[name="price_6hrs"]').value = 0;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            togglePriceFields();
            document.querySelector('[name="room_type"]').addEventListener('change', togglePriceFields);
        });

        // Real-time clock updater (optional, if used)
      function updateClock() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-PH', options);
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-PH');
      }
      setInterval(updateClock, 1000);
      updateClock();


// Data table
$(document).ready(function() {
  var roomTable = $('#roomTable').DataTable({
    paging: true,
    lengthChange: true,
    searching: true,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: true,
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50, 100],
    dom: '<"d-none"l>rt' +
         '<"row mt-3"<"col-sm-5"i><"col-sm-7"p>>',
    language: {
      emptyTable: "<i class='fas fa-bed fa-3x text-muted mb-3'></i><p class='mb-0'>No rooms found</p>",
      info: "Showing _START_ to _END_ of _TOTAL_ rooms",
      infoEmpty: "No entries available",
      infoFiltered: "(filtered from _MAX_ total rooms)",
      lengthMenu: "Show _MENU_ rooms",
      paginate: {
        first:    "«",
        last:     "»",
        next:     "›",
        previous: "‹"
      }
    }
  });

  // Move dropdown
  roomTable.on('init', function () {
    var lengthSelect = $('#roomTable_length select')
      .addClass('form-select')
      .css('width','80px');

    $('#customRoomLengthMenu').html(
      '<label class="d-flex align-items-center gap-2 mb-0">' +
        '<span>Show</span>' +
        lengthSelect.prop('outerHTML') +
        '<span>rooms</span>' +
      '</label>'
    );

    $('#roomTable_length').hide();
  });

  // Custom search
  $('#roomSearchInput').on('keyup', function() {
    roomTable.search(this.value).draw();
  });

  // Filter select (Status column is index 2)
  $('#roomFilterSelect').on('change', function() {
    roomTable.column(2).search(this.value).draw();
  });

  // Sorting icons
  roomTable.on('order.dt', function() {
    $('th.sorting', roomTable.table().header()).removeClass('sorting_asc sorting_desc');
    roomTable.columns().every(function(index) {
      var order = roomTable.order()[0];
      if (order[0] === index) {
        $('th:eq(' + index + ')', roomTable.table().header())
          .addClass(order[1] === 'asc' ? 'sorting_asc' : 'sorting_desc');
      }
    });
  });
});

// pricing validation
function validatePrice(input) {
  const value = parseFloat(input.value);


  const isOvertime = input.name === "price_ot";
  const minValue = isOvertime ? 120 : 400;

  const invalid =
    (value === -1 || value === 0 || value === 1 || value < minValue);

  if (invalid || isNaN(value)) {
    input.classList.add("is-invalid");
    input.classList.remove("is-valid");
  } else {
    input.classList.remove("is-invalid");
    input.classList.add("is-valid");
  }
}


document.querySelectorAll("#price_3hrs, #price_6hrs, #price_12hrs, #price_24hrs, #price_ot")
  .forEach(input => {
    input.addEventListener("input", function() {
      validatePrice(this);
    });
  });


document.querySelector("form").addEventListener("submit", function(event) {
  let invalidFound = false;
  document.querySelectorAll("#price_3hrs, #price_6hrs, #price_12hrs, #price_24hrs, #price_ot")
    .forEach(input => {
      validatePrice(input);
      if (input.classList.contains("is-invalid")) {
        invalidFound = true;
      }
    });

if (invalidFound) {
  event.preventDefault();
  event.stopPropagation();
  const toastEl = document.getElementById("validationToast");
  const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
  toast.show();
}
});


// edit modal
document.querySelectorAll("#roomTable .action-btn.edit").forEach(btn => {
  btn.addEventListener("click", function(e) {
    e.preventDefault();

    // Fill form fields with data attributes
    document.getElementById("edit_room_id").value = this.dataset.id;
    document.getElementById("edit_room_number").value = this.dataset.room_number;
    document.getElementById("edit_room_type").value = this.dataset.room_type;
    document.getElementById("edit_status").value = this.dataset.status;
    document.getElementById("edit_price_3hrs").value = this.dataset.price_3hrs;
    document.getElementById("edit_price_6hrs").value = this.dataset.price_6hrs;
    document.getElementById("edit_price_12hrs").value = this.dataset.price_12hrs;
    document.getElementById("edit_price_24hrs").value = this.dataset.price_24hrs;
    document.getElementById("edit_price_ot").value = this.dataset.price_ot;

    // Show modal
    const editModal = new bootstrap.Modal(document.getElementById("editRoomModal"));
    editModal.show();
  });
});


  // archive modal 
function confirmArchive(roomId, roomNumber) {
  const archiveUrl = `admin-room.php?action=archive&room_id=${roomId}`;
  document.getElementById("roomToArchive").textContent = `#${roomNumber}`;
  document.getElementById("confirmArchiveBtn").setAttribute("href", archiveUrl);
  const modal = new bootstrap.Modal(document.getElementById("deleteModal"));
  modal.show();
}

// Keycard DataTable
$(document).ready(function() {
    var cardTable = $('#cardTable').DataTable({
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        responsive: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        dom: '<"d-none"l>rt' +
             '<"row mt-3"<"col-sm-5"i><"col-sm-7"p>>',
        language: {
            emptyTable: "<i class='fas fa-id-card fa-3x text-muted mb-3'></i><p class='mb-0'>No keycards found</p>",
            info: "Showing _START_ to _END_ of _TOTAL_ cards",
            infoEmpty: "No entries available",
            infoFiltered: "(filtered from _MAX_ total cards)",
            lengthMenu: "Show _MENU_ cards",
            paginate: {
                first: "«",
                last: "»",
                next: "›",
                previous: "‹"
            }
        }
    });

    // Move dropdown
    cardTable.on('init', function () {
        var lengthSelect = $('#cardTable_length select')
            .addClass('form-select')
            .css('width','80px');

        $('#customCardLengthMenu').html(
            '<label class="d-flex align-items-center gap-2 mb-0">' +
                '<span>Show</span>' +
                lengthSelect.prop('outerHTML') +
                '<span>cards</span>' +
            '</label>'
        );

        $('#cardTable_length').hide();
    });

    // Custom search
    $('#cardSearchInput').on('keyup', function() {
        cardTable.search(this.value).draw();
    });

    // Sorting icons
    cardTable.on('order.dt', function() {
        $('th.sorting', cardTable.table().header()).removeClass('sorting_asc sorting_desc');
        cardTable.columns().every(function(index) {
            var order = cardTable.order()[0];
            if (order[0] === index) {
                $('th:eq(' + index + ')', cardTable.table().header())
                    .addClass(order[1] === 'asc' ? 'sorting_asc' : 'sorting_desc');
            }
        });
    });
});

// Delete card confirmation
function confirmDeleteCard(cardId, roomNumber) {
    const deleteUrl = `admin-room.php?action=delete_card&card_id=${cardId}`;
    document.getElementById("cardToDelete").textContent = `#${roomNumber}`;
    document.getElementById("confirmDeleteCardBtn").setAttribute("href", deleteUrl);
    const modal = new bootstrap.Modal(document.getElementById("deleteCardModal"));
    modal.show();
}

// Edit Card Modal Handler
document.addEventListener('DOMContentLoaded', function() {
  // Specifically target only edit buttons in the cardTable
  document.querySelectorAll('#cardTable .action-btn.edit').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation(); // Stop event from bubbling up
      
      // Get data from data attributes
      const cardId = this.dataset.cardId;
      const roomId = this.dataset.roomId;
      const roomNumber = this.dataset.roomNumber;
      const roomType = this.dataset.roomType;
      const cardCode = this.dataset.cardCode;
      
      // Fill the modal form fields
      document.getElementById('edit_card_id').value = cardId;
      document.getElementById('edit_card_room_id').value = roomId;
      document.getElementById('edit_card_room_number').value = '#' + roomNumber;
      document.getElementById('edit_card_room_type').value = roomType;
      document.getElementById('edit_card_code').value = cardCode;
      
      // Show the modal
      const editModal = new bootstrap.Modal(document.getElementById('editCardModal'));
      editModal.show();
    });
  });
});


    </script>
</div>
