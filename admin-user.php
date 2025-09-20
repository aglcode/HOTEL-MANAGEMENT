<?php
session_start();

require_once 'database.php'; // Include your database connection settings

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is an admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: signin.php");
    exit();
}

// Handle adding a new user
if (isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Insert new user into the database
    $query = "INSERT INTO users (name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'approved')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss", $name, $username, $email, $password, $role);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-user.php?success=added");
    exit();
}

// Handle editing an existing user
if (isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    // Check if password is provided (optional during edit)
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $query = "UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssi", $name, $username, $email, $password, $role, $user_id);
    } else {
        $query = "UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $name, $username, $email, $role, $user_id);
    }
    
    $stmt->execute();
    $stmt->close();

    header("Location: admin-user.php?success=edited");
    exit();
}

// Handle deleting a user
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    // Delete the user from the database
    $query = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-user.php?success=deleted");
    exit();
}

// Handle updating user status
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['user_id']) && isset($_GET['status'])) {
    $user_id = $_GET['user_id'];
    $status = $_GET['status']; // Can be 'approved' or 'pending'

    // Update the user's status
    $query = "UPDATE users SET status = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-user.php?success=status_updated");
    exit();
}

// Count total users
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = $conn->query($total_users_query);
$total_users = $total_users_result->fetch_assoc()['total'];

// Count pending accounts
$pending_users_query = "SELECT COUNT(*) as pending FROM users WHERE status = 'pending'";
$pending_users_result = $conn->query($pending_users_query);
$pending_users = $pending_users_result->fetch_assoc()['pending'];

