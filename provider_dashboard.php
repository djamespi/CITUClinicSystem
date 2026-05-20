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
    // Fetch provider — includes license_no and is_active per schema
    $stmt = $pdo->prepare("SELECT pr.provider_id, pr.specialization, pr.room_number,
                                  pr.license_no, pr.is_active,
                                  u.fname, u.lname, u.university_id
                           FROM providers pr
                           JOIN users u ON pr.user_id = u.user_id
                           WHERE pr.user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $provider = $stmt->fetch();

    if (!$provider) { session_destroy(); header("Location: index.php"); exit(); }

    $provider_id = $provider['provider_id'];

    // --- HANDLE ACTIONS ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $apt_id = $_POST['appointment_id'];

        if ($_POST['action'] === 'confirm_appointment') {
            $pdo->prepare("UPDATE appointments SET status = 'Confirmed'
                           WHERE appointment_id = :aid AND provider_id = :pid")
                ->execute([':aid' => $apt_id, ':pid' => $provider_id]);
            $success_message = "Appointment confirmed.";

        } elseif ($_POST['action'] === 'mark_noshow') {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE appointments SET status = 'No-Show'
                           WHERE appointment_id = :aid AND provider_id = :pid")
                ->execute([':aid' => $apt_id, ':pid' => $provider_id]);

            $patient_id = $_POST['patient_id'];
            $pdo->prepare("UPDATE patients SET no_show_count = no_show_count + 1
                           WHERE patient_id = :pid")
                ->execute([':pid' => $patient_id]);

            // Re-fetch updated count
            $chk = $pdo->prepare("SELECT no_show_count, user_id FROM patients WHERE patient_id = :pid");
            $chk->execute([':pid' => $patient_id]);
            $pat = $chk->fetch();

            if ($pat['no_show_count'] >= 3) {
                $pdo->prepare("UPDATE users SET account_status = 'Suspended' WHERE user_id = :uid")
                    ->execute([':uid' => $pat['user_id']]);
                $success_message = "No-show recorded. Patient auto-suspended (3 strikes reached).";
            } else {
                $success_message = "No-show recorded. Patient warned (" . $pat['no_show_count'] . "/3).";
            }
            $pdo->commit();
        }
    }

    // --- READ: appointments with proper schema columns ---
    $sql_all = "SELECT a.appointment_id, a.reason_for_visit, a.status,
                       s.date, s.start_time, s.end_time,
                       pu.fname, pu.lname, pu.university_id,
                       p.patient_id, p.no_show_count
                FROM appointments a
                JOIN slots s    ON a.slot_id    = s.slot_id
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN users pu   ON p.user_id    = pu.user_id
                WHERE a.provider_id = :pid
                ORDER BY
                    CASE WHEN a.status = 'Pending'   THEN 1
                         WHEN a.status = 'Confirmed' THEN 2
                         ELSE 3 END,
                    s.date ASC, s.start_time ASC";
    $stmt_all = $pdo->prepare($sql_all);
    $stmt_all->execute([':pid' => $provider_id]);
    $appointments = $stmt_all->fetchAll();

    $total      = count($appointments);
    $pending    = count(array_filter($appointments, fn($a) => $a['status'] === 'Pending'));
    $confirmed  = count(array_filter($appointments, fn($a) => $a['status'] === 'Confirmed'));
    $today_apts = count(array_filter($appointments, fn($a) =>
        $a['date'] === date('Y-m-d') && !in_array($a['status'], ['Cancelled','No-Show'])
    ));

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    die("System Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Dashboard | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background:var(--white); border-radius:16px; padding:28px 30px; box-shadow:var(--card-shadow); border:1px solid #F1F5F9; display:flex; align-items:center; gap:20px; }
        .stat-icon { width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0; }
        .stat-icon.blue   { background:linear-gradient(135deg,var(--citu-blue),#003B80); }
        .stat-icon.gold   { background:linear-gradient(135deg,var(--citu-gold),#D4AF37); }
        .stat-icon.green  { background:linear-gradient(135deg,#10B981,#059669); }
        .stat-icon.orange { background:linear-gradient(135deg,#F97316,#EA580C); }
        .stat-number { font-family:'Poppins',sans-serif; font-size:2rem; font-weight:700; color:var(--citu-blue); line-height:1; }
        .stat-label  { font-size:0.82rem; color:var(--text-muted); margin-top:4px; }

        .provider-banner { background:linear-gradient(135deg,var(--citu-blue),#003B80); border-radius:16px; padding:28px 36px; color:var(--white); display:flex; align-items:center; gap:24px; margin-bottom:30px; box-shadow:var(--card-shadow); }
        .provider-avatar-lg { width:70px; height:70px; border-radius:50%; background:var(--citu-gold); color:var(--citu-blue); font-family:'Poppins',sans-serif; font-weight:700; font-size:1.6rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .provider-banner-info h2  { color:var(--white); font-size:1.4rem; margin-bottom:6px; }
        .provider-banner-info .meta { font-size:0.88rem; opacity:0.75; display:flex; gap:20px; flex-wrap:wrap; }

        .filter-bar { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .filter-chip { padding:7px 18px; border-radius:50px; border:1.5px solid var(--border-color); background:var(--white); font-size:0.85rem; font-weight:500; color:var(--text-muted); cursor:pointer; transition:all 0.2s; }
        .filter-chip:hover, .filter-chip.active { border-color:var(--citu-blue); color:var(--citu-blue); background:#EEF2FF; font-weight:600; }

        tr.today-row td { background-color:#FFFBEB !important; }
        tr.today-row td:first-child { border-left:3px solid var(--citu-gold); }
        .appt-table-actions { display:flex; gap:8px; flex-wrap:wrap; }
    </style>
</head>
<body>

<?php include 'provider_navbar.php'; ?>

<div class="main-content">

    <?php $initials = strtoupper(substr($provider['fname'],0,1).substr($provider['lname'],0,1)); ?>
    <div class="provider-banner">
        <div class="provider-avatar-lg"><?php echo $initials; ?></div>
        <div class="provider-banner-info">
            <h2><?php echo $provider_display_name; ?></h2>
            <div class="meta">
                <span>🩺 <?php echo htmlspecialchars($provider['specialization']); ?></span>
                <span>📍 Room <?php echo htmlspecialchars($provider['room_number']); ?></span>
                <span>🪪 Lic. <?php echo htmlspecialchars($provider['license_no']); ?></span>
                <span><?php echo $provider['is_active'] ? '🟢 Active' : '🔴 Inactive'; ?></span>
            </div>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-icon blue">📋</div><div><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">Total Appointments</div></div></div>
        <div class="stat-card"><div class="stat-icon orange">⏳</div><div><div class="stat-number"><?php echo $pending; ?></div><div class="stat-label">Pending Review</div></div></div>
        <div class="stat-card"><div class="stat-icon green">✅</div><div><div class="stat-number"><?php echo $confirmed; ?></div><div class="stat-label">Confirmed</div></div></div>
        <div class="stat-card"><div class="stat-icon gold">📅</div><div><div class="stat-number"><?php echo $today_apts; ?></div><div class="stat-label">Today's Patients</div></div></div>
    </div>

    <?php if ($success_message): ?><div class="alert-success"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="card">
        <h2>Appointments</h2>
        <div class="filter-bar">
            <button class="filter-chip active" onclick="filterTable('all',this)">All</button>
            <button class="filter-chip" onclick="filterTable('pending',this)">Pending</button>
            <button class="filter-chip" onclick="filterTable('confirmed',this)">Confirmed</button>
            <button class="filter-chip" onclick="filterTable('no-show',this)">No-Show</button>
            <button class="filter-chip" onclick="filterTable('cancelled',this)">Cancelled</button>
        </div>
        <table id="appt-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Patient</th>
                    <th>Reason for Visit</th>
                    <th>No-Shows</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($appointments) > 0): ?>
                <?php foreach ($appointments as $apt): ?>
                    <?php $is_today = ($apt['date'] === date('Y-m-d')); ?>
                    <tr class="<?php echo $is_today ? 'today-row' : ''; ?>"
                        data-status="<?php echo strtolower(str_replace(' ','-',$apt['status'])); ?>">
                        <td>
                            <strong><?php echo date('M d, Y', strtotime($apt['date'])); ?></strong>
                            <?php if ($is_today): ?>
                                <span style="background:var(--citu-gold);color:var(--citu-blue);font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:50px;margin-left:6px;">TODAY</span>
                            <?php endif; ?>
                            <span class="subtext"><?php echo date('g:i A', strtotime($apt['start_time'])).' – '.date('g:i A', strtotime($apt['end_time'])); ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($apt['lname'].', '.$apt['fname']); ?></strong>
                            <span class="subtext"><?php echo htmlspecialchars($apt['university_id']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($apt['reason_for_visit']); ?></td>
                        <td>
                            <?php
                                $ns = $apt['no_show_count'];
                                $ns_color = $ns >= 2 ? '#DC2626' : ($ns >= 1 ? '#D97706' : '#059669');
                            ?>
                            <span style="font-weight:700;color:<?php echo $ns_color; ?>"><?php echo $ns; ?>/3</span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower(str_replace([' ','-'],'',$apt['status'])); ?>">
                                <?php echo htmlspecialchars($apt['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="appt-table-actions">
                            <?php if ($apt['status'] === 'Pending'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="confirm_appointment">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
                                    <button class="btn-resolve">Confirm</button>
                                </form>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="mark_noshow">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
                                    <input type="hidden" name="patient_id" value="<?php echo $apt['patient_id']; ?>">
                                    <button class="btn-cancel" onclick="return confirm('Mark as no-show? This increases the patient\'s strike count.');">No-Show</button>
                                </form>
                            <?php elseif ($apt['status'] === 'Confirmed'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="mark_noshow">
                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
                                    <input type="hidden" name="patient_id" value="<?php echo $apt['patient_id']; ?>">
                                    <button class="btn-cancel" onclick="return confirm('Mark as no-show?');">No-Show</button>
                                </form>
                            <?php else: ?>
                                <button class="btn-disabled" disabled><?php echo htmlspecialchars($apt['status']); ?></button>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No appointments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTable(status, chip) {
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    document.querySelectorAll('#appt-table tbody tr[data-status]').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
</script>
</body>
</html>