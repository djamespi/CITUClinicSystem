<?php
// We must start a session to keep the user logged in across pages
session_start();

require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $university_id = $_POST['university_id'];
    $password_attempt = $_POST['password'];

    try {
        // 1. Look up the user by their University ID
        $sql = "SELECT user_id, university_id, password_hash, user_type, account_status 
                FROM users WHERE university_id = :uni_id LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uni_id' => $university_id]);
        $user = $stmt->fetch();

        // 2. Check if user exists
        if ($user) {

            // 3. Check if account is suspended
            if ($user['account_status'] === 'Suspended') {
                $_SESSION['error'] = "Access Denied: This account has been suspended.";
                header("Location: index.php");
                exit();
            }

            // 4. Verify the password mathematically
            if (password_verify($password_attempt, $user['password_hash'])) {

                // 5. SUCCESS! Store their identity in the Session
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['university_id'] = $user['university_id'];
                $_SESSION['role'] = $user['user_type'];

                // 6. Direct them to their specific dashboard
                if ($_SESSION['role'] === 'Admin') {
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    // We will build patient/provider dashboards later!
                    $_SESSION['error'] = "Welcome Patient/Provider! Your dashboard is currently under construction.";
                    header("Location: index.php");
                    exit();
                }
            } else {
                // WRONG PASSWORD
                $_SESSION['error'] = "Error: Incorrect password. Please try again.";
                header("Location: index.php");
                exit();
            }
        } else {
            // NO ACCOUNT FOUND
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