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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

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
            padding: 20px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
            background: #fff;
            padding: 20px;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table th {
            background: #f8f9fa;
            text-align: left;
            padding: 14px 16px;
            font-weight: 600;
        }

        table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
            transition: background-color 0.2s ease;
        }

        .badge {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 20px;
        }

        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="user-info mb-4 text-center">
      <i class="fa-solid fa-user-circle mb-2" style="font-size: 60px;"></i>
      <h5 class="mb-1">Welcome,</h5>
      <p class="mb-0">Receptionist</p>
    </div>
    <a href="receptionist-dash.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="receptionist-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
    <a href="receptionist-guest.php"><i class="fa-solid fa-users"></i> Guest</a>
    <a href="receptionist-booking.php"><i class="fa-solid fa-calendar-check"></i> Booking</a>
    <a href="receptionist-payment.php" class="active"><i class="fa-solid fa-money-check"></i> Payment</a>
    <a href="signin.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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
    <div class="search-filter-container mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="searchInput" class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" id="searchInput" class="form-control" placeholder="Guest, Room, Payment..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterSelect" class="form-label">Payment Mode</label>
                <select name="filter" id="filterSelect" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="cash" <?= $filter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="gcash" <?= $filter === 'gcash' ? 'selected' : '' ?>>GCash</option>
                </select>
            </div>
            <div class="col-md-2 d-flex">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="fas fa-filter me-2"></i>Apply
                </button>
            </div>
        </form>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-money-check me-2"></i>Payment History</h5>
            <span class="badge bg-light text-primary rounded-pill"><?= $result->num_rows ?> Records</span>
        </div>
        <div class="card-body p-0">
            <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
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
                            <td>
                                <span class="badge bg-info">Room <?= htmlspecialchars($row['room_number']) ?></span>
                                <br>
                                <small><?= ucfirst($row['room_type']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= strtolower($row['payment_mode']) === 'cash' ? 'success' : 'primary' ?>">
                                    <?= ucfirst($row['payment_mode']) ?>
                                </span>
                            </td>
                            <td>₱<?= number_format($row['amount_paid'], 2) ?></td>
                            <td>₱<?= number_format($row['total_price'], 2) ?></td>
                            <td>
                                <?php if ($row['change_amount'] > 0): ?>
                                    <span class="text-success">₱<?= number_format($row['change_amount'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">₱0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= date('M d, Y h:i A', strtotime($row['check_in_date'])) ?></small>
                            </td>
                            <td>
                                <small><?= date('M d, Y h:i A', strtotime($row['check_out_date'])) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($row['gcash_reference'])): ?>
                                    <span class="badge bg-primary"><?= htmlspecialchars($row['gcash_reference']) ?></span>
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
  </div>

  <script>
    function updateClock() {
        const now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>
</body>
</html>
