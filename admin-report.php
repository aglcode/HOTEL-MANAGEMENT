<?php
session_start();
require_once 'database.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function fetchData($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    return $result;
}

/* ==========================
   DAILY, WEEKLY & MONTHLY DATA
   ========================== */

// Get current year data grouped by day
$report_query = "
    SELECT 
        DATE(check_in_date) AS date,
        COUNT(*) AS daily_checkins,
        SUM(amount_paid) AS daily_income
    FROM checkins
    WHERE YEAR(check_in_date) = YEAR(CURDATE())
    GROUP BY DATE(check_in_date)
    ORDER BY DATE(check_in_date)
";

$report_result = fetchData($conn, $report_query);

$dailyData = [];
$weeklyData = [];
$monthlyData = [];

// Process the results
while ($row = $report_result->fetch_assoc()) {
    $date = $row['date'];
    $dateObj = new DateTime($date);
    
    // Store daily data
    $dailyData[] = [
        'date' => $date,
        'month_name' => $dateObj->format('F'),
        'week_num' => $dateObj->format('W'),
        'month_num' => $dateObj->format('n'),
        'checkins' => (int)$row['daily_checkins'],
        'income' => (float)$row['daily_income']
    ];
}

// Aggregate weekly data
foreach ($dailyData as $day) {
    $weekKey = $day['month_name'] . ' W' . $day['week_num'];
    if (!isset($weeklyData[$weekKey])) {
        $weeklyData[$weekKey] = ['checkins' => 0, 'income' => 0];
    }
    $weeklyData[$weekKey]['checkins'] += $day['checkins'];
    $weeklyData[$weekKey]['income'] += $day['income'];
}

// Aggregate monthly data
foreach ($dailyData as $day) {
    $monthKey = $day['month_name'];
    if (!isset($monthlyData[$monthKey])) {
        $monthlyData[$monthKey] = ['checkins' => 0, 'income' => 0];
    }
    $monthlyData[$monthKey]['checkins'] += $day['checkins'];
    $monthlyData[$monthKey]['income'] += $day['income'];
}

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Report - Weekly Income & Check-ins</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

  /* === Sidebar Navigation === */
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
      <a href="admin-report.php" class="active"><i class="fa-solid fa-file-lines"></i> Reports</a>
      <a href="admin-supplies.php"><i class="fa-solid fa-cube"></i> Supplies</a>
      <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
      <a href="admin-archive.php"><i class="fa-solid fa-archive"></i> Archived</a>
    </div>

    <div class="signout">
      <a href="admin-logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
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
  <!-- Check-ins Card -->
<div class="card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5><i class="fas fa-chart-bar text-primary"></i> Check-ins Report</h5>
    <div class="d-flex gap-2">
      <select id="checkinsPeriod" class="form-select form-select-sm" style="width: 150px;" onchange="updateCheckinsChart()">
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="monthly" selected>Monthly</option>
      </select>
      <button class="btn btn-sm btn-outline-danger" onclick="exportCheckinsPDF()">
        <i class="fas fa-file-pdf"></i> Export PDF
      </button>
      <button class="btn btn-sm btn-outline-success" onclick="exportCheckinsCSV()">
        <i class="fas fa-file-csv"></i> Export CSV
      </button>
    </div>
  </div>
  <div id="checkinsCard">
    <p class="description" id="checkinsDescription">
      This graph shows the total check-ins for each month of the current year.
    </p>
    <canvas id="checkinsChart" height="100"></canvas>
    <div class="summary-grid" id="checkinsSummary"></div>
  </div>
