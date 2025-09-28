<?php
session_start();
require_once "config.php"; // this should create $conn = new mysqli(...);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!empty($_POST['unlock_code'])) {
        $unlock_code = trim($_POST['unlock_code']);

        // Prepare query
        $stmt = $conn->prepare("SELECT * FROM unlock_codes WHERE code = ? LIMIT 1");
        $stmt->bind_param("s", $unlock_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $codeData = $result->fetch_assoc();

        if ($codeData) {
            // Mark system as unlocked (store in session or update DB)
            $_SESSION['unlocked'] = true;

            // Optionally update code usage
            $update = $conn->prepare("UPDATE unlock_codes SET used = 1, used_at = NOW() WHERE id = ?");
            $update->bind_param("i", $codeData['id']);
            $update->execute();

            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error'] = "Invalid unlock code.";
            header("Location: unlock_form.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Please enter an unlock code.";
        header("Location: unlock_form.php");
        exit;
    }
} else {
    header("Location: unlock_form.php");
    exit;
}
