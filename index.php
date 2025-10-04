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
                        header("Location: receptionist-dash.php");
                        break;
                    default:
                        header("Location: dashboard.php");
                        break;
                }
                exit();
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
    <title>Gitarra Apartelle | Login & Register</title>
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
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        }

        .brand-title {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            text-align: center;
            margin-bottom: 1rem;
        }

        .form-title {
            text-align: center;
            margin-bottom: 1rem;
        }

        .toggle-btn {
            cursor: pointer;
            font-size: 1.5rem;
            color: #007bff;
            text-align: center;
        }

        .toggle-btn:hover {
            color: #0056b3;
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: gray;
        }
    </style>
</head>

<body>

    <div class="card col-md-5">
        <div class="brand-title">Gitarra Apartelle</div>
        <h4 class="form-title" id="formTitle">Login</h4>

        <div class="toggle-btn mb-3" id="toggleForm">
            <i class="fa fa-toggle-off"></i> Switch Form
        </div>

        <!-- Login Form -->
        <form method="POST" id="loginForm">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <div class="mb-3">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3 position-relative">
                <input type="password" name="password" class="form-control" placeholder="Password" id="loginPassword" required>
                <span class="password-toggle" onclick="togglePassword('loginPassword')">
                    <i class="fa fa-eye"></i>
                </span>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>

        <!-- Register Form -->
        <form method="POST" id="registerForm" class="d-none">
            <div class="mb-3">
                <input type="text" name="name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="mb-3">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="mb-3 position-relative">
                <input type="password" name="password" class="form-control" placeholder="Password" id="registerPassword" required>
                <span class="password-toggle" onclick="togglePassword('registerPassword')">
                    <i class="fa fa-eye"></i>
                </span>
            </div>
            <input name="user_type" value="Receptionist" hidden>
            <button type="submit" name="register" class="btn btn-success w-100">Register</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
    <script>
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