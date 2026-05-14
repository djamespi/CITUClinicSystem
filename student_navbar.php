<?php
// student_navbar.php
// Include this at the top of every student page (same pattern as sidebar.php for admins)

$current_page = basename($_SERVER['PHP_SELF']);

// Fetch student's name
$stmt_name = $pdo->prepare("SELECT fname, lname FROM users WHERE user_id = :uid LIMIT 1");
$stmt_name->execute([':uid' => $_SESSION['user_id']]);
$user_info   = $stmt_name->fetch();
$student_display_name = htmlspecialchars($user_info['lname'] . ', ' . $user_info['fname']);
?>

<div class="top-navbar">
    <div class="nav-brand">
        <img src="images/cit-logo.svg" alt="CIT-U Logo" class="nav-logo">
        <img src="images/clinic-logo.svg" alt="Clinic Logo" class="nav-logo">
        <span>CIT-U CLINIC</span>
    </div>

    <div class="nav-links">
        <a href="student_dashboard.php"
           class="<?php echo ($current_page == 'student_dashboard.php') ? 'active' : ''; ?>">
            Dashboard
        </a>
        <a href="student_tickets.php"
           class="<?php echo ($current_page == 'student_tickets.php') ? 'active' : ''; ?>">
            Support
        </a>
    </div>

    <div class="nav-logout">
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</div>