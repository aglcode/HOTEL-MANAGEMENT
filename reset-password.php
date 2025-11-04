<?php
session_start();
require_once 'database.php';
require 'vendor/autoload.php';

$message = "";
$showSuccessModal = false;
$token = null;
$userEmail = null;

// Get token from GET (link) or POST (form)
if (!empty($_GET['token'])) {
    $token = trim($_GET['token']);
} elseif (!empty($_POST['token'])) {
    $token = trim($_POST['token']);
}

$user = null;
if ($token) {
    // 🔍 Fetch user with matching token (valid or expired for debug)
    $stmt = $conn->prepare("SELECT email, reset_token, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();

        // Debugging (remove later)
        // echo "<pre>Token from form: " . htmlspecialchars($token) . "</pre>";
        // echo "<pre>Found in DB: " . htmlspecialchars($row['reset_token']) . "</pre>";
        // echo "<pre>Expires: " . htmlspecialchars($row['reset_expires']) . "</pre>";
        // echo "<pre>Email: " . htmlspecialchars($row['email']) . "</pre>";

        // Check expiry
        if (strtotime($row['reset_expires']) < time()) {
            $message = "<div class='alert alert-danger'>This reset link has expired. Please request a new password reset.</div>";
        } else {
            $user = $row;
            $userEmail = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');
        }
    } else {
        $message = "<div class='alert alert-danger'>Invalid reset token. Please request a new password reset.</div>";
    }
} else {
    $message = "<div class='alert alert-warning'>No reset token provided. Please request a password reset link.</div>";
}

// Handle password reset POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['password'], $_POST['confirm_password'], $_POST['token'])) {
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm_password']);
    $postToken = trim($_POST['token']);

    // Re-verify token exists & not expired
    $stmt = $conn->prepare("SELECT email, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $postToken);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!($res && $res->num_rows > 0)) {
        $message = "<div class='alert alert-danger'>Invalid token. Please request a new password reset.</div>";
    } else {
        $row = $res->fetch_assoc();
        if (strtotime($row['reset_expires']) < time()) {
            $message = "<div class='alert alert-danger'>This reset link has expired. Please request a new password reset.</div>";
        } elseif (strlen($password) < 8) {
            $message = "<div class='alert alert-danger'>Password must be at least 8 characters long.</div>";
        } elseif ($password !== $confirm) {
            $message = "<div class='alert alert-danger'>Passwords do not match.</div>";
        } else {
            $userEmail = $row['email'];
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?");
            $stmt->bind_param("ss", $hashed, $userEmail);

            if ($stmt->execute()) {
                $showSuccessModal = true;
                $userEmail = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');
            } else {
                $message = "<div class='alert alert-danger'>Failed to update password. Please try again later.</div>";
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create New Password | Gitarra Apartelle</title>
      <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  
  <style>
    body {
      background: rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }
    .reset-card {
      background: #fff;
      border-radius: 20px;
      width: 420px;
      padding: 2rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      text-align: center;
      position: relative;
    }
    .close-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      background: transparent;
      border: none;
      font-size: 20px;
      color: #9ca3af;
    }
    .icon-circle {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      background: #FADEDA;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
    }
    .icon-circle i {
      font-size: 28px;
      color: #871D2B;
    }
    h4 { font-weight: 700; margin-bottom: .5rem; }
    .form-control {
      border-radius: 12px;
      padding: .75rem 1rem;
    }
    .btn {
      border-radius: 12px;
      padding: .65rem 1rem;
      font-weight: 600;
    }
    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: none;
    }
    .btn-secondary:hover { background: #e5e7eb; color: #FF5457; }
    .btn-primary {
      background: #871D2B;
      border: none;
    }
    .btn-primary:hover { background: #FF5457; }
    .eye-btn {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      padding: 6px;
      cursor: pointer;
      color: #9ca3af;
    }
    .input-wrap { position: relative; }
    .input-wrap .fa-lock {
      position: absolute; left: 12px; top: 50%;
      transform: translateY(-50%); color: #9ca3af;
    }
    .input-wrap input { padding-left: 38px; }
  </style>
</head>
<body>

  <div class="reset-card">
    <button class="close-btn" onclick="window.location.href='signin.php'">&times;</button>
    <div class="icon-circle"><i class="fa-solid fa-lock"></i></div>
    <h4>Create New Password</h4>
    <p class="text-muted">Enter and confirm your new password</p>

    <form method="POST" id="resetForm" novalidate>
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8'); ?>">

      <div class="mb-3 text-start">
        <label for="password" class="form-label">New Password</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock"></i>
          <input type="password" id="password" name="password" placeholder="Enter new password" required class="form-control">
          <button type="button" class="eye-btn" data-target="password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div id="passwordFeedback" class="invalid-feedback">
          Password must be at least 8 characters and include a special character.
        </div>
      </div>

      <div class="mb-3 text-start">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock"></i>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required class="form-control">
          <button type="button" class="eye-btn" data-target="confirm_password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div id="confirmFeedback" class="invalid-feedback">Passwords do not match.</div>
      </div>

      <div class="d-flex gap-2">
        <a href="signin.php" class="btn btn-secondary w-50">Cancel</a>
        <button type="submit" class="btn btn-primary w-50" id="submitBtn">Reset Password</button>
      </div>
    </form>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content text-center p-4">
        <div class="icon-circle mb-3" style="background: #e8f9f0;">
          <i class="fa-solid fa-check text-success fa-2x"></i>
        </div>
        <h5 class="fw-bold">Password Reset Successful!</h5>
        <p class="text-muted">Your password has been updated. You can now login with your new password.</p>
        <button class="btn btn-success w-100" onclick="window.location.href='signin.php'">Back to Login</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Eye toggle
    document.querySelectorAll('.eye-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        if (!target) return;
        target.type = (target.type === 'password') ? 'text' : 'password';
        btn.innerHTML = (target.type === 'password')
          ? '<i class="fa-solid fa-eye"></i>'
          : '<i class="fa-solid fa-eye-slash"></i>';
      });
    });

    // Validation
    function isValidPassword(pwd) {
      return /^(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/.test(pwd);
    }
    const pwd = document.getElementById('password');
    const confirmPwd = document.getElementById('confirm_password');
    const passwordFeedback = document.getElementById('passwordFeedback');
    const confirmFeedback = document.getElementById('confirmFeedback');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('resetForm');

    function validatePassword() {
      if (!isValidPassword(pwd.value)) {
        pwd.classList.add('is-invalid');
        passwordFeedback.style.display = 'block';
        return false;
      } else {
        pwd.classList.remove('is-invalid');
        passwordFeedback.style.display = 'none';
        return true;
      }
    }
    function validateConfirm() {
      if (confirmPwd.value !== pwd.value) {
        confirmPwd.classList.add('is-invalid');
        confirmFeedback.style.display = 'block';
        return false;
      } else {
        confirmPwd.classList.remove('is-invalid');
        confirmFeedback.style.display = 'none';
        return true;
      }
    }

    if (pwd) pwd.addEventListener('input', validatePassword);
    if (confirmPwd) confirmPwd.addEventListener('input', validateConfirm);

    if (form) {
      form.addEventListener('submit', function(e) {
        if (!validatePassword() || !validateConfirm()) {
          e.preventDefault();
          return false;
        }
        submitBtn.disabled = true;
        submitBtn.innerText = 'Updating...';
      });
    }

    <?php if ($showSuccessModal ?? false): ?>
      document.addEventListener('DOMContentLoaded', function() {
        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
      });
    <?php endif; ?>
  </script>
</body>
</html>
