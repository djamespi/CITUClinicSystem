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
    // 1. Fetch the actual 'admin_id' for tracking who resolved the ticket
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $admin_data = $stmt->fetch();
    $admin_id = $admin_data['admin_id'];

    // --- HANDLE CRUD: UPDATE (Resolve Ticket) & CREATE (Audit Log) ---
    // --- HANDLE CRUD: MULTI-TABLE TICKET RESOLUTIONS ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {

        $target_ticket_id = $_POST['ticket_id'];
        $ticket_user_id = $_POST['ticket_user_id']; // The patient who submitted it
        $request_type = $_POST['request_type'];
        $action_type = $_POST['action'];

        try {
            $pdo->beginTransaction();

            // --- RULE 1: THE SUSPENSION APPEAL OVERRIDE ---
            if ($action_type == 'approve_appeal' && $request_type == 'Suspension Appeal') {

                // Step A: Reactivate the User Account
                $stmt = $pdo->prepare("UPDATE users SET account_status = 'Active' WHERE user_id = :uid");
                $stmt->execute([':uid' => $ticket_user_id]);

                // Step B: Forgive the Patient's No-Show Count (Reset to 0)
                $stmt = $pdo->prepare("UPDATE patients SET no_show_count = 0 WHERE user_id = :uid");
                $stmt->execute([':uid' => $ticket_user_id]);

                // Step C: Mark Ticket as Resolved
                $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'Resolved', resolved_at = CURRENT_TIMESTAMP, admin_id = :admin WHERE ticket_id = :tid");
                $stmt->execute([':admin' => $admin_id, ':tid' => $target_ticket_id]);

                // Step D: Log the complex action
                $action_desc = "Approved Suspension Appeal for Ticket #{$target_ticket_id}. Account activated and No-Shows reset.";
                $stmt = $pdo->prepare("INSERT INTO audit_logs (action_performed, target_table, target_record_id, admin_id) VALUES (:action, 'users', :uid, :admin)");
                $stmt->execute([':action' => $action_desc, ':uid' => $ticket_user_id, ':admin' => $admin_id]);

                $success_message = "Appeal Approved! The student's account is active and penalties are cleared.";
            }

            // --- STANDARD TICKET RESOLUTION (For questions/issues) ---
            elseif ($action_type == 'resolve_ticket') {
                $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'Resolved', resolved_at = CURRENT_TIMESTAMP, admin_id = :admin WHERE ticket_id = :tid");
                $stmt->execute([':admin' => $admin_id, ':tid' => $target_ticket_id]);

                // Log standard resolution... (omitted for brevity, same as your old code)
                $success_message = "Ticket #{$target_ticket_id} marked as resolved.";
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            die("Transaction Failed: " . $e->getMessage());
        }
    }

    // --- HANDLE CRUD: READ (Fetch all tickets with the submitter's info) ---
    // We order by status so 'Pending' tickets always show up at the top!
    $sql = "SELECT 
                t.ticket_id, t.request_type, t.description, t.status, t.created_at, t.resolved_at,
                u.user_id, u.fname, u.lname, u.university_id, u.user_type
            FROM support_tickets t
            JOIN users u ON t.user_id = u.user_id
            ORDER BY 
                CASE WHEN t.status = 'Pending' OR t.status = 'Open' THEN 1 ELSE 2 END, 
                t.created_at DESC";

    $tickets = $pdo->query($sql)->fetchAll();

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
    <title>Support Tickets | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="management-container">
    <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>

    <div class="card">
        <h2>IT Support & Ticket Resolution</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <table>
            <thead>
            <tr>
                <th>Ticket Info</th>
                <th>Submitted By</th>
                <th>Request Details</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($tickets) > 0): ?>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo $t['ticket_id']; ?></strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($t['created_at']))); ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($t['lname'] . ', ' . $t['fname']); ?></strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($t['university_id']); ?> (<?php echo htmlspecialchars($t['user_type']); ?>)</span>
                        </td>
                        <td class="col-description">
                            <strong><?php echo htmlspecialchars($t['request_type']); ?></strong>
                            <div class="desc-box">
                                <?php echo htmlspecialchars($t['description']); ?>
                            </div>
                        </td>
                        <td>
                                <span class="status-badge status-<?php echo strtolower($t['status']); ?>">
                                    <?php echo htmlspecialchars($t['status']); ?>
                                </span>
                        </td>
                        <td>
                            <?php if ($t['status'] === 'Pending' || $t['status'] === 'Open'): ?>
                                <form method="POST" class="inline-form">
                                    <!-- NEW: We pass the user_id and request_type silently to the PHP Engine -->
                                    <input type="hidden" name="ticket_user_id" value="<?php echo $t['user_id']; ?>">
                                    <input type="hidden" name="request_type" value="<?php echo $t['request_type']; ?>">
                                    <input type="hidden" name="ticket_id" value="<?php echo $t['ticket_id']; ?>">

                                    <!-- If it is a Suspension Appeal, show a special Gold button -->
                                    <?php if ($t['request_type'] === 'Suspension Appeal'): ?>
                                        <input type="hidden" name="action" value="approve_appeal">
                                        <button type="submit" class="btn btn-gold" onclick="return confirm('Approve this appeal, wipe penalties, and reactivate the student account?');">Approve Appeal</button>
                                        <!-- For all other standard tickets, show the normal Green Resolve button -->
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="resolve_ticket">
                                        <button type="submit" class="btn-resolve" onclick="return confirm('Mark this issue as resolved?');">Resolve</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Resolved on<br><?php echo htmlspecialchars(date('M d', strtotime($t['resolved_at']))); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-state">No support tickets in the system. Everything is running smoothly!</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>