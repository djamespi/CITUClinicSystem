<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

try {
    // 1. Fetch the actual 'admin_id' for the currently logged-in user
    // We need this to attach to our audit logs!
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $admin_data = $stmt->fetch();
    $admin_id = $admin_data['admin_id'];

    // --- HANDLE CRUD: UPDATE (Status Toggle) & CREATE (Audit Log) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'toggle_status') {

        $target_user_id = $_POST['target_user_id'];
        $new_status = $_POST['new_status'];

        // Begin transaction because we are writing to TWO tables at once
        $pdo->beginTransaction();

        // Update the Users Table
        $update_sql = "UPDATE users SET account_status = :status WHERE user_id = :uid";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':status' => $new_status,
            ':uid' => $target_user_id
        ]);

        // Create the Audit Log Entry
        $action_desc = "Changed account status to " . $new_status;
        $log_sql = "INSERT INTO audit_logs (action_performed, target_table, target_record_id, admin_id) 
                    VALUES (:action, 'users', :target_id, :admin_id)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':action' => $action_desc,
            ':target_id' => $target_user_id,
            ':admin_id' => $admin_id
        ]);

        $pdo->commit();
        $success_message = "User status updated and action logged successfully.";
    }

    // --- HANDLE CRUD: READ (Fetch all users) ---
    // UPDATED: Added a LEFT JOIN to grab the patient_id if they are a patient
    $sql = "SELECT u.user_id, u.university_id, u.fname, u.lname, u.user_type, u.account_status, p.patient_id 
            FROM users u 
            LEFT JOIN patients p ON u.user_id = p.user_id
            ORDER BY u.lname ASC";
    $users = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("System Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css"> <!-- Reusing your existing CSS -->
    <style>
        /* Specific styles for the Management Table */
        .management-container { padding: 40px; width: 100%; max-width: 1200px; margin: 0 auto; }
        .card { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card h2 { color: #0A192F; border-bottom: 3px solid #D4AF37; display: inline-block; padding-bottom: 10px; margin-bottom: 20px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #0A192F; color: #FFFFFF; }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 0.85rem; }
        .status-active { background-color: #d1fae5; color: #065f46; }
        .status-suspended { background-color: #fee2e2; color: #991b1b; }

        /* Action Buttons */
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-suspend { background-color: #dc3545; color: white; }
        .btn-suspend:hover { background-color: #bb2d3b; }
        .btn-activate { background-color: #198754; color: white; }
        .btn-activate:hover { background-color: #157347; }

        .alert-success { background-color: #d1fae5; color: #065f46; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .nav-link { display: inline-block; margin-bottom: 20px; color: #0A192F; text-decoration: none; font-weight: bold; }
        .nav-link:hover { color: #D4AF37; }
    </style>
</head>
<body style="background-color: #F3F4F6;">

<div class="management-container">
    <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>

    <div class="card">
        <h2>User Management Console</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <table>
            <thead>
            <tr>
                <th>Univ ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Current Status</th>
                <th>Admin Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['university_id']); ?></td>
                    <td><?php echo htmlspecialchars($u['lname'] . ', ' . $u['fname']); ?></td>
                    <td><?php echo htmlspecialchars($u['user_type']); ?></td>
                    <td>
                            <span class="status-badge <?php echo $u['account_status'] === 'Active' ? 'status-active' : 'status-suspended'; ?>">
                                <?php echo htmlspecialchars($u['account_status']); ?>
                            </span>
                    </td>
                    <td>
                        <!-- The Update Form (Your existing Suspend/Activate buttons) -->
                        <form method="POST" style="margin: 0; display: inline-block;">
                            <!-- ... keep your existing suspend/activate form code here ... -->
                        </form>

                        <!-- NEW: Export PDF Button (Only shows for Patients) -->
                        <?php if ($u['user_type'] === 'Patient' && $u['patient_id']): ?>
                            <a href="export_medical_history.php?patient_id=<?php echo $u['patient_id']; ?>"
                               class="btn" style="background-color: #0A192F; color: #D4AF37; text-decoration: none; margin-left: 5px; font-size: 0.85rem;"
                               target="_blank">Export PDF</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>