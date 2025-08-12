<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require_once 'database.php';
require 'vendor/autoload.php'; // Path to Composer autoload (PHPMailer)

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Check if email exists in users table
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Generate token and expiration
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Save to DB
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expires, $email);
        $stmt->execute();

        // Prepare reset link
        $resetLink = "http://yourdomain.com/reset-password.php?token=$token"; // ← Change domain

        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            // SMTP config
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // For Gmail
            $mail->SMTPAuth = true;
            $mail->Username = 'yourgmail@gmail.com'; // Your Gmail
            $mail->Password = 'your-app-password';   // App password from Gmail (not your real password)
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Email settings
            $mail->setFrom('yourgmail@gmail.com', 'Gitarra Apartelle');
            $mail->addAddress($email, $user['name']);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "Hi {$user['name']},\n\nClick the link below to reset your password:\n$resetLink\n\nThis link will expire in 1 hour.\n\nIf you didn’t request a password reset, please ignore this email.";

            $mail->send();
            $message = "<div class='alert alert-success'>A reset link has been sent to your email.</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>Mailer Error: {$mail->ErrorInfo}</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Email not found.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | Gitarra Apartelle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #4facfe, #00f2fe);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            padding: 2rem;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>

<div class="card">
    <h4 class="text-center mb-3">Forgot Password</h4>
    <?php echo $message; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="email" class="form-label">Enter your registered email:</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
        <p class="mt-3 text-center"><a href="signin.php">Back to Sign In</a></p>
    </form>
</div>

</body>
</html>
