<?php
// process_login.php  — Updated to route Patient role to student_dashboard.php
session_start();
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $university_id    = $_POST['university_id'];
    $university_id = trim($_POST['university_id']); // Automatically deletes accidental spaces!
    $password_attempt = $_POST['password'];

    try {
        $sql  = "SELECT user_id, university_id, password_hash, user_type, account_status
                 FROM users WHERE university_id = :uni_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uni_id' => $university_id]);
        $user = $stmt->fetch();

        if ($user) {

            if ($user['account_status'] === 'Suspended') {
                $_SESSION['error'] = "Access Denied: This account has been suspended. Submit a Suspension Appeal if you believe this is an error.";
                header("Location: index.php");
                exit();
            }

            if (password_verify($password_attempt, $user['password_hash'])) {

                $_SESSION['logged_in']     = true;
                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['university_id'] = $user['university_id'];
                $_SESSION['role']          = $user['user_type'];

                if ($_SESSION['role'] === 'Admin') {
                    header("Location: admin_dashboard.php");
                    exit();
                } elseif ($_SESSION['role'] === 'Patient') {
                    header("Location: student_dashboard.php");
                    exit();
                } elseif ($_SESSION['role'] === 'Provider') {
                    // Provider dashboard placeholder — build later
                    $_SESSION['error'] = "Provider dashboard coming soon.";
                    header("Location: index.php");
                    exit();
                }

            } else {
                $_SESSION['error'] = "Error: Incorrect password. Please try again.";
                header("Location: index.php");
                exit();
            }

        } else {
            $_SESSION['error'] = "Error: No account found with that University ID.";
            header("Location: index.php");
            exit();
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Login System Error: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }

} else {
    header("Location: index.php");
    exit();
}
?>