</div>

  <!-- Monthly Income -->
  <div class="card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5><i class="fas fa-dollar-sign text-success"></i> Income Report</h5>
    <div class="d-flex gap-2">
      <select id="incomePeriod" class="form-select form-select-sm" style="width: 150px;" onchange="updateIncomeChart()">
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="monthly" selected>Monthly</option>
      </select>
      <button class="btn btn-sm btn-outline-danger" onclick="exportIncomePDF()">
        <i class="fas fa-file-pdf"></i> Export PDF
      </button>
      <button class="btn btn-sm btn-outline-success" onclick="exportIncomeCSV()">
        <i class="fas fa-file-csv"></i> Export CSV
      </button>
    </div>
  </div>
  <div id="incomeCard">
    <p class="description" id="incomeDescription">
      This graph displays the total income generated per month for the current year.
    </p>
    <canvas id="incomeChart" height="100"></canvas>
    <div class="summary-grid" id="incomeSummary"></div>
  </div>
</div>

  <div class="footer">
    Dashboard updates in real-time • Last updated: Just now
  </div>
</div>
   
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

  <script src="https://kit.fontawesome.com/2d3e32b10b.js" crossorigin="anonymous"></script>

  <script>
// PHP data
const dailyData = <?php echo json_encode(array_values($dailyData)); ?>;
const weeklyData = <?php echo json_encode($weeklyData); ?>;
const monthlyData = <?php echo json_encode($monthlyData); ?>;

// Prepare data structures
const reportData = {
  daily: {
    labels: dailyData.map(d => d.date),
    checkins: dailyData.map(d => d.checkins),
    income: dailyData.map(d => d.income)
  },
  weekly: {
    labels: Object.keys(weeklyData),
    checkins: Object.values(weeklyData).map(d => d.checkins),
    income: Object.values(weeklyData).map(d => d.income)
  },
  monthly: {
    labels: Object.keys(monthlyData),
    checkins: Object.values(monthlyData).map(d => d.checkins),
    income: Object.values(monthlyData).map(d => d.income)
  }
};

// Chart instances
let checkinsChartInstance = null;
let incomeChartInstance = null;

// Initialize charts
function initCheckinsChart(period = 'monthly') {
  const ctx = document.getElementById('checkinsChart').getContext('2d');
  const data = reportData[period];
  
  if (checkinsChartInstance) {
    checkinsChartInstance.destroy();
  }
  
  checkinsChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [{
        label: `${period.charAt(0).toUpperCase() + period.slice(1)} Check-ins`,
        data: data.checkins,
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
      },
      plugins: {
        title: {
          display: true,
          text: `${period.charAt(0).toUpperCase() + period.slice(1)} Check-ins Overview`
        }
      }
    }
  });
  
  updateSummary('checkins', data.checkins, false);
  updateDescription('checkins', period);
}

function initIncomeChart(period = 'monthly') {
  const ctx = document.getElementById('incomeChart').getContext('2d');
  const data = reportData[period];
  
  if (incomeChartInstance) {
    incomeChartInstance.destroy();
  }
  
  incomeChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [{
        label: `${period.charAt(0).toUpperCase() + period.slice(1)} Income (₱)`,
        data: data.income,
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
      },
      plugins: {
        title: {
          display: true,
          text: `${period.charAt(0).toUpperCase() + period.slice(1)} Income Overview`
        }
      }
    }
  });
  
  updateSummary('income', data.income, true);
  updateDescription('income', period);
}

function updateCheckinsChart() {
  const period = document.getElementById('checkinsPeriod').value;
  initCheckinsChart(period);
}

function updateIncomeChart() {
  const period = document.getElementById('incomePeriod').value;
  initIncomeChart(period);
}

function updateDescription(type, period) {
  const descriptions = {
    daily: type === 'checkins' 
      ? 'This graph shows the total check-ins for each day of the current year.'
      : 'This graph displays the total income generated per day for the current year.',
    weekly: type === 'checkins'
      ? 'This graph shows the total check-ins for each week of the current year.'
      : 'This graph displays the total income generated per week for the current year.',
    monthly: type === 'checkins'
      ? 'This graph shows the total check-ins for each month of the current year.'
      : 'This graph displays the total income generated per month for the current year.'
  };
  
  document.getElementById(`${type}Description`).textContent = descriptions[period];
}

