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

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    // Check for related records in the checkins table
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM checkins WHERE room_number = (SELECT room_number FROM rooms WHERE id = ?)");
    $check_stmt->bind_param("i", $room_id);
    $check_stmt->execute();
    $related_count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();

    if ($related_count > 0) {
        header("Location: admin-room.php?error=foreign_key_violation");
        exit();
    }

    // Proceed with deletion if no related records
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin-room.php?success=deleted");
    exit();
}

// Fetch room data
$result = $conn->query("SELECT * FROM rooms ORDER BY room_number ASC");

// Total rooms
$total_rooms = $conn->query("SELECT COUNT(*) as total FROM rooms")->fetch_assoc()['total'];

// Count maintenance rooms
$maintenance_rooms = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'maintenance'")->fetch_assoc()['total'];

// Count available rooms
$available_rooms = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'available'")->fetch_assoc()['total'];

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>

<style>
    .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
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

        /* centering the dashboard content */
        .sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
        }

        .content {
            margin-left: 265px;
            max-width: 1400px;
            margin-right: auto;
        }

@media (max-width: 768px) {
    .table-responsive {
        display: block;
        overflow-x: auto;
    }
}
</style>


<body>
    <div class="sidebar" id="sidebar">
        <div class="user-info mb-4">
            <i class="fa-solid fa-user-circle mb-2"></i>
            <h5 class="mb-1">Welcome,</h5>
            <p id="user-role" class="mb-0">Admin</p>
        </div>
        <a href="admin-dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="admin-user.php"><i class="fa-solid fa-users"></i> Users</a>
        <a href="admin-room.php" class="active"><i class="fa-solid fa-bed"></i> Rooms</a>
        <a href="admin-report.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
        <a href="admin-supplies.php"><i class="fa-solid fa-boxes-stacked"></i> Supplies</a>
        <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
        <a href="admin-logout.php" class="mt-auto text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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

        <!-- Success Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php
                    if ($_GET['success'] == 'added') echo "Room added successfully!";
                    if ($_GET['success'] == 'edited') echo "Room edited successfully!";
                    if ($_GET['success'] == 'deleted') echo "Room deleted successfully!";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && $_GET['error'] == 'foreign_key_violation'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot delete this room because it has related check-in records.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Room Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-door-open text-white fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Rooms</h6>
                            <h2 class="mb-0"><?php echo $total_rooms; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-check-circle text-white fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Available Rooms</h6>
                            <h2 class="mb-0"><?php echo $available_rooms; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-tools text-white fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Maintenance Rooms</h6>
                            <h2 class="mb-0"><?php echo $maintenance_rooms; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Room Form -->
        <div class="card mb-4" id="addRoomForm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo isset($room_to_edit) ? "Edit Room" : "Add New Room"; ?></h5>
                <i class="fas fa-<?php echo isset($room_to_edit) ? "edit" : "plus-circle"; ?>"></i>
            </div>
            <div class="card-body">
                <form method="POST" action="admin-room.php" class="row g-3">
            <input type="hidden" name="room_id" value="<?php echo isset($room_to_edit) ? $room_to_edit['id'] : ''; ?>">

            <div class="mb-3">
                <label for="room_number" class="form-label">Room Number</label>
                <input type="text" name="room_number" class="form-control" value="<?php echo isset($room_to_edit) ? $room_to_edit['room_number'] : ''; ?>" required>
            </div>

            <div class="mb-3">
                <label for="room_type" class="form-label">Room Type</label>
                <select name="room_type" class="form-select" required>
                    <option value="single" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'single' ? 'selected' : ''; ?>>Single Room</option>
                    <option value="presidential_suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'presidential_suite' ? 'selected' : ''; ?>>Presidential Suite</option>
                    <option value="connecting_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'connecting_rooms' ? 'selected' : ''; ?>>Connecting Rooms</option>
                    <option value="quad_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'quad_room' ? 'selected' : ''; ?>>Quad Room</option>
                    <option value="deluxe_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'deluxe_room' ? 'selected' : ''; ?>>Deluxe Room</option>
                    <option value="double_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'double_room' ? 'selected' : ''; ?>>Double Room</option>
                    <option value="triple_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'triple_room' ? 'selected' : ''; ?>>Triple Room</option>
                    <option value="twin_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'twin_room' ? 'selected' : ''; ?>>Twin Room</option>
                    <option value="standard_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'standard_room' ? 'selected' : ''; ?>>Standard Room</option>
                    <option value="studio" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'studio' ? 'selected' : ''; ?>>Studio</option>
                    <option value="suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'suite' ? 'selected' : ''; ?>>Suite</option>
                    <option value="queen_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'queen_room' ? 'selected' : ''; ?>>Queen Room</option>
                    <option value="executive_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'executive_room' ? 'selected' : ''; ?>>Executive Room</option>
                    <option value="suites" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'suites' ? 'selected' : ''; ?>>Suites</option>
                    <option value="accessible_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'accessible_room' ? 'selected' : ''; ?>>Accessible Room</option>
                    <option value="hollywood_twin_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'hollywood_twin_room' ? 'selected' : ''; ?>>Hollywood Twin Room</option>
                    <option value="king_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'king_room' ? 'selected' : ''; ?>>King Room</option>
                    <option value="studio_hotel_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'studio_hotel_rooms' ? 'selected' : ''; ?>>Studio Hotel Rooms</option>
                    <option value="villa" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'villa' ? 'selected' : ''; ?>>Villa</option>
                    <option value="double_hotel_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'double_hotel_rooms' ? 'selected' : ''; ?>>Double Hotel Rooms</option>
                    <option value="honeymoon_suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'honeymoon_suite' ? 'selected' : ''; ?>>Honeymoon Suite</option>
                    <option value="penthouse_suite" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'penthouse_suite' ? 'selected' : ''; ?>>Penthouse Suite</option>
                    <option value="single_hotel_rooms" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'single_hotel_rooms' ? 'selected' : ''; ?>>Single Hotel Rooms</option>
                    <option value="adjoining_room" <?php echo isset($room_to_edit) && $room_to_edit['room_type'] == 'adjoining_room' ? 'selected' : ''; ?>>Adjoining Room</option>
                </select>
                
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="available" <?php echo isset($room_to_edit) && $room_to_edit['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="maintenance" <?php echo isset($room_to_edit) && $room_to_edit['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                </select>
            </div>

            <!-- Prices Section -->
            <div class="mb-3">
                <label for="price_3hrs" class="form-label">Price for 3hrs</label>
                <input type="number" step="0.01" name="price_3hrs" class="form-control" value="<?php echo isset($room_to_edit) ? $room_to_edit['price_3hrs'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="price_6hrs" class="form-label">Price for 6hrs</label>
                <input type="number" step="0.01" name="price_6hrs" class="form-control" value="<?php echo isset($room_to_edit) ? $room_to_edit['price_6hrs'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="price_12hrs" class="form-label">Price for 12hrs</label>
                <input type="number" step="0.01" name="price_12hrs" class="form-control" value="<?php echo isset($room_to_edit) ? $room_to_edit['price_12hrs'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="price_24hrs" class="form-label">Price for 24hrs</label>
                <input type="number" step="0.01" name="price_24hrs" class="form-control" value="<?php echo isset($room_to_edit) ? $room_to_edit['price_24hrs'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="price_ot" class="form-label">Overtime Price</label>
                <input type="number" step="0.01" name="price_ot" class="form-control" value="<?php echo isset($room_to_edit) ? $room_to_edit['price_ot'] : ''; ?>" required>
            </div>

            <button type="submit" name="<?php echo isset($room_to_edit) ? 'edit_room' : 'add_room'; ?>" class="btn btn-success">
                <?php echo isset($room_to_edit) ? 'Update Room' : 'Add Room'; ?>
            </button>
        </form>
    </div>

<!-- Room Table -->
<div class="card">
  <div class="card-header bg-light d-flex justify-content-between align-items-center p-3">
    <div>
      <h2 class="h5 mb-0 text-gray-900">Room List</h2>
      <p class="text-sm text-gray-600 mt-1"><?php echo $result->num_rows; ?> total rooms</p>
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
                  <a href="admin-room.php?action=edit&room_id=<?= $room['id'] ?>" class="p-1 action-btn edit" title="Edit">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="admin-room.php?action=delete&room_id=<?= $room['id'] ?>" class="p-1 action-btn delete" title="Delete">
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

    </script>
</div>
