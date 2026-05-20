<?php
// provider_navbar.php
$current_page = basename($_SERVER['PHP_SELF']);

$stmt_name = $pdo->prepare("SELECT u.fname, u.lname, pr.specialization, pr.room_number, pr.license_no
                             FROM users u
                             JOIN providers pr ON pr.user_id = u.user_id
                             WHERE u.user_id = :uid LIMIT 1");
$stmt_name->execute([':uid' => $_SESSION['user_id']]);
$provider_info         = $stmt_name->fetch();
$provider_display_name = htmlspecialchars('Dr. ' . $provider_info['lname'] . ', ' . $provider_info['fname']);
$provider_spec         = htmlspecialchars($provider_info['specialization']);
?>
<div class="top-navbar">
    <div class="nav-brand">
        <img src="images/cit-logo.svg" alt="CIT-U Logo" class="nav-logo">
        <img src="images/clinic-logo.svg" alt="Clinic Logo" class="nav-logo">
        <span>CIT-U CLINIC</span>
    </div>
    <div class="nav-links">
        <a href="provider_dashboard.php" class="<?php echo ($current_page=='provider_dashboard.php')?'active':''; ?>">Dashboard</a>
        <a href="provider_schedule.php"  class="<?php echo ($current_page=='provider_schedule.php') ?'active':''; ?>">My Schedule</a>
        <a href="provider_patients.php"  class="<?php echo ($current_page=='provider_patients.php') ?'active':''; ?>">Patient Records</a>
        <a href="provider_reports.php"   class="<?php echo ($current_page=='provider_reports.php')  ?'active':''; ?>">Reports</a>
    </div>
    <div class="nav-logout">
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</div>