function updateSummary(type, data, isCurrency) {
  const total = data.reduce((sum, val) => sum + val, 0);
  const avg = data.length ? total / data.length : 0;
  const max = Math.max(...data);
  const min = Math.min(...data);
  
  const format = isCurrency
    ? (val) => `₱${Number(val).toLocaleString(undefined, { minimumFractionDigits: 2 })}`
    : (val) => val;
  
  const color = type === 'checkins' ? 'blue' : 'green';
  const period = document.getElementById(`${type}Period`).value;
  
  document.getElementById(`${type}Summary`).innerHTML = `
    <div class="summary-box">
      <div class="summary-icon ${color}"><i class="fas fa-chart-line"></i></div>
      <div class="summary-text">
        <span>Total</span><strong>${format(total)}</strong>
      </div>
    </div>
    <div class="summary-box">
      <div class="summary-icon ${color}"><i class="fas fa-chart-bar"></i></div>
      <div class="summary-text">
        <span>Average per ${period.slice(0, -2)}</span><strong>${format(avg.toFixed(2))}</strong>
      </div>
    </div>
    <div class="summary-box">
      <div class="summary-icon ${color}"><i class="fas fa-arrow-up"></i></div>
      <div class="summary-text">
        <span>Highest ${period.slice(0, -2)}</span><strong>${format(max)}</strong>
      </div>
    </div>
    <div class="summary-box">
      <div class="summary-icon ${color}"><i class="fas fa-arrow-down"></i></div>
      <div class="summary-text">
        <span>Lowest ${period.slice(0, -2)}</span><strong>${format(min)}</strong>
      </div>
    </div>
  `;
}

