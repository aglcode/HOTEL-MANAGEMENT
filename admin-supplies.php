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
        $stmt->execute();
    } elseif ($action === 'add') {
        $stmt = $conn->prepare("SELECT id, quantity FROM supplies WHERE LOWER(name) = LOWER(?) AND category = ?");
        $stmt->bind_param("ss", $name, $category);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $newQty = $row['quantity'] + $quantity;
            $stmt = $conn->prepare("UPDATE supplies SET quantity = ?, price = ? WHERE id = ?");
            $stmt->bind_param("idi", $newQty, $price, $row['id']);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO supplies (name, price, quantity, category) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdis", $name, $price, $quantity, $category);
            $stmt->execute();
        }
    } elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM supplies WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    header("Location: admin-supplies.php");
    exit();
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

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
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
  <a href="admin-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
  <a href="admin-report.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
  <a href="admin-supplies.php" class="active"><i class="fa-solid fa-boxes-stacked"></i> Supplies</a>
  <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
  <a href="admin-logout.php" class="mt-auto text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
            <i class="fas fa-boxes-stacked text-white fs-3"></i>
          </div>
          <div>
            <h6 class="text-muted mb-1">Total Supplies</h6>
            <h2 class="mb-0"><?php echo $totalSupplies; ?></h2>
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
            <h6 class="text-muted mb-1">Total Cost</h6>
            <h2 class="mb-0">₱<?php echo number_format($totalCost, 2); ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center">
          <div class="rounded-circle bg-info d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
            <i class="fas fa-tags text-white fs-3"></i>
          </div>
          <div>
            <h6 class="text-muted mb-1">Categories</h6>
            <h2 class="mb-0">3</h2>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter and Add Supply Section -->
  <div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
      <h5 class="mb-0">Supply Filters</h5>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplyModal">
        <i class="fas fa-plus-circle me-2"></i>Add Supply
      </button>
    </div>
    <div class="card-body">
      <form class="row g-3" method="GET">
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search supplies..." value="<?= htmlspecialchars($search) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-filter"></i></span>
            <select name="category" class="form-select">
              <option value="">All Categories</option>
              <option value="Cleaning" <?= $category === 'Cleaning' ? 'selected' : '' ?>>Cleaning</option>
              <option value="Maintenance" <?= $category === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
              <option value="Food" <?= $category === 'Food' ? 'selected' : '' ?>>Food</option>
            </select>
          </div>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
        </div>
      </form>
    </div>
  </div>

 <!-- Supply Table -->
<div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
  <div class="d-flex align-items-center">
    <h5 class="mb-0 me-3">Supply Inventory</h5>
    <span class="badge bg-primary"><?php echo $totalSupplies; ?> items</span>
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
                <span class="action-btn delete" 
                      onclick="confirmDelete(<?= $s['id'] ?>)">
                  <i class="fas fa-trash"></i>
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
      <div class="modal-content">
        <form id="supplyForm" method="POST">
          <input type="hidden" name="action" id="supplyAction" value="add">
          <input type="hidden" name="id" id="supplyId">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addSupplyModalLabel">Add Supply</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-box"></i></span>
                <input type="text" class="form-control" id="supplyName" name="name" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Price</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-peso-sign"></i></span>
                <input type="number" class="form-control" id="supplyPrice" name="price" step="0.01" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Quantity</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span>
                <input type="number" class="form-control" id="supplyQuantity" name="quantity" min="1" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Category</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tags"></i></span>
                <select class="form-select" id="supplyCategory" name="category" required>
                  <option value="Cleaning">Cleaning</option>
                  <option value="Maintenance">Maintenance</option>
                  <option value="Food">Food</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-2"></i>Save
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Confirm Deletion</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <i class="fas fa-exclamation-triangle text-warning fa-4x mb-3"></i>
          <p class="fs-5">Are you sure you want to delete this supply?</p>
          <p class="text-muted">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <form id="deleteForm" method="POST" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteSupplyId">
            <button type="submit" class="btn btn-danger">
              <i class="fas fa-trash me-2"></i>Delete
            </button>
          </form>
        </div>
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

  // Show the modal
  const modal = new bootstrap.Modal(document.getElementById('addSupplyModal'));
  modal.show();
}

// Triggered when clicking the "Delete" button
function confirmDelete(id) {
  document.getElementById('deleteSupplyId').value = id;
  const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
  modal.show();
}

// Reset form when modal is closed
document.getElementById('addSupplyModal').addEventListener('hidden.bs.modal', function () {
  document.getElementById('supplyForm').reset();
  document.getElementById('supplyAction').value = 'add';
  document.getElementById('addSupplyModalLabel').innerText = 'Add Supply';
  document.getElementById('supplyId').value = '';
});

// Handle form submission via AJAX
document.getElementById('supplyForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const form = new FormData(this);

  fetch("admin-supplies.php", {
    method: "POST",
    body: form
  }).then(() => location.reload()); // Reload to reflect changes
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