// Fetch all users
$query = "SELECT * FROM users ORDER BY user_id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - User Management</title>
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
        <a href="admin-user.php" class="active"><i class="fa-solid fa-users"></i> Users</a>
        <a href="admin-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
        <a href="admin-report.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
        <a href="admin-supplies.php"><i class="fa-solid fa-boxes-stacked"></i> Supplies</a>
        <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
        <a href="admin-logout.php" class="mt-auto text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">User Management</h2>
                <p class="text-muted mb-0">Manage user accounts and permissions</p>
            </div>
            <div class="clock-box text-end">
                <div id="currentDate" class="fw-semibold"></div>
                <div id="currentTime"></div>
            </div>
        </div>
        
        <!-- User Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-users text-white fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Users</h6>
                            <h2 class="mb-0"><?php echo $total_users; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-user-clock text-white fs-3"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Accounts</h6>
                            <h2 class="mb-0"><?php echo $pending_users; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php
                    if ($_GET['success'] == 'added') echo "<i class='fas fa-check-circle me-2'></i>User added successfully!";
                    if ($_GET['success'] == 'edited') echo "<i class='fas fa-check-circle me-2'></i>User edited successfully!";
                    if ($_GET['success'] == 'deleted') echo "<i class='fas fa-check-circle me-2'></i>User deleted successfully!";
                    if ($_GET['success'] == 'status_updated') echo "<i class='fas fa-check-circle me-2'></i>User status updated successfully!";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Add User Form -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Add New User</h5>
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="admin-user.php" class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-at"></i></span>
                                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="role" class="form-label">Role</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <select name="role" class="form-select" required>
                                        <option value="" selected disabled>Select a role</option>
                                        <option value="Admin">Admin</option>
                                        <option value="Receptionist">Receptionist</option>
                                        <option value="Guest">Guest</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" name="add_user" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Add User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>


        <!-- User List -->
     
        <div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center p-3">
        <div>
            <h2 class="h5 mb-0 text-gray-900">User Accounts</h2>
            <p class="text-sm text-gray-600 mt-1"><?php echo $result->num_rows; ?> total users</p>
        </div>
        <div class="d-flex align-items-center gap-2">
    
             <!--- SHOW USERS --->
            <div id="customLengthMenu"></div>


            <div class="position-relative">
                <input type="text" class="form-control ps-4" id="searchInput" placeholder="Search users..." style="width: 200px;">
                <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-2 text-gray-400"></i>
            </div>
            <select class="form-select" id="filterSelect" style="width: 100px;">
                <option value="">Filter</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="userTable" class="table table-hover align-middle mb-0" style="width:100%;">
                <thead class="bg-gray-50 border-bottom border-gray-200">
                    <tr>
                        <th class="w-12 px-4 py-3">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="name">
                            Name <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="username">
                            Username <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="email">
                            Email <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="role">
                            Role <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="status">
                            Status <span class="sort-icon">↑↓</span>
                        </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="status">
                            Actions <span class="sort-icon">↑↓</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <input type="checkbox" class="form-check-input row-checkbox" data-id="<?= $user['user_id'] ?>">
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-<?= ($user['role'] == 'Admin') ? 'primary' : (($user['role'] == 'Receptionist') ? 'success' : 'secondary') ?> rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                        <span class="text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                                    </div>
                                    <div>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($user['username']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($user['email']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <span class="badge bg-<?= ($user['role'] == 'Admin') ? 'blue-100' : (($user['role'] == 'Receptionist') ? 'green-100' : 'gray-100') ?> text-<?= ($user['role'] == 'Admin') ? 'blue-800' : (($user['role'] == 'Receptionist') ? 'green-800' : 'gray-800') ?> border-<?= ($user['role'] == 'Admin') ? 'blue-200' : (($user['role'] == 'Receptionist') ? 'green-200' : 'gray-200') ?> rounded-pill px-2.5 py-0.5 text-xs font-medium">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <span class="badge bg-<?= ($user['status'] == 'approved') ? 'green-100' : 'amber-100' ?> text-<?= ($user['status'] == 'approved') ? 'green-800' : 'amber-800' ?> border-<?= ($user['status'] == 'approved') ? 'green-200' : 'amber-200' ?> rounded-pill px-2.5 py-0.5 text-xs font-medium">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                                <?php if ($user['role'] != 'Admin'): ?>
                                    <a href="admin-user.php?action=update_status&user_id=<?= $user['user_id'] ?>&status=<?= ($user['status'] == 'approved') ? 'pending' : 'approved' ?>" class="btn btn-sm btn-outline-<?= ($user['status'] == 'approved') ? 'warning' : 'success' ?> ms-1">
                                        <i class="fas fa-<?= ($user['status'] == 'approved') ? 'pause' : 'check' ?>"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        <td class="px-4 py-3 text-center align-middle">
                        <div class="d-flex gap-2 justify-content-center user-actions">
                            <a href="#"
                            onclick="editUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['name']) ?>', '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['role']) ?>')"
                            class="p-1 action-btn edit" title="Edit">
                            <i class="fas fa-edit"></i>
                            </a>

                            <a href="#"
                            onclick="confirmDelete(<?= $user['user_id'] ?>)"
                            class="p-1 action-btn delete" title="Delete">
                            <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        </td>


                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No users found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle text-warning fa-4x"></i>
                </div>
                <p class="text-center fs-5">Are you sure you want to delete this user?</p>
                <p class="text-center text-muted">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteUserLink" class="btn btn-danger">Delete User</a>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST" action="admin-user.php" class="row g-3">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="col-md-6">
                        <label for="edit_name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-at"></i></span>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_password" class="form-label">Password <small class="text-muted">(Leave blank to keep current)</small></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="edit_password" class="form-control" placeholder="Enter new password">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label for="edit_role" class="form-label">Role</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="Admin">Admin</option>
                                <option value="Receptionist">Receptionist</option>
                                <option value="Guest">Guest</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 mt-4 text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
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
        // Real-time clock updater
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').innerText = now.toLocaleDateString('en-PH', options);
            document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-PH');
        }
        setInterval(updateClock, 1000);
        updateClock();
        
        // Delete confirmation
        function confirmDelete(userId) {
            document.getElementById('deleteUserLink').href = 'admin-user.php?action=delete&user_id=' + userId;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // Edit user function
        function editUser(userId, name, username, email, role) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = ''; // Clear password field
            
            var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }


        // Data Tables
    $(document).ready(function() {
    var table = $('#userTable').DataTable({
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        responsive: true,
        pageLength: 5, 
        lengthMenu: [5, 10, 25, 50, 100],
        dom: '<"d-none"l>rt' +   // ✅ generate lengthMenu but keep hidden
             '<"row mt-3"<"col-sm-5"i><"col-sm-7"p>>',
        language: {
            emptyTable: "<i class='fas fa-users fa-3x text-muted mb-3'></i><p class='mb-0'>No users found</p>",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            infoEmpty: "No entries available",
            infoFiltered: "(filtered from _MAX_ total users)",
            lengthMenu: "Show _MENU_ users",
            paginate: {
                first:    "«",
                last:     "»",
                next:     "›",
                previous: "‹"
            }
        }
    });

    // Move dropdown
    table.on('init', function () {
        var lengthSelect = $('#userTable_length select')
            .addClass('form-select')
            .css('width','80px');

        // Optional: add "Show" + "users" text around dropdown
        $('#customLengthMenu').html(
            '<label class="d-flex align-items-center gap-2 mb-0">' +
                '<span>Show</span>' + 
                lengthSelect.prop('outerHTML') +
                '<span>users</span>' +
            '</label>'
        );

        $('#userTable_length').hide();
    });

    // Custom search
    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    // Filter select
    $('#filterSelect').on('change', function() {
        table.column(5).search(this.value).draw();
    });

    // Select All
    $('#selectAll').on('click', function() {
        $('.row-checkbox').prop('checked', this.checked);
    });

    $(document).on('click', '.row-checkbox', function() {
        if (!$('.row-checkbox:checked').length) {
            $('#selectAll').prop('checked', false);
        }
    });


    table.on('order.dt', function() {
        $('th.sorting', table.table().header()).removeClass('sorting_asc sorting_desc');
        table.columns().every(function(index) {
            var order = table.order()[0];
            if (order[0] === index) {
                $('th:eq(' + index + ')', table.table().header())
                  .addClass(order[1] === 'asc' ? 'sorting_asc' : 'sorting_desc');
            }
        });
    });
});


    </script>
</body>
</html>