// Export functions (update these to use current period)
function exportCheckinsPDF() {
  const period = document.getElementById('checkinsPeriod').value;
  const data = reportData[period];
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  const exportDate = new Date().toLocaleDateString("en-PH", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  doc.setFontSize(10);
  doc.text(`Date exported: ${exportDate}`, 14, 12);

  doc.setFontSize(16);
  doc.setFont("helvetica", "bold");
  doc.text("Gitarra Apartelle - Reports", 105, 32, { align: "center" });
  doc.setFontSize(13);
  doc.setFont("helvetica", "normal");
  doc.text(`${period.charAt(0).toUpperCase() + period.slice(1)} Check-ins Report`, 105, 42, { align: "center" });

  doc.setDrawColor(150);
  doc.line(14, 46, 196, 46);

  const body = data.labels.map((label, i) => [
    label,
    data.checkins[i].toString(),
  ]);

  doc.autoTable({
    head: [[period.charAt(0).toUpperCase() + period.slice(1), "Total Check-ins"]],
    body: body,
    startY: 54,
    theme: "grid",
    styles: { halign: "center", cellPadding: 3 },
    headStyles: { fillColor: [0, 102, 204], textColor: 255, fontStyle: "bold" },
    alternateRowStyles: { fillColor: [245, 245, 245] },
  });

  const total = data.checkins.reduce((a, b) => a + b, 0);
  const avg = (total / data.checkins.length).toFixed(2);
  const highest = Math.max(...data.checkins);
  const lowest = Math.min(...data.checkins);
  let startY = doc.lastAutoTable.finalY + 10;

  doc.setFillColor(240, 240, 240);
  doc.rect(14, startY, 182, 18, "F");

  doc.setFontSize(11);
  doc.setFont("helvetica", "bold");
  doc.text(`Total: ${total}`, 18, startY + 6);
  doc.text(`Average: ${avg}`, 110, startY + 6);
  doc.text(`Highest: ${highest}`, 18, startY + 12);
  doc.text(`Lowest: ${lowest}`, 110, startY + 12);

  doc.save(`${period}_Checkins_Report.pdf`);
}

function exportIncomePDF() {
  const period = document.getElementById('incomePeriod').value;
  const data = reportData[period];
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  const formatPeso = (value) =>
    "PHP " + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

  const exportDate = new Date().toLocaleDateString("en-PH", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  doc.setFontSize(10);
  doc.text(`Date exported: ${exportDate}`, 14, 12);

  doc.setFontSize(16);
  doc.setFont("helvetica", "bold");
  doc.text("Gitarra Apartelle - Reports", 105, 32, { align: "center" });
  doc.setFontSize(13);
  doc.setFont("helvetica", "normal");
  doc.text(`${period.charAt(0).toUpperCase() + period.slice(1)} Income Report`, 105, 42, { align: "center" });

  doc.setDrawColor(150);
  doc.line(14, 46, 196, 46);

  const body = data.labels.map((label, i) => [
    label,
    formatPeso(data.income[i]),
  ]);

  doc.autoTable({
    head: [[period.charAt(0).toUpperCase() + period.slice(1), "Total Income"]],
    body: body,
    startY: 54,
    theme: "grid",
    styles: { halign: "center", cellPadding: 3 },
    headStyles: { fillColor: [25, 135, 84], textColor: 255, fontStyle: "bold" },
    alternateRowStyles: { fillColor: [245, 245, 245] },
  });

  const total = data.income.reduce((a, b) => a + b, 0);
  const avg = total / data.income.length;
  const highest = Math.max(...data.income);
  const lowest = Math.min(...data.income);
  let startY = doc.lastAutoTable.finalY + 10;

  doc.setFillColor(240, 240, 240);
  doc.rect(14, startY, 182, 18, "F");

  doc.setFontSize(11);
  doc.setFont("helvetica", "bold");
  doc.text(`Total: ${formatPeso(total)}`, 18, startY + 6);
  doc.text(`Average: ${formatPeso(avg)}`, 110, startY + 6);
  doc.text(`Highest: ${formatPeso(highest)}`, 18, startY + 12);
  doc.text(`Lowest: ${formatPeso(lowest)}`, 110, startY + 12);

  doc.save(`${period}_Income_Report.pdf`);
}

function exportCheckinsCSV() {
  const period = document.getElementById('checkinsPeriod').value;
  const data = reportData[period];
  let csvContent = "data:text/csv;charset=utf-8,";

  csvContent += `${period.charAt(0).toUpperCase() + period.slice(1)},Total Check-ins\n`;

  for (let i = 0; i < data.labels.length; i++) {
    csvContent += `${data.labels[i]},${data.checkins[i]}\n`;
  }

  const totalAll = data.checkins.reduce((a, b) => a + b, 0);
  const avgAll = (totalAll / data.checkins.length).toFixed(2);
  const highest = Math.max(...data.checkins);
  const lowest = Math.min(...data.checkins);

  csvContent += `\nSummary,Total: ${totalAll},Average: ${avgAll},Highest: ${highest},Lowest: ${lowest}\n`;

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `${period}_Checkins_Report.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function exportIncomeCSV() {
  const period = document.getElementById('incomePeriod').value;
  const data = reportData[period];
  let csvContent = "data:text/csv;charset=utf-8,";

  const formatPeso = (value) =>
    "PHP " + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

  csvContent += `${period.charAt(0).toUpperCase() + period.slice(1)},Total Income (PHP)\n`;

  for (let i = 0; i < data.labels.length; i++) {
    csvContent += `${data.labels[i]},"${formatPeso(data.income[i])}"\n`;
  }

  const totalAll = data.income.reduce((a, b) => a + b, 0);
  const avgAll = totalAll / data.income.length;
  const highest = Math.max(...data.income);
  const lowest = Math.min(...data.income);

  csvContent += `\nSummary,"${formatPeso(totalAll)}","${formatPeso(avgAll)}","${formatPeso(highest)}","${formatPeso(lowest)}"\n`;

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `${period}_Income_Report.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Initialize with monthly view
initCheckinsChart('monthly');
initIncomeChart('monthly');

// Clock functions (keep your existing clock code)
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