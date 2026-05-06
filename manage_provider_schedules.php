<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

try {
    // 1. Fetch Admin ID for Audit Logs
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $admin_data = $stmt->fetch();
    $admin_id = $admin_data['admin_id'];

    // --- HANDLE CRUD: BULK UPDATE (Cancel & Lock Slots) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'bulk_override') {

        $provider_id = $_POST['provider_id'];
        $target_date = $_POST['target_date'];

        $pdo->beginTransaction();

        // Step A: Find all active appointments for this doctor on this specific date
        $find_sql = "SELECT a.appointment_id 
                     FROM appointments a 
                     JOIN slots s ON a.slot_id = s.slot_id 
                     WHERE a.provider_id = :pid AND s.date = :target_date AND a.status != 'Cancelled'";
        $find_stmt = $pdo->prepare($find_sql);
        $find_stmt->execute([':pid' => $provider_id, ':target_date' => $target_date]);
        $affected_appointments = $find_stmt->fetchAll(PDO::FETCH_COLUMN);

        $cancel_count = count($affected_appointments);

        if ($cancel_count > 0) {
            // Cancel all found appointments dynamically
            $placeholders = implode(',', array_fill(0, $cancel_count, '?'));
            $cancel_sql = "UPDATE appointments SET status = 'Cancelled' WHERE appointment_id IN ($placeholders)";
            $cancel_stmt = $pdo->prepare($cancel_sql);
            $cancel_stmt->execute($affected_appointments);
        }

        // Step B: Lock EVERY slot for this doctor on this date (so no one can book them while they are gone)
        // We set is_booked = 1 (TRUE) regardless of whether an appointment was attached.
        $lock_sql = "UPDATE slots SET is_booked = 1 WHERE provider_id = :pid AND date = :target_date";
        $lock_stmt = $pdo->prepare($lock_sql);
        $lock_stmt->execute([':pid' => $provider_id, ':target_date' => $target_date]);

        // Step C: Log the massive action
        $action_desc = "Emergency Bulk Override: Cancelled {$cancel_count} appts and locked schedule for Provider #{$provider_id} on {$target_date}.";
        $log_sql = "INSERT INTO audit_logs (action_performed, target_table, target_record_id, admin_id) 
                    VALUES (:action, 'appointments', :pid, :admin_id)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':action' => $action_desc,
            ':pid' => $provider_id,
            ':admin_id' => $admin_id
        ]);

        $pdo->commit();
        $success_message = "Schedule Override Complete. {$cancel_count} appointments were cancelled and the doctor's slots for {$target_date} are now locked.";
    }

    // --- HANDLE CRUD: READ (Fetch all providers for the dropdown menu) ---
    $sql_providers = "SELECT pr.provider_id, u.fname, u.lname, pr.specialization 
                      FROM providers pr 
                      JOIN users u ON pr.user_id = u.user_id 
                      WHERE pr.is_active = TRUE 
                      ORDER BY u.lname ASC";
    $providers = $pdo->query($sql_providers)->fetchAll();

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error_message = "System Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Provider Schedule Override | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .management-container { padding: 40px; width: 100%; max-width: 800px; margin: 0 auto; }
        .card { background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card h2 { color: #0A192F; border-bottom: 3px solid #dc3545; display: inline-block; padding-bottom: 10px; margin-bottom: 20px; }

        .warning-box { background-color: #fee2e2; border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 30px; border-radius: 5px; color: #991b1b; }
        .warning-box strong { display: block; margin-bottom: 5px; }

        .input-group { margin-bottom: 25px; }
        .input-group label { display: block; margin-bottom: 10px; color: #0A192F; font-weight: 600; }
        .input-group select, .input-group input { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }

        .btn-danger { background-color: #dc3545; color: white; padding: 14px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 1.1rem; width: 100%; transition: 0.3s; }
        .btn-danger:hover { background-color: #bb2d3b; }

        .alert-success { background-color: #d1fae5; color: #065f46; padding: 15px; border-radius: 5px; margin-bottom: 25px; font-weight: bold; border-left: 4px solid #10b981; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 5px; margin-bottom: 25px; font-weight: bold; border-left: 4px solid #dc3545; }
        .nav-link { display: inline-block; margin-bottom: 20px; color: #0A192F; text-decoration: none; font-weight: bold; }
        .nav-link:hover { color: #D4AF37; }
    </style>
</head>
<body style="background-color: #F3F4F6;">

<div class="management-container">
    <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>

    <div class="card">
        <h2>Emergency Provider Override</h2>

        <div class="warning-box">
            <strong>⚠️ CRITICAL ACTION WARNING</strong>
            This tool will immediately cancel ALL active appointments for the selected provider on the chosen date. It will also lock all of their time slots to prevent any new bookings. This action cannot be easily undone.
        </div>

        <?php if ($success_message): ?>
            <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="bulk_override">

            <div class="input-group">
                <label>Select Healthcare Provider</label>
                <select name="provider_id" required>
                    <option value="">-- Choose Doctor --</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?php echo $p['provider_id']; ?>">
                            Dr. <?php echo htmlspecialchars($p['lname'] . ', ' . $p['fname']); ?> (<?php echo htmlspecialchars($p['specialization']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label>Date of Emergency / Absence</label>
                <!-- Sets minimum date to today so Admins can't accidentally delete history -->
                <input type="date" name="target_date" min="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <button type="submit" class="btn-danger" onclick="return confirm('FINAL WARNING: Are you absolutely sure you want to cancel all appointments and lock the schedule for this provider?');">
                Execute Emergency Override
            </button>
        </form>
    </div>
</div>

</body>
</html>