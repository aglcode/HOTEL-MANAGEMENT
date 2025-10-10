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
    $dates[] = $row['date']; 
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
      $dates[]   = $row['date'];
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
    $checkinDates[] = $row['date'];
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
        $checkinDates[] = $row['date'];
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
      <!-- Daily Check-ins -->
    <div class="card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5><i class="fas fa-chart-bar text-primary"></i> Daily Check-ins</h5>
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
          This graph shows the daily check-ins for the current week. It helps track the occupancy trends over the week.
        </p>
        <canvas id="checkinsChart" height="100"></canvas>
        <div class="summary-grid" id="checkinsSummary"></div>
      </div>
    </div>

      <!-- Daily Income -->
    <div class="card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5><i class="fas fa-dollar-sign text-success"></i> Daily Income</h5>
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
          This graph displays the daily income for the current week. It provides insights into the revenue generated each day.
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


    // export pdf
 function exportCheckinsPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  // Header
  const exportDate = new Date().toLocaleDateString("en-PH", { 
    year: 'numeric', month: 'long', day: 'numeric' 
  });
  doc.setFontSize(10);
  doc.text(`Date exported: ${exportDate}`, 14, 12);

  // Report Title
  doc.setFontSize(16);
  doc.setFont("helvetica", "bold");
  doc.text("Gitarra Apartelle - Reports", 105, 32, { align: "center" });
  doc.setFontSize(13);
  doc.setFont("helvetica", "normal");
  doc.text("Daily Check-ins Report", 105, 42, { align: "center" });

  // Line under title
  doc.setDrawColor(150);
  doc.line(14, 46, 196, 46);

  // Group by month
  const grouped = {};
  checkinLabels.forEach((label, i) => {
    const dateObj = new Date(label);
    const month = dateObj.toLocaleString("en-PH", { month: "long", year: "numeric" });
    const fullDate = dateObj.toLocaleDateString("en-PH", { 
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    if (!grouped[month]) grouped[month] = [];
    grouped[month].push([fullDate, checkinData[i]]);
  });

  let startY = 54;
  Object.keys(grouped).forEach(month => {
    // Month Title
    doc.setFontSize(12);
    doc.setFont("helvetica", "bold");
    doc.text(month, 14, startY);
    startY += 6;

    // Table with better styles
    doc.autoTable({
      head: [['Date', 'Number of Check-ins']],
      body: grouped[month],
      startY: startY,
      theme: 'grid',
      styles: { halign: 'center', cellPadding: 3 },
      headStyles: { fillColor: [0, 102, 204], textColor: 255, fontStyle: 'bold' },
      alternateRowStyles: { fillColor: [245, 245, 245] }
    });

    // Monthly Summary
    const values = grouped[month].map(row => row[1]);
    const total = values.reduce((a, b) => a + b, 0);
    const avg = (total / values.length).toFixed(2);
    const highest = Math.max(...values);
    const lowest = Math.min(...values);

    startY = doc.lastAutoTable.finalY + 8;

    // Summary Box
    doc.setFillColor(240, 240, 240);
    doc.rect(14, startY, 182, 18, "F"); // background box

    doc.setFontSize(11);
    doc.setFont("helvetica", "bold");
    doc.text(`Total: ${total}`, 18, startY + 6);
    doc.text(`Average: ${avg}`, 110, startY + 6);
    doc.text(`Highest: ${highest}`, 18, startY + 12);
    doc.text(`Lowest: ${lowest}`, 110, startY + 12);

    startY += 26; // extra space before next month
  });

  doc.save("Daily_Checkins_Report.pdf");
}

// export pdf (Daily Income)
function exportIncomePDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  // Peso formatter
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
  doc.text("Gitarra Apartelle - Reports", 105, 36, { align: "center" });
  doc.setFontSize(13);
  doc.setFont("helvetica", "normal");
  doc.text("Daily Income Report", 105, 46, { align: "center" });

  // Line under title
  doc.setDrawColor(150);
  doc.line(14, 50, 196, 50);

  // Group by month
  const grouped = {};
  incomeLabels.forEach((label, i) => {
    const dateObj = new Date(label);
    const month = dateObj.toLocaleString("en-PH", {
      month: "long",
      year: "numeric",
    });
    const fullDate = dateObj.toLocaleDateString("en-PH", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
    if (!grouped[month]) grouped[month] = [];
    grouped[month].push([fullDate, incomeData[i]]);
  });

  let startY = 58;
  Object.keys(grouped).forEach((month, index) => {
    if (index > 0) {
      // Add a new page for the next month
      doc.addPage();
      startY = 20;
    }

    // Month Title
    doc.setFontSize(12);
    doc.setFont("helvetica", "bold");
    doc.text(month, 14, startY);
    startY += 6;

    // Table with better styles
    doc.autoTable({
      head: [["Date", "Income (PHP)"]],
      body: grouped[month].map((row) => [row[0], formatPeso(row[1])]),
      startY: startY,
      theme: "grid",
      styles: { halign: "center", cellPadding: 3 },
      headStyles: {
        fillColor: [25, 135, 84],
        textColor: 255,
        fontStyle: "bold",
      },
      alternateRowStyles: { fillColor: [245, 245, 245] },
    });

    // Monthly Summary
    const values = grouped[month].map((row) => row[1]);
    const total = values.reduce((a, b) => a + b, 0);
    const avg = total / values.length;
    const highest = Math.max(...values);
    const lowest = Math.min(...values);

    startY = doc.lastAutoTable.finalY + 8;

    // Summary Box
    doc.setFillColor(240, 240, 240);
    doc.rect(14, startY, 182, 18, "F"); // background box

    doc.setFontSize(11);
    doc.setFont("times", "bold");
    doc.text(`Total: ${formatPeso(total)}`, 18, startY + 6);
    doc.text(`Average: ${formatPeso(avg)}`, 110, startY + 6);
    doc.text(`Highest: ${formatPeso(highest)}`, 18, startY + 12);
    doc.text(`Lowest: ${formatPeso(lowest)}`, 110, startY + 12);
  });

  doc.save("Daily_Income_Report.pdf");
}

// export CSV
// export csv (Daily Check-ins)
function exportCheckinsCSV() {
  let csvContent = "data:text/csv;charset=utf-8,";

  // Header row
  csvContent += "Month,Date,Number of Check-ins\n";

  // Group by month
  const grouped = {};
  checkinLabels.forEach((label, i) => {
    const dateObj = new Date(label);
    const month = dateObj.toLocaleString("en-PH", { month: "long", year: "numeric" });
    const fullDate = dateObj.toLocaleDateString("en-PH", { 
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    if (!grouped[month]) grouped[month] = [];
    grouped[month].push([fullDate, checkinData[i]]);
  });

  // Add rows
  Object.keys(grouped).forEach(month => {
    grouped[month].forEach(row => {
      csvContent += `${month},"${row[0]}",${row[1]}\n`;
    });
  });

  // Trigger download
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "Daily_Checkins_Report.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// export csv (Daily Income)
function exportIncomeCSV() {
  let csvContent = "data:text/csv;charset=utf-8,";

  // Header row
  csvContent += "Month,Date,Income (PHP)\n";

  // Group by month
  const grouped = {};
  incomeLabels.forEach((label, i) => {
    const dateObj = new Date(label);
    const month = dateObj.toLocaleString("en-PH", { month: "long", year: "numeric" });
    const fullDate = dateObj.toLocaleDateString("en-PH", { 
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
    });
    if (!grouped[month]) grouped[month] = [];
    grouped[month].push([fullDate, incomeData[i]]);
  });

  // Add rows
  Object.keys(grouped).forEach(month => {
    grouped[month].forEach(row => {
      csvContent += `${month},"${row[0]}","PHP ${Number(row[1]).toLocaleString(undefined, { minimumFractionDigits: 2 })}"\n`;
    });
  });

  // Trigger download
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "Daily_Income_Report.csv");
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