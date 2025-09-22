<?php
// Start session and connect to DB
session_start();

require_once 'database.php'; // Include your database connection settings

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Weekly Income Data
$payment_query = "
    SELECT 
        DATE(check_in_date) AS date,
        SUM(amount_paid) AS daily_income
    FROM 
        checkins
    WHERE 
        check_in_date >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY
    GROUP BY 
        DATE(check_in_date)
    ORDER BY 
        DATE(check_in_date)
";

$payment_result = $conn->query($payment_query);
$dates = [];
$incomes = [];
while ($row = $payment_result->fetch_assoc()) {
    $dates[] = date('D, M j', strtotime($row['date']));
    $incomes[] = (float)$row['daily_income'];
}

// Weekly Check-ins Data
$checkin_query = "
    SELECT 
        DATE(check_in_date) AS date,
        COUNT(*) AS daily_checkins
    FROM 
        checkins
    WHERE 
        check_in_date >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY
    GROUP BY 
        DATE(check_in_date)
    ORDER BY 
        DATE(check_in_date)
";

$checkin_result = $conn->query($checkin_query);
$checkinDates = [];
$dailyCheckins = [];
while ($row = $checkin_result->fetch_assoc()) {
    $checkinDates[] = date('D, M j', strtotime($row['date']));
    $dailyCheckins[] = (int)$row['daily_checkins'];
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
        }

        .content-area {
            margin-left: 270px;
            padding: 30px;
        }

        @media (max-width: 768px) {
            .content-area {
                margin-left: 0;
                padding: 15px;
            }
        }

        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }

        .card-header {
            background-color: #007bff;
            color: white;
        }

        .summary-box {
            margin-top: 10px;
            padding: 10px 15px;
            background-color: #f1f3f5;
            border-left: 4px solid #0d6efd;
            border-radius: 4px;
            font-style: italic;
        }

        .summary-box.income {
            border-left-color: #198754;
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

<<div class="content p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Gitarra Apartelle - Reports </h2>
    <div class="clock-box text-end text-dark">
      <div id="currentDate" class="fw-semibold"></div>
      <div id="currentTime" class="fs-5"></div>
    </div>
  </div>

    <!-- Weekly Check-ins Report -->
    <div class="card mb-4">
        <div class="card-header">
            Weekly Check-ins Overview
        </div>
        <div class="card-body">
            <canvas id="checkinsChart" height="100"></canvas>
            <div class="summary-box mt-3" id="checkinsSummary"></div>
        </div>
    </div>

    <!-- Weekly Income Report -->
    <div class="card mb-4">
        <div class="card-header">
            Weekly Income Overview
        </div>
        <div class="card-body">
            <canvas id="incomeChart" height="100"></canvas>
            <div class="summary-box income mt-3" id="incomeSummary"></div>
        </div>
    </div>
</div>

<!-- Chart Scripts -->
<script>
    const checkinLabels = <?= json_encode($checkinDates); ?>;
    const checkinData = <?= json_encode($dailyCheckins); ?>;
    const incomeLabels = <?= json_encode($dates); ?>;
    const incomeData = <?= json_encode($incomes); ?>;

    // Helper function for summaries
    function generateSummary(data, label, isCurrency = false) {
        const total = data.reduce((sum, val) => sum + val, 0);
        const avg = data.length ? total / data.length : 0;
        const max = Math.max(...data);
        const min = Math.min(...data);

        const format = isCurrency
            ? (val) => `₱${Number(val).toLocaleString(undefined, { minimumFractionDigits: 2 })}`
            : (val) => val;

        return `
            Total ${label}: <strong>${format(total)}</strong><br>
            Average per day: <strong>${format(avg.toFixed(2))}</strong><br>
            Highest ${label} in a day: <strong>${format(max)}</strong><br>
            Lowest ${label} in a day: <strong>${format(min)}</strong>
        `;
    }

    // Check-ins Chart
    const checkinsCtx = document.getElementById('checkinsChart').getContext('2d');
    const checkinsChart = new Chart(checkinsCtx, {
        type: 'line',
        data: {
            labels: checkinLabels,
            datasets: [{
                label: 'Daily Check-ins',
                data: checkinData,
                backgroundColor: 'rgba(13, 110, 253, 0.2)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 2,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.parsed.y} check-ins`
                    }
                }
            },
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

    // Income Chart
    const incomeCtx = document.getElementById('incomeChart').getContext('2d');
    const incomeChart = new Chart(incomeCtx, {
        type: 'bar',
        data: {
            labels: incomeLabels,
            datasets: [{
                label: 'Daily Income (₱)',
                data: incomeData,
                backgroundColor: 'rgba(40, 167, 69, 0.6)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (context) => `₱${context.parsed.y.toLocaleString()}`
                    }
                }
            },
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

    // Generate and insert summaries
    document.getElementById('checkinsSummary').innerHTML = generateSummary(checkinData, 'check-ins');
    document.getElementById('incomeSummary').innerHTML = generateSummary(incomeData, 'income', true);
</script>

</body>
</html>
<script>
    // Adjust canvas size for larger graphs
    document.getElementById('checkinsChart').style.height = '150px';
    document.getElementById('incomeChart').style.height = '150px';

    // Add descriptions for the graphs
    const checkinsDescription = document.createElement('p');
    checkinsDescription.classList.add('text-muted', 'mt-2');
    checkinsDescription.textContent = 'This graph shows the daily check-ins for the current week. It helps track the occupancy trends over the week.';
    document.querySelector('.card-body canvas#checkinsChart').after(checkinsDescription);

    const incomeDescription = document.createElement('p');
    incomeDescription.classList.add('text-muted', 'mt-2');
    incomeDescription.textContent = 'This graph displays the daily income for the current week. It provides insights into the revenue generated each day.';
    document.querySelector('.card-body canvas#incomeChart').after(incomeDescription);

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