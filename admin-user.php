<?php
session_start();
require_once 'database.php'; 
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is an admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: signin.php");
    exit();
}

// ====== Send Verification Code ======
if (isset($_POST['send_verification'])) {
    $email = $_POST['email'];

    // Generate 6-digit code
    $verification_code = rand(100000, 999999);
    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['verification_email'] = $email;
    $_SESSION['verification_expiry'] = time() + (10 * 60); // expiry timestamp

    // Send email via PHPMailer
    $mail = new PHPMailer(true);

try {
    // Debugging
    $mail->SMTPDebug = 3;  
    $mail->Debugoutput = 'echo';  

    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'gitarraapartelle@gmail.com'; 
    $mail->Password = 'pngssmeypubvvhvg';   
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
    $mail->Port = 587;

    // Recipients
    $mail->setFrom('gitarraapartelle@gmail.com', 'Gitarra Apartelle');
    $mail->addAddress($email);

    // embededd logo
    // $mail->AddEmbeddedImage(__DIR__ . '/Image/logo.jpg', 'logo_cid');

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Verify Your Email Address";

    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    </head>
    <body style="font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px;">

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05);">
            
            <!-- Logo -->
                <tr>
                    <td align="center" style="padding: 30px 20px 10px;">
                    <img src="https://scontent.fmnl4-2.fna.fbcdn.net/v/t39.30808-6/385490993_637069471904349_5667210045998647721_n.jpg?_nc_cat=101&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeGzZeSS-VOU3mip2PYWEEzq1gvmc1__1N7WC-ZzX__U3mLCFaIrm7hU1jRbcR-aF-UDhFluOFLrdBAFUTM2hObl&_nc_ohc=dKD1RXbHvvkQ7kNvwG8VGOA&_nc_oc=AdmimU2yBWktWuhmYpR8Fnf12qph0CsLU3poBxHzYZUTgTvb5B4y_wTVy9ILZRovBH21sK152rXhDsLM6ON2Nl3F&_nc_zt=23&_nc_ht=scontent.fmnl4-2.fna&_nc_gid=duKeXcCy8oVLyFMJ-tw7pg&oh=00_Afa09rl7bQO66navTj9nz0We2qZUBrfnLGC_oVInN4PoaQ&oe=68E30617" alt="Gitarra Apartelle" style="display:block; margin-bottom:10px; max-width:150px; height:auto;">
                    <h2 style="margin: 0; font-size:22px; color:#333;">Gitarra Apartelle</h2>
                    </td>
                </tr>

            <!-- Title -->
            <tr>
                <td align="center" style="padding: 10px 30px;">
                <h3 style="margin:0; font-size:20px; color:#333;">Verify Your Email Address</h3>
                </td>
            </tr>

            <!-- Body Text -->
            <tr>
                <td style="padding: 20px 30px; font-size:16px; line-height:1.6; color:#555;">
                <p>Thank you for choosing <b>Gitarra Apartelle</b>. To complete your registration and secure your account, please use the verification code below:</p>
                </td>
            </tr>

            <!-- Verification Code -->
            <tr>
                <td align="center" style="padding: 10px 30px;">
                <div style="background:#f4f6f9; border:1px solid #e0e0e0; border-radius:6px; padding:20px; display:inline-block;">
                    <span style="font-size:28px; font-weight:bold; letter-spacing:4px; color:#2c3e50;">
                    ' . $verification_code . '
                    </span>
                </div>
                </td>
            </tr>

            <!-- Expiration Note -->
            <tr>
            <td style="padding: 20px 30px; font-size:14px; color:#555;">
                <p>
                This code will expire in 
                <b><span style="color:#FF0000;">10 minutes</span></b> 
                for security purposes.
                </p>
                <p>
                If you didn’t request this verification code, please ignore this email or contact our support team if you have concerns.
                </p>
            </td>
            </tr>

            <!-- Footer -->
            <tr>
                <td align="center" style="background:#f9f9f9; padding: 15px; font-size:13px; color:#777;">
                <p style="margin: 0; font-weight:bold;">Gitarra Apartelle</p>
                <p style="margin: 5px 0 0;">This is an automated message, please do not reply.</p>
                <p style="margin: 5px 0 0;">&copy; ' . date("Y") . ' Gitarra Apartelle. All rights reserved.</p>
                </td>
            </tr>

            </table>
        </td>
        </tr>
    </table>

    </body>
    </html>
    ';

    echo "<p>📡 Sending... Please wait...</p>";
    flush();

    $mail->send();
    echo "<h3 style='color:green;'>Verification code sent successfully to $email ✅</h3>";
    } catch (Exception $e) {
        echo "<h3 style='color:red;'>Mailer Exception: {$mail->ErrorInfo}</h3>";
    }
}


