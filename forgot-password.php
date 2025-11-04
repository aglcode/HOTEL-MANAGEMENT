<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require_once 'database.php';
require 'vendor/autoload.php'; 

$success = false;
$resetLink = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Save token
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expires, $email);
        if (!$stmt->execute()) {
            die("Error saving reset token: " . $stmt->error);
        }

        // Reset link
        $resetLink = "http://localhost/gitarra_apartelle/reset-password.php?token=" . urlencode($token);

        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'gitarraapartelle@gmail.com';
            $mail->Password = 'pngssmeypubvvhvg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('gitarraapartelle@gmail.com', 'Gitarra Apartelle');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';

            // Styled HTML email
           $mail->Body = "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Password Reset</title>
</head>
<body style='margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, sans-serif;'>
  <table align='center' cellpadding='0' cellspacing='0' width='100%' style='padding:20px 0;'>
    <tr>
      <td>
        <table align='center' cellpadding='0' cellspacing='0' width='600' style='background-color:#ffffff; border-radius:8px; overflow:hidden;'>
          <!-- Header -->
          <tr>
            <td align='center' style='background-color:#1a2b47; padding:30px; color:#ffffff;'>
              <!-- Inline white lock icon (SVG) -->
              <svg xmlns='http://www.w3.org/2000/svg' width='50' height='50' fill='white' viewBox='0 0 24 24'>
                <path d='M12 17a2 2 0 100-4 2 2 0 000 4zm6-7h-1V7a5 5 0 00-10 0v3H6c-1.1 0-2 .9-2 
                2v9c0 1.1.9 2 2 2h12c1.1 0 2-.9 
                2-2v-9c0-1.1-.9-2-2-2zM8 7a4 4 0 
                118 0v3H8V7zm10 14H6v-9h12v9z'/>
              </svg>
              <h2 style='margin:10px 0; font-size:22px;'>Gitarra Apartelle</h2>
              <p style='margin:0; font-size:14px;'>Your comfort is our priority</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style='padding:30px; color:#333333;'>
              <h2 style='margin-top:0;'>Reset Your Password</h2>
              <p>We received a request to reset your password. Click the button below to create a new password for your <b>Gitarra Apartelle</b> account.</p>
              <p style='text-align:center; margin:30px 0;'>
                <a href='$resetLink' style='background-color:#2c3e50; color:#ffffff; padding:12px 25px; text-decoration:none; border-radius:5px; display:inline-block; font-weight:bold;'>
                  Reset Password
                </a>
              </p>
              <div style='background-color:#fff3cd; border:1px solid #ffeeba; padding:15px; border-radius:5px; color:#856404; font-size:14px;'>
                ⚠ This link will expire in <b>1 hour</b><br>
                For security reasons, this password reset link is only valid for 60 minutes from the time it was requested.
              </div>
              <p style='margin-top:20px; font-size:14px; color:#666;'>If you didn’t request a password reset, please ignore this email or contact our support team if you have concerns about your account security.</p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align='center' style='background-color:#f9f9f9; padding:20px; font-size:12px; color:#999;'>
              Need help? Contact us at <a href='mailto:support@gitarra-apartelle.com' style='color:#2c3e50;'>support@gitarra-apartelle.com</a><br><br>
              © 2025 Gitarra Apartelle. All rights reserved.
              <p style='margin-top:10px; font-size:11px; color:#aaa;'>This is an automated email. Please do not reply to this message.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";


            $mail->AltBody = "Click this link to reset your password: $resetLink (valid for 1 hour)";

            $mail->send();
            $success = true;
        } catch (Exception $e) {
            $success = false;
        }
    } else {
        $success = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password | Gitarra Apartelle</title>
      <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }
    .forgot-card {
      background: #fff;
      border-radius: 20px;
      width: 420px;
      padding: 2rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      text-align: center;
      position: relative;
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
    h4 {
      font-weight: 700;
      margin-bottom: .5rem;
    }
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
    .btn-secondary:hover {
      background: #e5e7eb;
      color: #FF5457;
    }
    .btn-primary {
      background: #871D2B;
      border: none;
    }
    .btn-primary:hover {
      background: #FF5457;
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

    /* Rotating spinner around envelope */
    .loading-spinner {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 25px;
    height: 25px;
    border: 3px solid #2563eb;
    border-top: 3px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    }
    @keyframes spin {
    100% { transform: rotate(360deg); }
    }

    /* Animated check mark bounce */
    .animated-check {
    animation: pop 0.5s ease-in-out;
    }
    @keyframes pop {
    0% { transform: scale(0.5); opacity: 0; }
    80% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(1); }
    }

  </style>
</head>
<body>

  <div class="forgot-card">
    <button class="close-btn" onclick="window.location.href='signin.php'">&times;</button>
    <div class="icon-circle">
      <i class="fa-solid fa-envelope"></i>
    </div>
    <h4>Forgot Password?</h4>
    <p class="text-muted">Enter your registered email and we'll send you a reset link</p>

    <!-- Form -->
    <form method="POST" onsubmit="showLoadingModal()">
      <div class="mb-3 text-start">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="your.email@example.com" required>
      </div>
      <div class="d-flex gap-2">
        <a href="signin.php" class="btn btn-secondary w-50">Cancel</a>
        <button type="submit" class="btn btn-primary w-50">Send Reset Link</button>
      </div>
    </form>
  </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">
        <div class="icon-circle mb-3" style="position: relative;">
            <i class="fa-solid fa-envelope fa-2x text-primary"></i>
            <div class="loading-spinner"></div>
        </div>
        <h5 class="fw-bold">Sending Reset Link</h5>
        <p class="text-muted">Please wait while we send the password reset link to your email</p>
        <p class="fw-bold text-primary" id="loadingEmail"></p>
        <div class="progress mt-3" style="height: 4px; border-radius: 10px; overflow: hidden;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                role="progressbar" style="width: 0%" id="progressBar"></div>
        </div>
        </div>
    </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">
        <div class="icon-circle mb-3" style="background: #e8f9f0;">
            <i class="fa-solid fa-check text-success fa-2x animated-check"></i>
        </div>
        <h5 class="fw-bold">Reset Link Sent!</h5>
        <p>We've sent a password reset link to</p>
        <p class="fw-bold text-success" id="successEmail"></p>
        <p class="text-muted">Please check your inbox and click the link to reset your password</p>
        <button class="btn btn-success w-100" onclick="window.location.href='signin.php'">
            Back to Login
        </button>
        </div>
    </div>
    </div>


  <script>
 function showLoadingModal() {
  var email = document.getElementById("email").value;
  document.getElementById("loadingEmail").innerText = email;

  var loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
  loadingModal.show();

  // Animate progress bar
  let progress = 0;
  let progressBar = document.getElementById("progressBar");
  let interval = setInterval(() => {
    if (progress >= 100) {
      clearInterval(interval);
    } else {
      progress += 5;
      progressBar.style.width = progress + "%";
    }
  }, 100);
}

<?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
  document.addEventListener("DOMContentLoaded", function() {
    var loadingModalEl = document.getElementById('loadingModal');
    var loadingModal = bootstrap.Modal.getInstance(loadingModalEl);
    if (loadingModal) loadingModal.hide();

    <?php if ($success): ?>
      document.getElementById("successEmail").innerText = "<?php echo $email; ?>";
      var successModal = new bootstrap.Modal(document.getElementById('successModal'));
      successModal.show();
    <?php else: ?>
      alert("Failed to send reset link. Please try again.");
    <?php endif; ?>
  });
<?php endif; ?>

  </script>
</body>
</html>