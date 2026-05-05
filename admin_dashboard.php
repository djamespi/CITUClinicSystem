<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
// If they are not logged in, OR if their role is not 'Admin', kick them out!
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Fetch the currently logged-in admin's ID
$current_university_id = $_SESSION['university_id'];

try {
    // Let's pull the most recently registered users to display in a table
    // Notice we are grabbing the new fname and lname columns you just added!
    $sql = "SELECT university_id, fname, lname, user_type, account_status, date_of_birth 
            FROM users 
            ORDER BY user_id DESC LIMIT 10";
    $stmt = $pdo->query($sql);
    $recent_users = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CIT-U Clinic</title>
    <style>
        :root {
            --dark-blue: #0A192F;
            --gold: #D4AF37;
            --white: #FFFFFF;
            --light-gray: #F3F4F6;
            --text-dark: #333333;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        body { display: flex; background-color: var(--light-gray); min-height: 100vh; }

        /* Sidebar Navigation */
        .sidebar { width: 250px; background-color: var(--dark-blue); color: var(--white); display: flex; flex-direction: column; padding-top: 20px; }
        .sidebar h2 { text-align: center; color: var(--gold); margin-bottom: 30px; letter-spacing: 1px; }
        .sidebar a { text-decoration: none; color: var(--white); padding: 15px 25px; font-size: 1.1rem; border-left: 4px solid transparent; transition: all 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid var(--gold); color: var(--gold); }
        .logout-btn { margin-top: auto; margin-bottom: 20px; background-color: #dc3545; text-align: center; margin-left: 20px; margin-right: 20px; border-radius: 5px; padding: 10px; }

        /* Main Content Area */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header h1 { color: var(--dark-blue); }
        .user-profile { font-weight: 600; color: var(--text-dark); background-color: var(--white); padding: 10px 20px; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }

        /* Data Table Card */
        .card { background-color: var(--white); border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
        .card h3 { color: var(--dark-blue); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--gold); display: inline-block; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f8fafc; color: var(--dark-blue); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        tr:hover { background-color: #f8fafc; }

        /* Status Badges */
        .status { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .status.active { background-color: #d1fae5; color: #065f46; }
        .status.suspended { background-color: #fee2e2; color: #991b1b; }

        .role-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; background-color: var(--dark-blue); color: var(--white); }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>CIT-U CLINIC</h2>
    <a href="#" class="active">Dashboard</a>
    <a href="#">Appointments</a>
    <a href="#">Patients Directory</a>
    <a href="#">System Audit Logs</a>
    <a href="logout.php" class="logout-btn">Log Out</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="header">
        <h1>Administrator Dashboard</h1>
        <div class="user-profile">
            Admin ID: <?php echo htmlspecialchars($current_university_id); ?>
        </div>
    </div>

    <div class="card">
        <h3>Recently Registered Users</h3>
        <table>
            <thead>
            <tr>
                <th>University ID</th>
                <th>Name</th>
                <th>Date of Birth</th>
                <th>Role</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($recent_users) > 0): ?>
                <?php foreach ($recent_users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['university_id']); ?></td>
                        <td><?php echo htmlspecialchars($u['lname'] . ', ' . $u['fname']); ?></td>
                        <td><?php echo htmlspecialchars($u['date_of_birth']); ?></td>
                        <td><span class="role-badge"><?php echo htmlspecialchars($u['user_type']); ?></span></td>
                        <td>
                                    <span class="status <?php echo strtolower($u['account_status']); ?>">
                                        <?php echo htmlspecialchars($u['account_status']); ?>
                                    </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No users found in the database.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
