<?php
session_start();
require_once 'database.php'; 

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is an admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: signin.php");
    exit();
}

// ====== RESTORE USER ======
if (isset($_GET['action']) && $_GET['action'] == 'restore_user' && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $query = "UPDATE users SET is_archived = 0, archived_at = NULL WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin-archive.php?tab=users&success=restored");
    exit();
}

// ====== PERMANENTLY DELETE USER ======
if (isset($_GET['action']) && $_GET['action'] == 'delete_user' && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $query = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: admin-archive.php?tab=users&success=deleted");
    } else {
        $stmt->close();
        header("Location: admin-archive.php?tab=users&error=foreign_key_violation");
    }
    exit();
}

// ====== RESTORE ROOM ======
if (isset($_GET['action']) && $_GET['action'] == 'restore_room' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    $query = "UPDATE rooms SET is_archived = 0, archived_at = NULL WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin-archive.php?tab=rooms&success=restored");
    exit();
}

// ====== PERMANENTLY DELETE ROOM ======
if (isset($_GET['action']) && $_GET['action'] == 'delete_room' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    $query = "DELETE FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $room_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: admin-archive.php?tab=rooms&success=deleted");
    } else {
        $stmt->close();
        header("Location: admin-archive.php?tab=rooms&error=foreign_key_violation");
    }
    exit();
}

// ====== RESTORE SUPPLY ======
if (isset($_GET['action']) && $_GET['action'] == 'restore_supply' && isset($_GET['supply_id'])) {
    $supply_id = $_GET['supply_id'];
    $query = "UPDATE supplies SET is_archived = 0, archived_at = NULL WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $supply_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin-archive.php?tab=supplies&success=restored");
    exit();
}

// ====== PERMANENTLY DELETE SUPPLY ======
if (isset($_GET['action']) && $_GET['action'] == 'delete_supply' && isset($_GET['supply_id'])) {
    $supply_id = $_GET['supply_id'];
    $query = "DELETE FROM supplies WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $supply_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: admin-archive.php?tab=supplies&success=deleted");
    } else {
        $stmt->close();
        header("Location: admin-archive.php?tab=supplies&error=foreign_key_violation");
    }
    exit();
}

// Count archived items
$archived_users_query = "SELECT COUNT(*) as total FROM users WHERE is_archived = 1";
$archived_users_result = $conn->query($archived_users_query);
$archived_users = $archived_users_result->fetch_assoc()['total'];

$archived_rooms_query = "SELECT COUNT(*) as total FROM rooms WHERE is_archived = 1";
$archived_rooms_result = $conn->query($archived_rooms_query);
$archived_rooms = $archived_rooms_result->fetch_assoc()['total'];

$archived_supplies_query = "SELECT COUNT(*) as total FROM supplies WHERE is_archived = 1";
$archived_supplies_result = $conn->query($archived_supplies_query);
$archived_supplies = $archived_supplies_result->fetch_assoc()['total'];

// Fetch archived data
$users_result = $conn->query("SELECT * FROM users WHERE is_archived = 1 ORDER BY archived_at DESC");
$rooms_result = $conn->query("SELECT * FROM rooms WHERE is_archived = 1 ORDER BY archived_at DESC");
$supplies_result = $conn->query("SELECT * FROM supplies WHERE is_archived = 1 ORDER BY archived_at DESC");

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - Archive Management</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>

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

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 0.75rem;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
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
}

