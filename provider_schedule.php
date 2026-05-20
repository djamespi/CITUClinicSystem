<?php
session_start();
require_once 'db_connection.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Provider') {
    header("Location: index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$success_message = "";
$error_message   = "";

try {
    // Fetch provider ID
    $stmt = $pdo->prepare("SELECT pr.provider_id, pr.specialization, pr.room_number, u.fname, u.lname
                           FROM providers pr JOIN users u ON pr.user_id = u.user_id
                           WHERE pr.user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $provider = $stmt->fetch();
    $provider_id = $provider['provider_id'];

    // --- HANDLE: Add Single Slot ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_slot') {
        $date       = $_POST['slot_date'];
        $start_time = $_POST['start_time'];
        $end_time   = $_POST['end_time'];

        // Prevent duplicate slots
        $chk = $pdo->prepare("SELECT slot_id FROM slots WHERE provider_id = :pid AND date = :d AND start_time = :st LIMIT 1");
        $chk->execute([':pid' => $provider_id, ':d' => $date, ':st' => $start_time]);

        if ($chk->fetch()) {
            $error_message = "A slot already exists for that date and time.";
        } else {
            $pdo->prepare("INSERT INTO slots (provider_id, date, start_time, end_time, is_booked)
                           VALUES (:pid, :d, :st, :et, 0)")
                ->execute([':pid' => $provider_id, ':d' => $date, ':st' => $start_time, ':et' => $end_time]);
            $success_message = "Slot added successfully.";
        }
    }

    // --- HANDLE: Bulk Generate Slots for a Day ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'bulk_slots') {
        $date         = $_POST['bulk_date'];
        $from_time    = $_POST['from_time'];
        $to_time      = $_POST['to_time'];
        $duration_min = (int)$_POST['duration'];

        $current = strtotime($date . ' ' . $from_time);
        $end     = strtotime($date . ' ' . $to_time);
        $added   = 0;

        $pdo->beginTransaction();
        while ($current + ($duration_min * 60) <= $end) {
            $st = date('H:i:s', $current);
            $et = date('H:i:s', $current + ($duration_min * 60));

            // Skip if exists
            $chk = $pdo->prepare("SELECT slot_id FROM slots WHERE provider_id = :pid AND date = :d AND start_time = :st LIMIT 1");
            $chk->execute([':pid' => $provider_id, ':d' => $date, ':st' => $st]);
            if (!$chk->fetch()) {
                $pdo->prepare("INSERT INTO slots (provider_id, date, start_time, end_time, is_booked) VALUES (:pid,:d,:st,:et,0)")
                    ->execute([':pid' => $provider_id, ':d' => $date, ':st' => $st, ':et' => $et]);
                $added++;
            }
            $current += ($duration_min * 60);
        }
        $pdo->commit();
        $success_message = "{$added} slot(s) generated for " . date('M d, Y', strtotime($date)) . ".";
    }

    // --- HANDLE: Delete Slot ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_slot') {
        $slot_id = $_POST['slot_id'];
        // Only delete if not booked
        $chk = $pdo->prepare("SELECT is_booked FROM slots WHERE slot_id = :sid AND provider_id = :pid LIMIT 1");
        $chk->execute([':sid' => $slot_id, ':pid' => $provider_id]);
        $slot = $chk->fetch();

        if ($slot && !$slot['is_booked']) {
            $pdo->prepare("DELETE FROM slots WHERE slot_id = :sid")->execute([':sid' => $slot_id]);
            $success_message = "Slot removed.";
        } else {
            $error_message = "Cannot remove a slot that is already booked.";
        }
    }

    // --- READ: All slots for this provider (next 60 days + past 7) ---
    $sql_slots = "SELECT slot_id, date, start_time, end_time, is_booked
                  FROM slots
                  WHERE provider_id = :pid
                    AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  ORDER BY date ASC, start_time ASC";
    $stmt_s = $pdo->prepare($sql_slots);
    $stmt_s->execute([':pid' => $provider_id]);
    $slots = $stmt_s->fetchAll();

    // Group by date
    $slots_grouped = [];
    foreach ($slots as $slot) {
        $slots_grouped[$slot['date']][] = $slot;
    }

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
    <title>My Schedule | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        .schedule-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 960px) { .schedule-grid { grid-template-columns: 1fr; } }

        /* Calendar-style slot viewer */
        .day-block {
            margin-bottom: 24px;
        }

        .day-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .day-label {
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--citu-blue);
        }

        .day-label.today-label {
            color: var(--citu-gold);
        }

        .day-count {
            font-size: 0.78rem;
            background: var(--bg-light);
            color: var(--text-muted);
            padding: 3px 10px;
            border-radius: 50px;
            border: 1px solid var(--border-color);
        }

        .slot-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .slot-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 50px;
            font-size: 0.83rem;
            font-weight: 500;
            border: 1.5px solid var(--border-color);
            background: var(--white);
            color: var(--text-dark);
        }

        .slot-pill.booked {
            background: #ECFDF5;
            border-color: #A7F3D0;
            color: #059669;
        }

        .slot-pill.free {
            background: var(--white);
            border-color: var(--citu-gold);
            color: var(--citu-blue);
        }

        .slot-pill .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #EF4444;
            font-size: 1rem;
            padding: 0;
            line-height: 1;
            transition: transform 0.15s;
        }

        .slot-pill .delete-btn:hover { transform: scale(1.2); }

        /* Form panels */
        .form-tabs {
            display: flex;
            gap: 4px;
            background: #F1F5F9;
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-tab-btn {
            flex: 1;
            padding: 9px;
            background: none;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .form-tab-btn.active {
            background: var(--white);
            color: var(--citu-blue);
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.07);
        }

        .form-panel { display: none; }
        .form-panel.active { display: block; animation: fadeIn 0.25s ease; }

        @keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .mini-stat {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .mini-stat .num {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--citu-blue);
        }

        .mini-stat .lbl { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }

        .empty-schedule {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-schedule .big-icon { font-size: 3.5rem; margin-bottom: 12px; }
    </style>
</head>
<body>

<?php include 'provider_navbar.php'; ?>

<div class="main-content">
    <div class="header">
        <h1 style="font-size:1.8rem;">My Clinic Schedule</h1>
        <div class="user-profile"><?php echo $provider_display_name; ?></div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="schedule-grid">

        <!-- LEFT: Add Slots Panel -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <?php
                    $total_slots = count($slots);
                    $free_slots  = count(array_filter($slots, fn($s) => !$s['is_booked']));
                    $booked_slots = $total_slots - $free_slots;
                ?>
                <div class="stats-mini">
                    <div class="mini-stat">
                        <div class="num"><?php echo $free_slots; ?></div>
                        <div class="lbl">Open Slots</div>
                    </div>
                    <div class="mini-stat">
                        <div class="num"><?php echo $booked_slots; ?></div>
                        <div class="lbl">Booked Slots</div>
                    </div>
                </div>

                <h3>Add Time Slots</h3>

                <div class="form-tabs">
                    <button class="form-tab-btn active" onclick="switchFormTab('single', this)">Single Slot</button>
                    <button class="form-tab-btn" onclick="switchFormTab('bulk', this)">Bulk Generate</button>
                </div>

                <!-- Single Slot Form -->
                <div class="form-panel active" id="panel-single">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_slot">
                        <div class="input-group">
                            <label>Date</label>
                            <input type="date" name="slot_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="input-row">
                            <div class="input-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" required>
                            </div>
                            <div class="input-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-save">Add Slot</button>
                    </form>
                </div>

                <!-- Bulk Generate Form -->
                <div class="form-panel" id="panel-bulk">
                    <form method="POST">
                        <input type="hidden" name="action" value="bulk_slots">
                        <div class="input-group">
                            <label>Date</label>
                            <input type="date" name="bulk_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="input-row">
                            <div class="input-group">
                                <label>Clinic Start</label>
                                <input type="time" name="from_time" value="08:00" required>
                            </div>
                            <div class="input-group">
                                <label>Clinic End</label>
                                <input type="time" name="to_time" value="17:00" required>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Slot Duration</label>
                            <select name="duration" required>
                                <option value="15">15 minutes</option>
                                <option value="20">20 minutes</option>
                                <option value="30" selected>30 minutes</option>
                                <option value="45">45 minutes</option>
                                <option value="60">1 hour</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-save"
                                onclick="return confirm('Generate slots for the whole day?');">
                            Generate Slots
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT: Slot Viewer -->
        <div class="card">
            <h2>Schedule Overview</h2>

            <?php if (count($slots_grouped) > 0): ?>
                <?php foreach ($slots_grouped as $date => $day_slots): ?>
                    <?php
                        $is_today  = ($date === date('Y-m-d'));
                        $is_past   = (strtotime($date) < strtotime('today'));
                        $free_day  = count(array_filter($day_slots, fn($s) => !$s['is_booked']));
                        $booked_day = count($day_slots) - $free_day;
                    ?>
                    <div class="day-block">
                        <div class="day-header">
                            <span class="day-label <?php echo $is_today ? 'today-label' : ''; ?>">
                                <?php echo date('l, F j', strtotime($date)); ?>
                                <?php if ($is_today): ?>
                                    <span style="background:var(--citu-gold);color:var(--citu-blue);font-size:0.7rem;padding:2px 8px;border-radius:50px;margin-left:6px;">TODAY</span>
                                <?php endif; ?>
                            </span>
                            <span class="day-count"><?php echo $free_day; ?> free · <?php echo $booked_day; ?> booked</span>
                        </div>

                        <div class="slot-grid">
                            <?php foreach ($day_slots as $slot): ?>
                                <div class="slot-pill <?php echo $slot['is_booked'] ? 'booked' : 'free'; ?>">
                                    <?php echo date('g:i A', strtotime($slot['start_time'])); ?>
                                    <?php if ($slot['is_booked']): ?>
                                        <span>✓ Booked</span>
                                    <?php else: ?>
                                        <?php if (!$is_past): ?>
                                            <form method="POST" style="margin:0;display:inline;">
                                                <input type="hidden" name="action" value="delete_slot">
                                                <input type="hidden" name="slot_id" value="<?php echo $slot['slot_id']; ?>">
                                                <button class="delete-btn" title="Remove slot"
                                                        onclick="return confirm('Remove this slot?');">✕</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-schedule">
                    <div class="big-icon">🗓️</div>
                    <p>No slots created yet. Use the panel on the left to add your clinic schedule.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function switchFormTab(panel, btn) {
    document.querySelectorAll('.form-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('panel-' + panel).classList.add('active');
}
</script>

</body>
</html>