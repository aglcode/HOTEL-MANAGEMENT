<?php
session_start();
require_once 'database.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$incomeMonths = [];
$monthlyIncomes = [];
$checkinMonths = [];
$monthlyCheckins = [];

function fetchData($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    return $result;
}

/* ==========================
   MONTHLY INCOME
   ========================== */
$payment_query = "
    SELECT 
        MONTH(check_in_date) AS month_num,
        MONTHNAME(MIN(check_in_date)) AS month_name,
        YEAR(check_in_date) AS year,
        SUM(amount_paid) AS monthly_income
    FROM checkins
    WHERE YEAR(check_in_date) = YEAR(CURDATE())
    GROUP BY YEAR(check_in_date), MONTH(check_in_date)
    ORDER BY YEAR(check_in_date), MONTH(check_in_date)
";
$payment_result = fetchData($conn, $payment_query);

while ($row = $payment_result->fetch_assoc()) {
    $incomeMonths[] = $row['month_name'];
    $monthlyIncomes[] = (float)$row['monthly_income'];
}

/* fallback if no data this year */
if (empty($incomeMonths)) {
    $fallback_payment_query = "
        SELECT 
            MONTH(check_in_date) AS month_num,
            MONTHNAME(MIN(check_in_date)) AS month_name,
            SUM(amount_paid) AS monthly_income
        FROM checkins
        GROUP BY MONTH(check_in_date)
        ORDER BY MONTH(check_in_date)
    ";
    $payment_result = fetchData($conn, $fallback_payment_query);
    while ($row = $payment_result->fetch_assoc()) {
        $incomeMonths[] = $row['month_name'];
        $monthlyIncomes[] = (float)$row['monthly_income'];
    }
}

/* ==========================
   MONTHLY CHECK-INS
   ========================== */
$checkin_query = "
    SELECT 
        MONTH(check_in_date) AS month_num,
        MONTHNAME(MIN(check_in_date)) AS month_name,
        YEAR(check_in_date) AS year,
        COUNT(*) AS monthly_checkins
    FROM checkins
    WHERE YEAR(check_in_date) = YEAR(CURDATE())
    GROUP BY YEAR(check_in_date), MONTH(check_in_date)
    ORDER BY YEAR(check_in_date), MONTH(check_in_date)
";
$checkin_result = fetchData($conn, $checkin_query);

while ($row = $checkin_result->fetch_assoc()) {
    $checkinMonths[] = $row['month_name'];
    $monthlyCheckins[] = (int)$row['monthly_checkins'];
}

