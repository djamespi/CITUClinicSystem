<?php
// Find out which page we are currently on
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch the Admin's Name from the database to use on all pages
$stmt_name = $pdo->prepare("SELECT fname, lname FROM users WHERE user_id = :uid LIMIT 1");
$stmt_name->execute([':uid' => $_SESSION['user_id']]);
$user_info = $stmt_name->fetch();
$admin_display_name = htmlspecialchars($user_info['lname'] . ', ' . $user_info['fname']);
?>

<div class="top-navbar">
    <div class="nav-brand">CIT-U CLINIC</div>

    <div class="nav-links">
        <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
        <a href="manage_appointments.php" class="<?php echo ($current_page == 'manage_appointments.php') ? 'active' : ''; ?>">Appointments</a>
        <a href="manage_users.php" class="<?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">Patients</a>
        <a href="manage_provider_schedules.php" class="<?php echo ($current_page == 'manage_provider_schedules.php') ? 'active' : ''; ?>">Overrides</a>
        <a href="manage_tickets.php" class="<?php echo ($current_page == 'manage_tickets.php') ? 'active' : ''; ?>">Tickets</a>
        <a href="view_audit_logs.php" class="<?php echo ($current_page == 'view_audit_logs.php') ? 'active' : ''; ?>">Audit Logs</a>
    </div>

    <div class="nav-logout">
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</div>