// ====== Add User After Code Verified ======
if (isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $input_code = $_POST['verification_code']; // field from form
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check code
    // if (!isset($_SESSION['verification_code']) || $input_code != $_SESSION['verification_code'] || $email != $_SESSION['verification_email']) {
    //     header("Location: admin-user.php?error=invalid_code");
    //     exit();
    // }

    // Check if code exists
    if (!isset($_SESSION['verification_code']) || !isset($_SESSION['verification_expiry'])) {
        header("Location: admin-user.php?error=no_code");
        exit();
    }

    // Check expiration (10 mins)
    if (time() > $_SESSION['verification_expiry']) {
        unset($_SESSION['verification_code'], $_SESSION['verification_expiry'], $_SESSION['verification_email']);
        header("Location: admin-user.php?error=code_expired");
        exit();
    }

    // Check if code matches and email is same
    if ($input_code != $_SESSION['verification_code'] || $email != $_SESSION['verification_email']) {
        header("Location: admin-user.php?error=invalid_code");
        exit();
    }

    // Insert new user into DB
    $query = "INSERT INTO users (name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'approved')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss", $name, $username, $email, $password, $role);
    $stmt->execute();
    $stmt->close();

    // Clear session code
    unset($_SESSION['verification_code']);
    unset($_SESSION['verification_email']);

    header("Location: admin-user.php?success=added");
    exit();
}

// Handle editing an existing user
if (isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    // Check if password is provided (optional during edit)
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $query = "UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssi", $name, $username, $email, $password, $role, $user_id);
    } else {
        $query = "UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $name, $username, $email, $role, $user_id);
    }
    
    $stmt->execute();
    $stmt->close();

    header("Location: admin-user.php?success=edited");
    exit();
}

// Handle deleting a user
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    // Delete the user from the database
    $query = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-user.php?success=deleted");
    exit();
}

// Handle updating user status
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['user_id']) && isset($_GET['status'])) {
    $user_id = $_GET['user_id'];
    $status = $_GET['status']; // Can be 'approved' or 'pending'

    // Update the user's status
    $query = "UPDATE users SET status = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-user.php?success=status_updated");
    exit();
}

// Count total users
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = $conn->query($total_users_query);
$total_users = $total_users_result->fetch_assoc()['total'];

// Count pending accounts
$pending_users_query = "SELECT COUNT(*) as pending FROM users WHERE status = 'pending'";
$pending_users_result = $conn->query($pending_users_query);
$pending_users = $pending_users_result->fetch_assoc()['pending'];

// Fetch all users
$query = "SELECT * FROM users ORDER BY user_id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gitarra Apartelle - User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>


<style>
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

/* Checkmark Animation */
.checkmark-circle {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  border: 3px solid #28a745;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  animation: pop 0.4s ease forwards;
}

