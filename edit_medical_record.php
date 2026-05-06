<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
// Only Admins (and eventually Providers) should ever see this page
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

try {
    // 1. Fetch the actual 'admin_id' for the audit logs
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $admin_data = $stmt->fetch();
    $admin_id = $admin_data['admin_id'];

    // --- HANDLE CRUD: UPDATE (Fix Record) & CREATE (Audit Log) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_record') {

        $record_id = $_POST['record_id'];
        $symptoms = $_POST['symptoms'];
        $diagnosis = $_POST['diagnosis'];
        $prescription = $_POST['prescription_notes'];

        $pdo->beginTransaction();

        // Step A: Update the medical record
        $update_sql = "UPDATE medical_records 
                       SET symptoms = :symptoms, diagnosis = :diagnosis, prescription_notes = :prescription 
                       WHERE record_id = :record_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':symptoms' => $symptoms,
            ':diagnosis' => $diagnosis,
            ':prescription' => $prescription,
            ':record_id' => $record_id
        ]);

        // Step B: Log the admin's action in the audit trail
        $action_desc = "Amended Medical Record ID: " . $record_id . " for data correction.";
        $log_sql = "INSERT INTO audit_logs (action_performed, target_table, target_record_id, admin_id) 
                    VALUES (:action, 'medical_records', :target_id, :admin_id)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':action' => $action_desc,
            ':target_id' => $record_id,
            ':admin_id' => $admin_id
        ]);

        $pdo->commit();
        $success_message = "Medical record amended successfully and logged in the system.";
    }

    // --- HANDLE CRUD: READ (Fetch the specific record to edit) ---
    // We get the Record ID from the URL (e.g., ?id=1) or from the POST submission
    $target_id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['record_id']) ? $_POST['record_id'] : null);

    if (!$target_id) {
        die("Error: No medical record ID specified.");
    }

    // Fetch the record details along with Patient and Provider names for context
    $sql = "SELECT 
                mr.record_id, mr.symptoms, mr.diagnosis, mr.prescription_notes, mr.created_at,
                pu.fname AS patient_fname, pu.lname AS patient_lname, pu.university_id,
                pru.fname AS provider_fname, pru.lname AS provider_lname
            FROM medical_records mr
            JOIN patients p ON mr.patient_id = p.patient_id
            JOIN users pu ON p.user_id = pu.user_id
            JOIN providers pr ON mr.provider_id = pr.provider_id
            JOIN users pru ON pr.user_id = pru.user_id
            WHERE mr.record_id = :rid LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':rid' => $target_id]);
    $record = $stmt->fetch();

    if (!$record) {
        die("Error: Medical record not found.");
    }

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
    <title>Edit Medical Record | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .management-container { padding: 40px; width: 100%; max-width: 900px; margin: 0 auto; }
        .card { background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card h2 { color: #0A192F; border-bottom: 3px solid #D4AF37; display: inline-block; padding-bottom: 10px; margin-bottom: 30px; }

        .info-panel { background-color: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #0A192F; display: flex; justify-content: space-between; }
        .info-block { display: flex; flex-direction: column; }
        .info-label { font-size: 0.85rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold; margin-bottom: 5px; }
        .info-value { font-size: 1.1rem; color: #111827; font-weight: 600; }

        .input-group { margin-bottom: 25px; }
        .input-group label { display: block; margin-bottom: 10px; color: #0A192F; font-weight: 600; }
        .input-group textarea { width: 100%; padding: 15px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; resize: vertical; min-height: 100px; transition: border-color 0.3s; }
        .input-group textarea:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); }

        .btn-save { background-color: #0A192F; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 1.1rem; transition: 0.3s; width: 100%; }
        .btn-save:hover { background-color: #D4AF37; color: #0A192F; }

        .alert-success { background-color: #d1fae5; color: #065f46; padding: 15px; border-radius: 5px; margin-bottom: 25px; font-weight: bold; border-left: 4px solid #10b981; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 5px; margin-bottom: 25px; font-weight: bold; border-left: 4px solid #ef4444; }
        .nav-link { display: inline-block; margin-bottom: 20px; color: #0A192F; text-decoration: none; font-weight: bold; }
        .nav-link:hover { color: #D4AF37; }
    </style>
</head>
<body style="background-color: #F3F4F6;">

<div class="management-container">
    <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>

    <div class="card">
        <h2>Amend Medical Record #<?php echo htmlspecialchars($record['record_id']); ?></h2>

        <?php if ($success_message): ?>
            <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Read-Only Context Panel -->
        <div class="info-panel">
            <div class="info-block">
                <span class="info-label">Patient</span>
                <span class="info-value"><?php echo htmlspecialchars($record['patient_lname'] . ', ' . $record['patient_fname']); ?> (<?php echo htmlspecialchars($record['university_id']); ?>)</span>
            </div>
            <div class="info-block">
                <span class="info-label">Attending Provider</span>
                <span class="info-value">Dr. <?php echo htmlspecialchars($record['provider_lname']); ?></span>
            </div>
            <div class="info-block">
                <span class="info-label">Date of Record</span>
                <span class="info-value"><?php echo htmlspecialchars(date('M d, Y', strtotime($record['created_at']))); ?></span>
            </div>
        </div>

        <!-- The Editable Form -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_record">
            <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">

            <div class="input-group">
                <label>Symptoms Reported</label>
                <textarea name="symptoms" required><?php echo htmlspecialchars($record['symptoms']); ?></textarea>
            </div>

            <div class="input-group">
                <label>Clinical Diagnosis</label>
                <textarea name="diagnosis" required><?php echo htmlspecialchars($record['diagnosis']); ?></textarea>
            </div>

            <div class="input-group">
                <label>Prescription & Treatment Notes</label>
                <textarea name="prescription_notes" required><?php echo htmlspecialchars($record['prescription_notes']); ?></textarea>
            </div>

            <button type="submit" class="btn-save" onclick="return confirm('You are amending an official medical record. This action will be logged. Proceed?');">Save & Audit Amendment</button>
        </form>
    </div>
</div>

</body>
</html>