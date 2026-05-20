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
    // 1. Fetch the actual 'admin_id' for the audit logs
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $admin_data = $stmt->fetch();
    $admin_id = $admin_data['admin_id'];

    // --- HANDLE CRUD: UPDATE (Cancel Appt), UPDATE (Free Slot) & CREATE (Log) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'cancel_appointment') {

        $target_appointment_id = $_POST['appointment_id'];
        $target_slot_id = $_POST['slot_id'];

        $pdo->beginTransaction();

        // Step A: Mark the appointment as 'Cancelled'
        $cancel_sql = "UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = :apt_id";
        $cancel_stmt = $pdo->prepare($cancel_sql);
        $cancel_stmt->execute([':apt_id' => $target_appointment_id]);

        // Step B: Free up the time slot so someone else can book it
        $slot_sql = "UPDATE slots SET is_booked = FALSE WHERE slot_id = :slot_id";
        $slot_stmt = $pdo->prepare($slot_sql);
        $slot_stmt->execute([':slot_id' => $target_slot_id]);

        // Step C: Log the admin's action
        $action_desc = "Emergency Cancellation of Appointment ID: " . $target_appointment_id;
        $log_sql = "INSERT INTO audit_logs (action_performed, target_table, target_record_id, admin_id) 
                    VALUES (:action, 'appointments', :target_id, :admin_id)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':action' => $action_desc,
            ':target_id' => $target_appointment_id,
            ':admin_id' => $admin_id
        ]);

        $pdo->commit();
        $success_message = "Appointment cancelled and slot opened. Action logged.";
    }

    // --- HANDLE CRUD: READ (Fetch all appointments with patient & provider names) ---
    // This uses SQL JOINs to connect your foreign keys to the actual user names!
    $sql = "SELECT 
                a.appointment_id, a.reason_for_visit, a.status, a.slot_id,
                s.date, s.start_time, s.end_time,
                pu.fname AS patient_fname, pu.lname AS patient_lname,
                pru.fname AS provider_fname, pru.lname AS provider_lname
            FROM appointments a
            JOIN slots s ON a.slot_id = s.slot_id
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users pu ON p.user_id = pu.user_id
            JOIN providers pr ON a.provider_id = pr.provider_id
            JOIN users pru ON pr.user_id = pru.user_id
            ORDER BY s.date DESC, s.start_time DESC";

    $appointments = $pdo->query($sql)->fetchAll();

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
    <title>Manage Appointments | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="management-container">
    <div class="header">
        <h1 class="admin-name"><?php echo $admin_display_name; ?></h1>
        <div class="user-profile">Admin ID: <?php echo htmlspecialchars($_SESSION['university_id']); ?></div>
    </div>
    <div class="card">
        <h2>Clinic Appointments Master Schedule</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <table>
            <thead>
            <tr>
                <th>Date & Time</th>
                <th>Patient Name</th>
                <th>Provider</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Admin Override</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($appointments) > 0): ?>
                <?php foreach ($appointments as $apt): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($apt['date']); ?></strong><br>
                            <span class="subtext">
                                <?php echo htmlspecialchars($apt['start_time']) . ' - ' . htmlspecialchars($apt['end_time']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($apt['patient_lname'] . ', ' . $apt['patient_fname']); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($apt['provider_lname']); ?></td>
                        <td><?php echo htmlspecialchars($apt['reason_for_visit']); ?></td>
                        <td>
                                <span class="status-badge status-<?php echo strtolower($apt['status']); ?>">
                                    <?php echo htmlspecialchars($apt['status']); ?>
                                </span>
                        </td>
                        <td>
                            <?php if ($apt['status'] !== 'Cancelled'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="cancel_appointment">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
                                    <input type="hidden" name="slot_id" value="<?php echo $apt['slot_id']; ?>">
                                    <button type="submit" class="btn-cancel" onclick="return confirm('Emergency Override: Cancel this appointment and free the slot?');">Cancel</button>
                                </form>
                            <?php else: ?>
                                <button class="btn-disabled" disabled>Cancelled</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="empty-state">No appointments found in the system.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
