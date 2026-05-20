<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$current_university_id = $_SESSION['university_id'];

try {
    // Fetch ALL registered users - removed LIMIT 10
    $sql = "SELECT university_id, fname, lname, user_type, account_status, date_of_birth 
            FROM users 
            ORDER BY user_id DESC";
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
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="header">
        <h1 class="admin-name"><?php echo $admin_display_name; ?></h1>
        <div class="user-profile">Admin ID: <?php echo htmlspecialchars($_SESSION['university_id']); ?></div>
    </div>

    <div class="card">
        <h3>All Registered Users (<?php echo count($recent_users); ?>)</h3>
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
                    <td colspan="5" class="empty-state">No users found in the database.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>