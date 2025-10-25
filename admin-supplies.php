<?php
session_start();
require_once 'database.php';

// Handle add, edit, delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $category = trim($_POST['category'] ?? '');
    $id = $_POST['id'] ?? null;

    if ($action === 'edit' && $id) {
        $stmt = $conn->prepare("UPDATE supplies SET name = ?, price = ?, quantity = ?, category = ? WHERE id = ?");
        $stmt->bind_param("sdisi", $name, $price, $quantity, $category, $id);
        if ($stmt->execute()) {
            header("Location: admin-supplies.php?success=edited");
        } else {
            header("Location: admin-supplies.php?error=unknown");
        }
        exit();
    } 
    elseif ($action === 'add') {
        $stmt = $conn->prepare("SELECT id, quantity FROM supplies WHERE LOWER(name) = LOWER(?) AND category = ?");
        $stmt->bind_param("ss", $name, $category);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $newQty = $row['quantity'] + $quantity;
            $stmt = $conn->prepare("UPDATE supplies SET quantity = ?, price = ? WHERE id = ?");
            $stmt->bind_param("idi", $newQty, $price, $row['id']);
            if ($stmt->execute()) {
                header("Location: admin-supplies.php?success=edited");
            } else {
                header("Location: admin-supplies.php?error=unknown");
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO supplies (name, price, quantity, category) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdis", $name, $price, $quantity, $category);
            if ($stmt->execute()) {
                header("Location: admin-supplies.php?success=added");
            } else {
                header("Location: admin-supplies.php?error=unknown");
            }
        }
        exit();
    }
