<?php
// Start session and connect to DB
session_start();

require_once 'database.php'; // Include your database connection settings

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initializse muna yung arrays
$dates = [];
$incomes = [];
$checkinDates = [];
$dailyCheckins = [];

// run query safe
function fetchData($conn, $query) {
  $result = $conn->query($query);
  if(!$result) {
    die("Query failed: " . $conn->error);
  }
  return $result;
}

// Income for a month
$payment_query = "
    SELECT 
        DATE(check_in_date) AS date,
        SUM(amount_paid) AS daily_income
    FROM 
        checkins
    WHERE 
        check_in_date >= NOW() - INTERVAL 30 DAY
    GROUP BY 
        DATE(check_in_date)
    ORDER BY 
        DATE(check_in_date)
";

$payment_result = fetchData($conn, $payment_query);

while ($row = $payment_result->fetch_assoc()) {
    $dates[] = date('D, M j', strtotime($row['date']));
    $incomes[] = (float)$row['daily_income'];
}

// pag walang rows to fetch go for all DATA (income)
if (empty($dates)) {
  $fallback_payment_query = "
  SELECT 
     DATE(check_in_date) AS date,
     SUM(amount_paid) AS daily_income
  FROM checkins
  GROUP BY DATE(check_in_date)
  ORDER BY DATE(check_in_date)
  ";

  $payment_result = fetchData($conn, $fallback_payment_query);

  while ($row = $payment_result->fetch_assoc()) {
      $dates[]   = date('D, M j', strtotime($row['date']));
      $incomes[] = (float)$row['daily_income'];
  }
}

// MONTHLY CHECKINS DATA
$checkin_query = "
    SELECT 
        DATE(check_in_date) AS date,
        COUNT(*) AS daily_checkins
    FROM 
        checkins
    WHERE 
        check_in_date >= NOW() - INTERVAL 30 DAY
    GROUP BY 
        DATE(check_in_date)
    ORDER BY 
        DATE(check_in_date)
";

$checkin_result = fetchData($conn, $checkin_query);

while ($row = $checkin_result->fetch_assoc()) {
    $checkinDates[]   = date('D, M j', strtotime($row['date']));
    $dailyCheckins[]  = (int)$row['daily_checkins'];
}

