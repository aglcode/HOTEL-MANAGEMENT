<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Receptionist') {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Receptionist';
$email = $_SESSION['email'] ?? '';

$check = $conn->prepare("SELECT * FROM receptionist_profiles WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$profile_result = $check->get_result();
$profile_data = $profile_result->fetch_assoc();
$check->close();




if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    $dob = $_POST['dob'];
    $place_of_birth = $_POST['place_of_birth'];
    $gender = $_POST['gender'];
    $emergency_contact_name = $_POST['emergency_contact_name'];
    $emergency_contact = $_POST['emergency_contact'];
    $address = $_POST['address'];
    $profile_picture = $profile_data['profile_picture'] ?? '';

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $profile_picture = "receptionist_{$user_id}." . $ext;
        $upload_path = "uploads/" . $profile_picture;

        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }

        move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path);
    }

    if ($profile_data) {
        $stmt = $conn->prepare("UPDATE receptionist_profiles SET 
            full_name=?, contact=?, dob=?, place_of_birth=?, gender=?, 
            emergency_contact_name=?, emergency_contact=?, address=?, profile_picture=?
            WHERE user_id=?");

        $stmt->bind_param("sssssssssi", $full_name, $contact, $dob, $place_of_birth, $gender,
            $emergency_contact_name, $emergency_contact, $address, $profile_picture, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO receptionist_profiles 
            (user_id, full_name, contact, dob, place_of_birth, gender, emergency_contact_name, emergency_contact, address, profile_picture)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("isssssssss", $user_id, $full_name, $contact, $dob, $place_of_birth,
            $gender, $emergency_contact_name, $emergency_contact, $address, $profile_picture);
    }

    if ($stmt->execute()) {
        header("Location: receptionist-profile.php");
        exit();
    } else {
        $error = "Failed to save profile. Try again.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receptionist Profile</title>
        <!-- Favicon -->
<link rel="icon" type="image/png" href="Image/logo/gitarra_apartelle_logo.png">
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
            border-radius: 1rem;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 850px;
            background: #fff;
            position: relative;
        }
        .exit-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            color: #999;
            text-decoration: none;
        }
        .exit-btn:hover {
            color: #000;
        }
        .profile-pic {
            display: block;
            margin: 0 auto 15px;
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #007bff;
            cursor: pointer;
        }
        .modal-img {
            width: 100%;
        }
    </style>
</head>
<body>
<div class="card">
    <a href="receptionist-dash.php" class="exit-btn">&times;</a>

    <?php if (!empty($profile_data['profile_picture'])): ?>
        <img src="uploads/<?= $profile_data['profile_picture'] ?>" alt="Profile" class="profile-pic" data-bs-toggle="modal" data-bs-target="#imageModal">
    <?php endif; ?>

    <h4 class="text-center mb-4">My Profile</h4>

    <form method="POST" enctype="multipart/form-data" id="profileForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label>Username</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" disabled>
            </div>
            <div class="col-md-6">
                <label>Email</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" disabled>
            </div>

            <div class="col-md-6">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($profile_data['full_name'] ?? '') ?>" required disabled>
            </div>
            <div class="col-md-6">
                <label>Contact Number</label>
                <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($profile_data['contact'] ?? '') ?>" required disabled>
            </div>

            <div class="col-md-6">
                <label>Date of Birth</label>
                <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($profile_data['dob'] ?? '') ?>" required disabled>
            </div>
            <div class="col-md-6">
                <label>Place of Birth</label>
                <input type="text" name="place_of_birth" class="form-control" value="<?= htmlspecialchars($profile_data['place_of_birth'] ?? '') ?>" required disabled>
            </div>

            <div class="col-md-12">
                <label>Gender</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input gender-radio" type="radio" name="gender" value="Male" <?= (isset($profile_data['gender']) && $profile_data['gender'] === 'Male') ? 'checked' : '' ?> disabled>
                    <label class="form-check-label">Male</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input gender-radio" type="radio" name="gender" value="Female" <?= (isset($profile_data['gender']) && $profile_data['gender'] === 'Female') ? 'checked' : '' ?> disabled>
                    <label class="form-check-label">Female</label>
                </div>
            </div>

            <div class="col-md-6">
                <label>Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($profile_data['emergency_contact_name'] ?? '') ?>" required disabled>
            </div>
            <div class="col-md-6">
                <label>Emergency Contact Number</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($profile_data['emergency_contact'] ?? '') ?>" required disabled>
            </div>

            <div class="col-md-12">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2" required disabled><?= htmlspecialchars($profile_data['address'] ?? '') ?></textarea>
            </div>

            <div class="col-md-12">
                <label>Profile Picture</label>
                <input type="file" name="profile_picture" class="form-control" disabled>
            </div>

            <div class="col-md-12 text-center mt-3">
                <button type="button" class="btn btn-warning" onclick="enableEdit()">Edit</button>
                <button type="submit" class="btn btn-primary" id="saveBtn" style="display:none;">Save</button>
            </div>
        </div>
    </form>
</div>

<!-- Modal to show full-size image -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-0">
                <img src="uploads/<?= $profile_data['profile_picture'] ?? '' ?>" class="modal-img" alt="Full Size Profile">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function enableEdit() {
    const form = document.getElementById('profileForm');
    [...form.elements].forEach(el => {
        if (el.name !== "" && el.type !== "button") el.disabled = false;
    });
    document.getElementById('saveBtn').style.display = 'inline-block';
}
</script>
</body>
</html>