@keyframes pop {
  0% { transform: scale(0.5); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
/* Smooth filling progress bar */
.progress-animate {
  width: 0%;
  animation: loadProgress 2s linear forwards;
}

@keyframes loadProgress {
  from { width: 0%; }
  to { width: 100%; }
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
        <a href="admin-user.php" class="active"><i class="fa-solid fa-users"></i> Users</a>
        <a href="admin-room.php"><i class="fa-solid fa-bed"></i> Rooms</a>
        <a href="admin-report.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
        <a href="admin-supplies.php"><i class="fa-solid fa-boxes-stacked"></i> Supplies</a>
        <a href="admin-inventory.php"><i class="fa-solid fa-clipboard-list"></i> Inventory</a>
        <a href="admin-logout.php" class="mt-auto text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">User Management</h2>
                <p class="text-muted mb-0">Manage user accounts and permissions</p>
            </div>
            <div class="clock-box text-end">
                <div id="currentDate" class="fw-semibold"></div>
                <div id="currentTime"></div>
            </div>
        </div>
        
<!-- User Statistics Cards -->
<div class="row mb-4">
    <!-- Total Users Card -->
    <div class="col-md-6 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Total Users</p>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?php echo $total_users; ?></h3>
            <p class="stat-change text-success">+10% <span>from last month</span></p>
        </div>
    </div>

    <!-- Pending Accounts Card -->
    <div class="col-md-6 mb-3">
        <div class="card stat-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <p class="stat-title">Pending Accounts</p>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-user-clock"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?php echo $pending_users; ?></h3>
            <p class="stat-change text-danger">-2% <span>from last month</span></p>
        </div>
    </div>
</div>

        
<!-- Add User Form -->
<div class="row mb-4">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add New User</h5>
        <i class="fas fa-user-plus"></i>
      </div>
      <div class="card-body">
        <form method="POST" action="admin-user.php" class="row g-3" id="addUserForm">
          <div class="col-md-6">
            <label for="name" class="form-label">Full Name</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user"></i></span>
              <input type="text" name="name" id="name" class="form-control" placeholder="Enter full name" required>
            </div>
          </div>

          <div class="col-md-6">
            <label for="username" class="form-label">Username</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user"></i></span>
              <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required>
            </div>
          </div>

            <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" name="email" id="email" class="form-control" placeholder="Enter email address" required>
                <button type="button" id="sendCodeBtn" class="btn btn-outline-primary d-none">
                Send Code
                </button>
                <div class="invalid-feedback">You must verify this email(@) before continuing.</div>
            </div>
            </div>

          <div class="col-md-6">
            <label for="verification_code" class="form-label">Enter Verification Code</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-key"></i></span>
              <input type="text" name="verification_code" id="verification_code" class="form-control" placeholder="Enter code sent to email" required>
            </div>
            <div class="invalid-feedback">
              Please enter the 6-digit code sent to your email.
            </div>
          </div>

          <div class="col-md-6">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
              <div class="invalid-feedback">Password must be at least 8 characters and include a special character.</div>
            </div>
          </div>

          <div class="col-md-12">
            <label for="role" class="form-label">Role</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
              <select name="role" id="role" class="form-select" required>
                <option value="" selected disabled>Select a role</option>
                <option value="Admin">Admin</option>
                <option value="Receptionist">Receptionist</option>
                <option value="Guest">Guest</option>
              </select>
            </div>
          </div>

          <div class="col-12 mt-4">
            <button type="submit" name="add_user" class="btn btn-primary">
              <i class="fas fa-user-plus me-2"></i>Add User
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Error / Validation Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
  <div id="validationToast" class="toast align-items-center text-bg-danger border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="validationToastBody">
        <i class="fas fa-exclamation-triangle me-2"></i> Please correct the highlighted errors.
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Success messages -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
  <?php if (isset($_GET['success'])): ?>
    <div id="userToastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-check-circle me-2"></i>
          <?php
            if ($_GET['success'] == 'added') echo "User added successfully!";
            if ($_GET['success'] == 'edited') echo "User edited successfully!";
            if ($_GET['success'] == 'deleted') echo "User deleted successfully!";
            if ($_GET['success'] == 'status_updated') echo "User status updated successfully!";
          ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] == 'foreign_key_violation'): ?>
    <div id="userToastError" class="toast align-items-center text-bg-danger border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="fas fa-exclamation-triangle me-2"></i>
          Cannot delete this user because it has related records.
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Client-side validation toast -->
  <div id="validationToastError" class="toast align-items-center text-bg-danger border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="validationErrorMsg">
        <i class="fas fa-exclamation-triangle me-2"></i> Validation error
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <!-- Email Icon -->
      <div class="mb-2">
        <i class="fas fa-envelope fa-3x text-primary"></i>
      </div>

      <!-- Spinner BELOW icon -->
      <div class="mb-3">
        <div class="spinner-border text-primary" role="status"></div>
      </div>

      <h5>Sending Verification Code</h5>
      <p class="text-muted">
        Please wait while we send the code to your email 
        <span id="loadingEmail"></span>
      </p>

      <!-- Animated Progress Bar -->
      <div class="progress mt-3" style="height:5px;">
        <div class="progress-bar bg-primary progress-animate" role="progressbar"></div>
      </div>
    </div>
  </div>
</div>

<!-- Code Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="checkmark-container mb-3">
        <div class="checkmark-circle">
          <i class="fas fa-check fa-2x text-success"></i>
        </div>
      </div>
      <h5>Code Sent Successfully!</h5>
      <p class="text-muted">We've sent a verification code to <span id="successEmail"></span></p>
      <p class="text-muted">Please check your inbox and enter the code to verify your email</p>
      <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">Continue</button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const userToastSuccess = document.getElementById("userToastSuccess");
  const userToastError = document.getElementById("userToastError");
  const validationToast = document.getElementById("validationToast");
  const validationToastBody = document.getElementById("validationToastBody");

  if (userToastSuccess) new bootstrap.Toast(userToastSuccess, { delay: 4000 }).show();
  if (userToastError) new bootstrap.Toast(userToastError, { delay: 4000 }).show();

  // ====== Show server-side errors ======
  const urlParams = new URLSearchParams(window.location.search);
  const error = urlParams.get("error");

  if (error === "code_expired") {
    validationToastBody.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> Your verification code has expired. Please request a new one.`;
    new bootstrap.Toast(validationToast, { delay: 5000 }).show();
  }
  if (error === "invalid_code") {
    validationToastBody.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> The verification code is invalid.`;
    new bootstrap.Toast(validationToast, { delay: 5000 }).show();
  }

  // ====== Validation Functions ======
  function validateEmail(input) {
    const value = input.value.trim();
    const valid = value.includes("@");
    if (!valid) {
      input.classList.add("is-invalid");
      input.classList.remove("is-valid");
    } else {
      input.classList.remove("is-invalid");
      input.classList.add("is-valid");
    }
    return valid;
  }

  function validatePassword(input) {
    const value = input.value;
    const valid = value.length >= 8 && /[!@#$%^&*(),.?":{}|<>]/.test(value);
    if (!valid) {
      input.classList.add("is-invalid");
      input.classList.remove("is-valid");
    } else {
      input.classList.remove("is-invalid");
      input.classList.add("is-valid");
    }
    return valid;
  }

  function validateVerificationCode(input) {
    const value = input.value.trim();
    const valid = /^\d{6}$/.test(value); // must be exactly 6 digits
    if (!valid) {
      input.classList.add("is-invalid");
      input.classList.remove("is-valid");
    } else {
      input.classList.remove("is-invalid");
      input.classList.add("is-valid");
    }
    return valid;
  }

  const emailInput = document.getElementById("email");
  const passwordInput = document.getElementById("password");
  const codeInput = document.getElementById("verification_code");
  const sendCodeBtn = document.getElementById("sendCodeBtn");
  let emailVerified = false;

  // Show/hide Send Code button as user types
  emailInput.addEventListener("input", () => {
    if (validateEmail(emailInput)) {
      sendCodeBtn.classList.remove("d-none");
    } else {
      sendCodeBtn.classList.add("d-none");
    }
  });

  // Real-time validation for password & code
  passwordInput.addEventListener("input", () => validatePassword(passwordInput));
  codeInput.addEventListener("input", () => validateVerificationCode(codeInput));
  emailInput.addEventListener("input", () => validateEmail(emailInput));

// Handle Send Code click
sendCodeBtn.addEventListener("click", () => {
  if (!validateEmail(emailInput)) return;

  // ====== Show loading modal ======
  document.getElementById("loadingEmail").textContent = emailInput.value;
  const loadingModalEl = document.getElementById("loadingModal");
  const loadingModal = new bootstrap.Modal(loadingModalEl, { backdrop: "static", keyboard: false });

  // Reset progress animation each time
  const progressBar = loadingModalEl.querySelector(".progress-bar");
  progressBar.style.width = "0%";
  progressBar.classList.remove("progress-animate");
  void progressBar.offsetWidth; // trigger reflow
  progressBar.classList.add("progress-animate");

  loadingModal.show();

  fetch("admin-user.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "send_verification=1&email=" + encodeURIComponent(emailInput.value)
  })
    .then(res => res.text())
    .then(data => {
      console.log("Response:", data);

      // Hide loading, show success
      setTimeout(() => {
        loadingModal.hide();
        document.getElementById("successEmail").textContent = emailInput.value;
        const successModal = new bootstrap.Modal(document.getElementById("successModal"));
        successModal.show();

        sendCodeBtn.disabled = true;
        sendCodeBtn.textContent = "Code Sent";
        emailVerified = true;
        emailInput.classList.remove("is-invalid");
        emailInput.classList.add("is-valid");
      }, 2000); // sync with animation duration
    })
    .catch(err => {
      console.error("Error:", err);
      loadingModal.hide();
    });
});


  // Form submit validation
  document.getElementById("addUserForm").addEventListener("submit", function(event) {
    const emailOk = validateEmail(emailInput);
    const passwordOk = validatePassword(passwordInput);
    const codeOk = validateVerificationCode(codeInput);

    let hasError = false;

    if (!emailOk || !emailVerified) {
      hasError = true;
      emailInput.classList.add("is-invalid");
    }

    if (!passwordOk) {
      hasError = true;
      passwordInput.classList.add("is-invalid");
    }

    if (!codeOk) {
      hasError = true;
      codeInput.classList.add("is-invalid");
    }

    if (hasError) {
      event.preventDefault();
      validationToastBody.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> Please correct the highlighted errors.`;
      new bootstrap.Toast(validationToast, { delay: 4000 }).show();
    }
  });
});
</script>


        <!-- User List --> 
        <div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center p-3">
        <div>
            <h2 class="h5 mb-0 text-gray-900">User Accounts</h2>
            <p class="text-sm text-gray-600 mt-1"><?php echo $result->num_rows; ?> total users</p>
        </div>
        <div class="d-flex align-items-center gap-2">
    
             <!--- SHOW USERS --->
            <div id="customLengthMenu"></div>


            <div class="position-relative">
                <input type="text" class="form-control ps-4" id="searchInput" placeholder="Search users..." style="width: 200px;">
                <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-2 text-gray-400"></i>
            </div>
            <select class="form-select" id="filterSelect" style="width: 100px;">
                <option value="">Filter</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="userTable" class="table table-hover align-middle mb-0" style="width:100%;">
                <thead class="bg-gray-50 border-bottom border-gray-200">
                    <tr>
                        <th class="w-12 px-4 py-3">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="name">
                            Name <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="username">
                            Username <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="email">
                            Email <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="role">
                            Role <span class="sort-icon">↑↓</span>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="status">
                            Status <span class="sort-icon">↑↓</span>
                        </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sorting" data-sort="status">
                            Actions <span class="sort-icon">↑↓</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <input type="checkbox" class="form-check-input row-checkbox" data-id="<?= $user['user_id'] ?>">
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-<?= ($user['role'] == 'Admin') ? 'primary' : (($user['role'] == 'Receptionist') ? 'success' : 'secondary') ?> rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                        <span class="text-white"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                                    </div>
                                    <div>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($user['username']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($user['email']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <span class="badge bg-<?= ($user['role'] == 'Admin') ? 'blue-100' : (($user['role'] == 'Receptionist') ? 'green-100' : 'gray-100') ?> text-<?= ($user['role'] == 'Admin') ? 'blue-800' : (($user['role'] == 'Receptionist') ? 'green-800' : 'gray-800') ?> border-<?= ($user['role'] == 'Admin') ? 'blue-200' : (($user['role'] == 'Receptionist') ? 'green-200' : 'gray-200') ?> rounded-pill px-2.5 py-0.5 text-xs font-medium">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <span class="badge bg-<?= ($user['status'] == 'approved') ? 'green-100' : 'amber-100' ?> text-<?= ($user['status'] == 'approved') ? 'green-800' : 'amber-800' ?> border-<?= ($user['status'] == 'approved') ? 'green-200' : 'amber-200' ?> rounded-pill px-2.5 py-0.5 text-xs font-medium">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                                <?php if ($user['role'] != 'Admin'): ?>
                                    <a href="admin-user.php?action=update_status&user_id=<?= $user['user_id'] ?>&status=<?= ($user['status'] == 'approved') ? 'pending' : 'approved' ?>" class="btn btn-sm btn-outline-<?= ($user['status'] == 'approved') ? 'warning' : 'success' ?> ms-1">
                                        <i class="fas fa-<?= ($user['status'] == 'approved') ? 'undo' : 'check' ?>"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        <td class="px-4 py-3 text-center align-middle">
                        <div class="d-flex gap-2 justify-content-center user-actions">
                            <a href="javascript:void(0)"
                            onclick="editUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['name']) ?>', '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['role']) ?>')"
                            class="p-1 action-btn edit" title="Edit">
                            <i class="fas fa-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                            onclick="confirmDelete(<?= $user['user_id'] ?>)"
                            class="p-1 action-btn delete" title="Delete">
                            <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        </td>


                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No users found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;"> <!-- Reduced width -->
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Header -->
      <div class="modal-header border-0 pb-2">
        <h5 class="modal-title fw-bold text-danger" id="deleteModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-0"> <!-- Line after header -->
      
      <!-- Body -->
      <div class="modal-body text-center">
        <div class="mb-3">
          <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width:60px; height:60px;">
            <i class="fas fa-user-times text-danger fa-2x"></i> <!-- Changed icon for user -->
          </div>
        </div>
        <h5 class="fw-bold mb-2">Delete user?</h5>
        <p class="text-muted mb-0">
          Are you sure you want to delete this user?<br>
          This action cannot be undone and all associated data will be permanently removed.
        </p>
      </div>
      <hr class="my-0"> <!-- Line before buttons -->
      
      <!-- Footer -->
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="deleteUserLink" class="btn btn-danger">
          <i class="fas fa-user-times me-1"></i> Delete User
        </a>
      </div>
    </div>
  </div>
</div>



<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST" action="admin-user.php" class="row g-3">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="col-md-6">
                        <label for="edit_name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-at"></i></span>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                            <div class="invalid-feedback">Email must contain '@'.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_password" class="form-label">Password <small class="text-muted">(Leave blank to keep current)</small></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="edit_password" class="form-control" placeholder="Enter new password">
                            <div class="invalid-feedback">Password must be at least 8 characters and include a special character.</div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label for="edit_role" class="form-label">Role</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="Admin">Admin</option>
                                <option value="Receptionist">Receptionist</option>
                                <option value="Guest">Guest</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 mt-4 text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
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
        // Real-time clock updater
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').innerText = now.toLocaleDateString('en-PH', options);
            document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-PH');
        }
        setInterval(updateClock, 1000);
        updateClock();
        
        // Delete confirmation
        function confirmDelete(userId) {
            document.getElementById('deleteUserLink').href = 'admin-user.php?action=delete&user_id=' + userId;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // Edit user function
        function editUser(userId, name, username, email, role) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = ''; // Clear password field
            
            var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }

        // edit user modal validation
        const editEmailInput = document.getElementById("edit_email");
        const editPasswordInput = document.getElementById("edit_password");

        function validateEditEmail(input) {
        const value = input.value.trim();
        if (!value.includes("@")) {
            input.classList.add("is-invalid");
            input.classList.remove("is-valid");
        } else {
            input.classList.remove("is-invalid");
            input.classList.add("is-valid");
        }
        }

        function validateEditPassword(input) {
        const value = input.value;
        if (value === "") {
            // empty password is allowed (keep current)
            input.classList.remove("is-invalid", "is-valid");
            return;
        }
        const valid = value.length >= 8 && /[!@#$%^&*(),.?":{}|<>]/.test(value);
        if (!valid) {
            input.classList.add("is-invalid");
            input.classList.remove("is-valid");
        } else {
            input.classList.remove("is-invalid");
            input.classList.add("is-valid");
        }
        }

        // Live validation
        editEmailInput.addEventListener("input", () => validateEditEmail(editEmailInput));
        editPasswordInput.addEventListener("input", () => validateEditPassword(editPasswordInput));

        // On submit
        document.getElementById("editUserForm").addEventListener("submit", function(event) {
        validateEditEmail(editEmailInput);
        validateEditPassword(editPasswordInput);

        if (editEmailInput.classList.contains("is-invalid") || editPasswordInput.classList.contains("is-invalid")) {
            event.preventDefault();
            event.stopPropagation();

            const validationToast = document.getElementById("validationToast");
            new bootstrap.Toast(validationToast, { delay: 4000 }).show();
        }
        });


        // Data Tables
        $(document).ready(function() {
        var table = $('#userTable').DataTable({
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        responsive: true,
        pageLength: 5, 
        lengthMenu: [5, 10, 25, 50, 100],
        dom: '<"d-none"l>rt' +  
             '<"row mt-3"<"col-sm-5"i><"col-sm-7"p>>',
        language: {
            emptyTable: "<i class='fas fa-users fa-3x text-muted mb-3'></i><p class='mb-0'>No users found</p>",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            infoEmpty: "No entries available",
            infoFiltered: "(filtered from _MAX_ total users)",
            lengthMenu: "Show _MENU_ users",
            paginate: {
                first:    "«",
                last:     "»",
                next:     "›",
                previous: "‹"
            }
        }
    });

    // Move dropdown
    table.on('init', function () {
        var lengthSelect = $('#userTable_length select')
            .addClass('form-select')
            .css('width','80px');

        // Optional: add "Show" + "users" text around dropdown
        $('#customLengthMenu').html(
            '<label class="d-flex align-items-center gap-2 mb-0">' +
                '<span>Show</span>' + 
                lengthSelect.prop('outerHTML') +
                '<span>users</span>' +
            '</label>'
        );

        $('#userTable_length').hide();
    });

    // Custom search
    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    // Filter select
    $('#filterSelect').on('change', function() {
        table.column(5).search(this.value).draw();
    });

    // Select All
    $('#selectAll').on('click', function() {
        $('.row-checkbox').prop('checked', this.checked);
    });

    $(document).on('click', '.row-checkbox', function() {
        if (!$('.row-checkbox:checked').length) {
            $('#selectAll').prop('checked', false);
        }
    });


    table.on('order.dt', function() {
        $('th.sorting', table.table().header()).removeClass('sorting_asc sorting_desc');
        table.columns().every(function(index) {
            var order = table.order()[0];
            if (order[0] === index) {
                $('th:eq(' + index + ')', table.table().header())
                  .addClass(order[1] === 'asc' ? 'sorting_asc' : 'sorting_desc');
            }
        });
    });
});


    </script>
</body>
</html>