// pag walang rows to fetch go for all DATA (checkins)
if (empty($checkinDates)) {
    $fallback_checkin_query = "
        SELECT 
            DATE(check_in_date) AS date,
            COUNT(*) AS daily_checkins
        FROM checkins
        GROUP BY DATE(check_in_date)
        ORDER BY DATE(check_in_date)
    ";
    $checkin_result = fetchData($conn, $fallback_checkin_query);

    while ($row = $checkin_result->fetch_assoc()) {
        $checkinDates[]  = date('D, M j', strtotime($row['date']));
        $dailyCheckins[] = (int)$row['daily_checkins'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Report - Weekly Income & Check-ins</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">

   <style>
    body {
      background-color: #f8f9fa;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
    }

    .content {
      padding: 30px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .header h2 {
      font-weight: bold;
      margin: 0;
    }

    .clock-box {
      text-align: right;
      color: #212529;
    }

    /* Stack cards vertically */
    .dashboard-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      padding: 20px;
    }

    .card h5 {
      display: flex;
      align-items: center;
      font-weight: bold;
      margin-bottom: 8px;
    }

    .card h5 i {
      margin-right: 8px;
      font-size: 1.2rem;
    }

    .card p.description {
      color: #6c757d;
      margin-bottom: 20px;
    }

    .summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 20px;
    }

    .summary-box {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    padding: 16px;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .summary-icon {
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    font-size: 18px;
    margin-right: 12px;
    color: #fff;
    }

    .summary-icon.blue {
    background: #0d6efd; /* Blue */
    }

    .summary-icon.green {
    background: #198754; /* Bootstrap green */
    }
    .summary-text {
    flex: 1;
    }

    .summary-text span {
    display: block;
    font-size: 0.9rem;
    color: #6c757d;
    }

    .summary-text strong {
    font-size: 1.2rem;
    color: #000;
    }

    .footer {
      text-align: center;
      font-size: 0.85rem;
      color: #6c757d;
      margin-top: 30px;
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
  </style>
</head>
<body>

<div class="sidebar" id="sidebar">
        <div class="user-info">
            <i class="fa-solid fa-user-circle"></i>
            <p id="user-role">Admin</p>
        </div>
        <a href="admin-dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
  <a href="admin-user.php"><i class="fa-solid fa-users"></i> Users</a>
  <a href="admin-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
  <a href="admin-report.php" class="active"><i class="fa-solid fa-chart-line"></i> Reports</a>
  <a href="admin-supplies.php"><i class="fa-solid fa-boxes-stacked"></i> Supplies</a>
  <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
  <a href="admin-logout.php" class="mt-auto text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

  <div class="content p-4">
    <div class="header">
      <h2>Gitarra Apartelle - Reports</h2>
      <div class="clock-box">
        <div id="currentDate" class="fw-semibold"></div>
        <div id="currentTime" class="fs-5"></div>
      </div>
    </div>

    <div class="dashboard-grid">
      <!-- Daily Check-ins -->
      <div class="card">
        <h5><i class="fas fa-chart-bar text-primary"></i> Daily Check-ins</h5>
        <p class="description">
          This graph shows the daily check-ins for the current week. It helps track the occupancy trends over the week.
        </p>
        <canvas id="checkinsChart" height="350"></canvas>
        <div class="summary-grid" id="checkinsSummary"></div>
      </div>

      <!-- Daily Income -->
      <div class="card">
        <h5><i class="fas fa-dollar-sign text-success"></i> Daily Income</h5>
        <p class="description">
          This graph displays the daily income for the current week. It provides insights into the revenue generated each day.
        </p>
        <canvas id="incomeChart" height="350"></canvas>
        <div class="summary-grid" id="incomeSummary"></div>
      </div>
    </div>

    <div class="footer">
      Dashboard updates in real-time • Last updated: Just now
    </div>
  </div>

  <script src="https://kit.fontawesome.com/2d3e32b10b.js" crossorigin="anonymous"></script>

  <script>
    const checkinLabels = <?php echo json_encode($checkinDates); ?>;
    const checkinData = <?php echo json_encode($dailyCheckins); ?>;

    const incomeLabels  = <?php echo json_encode($dates); ?>;
    const incomeData = <?php echo json_encode($incomes); ?>;

    function generateSummaryBoxes(data, type, isCurrency = false, color = "blue") {
    const total = data.reduce((sum, val) => sum + val, 0);
    const avg = data.length ? total / data.length : 0;
    const max = Math.max(...data);
    const min = Math.min(...data);

    const format = isCurrency
        ? (val) => `₱${Number(val).toLocaleString(undefined, { minimumFractionDigits: 2 })}`
        : (val) => val;

    const icons = {
        total: "fa-chart-line",
        average: "fa-chart-bar",
        highest: "fa-arrow-up",
        lowest: "fa-arrow-down"
    };

    return `
        <div class="summary-box">
        <div class="summary-icon ${color}"><i class="fas ${icons.total}"></i></div>
        <div class="summary-text">
            <span>Total</span><strong>${format(total)}</strong>
        </div>
        </div>
        <div class="summary-box">
        <div class="summary-icon ${color}"><i class="fas ${icons.average}"></i></div>
        <div class="summary-text">
            <span>Average per day</span><strong>${format(avg.toFixed(2))}</strong>
        </div>
        </div>
        <div class="summary-box">
        <div class="summary-icon ${color}"><i class="fas ${icons.highest}"></i></div>
        <div class="summary-text">
            <span>Highest in a day</span><strong>${format(max)}</strong>
        </div>
        </div>
        <div class="summary-box">
        <div class="summary-icon ${color}"><i class="fas ${icons.lowest}"></i></div>
        <div class="summary-text">
            <span>Lowest in a day</span><strong>${format(min)}</strong>
        </div>
        </div>
    `;
    }

    const checkinsCtx = document.getElementById('checkinsChart').getContext('2d');
    new Chart(checkinsCtx, {
      type: 'bar',
      data: {
        labels: checkinLabels,
        datasets: [{
          label: 'Daily Check-ins',
          data: checkinData,
          backgroundColor: 'rgba(13, 110, 253, 0.6)',
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (val) => `${val} check-ins`
            }
          }
        }
      }
    });


    const incomeCtx = document.getElementById('incomeChart').getContext('2d');
    new Chart(incomeCtx, {
      type: 'bar',
      data: {
        labels: incomeLabels,
        datasets: [{
          label: 'Daily Income (₱)',
          data: incomeData,
          backgroundColor: 'rgba(40, 167, 69, 0.6)',
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (val) => `₱${val.toLocaleString()}`
            }
          }
        }
      }
    });


    document.getElementById('checkinsSummary').innerHTML = generateSummaryBoxes(checkinData, 'check-ins', false, 'blue');
    document.getElementById('incomeSummary').innerHTML   = generateSummaryBoxes(incomeData, 'income', true, 'green');



    function updateClock() {
      const now = new Date();
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      document.getElementById('currentDate').innerText = now.toLocaleDateString('en-PH', options);
      document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-PH');
    }
    setInterval(updateClock, 1000);
    updateClock();
  </script>
</body>
</html>