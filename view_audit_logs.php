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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .management-container { padding: 40px; width: 100%; max-width: 1200px; margin: 0 auto; }
        .card { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card h2 { color: #0A192F; border-bottom: 3px solid #D4AF37; display: inline-block; padding-bottom: 10px; margin-bottom: 20px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 0.95rem; }
        th { background-color: #0A192F; color: #FFFFFF; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }

        tr:hover { background-color: #f8fafc; }

        .table-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; background-color: #e5e7eb; color: #374151; font-family: monospace; }
        .action-text { font-weight: 600; color: #111827; }
        .time-text { color: #6b7280; font-size: 0.85rem; }

        .nav-link { display: inline-block; margin-bottom: 20px; color: #0A192F; text-decoration: none; font-weight: bold; }
        .nav-link:hover { color: #D4AF37; }
    </style>
</head>
<body style="background-color: #F3F4F6;">

<div class="management-container">
    <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>

    <div class="card">
        <h2>System Security & Audit Trail</h2>
        <p style="margin-bottom: 20px; color: #6b7280; font-size: 0.9rem;">This is a read-only security record of all administrative actions taken within the clinic system.</p>

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
                    <td colspan="5" style="text-align: center; padding: 20px;">No administrative actions have been logged yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>