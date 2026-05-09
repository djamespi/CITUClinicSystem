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
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>"> <!-- Reusing your existing CSS -->

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="management-container">
    <div class="header">
        <h1 class="admin-name"><?php echo $admin_display_name; ?></h1>
        <div class="user-profile">Admin ID: <?php echo htmlspecialchars($_SESSION['university_id']); ?></div>
    </div>

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
                        <!-- The Update Form (Suspend/Activate buttons) -->
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="target_user_id" value="<?php echo $u['user_id']; ?>">

                            <?php if ($u['user_id'] == $current_user_id): ?>
                                <!-- Safety: Prevent Admin from suspending themselves -->
                                <button type="button" class="btn-disabled" disabled>It's You!</button>

                            <?php elseif ($u['account_status'] === 'Active'): ?>
                                <!-- If Active, show Suspend button -->
                                <input type="hidden" name="new_status" value="Suspended">
                                <button type="submit" class="btn-suspend" onclick="return confirm('Are you sure you want to suspend this user? They will not be able to log in.');">Suspend</button>

                            <?php else: ?>
                                <!-- If Suspended, show Activate button -->
                                <input type="hidden" name="new_status" value="Active">
                                <button type="submit" class="btn-activate" onclick="return confirm('Reactivate this account?');">Activate</button>
                            <?php endif; ?>
                        </form>

                        <!-- NEW: Export PDF Button (Only shows for Patients) -->
                        <?php if ($u['user_type'] === 'Patient' && $u['patient_id']): ?>
                            <a href="export_medical_history.php?patient_id=<?php echo $u['patient_id']; ?>"
                               class="btn btn-export" target="_blank">Export PDF</a>
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