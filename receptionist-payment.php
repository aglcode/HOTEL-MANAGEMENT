<?php
session_start();
require_once 'database.php';

// Fetch payment history from checkins table (Guest Check-In History)
$search = isset($_GET['search']) ? trim($conn->real_escape_string($_GET['search'])) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT 
    id,
    guest_name,
    room_number,
    room_type,
    payment_mode,
    amount_paid,
    total_price,
    change_amount,
    check_in_date,
    check_out_date,
    gcash_reference
FROM checkins WHERE 1=1";

// Optional search/filter
if (!empty($search)) {
    $sql .= " AND (guest_name LIKE '%$search%' OR room_number LIKE '%$search%' OR payment_mode LIKE '%$search%' OR gcash_reference LIKE '%$search%')";
}
if ($filter === 'cash') {
    $sql .= " AND payment_mode = 'cash'";
} elseif ($filter === 'gcash') {
    $sql .= " AND payment_mode = 'gcash'";
}
$sql .= " ORDER BY check_in_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History - Gitarra Apartelle</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>

      /* === Sidebar Navigation === */
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
  font-family: 'Poppins', sans-serif;
}

/* === Header === */
.sidebar h4 {
  text-align: center;
  font-weight: 700;
  color: #111827;
  margin-bottom: 30px;
}

/* === User Info === */
.user-info {
  text-align: center;
  background: #f9fafb;
  border-radius: 10px;
  padding: 15px;
  margin: 0 20px 25px 20px;
}

