<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

try {
    // --- CRUD: READ ONLY (Fetch all logs with Admin names attached) ---
    $sql = "SELECT 
                al.log_id, 
                al.action_performed, 
                al.target_table, 
                al.target_record_id, 
                al.timestamp,
                u.fname AS admin_fname, 
                u.lname AS admin_lname
            FROM audit_logs al
            JOIN admins a ON al.admin_id = a.admin_id
            JOIN users u ON a.user_id = u.user_id
            ORDER BY al.timestamp DESC";

    $logs = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    die("System Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Audit Logs | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body">

<div class="management-container">
    <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>

    <div class="card">
        <h2>System Security & Audit Trail</h2>
        <p class="page-description">This is a read-only security record of all administrative actions taken within the clinic system.</p>

        <table>
            <thead>
            <tr>
                <th>Timestamp</th>
                <th>Admin Name</th>
                <th>Action Performed</th>
                <th>Database Table Affected</th>
                <th>Record ID</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="time-text"><?php echo htmlspecialchars(date('M d, Y - H:i:s', strtotime($log['timestamp']))); ?></td>
                        <td><?php echo htmlspecialchars($log['admin_lname'] . ', ' . $log['admin_fname']); ?></td>
                        <td class="action-text"><?php echo htmlspecialchars($log['action_performed']); ?></td>
                        <td><span class="table-badge"><?php echo htmlspecialchars($log['target_table']); ?></span></td>
                        <td>#<?php echo htmlspecialchars($log['target_record_id']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-state">No administrative actions have been logged yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>