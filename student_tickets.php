<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Patient') {
    header("Location: index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$success_message = "";
$error_message   = "";

try {
    // --- HANDLE: Submit Ticket ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'submit_ticket') {
        $request_type = $_POST['request_type'];
        $description  = trim($_POST['description']);

        $ins = $pdo->prepare("INSERT INTO support_tickets (user_id, request_type, description, status)
                              VALUES (:uid, :type, :desc, 'Open')");
        $ins->execute([':uid' => $current_user_id, ':type' => $request_type, ':desc' => $description]);
        $success_message = "Your ticket has been submitted. An admin will review it shortly.";
    }

    // --- READ: My Tickets ---
    $my_tickets = $pdo->prepare("SELECT ticket_id, request_type, description, status, created_at, resolved_at
                                 FROM support_tickets
                                 WHERE user_id = :uid
                                 ORDER BY created_at DESC");
    $my_tickets->execute([':uid' => $current_user_id]);
    $tickets = $my_tickets->fetchAll();

} catch (PDOException $e) {
    $error_message = "System Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>

<?php include 'student_navbar.php'; ?>

<div class="main-content">
    <div class="header">
        <h1 style="font-size:1.8rem;">Support & Requests</h1>
        <div class="user-profile">Patient Portal</div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 1.6fr; gap:24px;">

        <!-- Submit Form -->
        <div class="card" style="height:fit-content;">
            <h3>Submit a Request</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="submit_ticket">

                <div class="input-group">
                    <label>Request Type</label>
                    <select name="request_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="General Inquiry">General Inquiry</option>
                        <option value="Appointment Issue">Appointment Issue</option>
                        <option value="Medical Record Request">Medical Record Request</option>
                        <option value="Suspension Appeal">Suspension Appeal</option>
                        <option value="Technical Problem">Technical Problem</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Description</label>
                    <textarea name="description" rows="5" placeholder="Describe your concern in detail..." required></textarea>
                </div>

                <button type="submit" class="btn-save">Submit Ticket</button>
            </form>
        </div>

        <!-- My Tickets -->
        <div class="card">
            <h3>My Tickets</h3>
            <?php if (count($tickets) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><strong>#<?php echo $t['ticket_id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($t['request_type']); ?>
                                    <div class="desc-box"><?php echo htmlspecialchars(substr($t['description'], 0, 100)) . (strlen($t['description']) > 100 ? '...' : ''); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($t['status']); ?>">
                                        <?php echo htmlspecialchars($t['status']); ?>
                                    </span>
                                    <?php if ($t['resolved_at']): ?>
                                        <span class="subtext">Resolved <?php echo date('M d', strtotime($t['resolved_at'])); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="time-text"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">You haven't submitted any support tickets yet.</div>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>