.user-info i {
  font-size: 40px;
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

/* Hover effect — same feel as other links */
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

        /* === Search and Filter Controls === */
.search-filter-container {
  background-color: #fff;
  border-radius: 10px;
  padding: 1rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-filter-container .form-label {
  font-weight: 600;
  color: #495057;
}

.search-filter-container .input-group-text {
  background-color: #f8f9fa;
  border-right: none;
}

.search-filter-container .form-control {
  border-left: none;
  font-size: 0.95rem;
}

.search-filter-container .btn {
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.search-filter-container .btn:hover {
  transform: translateY(-2px);
}

/* === TABLE HEADER === */
.table thead th {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  padding: 0.75rem;
  font-size: 0.75rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
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

.table th.sorting_asc::after { content: '↑'; }
.table th.sorting_desc::after { content: '↓'; }

/* === TABLE CELLS === */
.table td {
  padding: 2rem 0.75rem;
  vertical-align: middle;
  font-size: 0.85rem;
  color: #4a5568;
}

.table-hover tbody tr:hover {
  background-color: #f8f9fa;
  transition: background-color 0.15s ease;
}

/* === BADGES === */
.table .badge {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
  border: 1px solid;
  border-radius: 0.5rem;
  background-color: #fff;
  transition: all 0.2s ease;
}

/* === Color Variants === */
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

/* pagination */
.dataTables_wrapper .dataTables_paginate .pagination {
    margin: 0;
}

.dataTables_wrapper .dataTables_info {
    padding: 0.75rem;
}

.dataTables_wrapper .dataTables_paginate {
    padding-right: 15px; 
    padding-bottom: 1rem;
}
/* Notification Badge Styles */
.notification-badge {
  position: absolute;
  top: 2px;     /* move higher up */
  right: 10px;  /* slightly tighter alignment */
  background: #dc3545;
  color: white;
  border-radius: 50%;
  min-width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 5px;
  animation: pulse-badge 2s infinite;
  box-shadow: 0 2px 4px rgba(220, 53, 69, 0.4);
}

    </style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <h4>Gitarra Apartelle</h4>

  <div class="user-info">
    <i class="fa-solid fa-user-circle"></i>
    <p>Welcome</p>
    <h6>Receptionist</h6>
  </div>

   <?php include __DIR__ . '/includes/get-notifications.php'; ?>

  <div class="nav-links">
    <a href="receptionist-dash.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="receptionist-room.php" class="position-relative">
  <i class="fa-solid fa-bed"></i> Rooms
  <?php if (!empty($totalNotifications) && $totalNotifications > 0): ?>
    <span class="notification-badge">
      <?= $totalNotifications ?>
    </span>
  <?php endif; ?>
</a>
    <a href="receptionist-guest.php"><i class="fa-solid fa-users"></i> Guests</a>
    <a href="receptionist-booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a>
    <a href="receptionist-payment.php" class="active"><i class="fa-solid fa-money-check"></i> Payment</a>
  </div>

  <div class="signout">
    <a href="signin.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
  </div>
</div>

  <!-- Content -->
  <div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Payment History</h2>
            <p class="text-muted mb-0">All payments based on Guest Check-In History</p>
        </div>
        <div class="clock-box text-end text-dark">
            <div id="currentDate" class="fw-semibold"></div>
            <div id="currentTime" class="fs-5"></div>
        </div>
    </div>

    <!-- Search and Filter Controls -->
<div class="search-filter-container mb-4 no-print">
  <form method="get" class="row g-3 align-items-end">
    <!-- Search Input -->
    <div class="col-md-4">
      <label for="searchInput" class="form-label">Search Guests</label>
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input
          type="text"
          name="search"
          id="searchInput"
          class="form-control"
          placeholder="Guest, Room, Payment..."
          value="<?= htmlspecialchars($search) ?>"
        >
      </div>
    </div>

    <!-- Filter Select -->
    <div class="col-md-4">
      <label for="filterSelect" class="form-label">Payment Mode</label>
      <select
        name="filter"
        id="filterSelect"
        class="form-select"
        onchange="this.form.submit()"
      >
        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Payments</option>
        <option value="cash" <?= $filter === 'cash' ? 'selected' : '' ?>>Cash</option>
        <option value="gcash" <?= $filter === 'gcash' ? 'selected' : '' ?>>GCash</option>
      </select>
    </div>

    <!-- Buttons -->
    <div class="col-md-4 d-flex">
      <button type="submit" class="btn btn-dark me-2 flex-grow-1">
        <i class="fas fa-filter me-2"></i>Apply Filters
      </button>
      <a href="receptionist-payment.php" class="btn btn-outline-secondary flex-grow-1">
        <i class="fas fa-redo me-2"></i>Reset
      </a>
    </div>
  </form>
</div>

<!-- TABLE -->
<div class="card mb-4">
  <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #871D2B;">
    <h5 class="mb-0"><i class="fas fa-money-check me-2"></i>Payment History</h5>
    <span class="badge bg-blue-100 text-blue-800 border-blue-200 rounded-pill">
      <?= $result->num_rows ?> Records
    </span>
  </div>

  <div class="card-body p-0">
    <?php if ($result->num_rows > 0): ?>
      <div class="table-responsive">
        <table id="paymentTable" class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Guest Name</th>
              <th>Room</th>
              <th>Payment Mode</th>
              <th>Amount Paid</th>
              <th>Total Price</th>
              <th>Change</th>
              <th>Check-In</th>
              <th>Check-Out</th>
              <th>GCash Ref</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['guest_name']) ?></td>

                <!-- Room Badge -->
                <td>
                  <span class="badge bg-info-100 text-info-800 border-info-200">
                    Room <?= htmlspecialchars($row['room_number']) ?>
                  </span><br>
                  <small class="text-muted"><?= ucfirst($row['room_type']) ?></small>
                </td>

                <!-- Payment Mode Badge -->
                <td>
                  <?php if (strtolower($row['payment_mode']) === 'cash'): ?>
                    <span class="badge bg-green-100 text-green-800 border-green-200">
                      <?= ucfirst($row['payment_mode']) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-blue-100 text-blue-800 border-blue-200">
                      <?= ucfirst($row['payment_mode']) ?>
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Payment Values -->
                <td>₱<?= number_format($row['amount_paid'], 2) ?></td>
                <td>₱<?= number_format($row['total_price'], 2) ?></td>

                <!-- Change -->
                <td>
                  <?php if ($row['change_amount'] > 0): ?>
                    <span class="text-success">₱<?= number_format($row['change_amount'], 2) ?></span>
                  <?php else: ?>
                    <span class="text-muted">₱0.00</span>
                  <?php endif; ?>
                </td>

                <!-- Dates -->
                <td><small><?= date('M d, Y h:i A', strtotime($row['check_in_date'])) ?></small></td>
                <td><small><?= date('M d, Y h:i A', strtotime($row['check_out_date'])) ?></small></td>

                <!-- GCash Reference -->
                <td>
                  <?php if (!empty($row['gcash_reference'])): ?>
                    <span class="badge bg-amber-100 text-amber-800 border-amber-200">
                      <?= htmlspecialchars($row['gcash_reference']) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="text-center py-5">
        <i class="fas fa-money-check fa-3x text-muted mb-3"></i>
        <h5>No Payment Records Found</h5>
        <p class="text-muted">No payment history found matching your criteria.</p>
      </div>
    <?php endif; ?>
  </div>
</div>


<!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

  <script>

let previousNotifCount = 0;

// 🔔 Check pending orders count
async function checkOrderNotifications() {
  const notifBadge = document.getElementById("orderNotifCount");
  if (!notifBadge) return;

  try {
    const res = await fetch("fetch_pending_orders.php");
    const data = await res.json();

    let pendingCount = 0;
    if (data && Object.keys(data).length > 0) {
      for (const orders of Object.values(data)) {
        pendingCount += orders.filter(o => o.status === "pending").length;
      }
    }

    // 🔴 Update the badge
    if (pendingCount > 0) {
      notifBadge.textContent = pendingCount;
      notifBadge.classList.remove("d-none");

      // 🌀 Animate if the number increased
      if (pendingCount > previousNotifCount) {
        notifBadge.classList.add("animate__animated", "animate__bounceIn");
        setTimeout(() => notifBadge.classList.remove("animate__animated", "animate__bounceIn"), 1000);
      }

    } else {
      notifBadge.classList.add("d-none");
    }

    previousNotifCount = pendingCount;

  } catch (error) {
    console.error("Failed to fetch order notifications:", error);
  }
}

// Run every 10 seconds
checkOrderNotifications();
setInterval(checkOrderNotifications, 10000);
    // Data tables
  $(document).ready(function() {
  var paymentTable = $('#paymentTable').DataTable({
    paging: true,
    lengthChange: false,
    searching: false,
    ordering: true,
    info: true,
    autoWidth: false,
    responsive: true,
    pageLength: 5,
    language: {
      search: "_INPUT_",
      searchPlaceholder: "Search payments...",
      info: "Showing _START_ to _END_ of _TOTAL_ records",
      infoEmpty: "No records available",
      emptyTable: "<i class='fas fa-money-check fa-3x text-muted mb-3'></i><p class='mb-0'>No payment records found</p>",
      paginate: {
        first: "«",
        previous: "‹",
        next: "›",
        last: "»"
      }
    },
    dom: '<"top"f>t<"bottom"ip><"clear">'
  });

  // Move pagination + info below statistics
  paymentTable.on('init', function() {
    setTimeout(() => {
      const paginate = $('#paymentTable_paginate').detach();
      const info = $('#paymentTable_info').detach();

      const wrapper = $('<div class="custom-pagination"></div>')
        .append(info)
        .append(paginate);

      $('.card.mb-4 .card-body').append(wrapper);
    }, 150);
  });
});



    function updateClock() {
        const now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
    setInterval(updateClock, 1000);
    updateClock();
  </script>
</body>
</html>