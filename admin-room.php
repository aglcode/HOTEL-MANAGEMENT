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
    <link href="style.css" rel="stylesheet">
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
    <div class="table-container" id="roomList">
        <h3>Room List</h3>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Room Number</th>
                    <th>Room Type</th>
                    <th>Status</th>
                    <th>3hrs</th>
                    <th>6hrs</th>
                    <th>12hrs</th>
                    <th>24hrs</th>
                    <th>OT</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($room = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($room['room_number']) ?></td>
                        <td><?= htmlspecialchars($room['room_type']) ?></td>
                        <td><?= ucfirst($room['status']) ?></td>
                        <td><?= number_format($room['price_3hrs'], 2) ?></td>
                        <td><?= number_format($room['price_6hrs'], 2) ?></td>
                        <td><?= number_format($room['price_12hrs'], 2) ?></td>
                        <td><?= number_format($room['price_24hrs'], 2) ?></td>
                        <td><?= number_format($room['price_ot'], 2) ?></td>
                        <td>
                            <a href="admin-room.php?action=edit&room_id=<?= $room['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="admin-room.php?action=delete&room_id=<?= $room['id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        
    </div>
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
    </script>
</div>