.bg-blue-100 { background-color: #ebf8ff; }
.text-blue-800 { color: #2b6cb0; }
.border-blue-200 { border-color: #bee3f8; }
.bg-green-100 { background-color: #f0fff4; }
.text-green-800 { color: #2f855a; }
.border-green-200 { border-color: #c6f6d5; }
.bg-gray-100 { background-color: #f7fafc; }
.text-gray-800 { color: #2d3748; }
.border-gray-200 { border-color: #edf2f7; }

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.user-actions .action-btn {
  color: #9b9da2ff;                
  transition: color .15s ease;   
  text-decoration: none;
  cursor: pointer;
}

.user-actions .action-btn.restore:hover {
  color: #10b981;
}

.user-actions .action-btn.delete:hover {
  color: #dc2626;
}

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

.sidebar h4 {
  text-align: center;
  font-weight: 700;
  color: #111827;
  margin-bottom: 30px;
}

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

.nav-links a:hover {
    background: #f3f4f6;
    color: #111827;
}

.nav-links a:hover i {
    color: #111827;
}

.nav-links a.active {
    background: #871D2B;
    color: #fff;
}

.nav-links a.active i {
    color: #fff;
}

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

.signout a:hover {
    background: #f3f4f6;
    color: #dc2626;
}

.content {
  margin-left: 270px;
  padding: 30px;
  max-width: 1400px;
}

.nav-tabs .nav-link {
    color: #6b7280;
    border: none;
    border-bottom: 2px solid transparent;
}

.nav-tabs .nav-link:hover {
    border-color: #e5e7eb;
}

.nav-tabs .nav-link.active {
    color: #871D2B;
    border-color: #871D2B;
    background: transparent;
}

/* DataTables custom styling */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    display: none;
}

/* Force pagination to always be visible */
.dataTables_wrapper .dataTables_paginate,
.dataTables_wrapper .dataTables_info {
    display: block !important;
    visibility: visible !important;
}

.dataTables_paginate .pagination {
    display: flex !important;
    visibility: visible !important;
}

.card-header.with-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px !important;
}

.card-header .header-title {
    margin: 0;
    font-size: 18px;
}

.table-controls {
    display: flex;
    align-items: center;
    gap: 20px;
}

.table-controls .entries-control {
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-controls .entries-control label {
    margin: 0;
    font-size: 14px;
    color: #fff;
}

.table-controls .entries-control select {
    padding: 6px 30px 6px 12px;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    background-color: rgba(255,255,255,0.1);
    color: #fff;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='white' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}

.table-controls .entries-control select option {
    background-color: #343a40;
    color: #fff;
}

.table-controls .search-control {
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-controls .search-control input {
    padding: 6px 12px;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 6px;
    font-size: 14px;
    width: 200px;
    transition: all 0.2s;
    background-color: rgba(255,255,255,0.1);
    color: #fff;
}

.table-controls .search-control input::placeholder {
    color: rgba(255,255,255,0.6);
}

.table-controls .search-control input:focus {
    outline: none;
    border-color: rgba(255,255,255,0.5);
    background-color: rgba(255,255,255,0.15);
}

.table-controls .search-control label {
    margin: 0;
    font-size: 14px;
    color: #fff;
}

.dataTables_wrapper .dataTables_info {
    padding: 15px 20px;
    color: #6b7280;
    font-size: 14px;
}

.dataTables_wrapper .dataTables_paginate {
    padding: 15px 20px;
}

@media (max-width: 768px) {
    .table-responsive {
        display: block;
        overflow-x: auto;
    }
    
    .card-header.with-controls {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .table-controls {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .table-controls .search-control input {
        width: 100%;
    }
}
</style>

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
      <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
      <a href="admin-archive.php" class="active"><i class="fa-solid fa-archive"></i> Archive</a>
    </div>

    <div class="signout">
      <a href="admin-logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
  </div>

    <div class="content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Archive Management</h2>
                <p class="text-muted mb-0">Manage archived records - Users, Rooms & Supplies</p>
            </div>
            <div class="clock-box text-end">
                <div id="currentDate" class="fw-semibold"></div>
                <div id="currentTime"></div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Archived Users</p>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $archived_users; ?></h3>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Archived Rooms</p>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-bed"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $archived_rooms; ?></h3>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="stat-title">Archived Supplies</p>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-cube"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $archived_supplies; ?></h3>
                </div>
            </div>
        </div>

        <!-- Toast Messages -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
            <?php if (isset($_GET['success'])): ?>
                <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php
                                if ($_GET['success'] == 'restored') echo "Item restored successfully!";
                                if ($_GET['success'] == 'deleted') echo "Item permanently deleted!";
                            ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] == 'foreign_key_violation'): ?>
                <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Cannot delete this item because it has related records.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="archiveTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab == 'users' ? 'active' : ''; ?>" 
                        id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">
                    <i class="fas fa-users me-2"></i>Users (<?php echo $archived_users; ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab == 'rooms' ? 'active' : ''; ?>" 
                        id="rooms-tab" data-bs-toggle="tab" data-bs-target="#rooms" type="button">
                    <i class="fas fa-bed me-2"></i>Rooms (<?php echo $archived_rooms; ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab == 'supplies' ? 'active' : ''; ?>" 
                        id="supplies-tab" data-bs-toggle="tab" data-bs-target="#supplies" type="button">
                    <i class="fas fa-cube me-2"></i>Supplies (<?php echo $archived_supplies; ?>)
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="archiveTabContent">
            
            <!-- USERS TAB -->
            <div class="tab-pane fade <?php echo $active_tab == 'users' ? 'show active' : ''; ?>" id="users" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-dark text-white with-controls">
                        <h5 class="header-title"><i class="fas fa-users me-2"></i>Archived Users</h5>
                        <div class="table-controls">
                            <div class="entries-control">
                                <label for="usersLength">Show</label>
                                <select id="usersLength">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span style="color: #fff;">entries</span>
                            </div>
                            <div class="search-control">
                                <label for="usersSearch">Search:</label>
                                <input type="text" id="usersSearch" placeholder="Search users...">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 archive-table" id="usersTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3">Name</th>
                                        <th class="px-4 py-3">Username</th>
                                        <th class="px-4 py-3">Email</th>
                                        <th class="px-4 py-3">Role</th>
                                        <th class="px-4 py-3">Archived Date</th>
                                        <th class="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users_result->num_rows > 0): ?>
                                        <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                        <span class="text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                                                    </div>
                                                    <?= htmlspecialchars($user['name']) ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3"><?= htmlspecialchars($user['username']) ?></td>
                                            <td class="px-4 py-3"><?= htmlspecialchars($user['email']) ?></td>
                                            <td class="px-4 py-3">
                                                <span class="badge bg-gray-100 text-gray-800 border-gray-200 rounded-pill">
                                                    <?= htmlspecialchars($user['role']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?= $user['archived_at'] ? date('M d, Y h:i A', strtotime($user['archived_at'])) : 'N/A' ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="d-flex gap-2 justify-content-center user-actions">
                                                    <a href="javascript:void(0)" onclick="confirmRestore('user', <?= $user['user_id'] ?>)" 
                                                       class="p-1 action-btn restore" title="Restore">
                                                        <i class="fas fa-undo"></i>
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="confirmDelete('user', <?= $user['user_id'] ?>)" 
                                                       class="p-1 action-btn delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <p class="mb-0">No archived users found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROOMS TAB -->
            <div class="tab-pane fade <?php echo $active_tab == 'rooms' ? 'show active' : ''; ?>" id="rooms" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-dark text-white with-controls">
                        <h5 class="header-title"><i class="fas fa-bed me-2"></i>Archived Rooms</h5>
                        <div class="table-controls">
                            <div class="entries-control">
                                <label for="roomsLength">Show</label>
                                <select id="roomsLength">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span style="color: #fff;">entries</span>
                            </div>
                            <div class="search-control">
                                <label for="roomsSearch">Search:</label>
                                <input type="text" id="roomsSearch" placeholder="Search rooms...">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 archive-table" id="roomsTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3">Room Number</th>
                                        <th class="px-4 py-3">Room Type</th>
                                        <th class="px-4 py-3">Price</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Archived Date</th>
                                        <th class="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rooms_result->num_rows > 0): ?>
                                        <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-4 py-3 fw-bold"><?= htmlspecialchars($room['room_number']) ?></td>
                                            <td class="px-4 py-3"><?= htmlspecialchars($room['room_type']) ?></td>
                                            <td class="px-4 py-3">
                                                ₱<?= number_format($room['price_24hrs'], 2) ?>
                                                <small class="text-muted d-block">
                                                    3hrs: ₱<?= number_format($room['price_3hrs'], 2) ?> | 
                                                    12hrs: ₱<?= number_format($room['price_12hrs'], 2) ?>
                                                </small>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="badge bg-gray-100 text-gray-800 border-gray-200 rounded-pill">
                                                    <?= htmlspecialchars($room['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?= $room['archived_at'] ? date('M d, Y h:i A', strtotime($room['archived_at'])) : 'N/A' ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="d-flex gap-2 justify-content-center user-actions">
                                                    <a href="javascript:void(0)" onclick="confirmRestore('room', <?= $room['id'] ?>)" 
                                                       class="p-1 action-btn restore" title="Restore">
                                                        <i class="fas fa-undo"></i>
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="confirmDelete('room', <?= $room['id'] ?>)" 
                                                       class="p-1 action-btn delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                                                <p class="mb-0">No archived rooms found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUPPLIES TAB -->
            <div class="tab-pane fade <?php echo $active_tab == 'supplies' ? 'show active' : ''; ?>" id="supplies" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-dark text-white with-controls">
                        <h5 class="header-title"><i class="fas fa-cube me-2"></i>Archived Supplies</h5>
                        <div class="table-controls">
                            <div class="entries-control">
                                <label for="suppliesLength">Show</label>
                                <select id="suppliesLength">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span style="color: #fff;">entries</span>
                            </div>
                            <div class="search-control">
                                <label for="suppliesSearch">Search:</label>
                                <input type="text" id="suppliesSearch" placeholder="Search supplies...">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 archive-table" id="suppliesTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3">Supply Name</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Price</th>
                                        <th class="px-4 py-3">Quantity</th>
                                        <th class="px-4 py-3">Archived Date</th>
                                        <th class="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($supplies_result->num_rows > 0): ?>
                                        <?php while ($supply = $supplies_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-4 py-3 fw-bold"><?= htmlspecialchars($supply['name']) ?></td>
                                            <td class="px-4 py-3">
                                                <span class="badge bg-gray-100 text-gray-800 border-gray-200 rounded-pill">
                                                    <?= htmlspecialchars($supply['category']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">₱<?= number_format($supply['price'], 2) ?></td>
                                            <td class="px-4 py-3"><?= htmlspecialchars($supply['quantity']) ?></td>
                                            <td class="px-4 py-3">
                                                <?= $supply['archived_at'] ? date('M d, Y h:i A', strtotime($supply['archived_at'])) : 'N/A' ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="d-flex gap-2 justify-content-center user-actions">
                                                    <a href="javascript:void(0)" onclick="confirmRestore('supply', <?= $supply['id'] ?>)" 
                                                       class="p-1 action-btn restore" title="Restore">
                                                        <i class="fas fa-undo"></i>
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="confirmDelete('supply', <?= $supply['id'] ?>)" 
                                                       class="p-1 action-btn delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-cube fa-3x text-muted mb-3"></i>
                                                <p class="mb-0">No archived supplies found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title fw-bold text-success">
                    <i class="fas fa-undo me-2"></i>Restore Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <hr class="my-0">
            <div class="modal-body text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="fas fa-undo text-success fa-2x"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-2">Restore this item?</h5>
                <p class="text-muted mb-0">
                    This item will be restored to the active list.
                </p>
            </div>
            <hr class="my-0">
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="restoreLink" class="btn btn-success">
                    <i class="fas fa-undo me-1"></i> Restore
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Permanent Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <hr class="my-0">
            <div class="modal-body text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="fas fa-trash-alt text-danger fa-2x"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-2">Permanently delete this item?</h5>
                <p class="text-muted mb-0">
                    <strong>Warning:</strong> This action cannot be undone!<br>
                    All data will be permanently removed from the database.
                </p>
            </div>
            <hr class="my-0">
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteLink" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> Delete Permanently
                </a>
            </div>
        </div>
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Clock
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').innerText = now.toLocaleDateString('en-PH', options);
            document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-PH');
        }
        setInterval(updateClock, 1000);
        updateClock();
        
        // Restore confirmation
        function confirmRestore(type, id) {
            const actions = {
                'user': `admin-archive.php?action=restore_user&user_id=${id}`,
                'room': `admin-archive.php?action=restore_room&room_id=${id}`,
                'supply': `admin-archive.php?action=restore_supply&supply_id=${id}`
            };
            
            document.getElementById('restoreLink').href = actions[type];
            var restoreModal = new bootstrap.Modal(document.getElementById('restoreModal'));
            restoreModal.show();
        }
        
        // Delete confirmation
        function confirmDelete(type, id) {
            const actions = {
                'user': `admin-archive.php?action=delete_user&user_id=${id}`,
                'room': `admin-archive.php?action=delete_room&room_id=${id}`,
                'supply': `admin-archive.php?action=delete_supply&supply_id=${id}`
            };
            
            document.getElementById('deleteLink').href = actions[type];
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }


// DataTables initialization
$(document).ready(function() {
    const toastSuccess = document.getElementById('toastSuccess');
    const toastError = document.getElementById('toastError');
    
    if (toastSuccess) new bootstrap.Toast(toastSuccess, { delay: 4000 }).show();
    if (toastError) new bootstrap.Toast(toastError, { delay: 4000 }).show();

    // DataTable default settings to always show pagination
    $.extend(true, $.fn.dataTable.defaults, {
        pagingType: 'simple_numbers'
    });

    // Initialize each table
    var usersTable = $('#usersTable').DataTable({
        paging: true,
        lengthChange: false,
        searching: true,
        ordering: true,
        info: true,
        pageLength: 10,
        dom: 'rtip',
        destroy: true,
        stateSave: false,
        language: {
            emptyTable: "<div style='padding: 40px;'><i class='fas fa-users fa-3x text-muted mb-3 d-block'></i><p class='mb-0'>No archived users found</p></div>",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            infoEmpty: "Showing 0 to 0 of 0 users",
            zeroRecords: "<div style='padding: 40px;'><i class='fas fa-users fa-3x text-muted mb-3 d-block'></i><p class='mb-0'>No matching users found</p></div>",
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });

    var roomsTable = $('#roomsTable').DataTable({
        paging: true,
        lengthChange: false,
        searching: true,
        ordering: true,
        info: true,
        pageLength: 10,
        dom: 'rtip',
        destroy: true,
        stateSave: false,
        language: {
            emptyTable: "<div style='padding: 40px;'><i class='fas fa-bed fa-3x text-muted mb-3 d-block'></i><p class='mb-0'>No archived rooms found</p></div>",
            info: "Showing _START_ to _END_ of _TOTAL_ rooms",
            infoEmpty: "Showing 0 to 0 of 0 rooms",
            zeroRecords: "<div style='padding: 40px;'><i class='fas fa-bed fa-3x text-muted mb-3 d-block'></i><p class='mb-0'>No matching rooms found</p></div>",
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });

    var suppliesTable = $('#suppliesTable').DataTable({
        paging: true,
        lengthChange: false,
        searching: true,
        ordering: true,
        info: true,
        pageLength: 10,
        dom: 'rtip',
        destroy: true,
        stateSave: false,
        language: {
            emptyTable: "<div style='padding: 40px;'><i class='fas fa-cube fa-3x text-muted mb-3 d-block'></i><p class='mb-0'>No archived supplies found</p></div>",
            info: "Showing _START_ to _END_ of _TOTAL_ supplies",
            infoEmpty: "Showing 0 to 0 of 0 supplies",
            zeroRecords: "<div style='padding: 40px;'><i class='fas fa-cube fa-3x text-muted mb-3 d-block'></i><p class='mb-0'>No matching supplies found</p></div>",
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });

    // Force pagination to show after initialization
    $('.dataTables_paginate, .dataTables_info').show();

    // Connect custom controls to DataTables
    $('#usersSearch').on('keyup', function() {
        usersTable.search(this.value).draw();
        $('.dataTables_paginate, .dataTables_info').show();
    });

    $('#usersLength').on('change', function() {
        usersTable.page.len(this.value).draw();
        $('.dataTables_paginate, .dataTables_info').show();
    });

    $('#roomsSearch').on('keyup', function() {
        roomsTable.search(this.value).draw();
        $('.dataTables_paginate, .dataTables_info').show();
    });

    $('#roomsLength').on('change', function() {
        roomsTable.page.len(this.value).draw();
        $('.dataTables_paginate, .dataTables_info').show();
    });

    $('#suppliesSearch').on('keyup', function() {
        suppliesTable.search(this.value).draw();
        $('.dataTables_paginate, .dataTables_info').show();
    });

    $('#suppliesLength').on('change', function() {
        suppliesTable.page.len(this.value).draw();
        $('.dataTables_paginate, .dataTables_info').show();
    });

    // Show active tab based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    
    if (activeTab) {
        const tabTrigger = new bootstrap.Tab(document.querySelector(`#${activeTab}-tab`));
        tabTrigger.show();
    }
});
    </script>
</body>
</html>