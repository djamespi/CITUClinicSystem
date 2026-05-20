<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Provider') {
    header("Location: index.php"); exit();
}

$current_user_id = $_SESSION['user_id'];
$success_message = "";
$error_message   = "";

try {
    $stmt = $pdo->prepare("SELECT pr.provider_id, pr.specialization, pr.room_number,
                                  u.fname, u.lname
                           FROM providers pr JOIN users u ON pr.user_id = u.user_id
                           WHERE pr.user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $provider    = $stmt->fetch();
    $provider_id = $provider['provider_id'];

    // --- HANDLE: Submit Report ---
    // request_type must match schema: Suspension Appeal | Emergency Cancellation |
    // Record Correction | Data Export | Provider Schedule Override | Other Technical Issue
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'submit_report') {
        $request_type = $_POST['request_type'];  // Already one of the valid schema values
        $subject      = trim($_POST['subject']);
        $body         = trim($_POST['body']);
        $priority     = $_POST['priority'];

        // Validate request_type is one of the allowed values
        $allowed_types = [
            'Emergency Cancellation',
            'Record Correction',
            'Provider Schedule Override',
            'Other Technical Issue',
        ];

        if (!in_array($request_type, $allowed_types)) {
            $error_message = "Invalid report type selected.";
        } else {
            $full_description = "[PROVIDER REPORT]\n"
                . "From: Dr. " . $provider['lname'] . ", " . $provider['fname'] . "\n"
                . "Specialization: " . $provider['specialization'] . "\n"
                . "Room: " . $provider['room_number'] . "\n"
                . "Priority: " . $priority . "\n"
                . "Subject: " . $subject . "\n\n"
                . $body;

            // support_tickets schema: (ticket_id, request_type, description, status,
            //                          created_at, resolved_at, user_id, admin_id)
            // admin_id is NULL until an admin resolves it
            $pdo->prepare("INSERT INTO support_tickets (user_id, request_type, description, status)
                           VALUES (:uid, :type, :desc, 'Open')")
                ->execute([
                    ':uid'  => $current_user_id,
                    ':type' => $request_type,
                    ':desc' => $full_description,
                ]);

            $success_message = "Report submitted to clinic admin. You will be notified once it is reviewed.";
        }
    }

    // --- READ: My submitted reports ---
    $my_reports = $pdo->prepare("SELECT ticket_id, request_type, description, status, created_at, resolved_at
                                 FROM support_tickets
                                 WHERE user_id = :uid
                                 ORDER BY created_at DESC");
    $my_reports->execute([':uid' => $current_user_id]);
    $reports = $my_reports->fetchAll();

    // --- READ: Quick appointment stats ---
    $stats_stmt = $pdo->prepare("SELECT
                                    COUNT(*) as total,
                                    SUM(CASE WHEN a.status = 'Pending'   THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN a.status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                                    SUM(CASE WHEN a.status = 'No-Show'   THEN 1 ELSE 0 END) as noshow,
                                    SUM(CASE WHEN s.date = CURDATE()     THEN 1 ELSE 0 END) as today
                                 FROM appointments a
                                 JOIN slots s ON a.slot_id = s.slot_id
                                 WHERE a.provider_id = :pid");
    $stats_stmt->execute([':pid' => $provider_id]);
    $appt_stats = $stats_stmt->fetch();

} catch (PDOException $e) {
    die("System Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        .reports-layout { display:grid; grid-template-columns:1fr 1.5fr; gap:24px; align-items:start; }
        @media(max-width:960px){ .reports-layout{ grid-template-columns:1fr; } }

        .summary-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:24px; }
        .summary-tile { background:var(--bg-light); border:1px solid var(--border-color); border-radius:12px; padding:18px; text-align:center; }
        .s-num   { font-family:'Poppins',sans-serif; font-size:2rem; font-weight:700; color:var(--citu-blue); }
        .s-label { font-size:0.78rem; color:var(--text-muted); margin-top:2px; }

        /* Report type selector — uses actual schema values */
        .report-type-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:16px; }
        .report-type-btn { padding:12px 10px; border:2px solid var(--border-color); border-radius:10px; background:var(--white); cursor:pointer; text-align:center; transition:all 0.2s; font-size:0.83rem; color:var(--text-muted); font-family:'Inter',sans-serif; }
        .report-type-btn:hover, .report-type-btn.selected { border-color:var(--citu-blue); color:var(--citu-blue); background:#EEF2FF; font-weight:600; }
        .ricon { font-size:1.4rem; display:block; margin-bottom:4px; }

        .priority-row { display:flex; gap:8px; margin-bottom:16px; }
        .priority-btn { flex:1; padding:9px; border-radius:9px; border:2px solid var(--border-color); background:var(--white); font-size:0.83rem; font-weight:500; cursor:pointer; text-align:center; transition:all 0.2s; font-family:'Inter',sans-serif; }
        .priority-btn.low.selected    { border-color:#10B981; background:#ECFDF5; color:#059669; }
        .priority-btn.medium.selected { border-color:#F59E0B; background:#FFFBEB; color:#B45309; }
        .priority-btn.high.selected   { border-color:#EF4444; background:#FEF2F2; color:#DC2626; }

        .report-item { border:1px solid var(--border-color); border-radius:12px; padding:16px 20px; margin-bottom:12px; background:var(--white); transition:box-shadow 0.2s; }
        .report-item:hover { box-shadow:var(--card-shadow); }
        .r-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; gap:10px; }
        .r-type   { font-weight:600; color:var(--citu-blue); font-size:0.92rem; }
        .r-desc   { font-size:0.85rem; color:var(--text-muted); line-height:1.6; background:var(--bg-light); padding:10px 14px; border-radius:8px; border-left:3px solid var(--border-color); white-space:pre-line; }
        .r-date   { font-size:0.78rem; color:var(--text-muted); margin-top:8px; }
        .empty-reports { text-align:center; padding:50px 20px; color:var(--text-muted); }
    </style>
</head>
<body>

<?php include 'provider_navbar.php'; ?>

<div class="main-content">
    <div class="header">
        <h1 style="font-size:1.8rem;">Reports to Admin</h1>
        <div class="user-profile"><?php echo $provider_display_name; ?></div>
    </div>

    <?php if ($success_message): ?><div class="alert-success"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="reports-layout">

        <!-- LEFT: Form -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <h3>My Clinic Summary</h3>
                <div class="summary-grid">
                    <div class="summary-tile"><div class="s-num"><?php echo $appt_stats['today']     ?? 0; ?></div><div class="s-label">Today's Patients</div></div>
                    <div class="summary-tile"><div class="s-num"><?php echo $appt_stats['pending']   ?? 0; ?></div><div class="s-label">Pending</div></div>
                    <div class="summary-tile"><div class="s-num"><?php echo $appt_stats['noshow']    ?? 0; ?></div><div class="s-label">No-Shows Total</div></div>
                    <div class="summary-tile"><div class="s-num"><?php echo $appt_stats['total']     ?? 0; ?></div><div class="s-label">All Appointments</div></div>
                </div>
            </div>

            <div class="card">
                <h3>Submit a Report</h3>
                <form method="POST" id="report-form">
                    <input type="hidden" name="action" value="submit_report">
                    <input type="hidden" name="request_type" id="hidden_request_type">
                    <input type="hidden" name="priority" id="hidden_priority" value="Medium">

                    <!-- Report Types — values MATCH the schema request_type enum exactly -->
                    <div class="input-group">
                        <label>Report Type</label>
                        <div class="report-type-grid">
                            <button type="button" class="report-type-btn"
                                    onclick="selectType('Emergency Cancellation', this)">
                                <span class="ricon">🚨</span>Emergency Cancellation
                            </button>
                            <button type="button" class="report-type-btn"
                                    onclick="selectType('Record Correction', this)">
                                <span class="ricon">📝</span>Record Correction
                            </button>
                            <button type="button" class="report-type-btn"
                                    onclick="selectType('Provider Schedule Override', this)">
                                <span class="ricon">📅</span>Schedule Override
                            </button>
                            <button type="button" class="report-type-btn"
                                    onclick="selectType('Other Technical Issue', this)">
                                <span class="ricon">🔧</span>Other Technical Issue
                            </button>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Priority Level</label>
                        <div class="priority-row">
                            <button type="button" class="priority-btn low"    onclick="selectPriority('Low',this)">🟢 Low</button>
                            <button type="button" class="priority-btn medium selected" onclick="selectPriority('Medium',this)">🟡 Medium</button>
                            <button type="button" class="priority-btn high"   onclick="selectPriority('High',this)">🔴 High</button>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Subject</label>
                        <input type="text" name="subject" placeholder="Brief summary of your report..." required>
                    </div>

                    <div class="input-group">
                        <label>Detailed Description</label>
                        <textarea name="body" rows="6"
                            placeholder="Describe the issue in detail. Include relevant dates, patient IDs if applicable, and any actions already taken..." required></textarea>
                    </div>

                    <button type="submit" class="btn-override" onclick="return validateReport();" style="width:100%;">
                        Submit Report to Admin
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT: Submitted Reports History -->
        <div class="card">
            <h2>Submitted Reports</h2>
            <?php if (count($reports) > 0): ?>
                <?php foreach ($reports as $r): ?>
                    <?php
                        preg_match('/Subject: (.+)/m', $r['description'], $subj_match);
                        $subject_line = $subj_match[1] ?? 'Report #' . $r['ticket_id'];

                        preg_match('/Priority: (.+)/m', $r['description'], $pri_match);
                        $priority_label = trim($pri_match[1] ?? 'Medium');

                        $pri_colors = [
                            'Low'    => ['bg'=>'#ECFDF5','color'=>'#059669','border'=>'#A7F3D0'],
                            'Medium' => ['bg'=>'#FFFBEB','color'=>'#D97706','border'=>'#FDE68A'],
                            'High'   => ['bg'=>'#FEF2F2','color'=>'#DC2626','border'=>'#FECACA'],
                        ];
                        $pc = $pri_colors[$priority_label] ?? $pri_colors['Medium'];
                    ?>
                    <div class="report-item">
                        <div class="r-header">
                            <div>
                                <div class="r-type"><?php echo htmlspecialchars($subject_line); ?></div>
                                <span style="font-size:0.78rem;color:var(--text-muted);"><?php echo htmlspecialchars($r['request_type']); ?></span>
                            </div>
                            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                                <span style="background:<?php echo $pc['bg']; ?>;color:<?php echo $pc['color']; ?>;border:1px solid <?php echo $pc['border']; ?>;padding:3px 10px;border-radius:50px;font-size:0.75rem;font-weight:600;">
                                    <?php echo htmlspecialchars($priority_label); ?>
                                </span>
                                <span class="status-badge status-<?php echo strtolower($r['status']); ?>">
                                    <?php echo htmlspecialchars($r['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="r-desc"><?php echo htmlspecialchars($r['description']); ?></div>
                        <div class="r-date">
                            Submitted: <?php echo date('M d, Y g:i A', strtotime($r['created_at'])); ?>
                            <?php if ($r['resolved_at']): ?>
                                &nbsp;·&nbsp; Resolved: <?php echo date('M d, Y', strtotime($r['resolved_at'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-reports">
                    <div style="font-size:3rem;margin-bottom:12px;">📋</div>
                    <p>No reports submitted yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function selectType(type, btn) {
    document.querySelectorAll('.report-type-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('hidden_request_type').value = type;
}
function selectPriority(priority, btn) {
    document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('hidden_priority').value = priority;
}
function validateReport() {
    if (!document.getElementById('hidden_request_type').value) {
        showMessage('Please select a report type first.', 'error');
        return false;
    }
    return confirm('Submit this report to the clinic admin?');
}
</script>
</body>
</html>