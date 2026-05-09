<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $university_id = trim($_POST['university_id']);
    $user_type = $_POST['user_type'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match. Please try again.";
        header("Location: index.php");
        exit();
    }

    // 2. Check if University ID is already registered
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE university_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $university_id]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "This University ID is already registered. Please log in.";
        header("Location: index.php");
        exit();
    }

    // 3. Temporarily save data to Session to pass it to Step 2
    $_SESSION['reg_uid'] = $university_id;
    $_SESSION['reg_type'] = $user_type;
    $_SESSION['reg_pass'] = password_hash($password, PASSWORD_DEFAULT); // Securely hash immediately!

    // 4. Send to Step 2!
    header("Location: setup_profile.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