elseif ($action === 'archive' && $id) {
    // Archive the supply instead of deleting
    $stmt = $conn->prepare("UPDATE supplies SET is_archived = 1, archived_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: admin-supplies.php?success=archived");
    } else {
        header("Location: admin-supplies.php?error=unknown");
    }
    exit();
}
}

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "name LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}
if (!empty($category)) {
    $where[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}

$where[] = "is_archived = 0";
$whereSql = 'WHERE ' . implode(' AND ', $where);
$stmt = $conn->prepare("SELECT * FROM supplies $whereSql ORDER BY name ASC");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$supplies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalSupplies = count($supplies);
$totalCost = array_reduce($supplies, fn($sum, $s) => $sum + ($s['price'] * $s['quantity']), 0);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gitarra Apartelle - Supply Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

  .same-height {
    height: 48px; /* match input/select height */
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
  color: #2563eb; /* blue-600 */
}

.user-actions .action-btn.archive:hover {
  color: #f59e0b; /* amber/orange */
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
      <a href="admin-supplies.php" class="active"><i class="fa-solid fa-cube"></i> Supplies</a>
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
      <h2 class="fw-bold mb-0">Supply Management</h2>
      <p class="text-muted mb-0">Manage inventory supplies and costs</p>
    </div>
    <div class="clock-box text-end">
      <div id="currentDate" class="fw-semibold"></div>
      <div id="currentTime"></div>
    </div>
  </div>

<!-- Supply Statistics Cards -->
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
            <h3 class="fw-bold mb-1"><?php echo $totalSupplies; ?></h3>
            <p class="stat-change text-success">+6% <span>from last month</span></p>
        </div>
    </div>

    <!-- Total Cost -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Total Cost</p>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-peso-sign"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1">₱<?php echo number_format($totalCost, 2); ?></h3>
            <p class="stat-change text-danger">-3% <span>from last month</span></p>
        </div>
    </div>

    <!-- Categories -->
    <div class="col-md-4 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Categories</p>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1">3</h3>
            <p class="stat-change text-success">+2% <span>from last month</span></p>
        </div>
    </div>
</div>


<!-- Filter and Add Supply Section -->
<div class="card mb-4 border-0 shadow-sm rounded-3">
  <!-- Header with Title + Add Button -->
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
    <div class="d-flex align-items-center">
      <div class="bg-light p-2 rounded-3 me-2">
        <i class="fas fa-filter text-dark"></i>
      </div>
      <h5 class="mb-0 fw-bold">Supply Filters</h5>
    </div>
    <button class="btn btn-dark d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addSupplyModal">
      <i class="fas fa-plus me-2"></i> Add Supply
    </button>
  </div>

  <!-- Body with Filters -->
  <div class="card-body">
    <form class="row g-3 align-items-end" method="GET">
      <!-- Search -->
      <div class="col-md-6">
        <label class="form-label fw-semibold">Search Supplies</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
          <input type="text" name="search" class="form-control border-start-0 same-height"
                 placeholder="Search supplies..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>

      <!-- Category -->
      <div class="col-md-4">
        <label class="form-label fw-semibold">Category</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="fas fa-tags"></i></span>
          <select name="category" class="form-select border-start-0 same-height">
            <option value="">All Categories</option>
            <option value="Cleaning" <?= $category === 'Cleaning' ? 'selected' : '' ?>>Cleaning</option>
            <option value="Maintenance" <?= $category === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
            <option value="Food" <?= $category === 'Food' ? 'selected' : '' ?>>Food</option>
          </select>
        </div>
      </div>

      <!-- Filter Button -->
      <div class="col-md-2">
        <label class="form-label fw-semibold invisible">Filter</label>
        <button class="btn btn-outline-dark w-100 same-height d-flex align-items-center justify-content-center">
          <i class="fas fa-filter me-2"></i> Filter
        </button>
      </div>
    </form>
  </div>
</div>




  <!--- Success Messages ---->
 <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
  <?php if (isset($_GET['success'])): ?>
    <div id="serverToastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          <?php
            if ($_GET['success'] == 'added') echo "Supply added successfully!";
            if ($_GET['success'] == 'edited') echo "Supply edited successfully!";
            if ($_GET['success'] == 'archived') echo "Supply archived successfully!";
          ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>

    <!--- Error Message ---->
  <?php if (isset($_GET['error'])): ?>
    <div id="serverToastError" class="toast align-items-center text-bg-danger border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <?php
            if ($_GET['error'] == 'foreign_key_violation') {
              echo "Cannot delete this supply because it has related records.";
            } else {
              echo "Failed to process the request. Please try again.";
            }
          ?>
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


 <!-- Supply Table -->
<div class="card-header d-flex justify-content-between align-items-center py-3" style="background-color: #871D2B;">
  <div class="d-flex align-items-center">
    <h5 class="mb-0 me-3 text-white">Supply Inventory</h5>
    <span class="badge bg-dark"><?php echo $totalSupplies; ?> items</span>
  </div>
  <!-- Custom search box -->
  <div class="input-group" style="width: 250px;">
    <input type="text" id="supplySearch" class="form-control form-control-sm" placeholder="Search supplies...">
    <span class="input-group-text"><i class="fas fa-search"></i></span>
  </div>
</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="supplyTable" class="table table-bordered table-hover">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Name</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Total</th>
            <th>Category</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($supplies) > 0): ?>
            <?php foreach ($supplies as $s): ?>
            <tr>
              <td class="ps-3">
                <div class="d-flex align-items-center">
                  <!-- Avatar with custom category colors -->
                  <div class="avatar-sm 
                      <?php if ($s['category'] == 'Cleaning'): ?>
                        bg-blue-100 text-blue-800 border-blue-200
                      <?php elseif ($s['category'] == 'Maintenance'): ?>
                        bg-amber-100 text-amber-800 border-amber-200
                      <?php else: ?>
                        bg-green-100 text-green-800 border-green-200
                      <?php endif; ?>
                      rounded-circle d-flex align-items-center justify-content-center me-2" 
                      style="width: 32px; height: 32px; border:1px solid;">
                    <span><?= strtoupper(substr($s['name'], 0, 1)) ?></span>
                  </div>
                  <div><?= htmlspecialchars($s['name']) ?></div>
                </div>
              </td>
              <td>₱<?= number_format($s['price'], 2) ?></td>
                <td>
                  <span class="badge rounded-pill 
                    <?php if ($s['quantity'] > 10): ?>
                      bg-green-100 text-green-800 border-green-200
                    <?php elseif ($s['quantity'] > 5): ?>
                      bg-yellow-100 text-yellow-800 border-yellow-200
                    <?php else: ?>
                      bg-amber-100 text-amber-800 border-amber-200
                    <?php endif; ?>
                  ">
                    <?= (int)$s['quantity'] ?>
                  </span>
                </td>

              <td>₱<?= number_format($s['price'] * $s['quantity'], 2) ?></td>
              <td>
                <!-- Category badge with same custom color sets -->
                <span class="badge 
                  <?php if ($s['category'] == 'Cleaning'): ?>
                    bg-yellow-100 text-yellow-800 border-yellow-200
                  <?php elseif ($s['category'] == 'Maintenance'): ?>
                    bg-amber-100 text-amber-800 border-amber-200
                  <?php else: ?>
                    bg-green-100 text-green-800 border-green-200
                  <?php endif; ?>
                ">
                  <?= htmlspecialchars($s['category']) ?>
                </span>
              </td>
              <td class="text-center user-actions">
                <span class="action-btn edit me-2" 
                      onclick="populateEditForm(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>', <?= $s['price'] ?>, <?= $s['quantity'] ?>, '<?= $s['category'] ?>')">
                  <i class="fas fa-edit"></i>
                </span>
<span class="action-btn archive" 
      onclick="confirmArchive(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
  <i class="fas fa-archive"></i>
</span>
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


 <!-- Add/Edit Modal -->
<div class="modal fade" id="addSupplyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <form id="supplyForm" method="POST">
        <input type="hidden" name="action" id="supplyAction" value="add">
        <input type="hidden" name="id" id="supplyId">

        <!-- Modal Header -->
        <div class="modal-header">
          <div class="d-flex align-items-center">
            <div class="bg-secondary bg-opacity-10 p-2 rounded-3 me-2">
              <i class="fas fa-cube text-dark fa-lg"></i>
            </div>
            <h5 class="modal-title fw-bold mb-0" id="addSupplyModalLabel">Add New Supply</h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
          <!-- Name -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Supply Name</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="fas fa-box"></i></span>
              <input type="text" class="form-control border-start-0" id="supplyName" name="name" placeholder="Enter supply name" required>
            </div>
          </div>

          <!-- Price -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Price</label>
            <div class="input-group">
              <span class="input-group-text bg-white">₱</span>
              <input type="number" class="form-control border-start-0" id="supplyPrice" name="price" step="0.01" placeholder="00" required>
                  <div class="invalid-feedback">
                  Invalid input
                </div>
            </div>
          </div>

          <!-- Quantity -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Quantity</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="fas fa-hashtag"></i></span>
              <input type="number" class="form-control border-start-0" id="supplyQuantity" name="quantity" min="1" placeholder="Enter quantity" required>
            </div>
          </div>

          <!-- Category -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Category</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="fas fa-tags"></i></span>
              <select class="form-select border-start-0" id="supplyCategory" name="category" required>
                <option value="" disabled selected>Select a category</option>
                <option value="Cleaning">Cleaning</option>
                <option value="Maintenance">Maintenance</option>
                <option value="Food">Food</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="supplySubmitBtn" class="btn btn-dark px-4 fw-semibold">Add Supply</button>
        </div>

      </form>
    </div>
  </div>
</div>

  
  <!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Header -->
<div class="modal-header border-0 pb-2">
  <h5 class="modal-title fw-bold text-warning" id="deleteModalLabel">
    <i class="fas fa-archive me-2"></i>Archive Supply
  </h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
      <hr class="my-0">

      <!-- Body -->
<div class="modal-body text-center">
  <div class="mb-3">
    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px; height:60px;">
      <i class="fas fa-archive text-warning fa-2x"></i>
    </div>
  </div>
  <h5 class="fw-bold mb-2">Archive this supply?</h5>
  <p class="text-muted mb-0">
    Are you sure you want to archive <span id="supplyToArchive" class="fw-semibold text-dark"></span>?<br>
    This supply will be moved to the archive. You can restore or permanently delete it from the archive page.
  </p>
</div>
      <hr class="my-0">

      <!-- Footer -->
<div class="modal-footer border-0 justify-content-center">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
  <form id="archiveForm" method="POST" style="display:inline">
    <input type="hidden" name="action" value="archive">
    <input type="hidden" name="id" id="archiveSupplyId">
    <button type="submit" class="btn btn-warning">
      <i class="fas fa-archive me-1"></i> Archive Supply
    </button>
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
// Triggered when clicking the "Edit" button
function populateEditForm(id, name, price, quantity, category) {
  document.getElementById('supplyId').value = id;
  document.getElementById('supplyName').value = name;
  document.getElementById('supplyPrice').value = price;
  document.getElementById('supplyQuantity').value = quantity;
  document.getElementById('supplyCategory').value = category;
  document.getElementById('supplyAction').value = 'edit';
  document.getElementById('addSupplyModalLabel').innerText = 'Edit Supply';

  // Change button text
  document.getElementById('supplySubmitBtn').innerText = 'Save Changes';

  // Show the modal
  const modal = new bootstrap.Modal(document.getElementById('addSupplyModal'));
  modal.show();
}

// Triggered when clicking the "Add" button
function resetAddForm() {
  document.getElementById('supplyForm').reset();
  document.getElementById('supplyAction').value = 'add';
  document.getElementById('addSupplyModalLabel').innerText = 'Add New Supply';

  // Reset button text
  document.getElementById('supplySubmitBtn').innerText = 'Add Supply';
}

// Triggered when clicking the "Archive" button
function confirmArchive(id, name) {
  document.getElementById('archiveSupplyId').value = id;
  document.getElementById('supplyToArchive').textContent = name;
  const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
  modal.show();
}

// Reset form when modal is closed
document.getElementById('addSupplyModal').addEventListener('hidden.bs.modal', function () {
  document.getElementById('supplyForm').reset();
  document.getElementById('supplyAction').value = 'add';
  document.getElementById('addSupplyModalLabel').innerText = 'Add Supply';
  document.getElementById('supplyId').value = '';

  // Reset button text
  document.getElementById('supplySubmitBtn').innerText = 'Add Supply';
});

// price validation
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("supplyForm");
  const priceInput = document.getElementById("supplyPrice");

  // Function to validate price
  function validatePrice(showToast = false) {
    const value = priceInput.value.trim();
    const number = parseFloat(value);

    // Rules:
    // 1. Must be a number
    // 2. Must be greater than 0
    // 3. Must be a whole number (no decimals)
    const isInvalid =
      isNaN(number) || number <= 0 || !/^\d+$/.test(value);

    priceInput.classList.toggle("is-invalid", isInvalid);

    if (isInvalid && showToast) {
      const errorToastEl = document.createElement("div");
      errorToastEl.className = "toast align-items-center text-bg-danger border-0";
      errorToastEl.role = "alert";
      errorToastEl.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Price must be a whole number greater than 0.
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      `;
      document.querySelector(".toast-container").appendChild(errorToastEl);
      new bootstrap.Toast(errorToastEl, { delay: 4000 }).show();
    }

    return !isInvalid;
  }

  // Validate in real-time
  priceInput.addEventListener("input", () => validatePrice(false));
  priceInput.addEventListener("blur", () => validatePrice(false));

  // Validate on submit
  form.addEventListener("submit", function(event) {
    if (!validatePrice(true)) {
      event.preventDefault();
      event.stopPropagation();
    }
  });
});

// Real-time clock updater
function updateClock() {
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  document.getElementById('currentDate').innerText = now.toLocaleDateString('en-PH', options);
  document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-PH');
}
setInterval(updateClock, 1000);
updateClock();

// Data Tables
$(document).ready(function() {
  var table = $('#supplyTable').DataTable({
    paging: true,
    lengthChange: true,
    searching: true, // keep enabled so API works
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: true,
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50, 100],
    dom: 't<"bottom"ip>', // remove default search bar from header
    language: {
      paginate: {
        first: '<<',
        previous: '<',
        next: '>',
        last: '>>'
      }
    }
  });

  // Bind custom search
  $('#supplySearch').on('keyup', function() {
    table.search(this.value).draw();
  });
});


</script>

</body>
</html>