/* fallback if no data this year */
if (empty($checkinMonths)) {
    $fallback_checkin_query = "
        SELECT 
            MONTH(check_in_date) AS month_num,
            MONTHNAME(MIN(check_in_date)) AS month_name,
            COUNT(*) AS monthly_checkins
        FROM checkins
        GROUP BY MONTH(check_in_date)
        ORDER BY MONTH(check_in_date)
    ";
    $checkin_result = fetchData($conn, $fallback_checkin_query);
    while ($row = $checkin_result->fetch_assoc()) {
        $checkinMonths[] = $row['month_name'];
        $monthlyCheckins[] = (int)$row['monthly_checkins'];
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
  <!-- Monthly Check-ins -->
  <div class="card">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5><i class="fas fa-chart-bar text-primary"></i> Monthly Check-ins</h5>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-danger" onclick="exportCheckinsPDF()">
          <i class="fas fa-file-pdf"></i> Export PDF
        </button>
        <button class="btn btn-sm btn-outline-success" onclick="exportCheckinsCSV()">
          <i class="fas fa-file-csv"></i> Export CSV
        </button>
      </div>
    </div>
    <div id="checkinsCard">
      <p class="description">
        This graph shows the total check-ins for each month of the current year. It helps track occupancy trends over time.
      </p>
      <canvas id="checkinsChart" height="100"></canvas>
      <div class="summary-grid" id="checkinsSummary"></div>
    </div>
  </div>

  <!-- Monthly Income -->
  <div class="card">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5><i class="fas fa-dollar-sign text-success"></i> Monthly Income</h5>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-danger" onclick="exportIncomePDF()">
          <i class="fas fa-file-pdf"></i> Export PDF
        </button>
        <button class="btn btn-sm btn-outline-success" onclick="exportIncomeCSV()">
          <i class="fas fa-file-csv"></i> Export CSV
        </button>
      </div>
    </div>
    <div id="incomeCard">
      <p class="description">
        This graph displays the total income generated per month for the current year. It provides insights into the overall revenue performance.
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
 const checkinLabels = <?php echo json_encode($checkinMonths); ?>; // e.g. ["January", "February", "March"]
const checkinData   = <?php echo json_encode($monthlyCheckins); ?>; // aggregated per month

const incomeLabels  = <?php echo json_encode($incomeMonths); ?>; // same month labels
const incomeData    = <?php echo json_encode($monthlyIncomes); ?>; // aggregated per month

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
        <span>Average per month</span><strong>${format(avg.toFixed(2))}</strong>
      </div>
    </div>
    <div class="summary-box">
      <div class="summary-icon ${color}"><i class="fas ${icons.highest}"></i></div>
      <div class="summary-text">
        <span>Highest month</span><strong>${format(max)}</strong>
      </div>
    </div>
    <div class="summary-box">
      <div class="summary-icon ${color}"><i class="fas ${icons.lowest}"></i></div>
      <div class="summary-text">
        <span>Lowest month</span><strong>${format(min)}</strong>
      </div>
    </div>
  `;
}

// === Monthly Check-ins Chart ===
const checkinsCtx = document.getElementById('checkinsChart').getContext('2d');
new Chart(checkinsCtx, {
  type: 'bar',
  data: {
    labels: checkinLabels,
    datasets: [{
      label: 'Monthly Check-ins',
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
    },
    plugins: {
      title: {
        display: true,
        text: 'Monthly Check-ins Overview (Current Year)'
      }
    }
  }
});

// === Monthly Income Chart ===
const incomeCtx = document.getElementById('incomeChart').getContext('2d');
new Chart(incomeCtx, {
  type: 'bar',
  data: {
    labels: incomeLabels,
    datasets: [{
      label: 'Monthly Income (₱)',
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
    },
    plugins: {
      title: {
        display: true,
        text: 'Monthly Income Overview (Current Year)'
      }
    }
  }
});

// === Summary Boxes ===
document.getElementById('checkinsSummary').innerHTML = generateSummaryBoxes(checkinData, 'check-ins', false, 'blue');
document.getElementById('incomeSummary').innerHTML   = generateSummaryBoxes(incomeData, 'income', true, 'green');


// export pdf (Monthly Check-ins)
function exportCheckinsPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  // Header
  const exportDate = new Date().toLocaleDateString("en-PH", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  doc.setFontSize(10);
  doc.text(`Date exported: ${exportDate}`, 14, 12);

  // Report Title
  doc.setFontSize(16);
  doc.setFont("helvetica", "bold");
  doc.text("Gitarra Apartelle - Reports", 105, 32, { align: "center" });
  doc.setFontSize(13);
  doc.setFont("helvetica", "normal");
  doc.text("Monthly Check-ins Report", 105, 42, { align: "center" });

  // Line under title
  doc.setDrawColor(150);
  doc.line(14, 46, 196, 46);

  // Prepare Monthly Data (label already month names like “January”)
  const body = checkinLabels.map((month, i) => [
    month,
    checkinData[i].toString(),
  ]);

  // Build Table
  doc.autoTable({
    head: [["Month", "Total Check-ins"]],
    body: body,
    startY: 54,
    theme: "grid",
    styles: { halign: "center", cellPadding: 3 },
    headStyles: { fillColor: [0, 102, 204], textColor: 255, fontStyle: "bold" },
    alternateRowStyles: { fillColor: [245, 245, 245] },
  });

  // Summary
  const total = checkinData.reduce((a, b) => a + b, 0);
  const avg = (total / checkinData.length).toFixed(2);
  const highest = Math.max(...checkinData);
  const lowest = Math.min(...checkinData);
  let startY = doc.lastAutoTable.finalY + 10;

  doc.setFillColor(240, 240, 240);
  doc.rect(14, startY, 182, 18, "F");

  doc.setFontSize(11);
  doc.setFont("helvetica", "bold");
  doc.text(`Total: ${total}`, 18, startY + 6);
  doc.text(`Average: ${avg}`, 110, startY + 6);
  doc.text(`Highest: ${highest}`, 18, startY + 12);
  doc.text(`Lowest: ${lowest}`, 110, startY + 12);

  // Save File
  doc.save("Monthly_Checkins_Report.pdf");
}

// export pdf (Monthly Income)
function exportIncomePDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  const formatPeso = (value) =>
    "PHP " + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

  // Header
  const exportDate = new Date().toLocaleDateString("en-PH", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  doc.setFontSize(10);
  doc.text(`Date exported: ${exportDate}`, 14, 12);

  // Report Title
  doc.setFontSize(16);
  doc.setFont("helvetica", "bold");
  doc.text("Gitarra Apartelle - Reports", 105, 32, { align: "center" });
  doc.setFontSize(13);
  doc.setFont("helvetica", "normal");
  doc.text("Monthly Income Report", 105, 42, { align: "center" });

  // Line under title
  doc.setDrawColor(150);
  doc.line(14, 46, 196, 46);

  // Prepare Monthly Data (label already month names)
  const body = incomeLabels.map((month, i) => [
    month,
    formatPeso(incomeData[i]),
  ]);

  // Build Table
  doc.autoTable({
    head: [["Month", "Total Income"]],
    body: body,
    startY: 54,
    theme: "grid",
    styles: { halign: "center", cellPadding: 3 },
    headStyles: { fillColor: [25, 135, 84], textColor: 255, fontStyle: "bold" },
    alternateRowStyles: { fillColor: [245, 245, 245] },
  });

  // Summary
  const total = incomeData.reduce((a, b) => a + b, 0);
  const avg = total / incomeData.length;
  const highest = Math.max(...incomeData);
  const lowest = Math.min(...incomeData);
  let startY = doc.lastAutoTable.finalY + 10;

  doc.setFillColor(240, 240, 240);
  doc.rect(14, startY, 182, 18, "F");

  doc.setFontSize(11);
  doc.setFont("helvetica", "bold");
  doc.text(`Total: ${formatPeso(total)}`, 18, startY + 6);
  doc.text(`Average: ${formatPeso(avg)}`, 110, startY + 6);
  doc.text(`Highest: ${formatPeso(highest)}`, 18, startY + 12);
  doc.text(`Lowest: ${formatPeso(lowest)}`, 110, startY + 12);

  // Save File
  doc.save("Monthly_Income_Report.pdf");
}

// export csv (Monthly Check-ins)
function exportCheckinsCSV() {
  let csvContent = "data:text/csv;charset=utf-8,";

  // Header row
  csvContent += "Month,Total Check-ins,Average,Highest,Lowest\n";

  // Since checkinLabels and checkinData are already monthly
  for (let i = 0; i < checkinLabels.length; i++) {
    const month = checkinLabels[i];
    const total = checkinData[i];

    // For monthly, only one value per month (so all same)
    csvContent += `${month},${total},${total},${total},${total}\n`;
  }

  // Grand summary
  const totalAll = checkinData.reduce((a, b) => a + b, 0);
  const avgAll = (totalAll / checkinData.length).toFixed(2);
  const highest = Math.max(...checkinData);
  const lowest = Math.min(...checkinData);

  csvContent += `\nOverall Summary,Total: ${totalAll},Average: ${avgAll},Highest: ${highest},Lowest: ${lowest}\n`;

  // Trigger download
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "Monthly_Checkins_Report.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// export csv (Monthly Income)
function exportIncomeCSV() {
  let csvContent = "data:text/csv;charset=utf-8,";

  // Header row
  csvContent += "Month,Total Income (PHP),Average,Highest,Lowest\n";

  // Peso format
  const formatPeso = (value) =>
    "PHP " + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

  // Monthly rows
  for (let i = 0; i < incomeLabels.length; i++) {
    const month = incomeLabels[i];
    const total = incomeData[i];
    csvContent += `${month},"${formatPeso(total)}","${formatPeso(total)}","${formatPeso(total)}","${formatPeso(total)}"\n`;
  }

  // Overall summary
  const totalAll = incomeData.reduce((a, b) => a + b, 0);
  const avgAll = totalAll / incomeData.length;
  const highest = Math.max(...incomeData);
  const lowest = Math.min(...incomeData);

  csvContent += `\nOverall Summary,"${formatPeso(totalAll)}","${formatPeso(avgAll)}","${formatPeso(highest)}","${formatPeso(lowest)}"\n`;

  // Trigger download
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "Monthly_Income_Report.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}



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