<?php 
session_start();

require_once 'database.php'; // Include your database connection settings

// Handle Registration
if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role = $_POST['user_type'] ?? null;

if (!in_array($role, ['Admin', 'Receptionist'])) {
    die("Invalid user type.");
}

$confirm_password = $_POST['confirm_password'];
if ($_POST['password'] !== $confirm_password) {
    die("Passwords do not match.");
}

$query = "INSERT INTO users (name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'pending')";
$stmt = $conn->prepare($query);
$stmt->bind_param("sssss", $name, $username, $email, $password, $role);

if ($stmt->execute()) {
    header("Location: signin.php?success=registered");
} else {
    die("Registration failed. Try again.");
}
$stmt->close();
exit();

}

// Handle Login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            if ($user['status'] !== 'approved') {
                $error_message = "Your account is pending approval.";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                switch ($user['role']) {
                    case 'Admin':
                        header("Location: admin-dashboard.php");
                        break;
                    case 'Receptionist':
                        if ($user['profile_completed'] == 0) {
                            header("Location: receptionist-dash.php");
                        } else {
                            header("Location: receptionist-dashboard.php");
                        }
                        break;
                    default:
                        header("Location: dashboard.php");
                        break;
                }
                 
            }
        } else {
            $error_message = "Incorrect Password.";
        }
    } else {
        $error_message = "Username not found.";
    }
    $stmt->close();
}
?>

<!-- HTML for Login/Register -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Gitarra Apartelle | Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

  <style>
    body {
      background: linear-gradient(to right, #eef2f3, #dfe9f3);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .login-card {
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      text-align: center;
    }
    .login-card img {
      width: 60px;
      margin-bottom: 1rem;
    }
    .login-card h2 {
      font-weight: 700;
      margin-bottom: .25rem;
    }
    .login-card p {
      color: #6c757d;
      margin-bottom: 2rem;
    }
    .form-control {
      padding-left: 40px;
      border-radius: 10px;
    }
    .input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
    }
    .btn-primary {
      background: #2563eb;
      border: none;
      border-radius: 12px;
      padding: .75rem;
      font-weight: 600;
      font-size: 1rem;
      box-shadow: 0 4px 10px rgba(37,99,235,0.3);
      transition: 0.3s;
    }
    .btn-primary:hover {
      background: #1e4ed8;
    }
    .forgot-link {
      font-size: 0.9rem;
      text-decoration: none;
      color: #2563eb;
    }
    .forgot-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="login-card">
    <!-- Title -->
    <h2>Gitarra Apartelle</h2>
    <p>Sign in to your account</p>

    <!-- Error -->
    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger py-2"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST">
      <div class="mb-3 position-relative">
        <span class="input-icon"><i class="fa fa-user"></i></span>
        <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
      </div>
      <div class="mb-3 position-relative">
        <span class="input-icon"><i class="fa fa-lock"></i></span>
        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
      </div>
      <div class="d-flex justify-content-end mb-3">
        <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
      </div>
      <button type="submit" name="login" class="btn btn-primary w-100">Sign In</button>
    </form>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
<script>
    // Confirm password match check
    document.getElementById("registerForm").addEventListener("submit", function(e) {
        const password = document.getElementById("registerPassword").value;
        const confirmPassword = document.getElementById("confirmPassword").value;

        if (password !== confirmPassword) {
            e.preventDefault();
            alert("Passwords do not match.");
        }
    });

    const toggleFormBtn = document.getElementById('toggleForm');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const formTitle = document.getElementById('formTitle');

    toggleFormBtn.addEventListener('click', function() {
        loginForm.classList.toggle('d-none');
        registerForm.classList.toggle('d-none');
        let icon = toggleFormBtn.querySelector('i');
        if (loginForm.classList.contains('d-none')) {
            formTitle.innerText = 'Register';
            icon.classList.remove('fa-toggle-off');
            icon.classList.add('fa-toggle-on');
        } else {
            formTitle.innerText = 'Login';
            icon.classList.remove('fa-toggle-on');
            icon.classList.add('fa-toggle-off');
        }
    });

    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.nextElementSibling.querySelector('i');
        if (field.type === "password") {
            field.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>
