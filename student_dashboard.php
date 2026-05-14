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
$error_message = "";

try {
    // Fetch the patient's record
    $stmt = $pdo->prepare("SELECT p.patient_id, p.no_show_count, p.medical_history_summary, p.emergency_contact,
                                  u.fname, u.lname, u.university_id, u.account_status
                           FROM patients p
                           JOIN users u ON p.user_id = u.user_id
                           WHERE p.user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $patient = $stmt->fetch();

    if (!$patient) {
        session_destroy();
        header("Location: index.php");
        exit();
    }

    $patient_id = $patient['patient_id'];
    $display_name = htmlspecialchars($patient['lname'] . ', ' . $patient['fname']);

    // --- HANDLE: BOOK APPOINTMENT ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'book_appointment') {
        $slot_id       = $_POST['slot_id'];
        $provider_id   = $_POST['provider_id'];
        $reason        = trim($_POST['reason_for_visit']);

        // Check slot is still free
        $chk = $pdo->prepare("SELECT is_booked FROM slots WHERE slot_id = :sid FOR UPDATE");
        $chk->execute([':sid' => $slot_id]);
        $slot = $chk->fetch();

        if ($slot && !$slot['is_booked']) {
            $pdo->beginTransaction();

            // Insert appointment
            $ins = $pdo->prepare("INSERT INTO appointments (patient_id, provider_id, slot_id, reason_for_visit, status)
                                  VALUES (:pid, :prid, :sid, :reason, 'Pending')");
            $ins->execute([':pid' => $patient_id, ':prid' => $provider_id, ':sid' => $slot_id, ':reason' => $reason]);

            // Mark slot as booked
            $upd = $pdo->prepare("UPDATE slots SET is_booked = 1 WHERE slot_id = :sid");
            $upd->execute([':sid' => $slot_id]);

            $pdo->commit();
            $success_message = "Appointment booked successfully! Please arrive on time.";
        } else {
            $error_message = "That slot was just taken. Please choose another.";
        }
    }

    // --- HANDLE: CANCEL APPOINTMENT ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'cancel_appointment') {
        $appointment_id = $_POST['appointment_id'];
        $slot_id        = $_POST['slot_id'];

        $pdo->beginTransaction();

        $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = :aid AND patient_id = :pid")
            ->execute([':aid' => $appointment_id, ':pid' => $patient_id]);

        $pdo->prepare("UPDATE slots SET is_booked = 0 WHERE slot_id = :sid")
            ->execute([':sid' => $slot_id]);

        $pdo->commit();
        $success_message = "Appointment cancelled. The slot is now free.";
    }

    // --- READ: My Upcoming Appointments ---
    $sql_my = "SELECT a.appointment_id, a.reason_for_visit, a.status, a.slot_id,
                      s.date, s.start_time, s.end_time,
                      u.fname AS dr_fname, u.lname AS dr_lname, pr.specialization
               FROM appointments a
               JOIN slots s ON a.slot_id = s.slot_id
               JOIN providers pr ON a.provider_id = pr.provider_id
               JOIN users u ON pr.user_id = u.user_id
               WHERE a.patient_id = :pid
               ORDER BY s.date ASC, s.start_time ASC";
    $stmt_my = $pdo->prepare($sql_my);
    $stmt_my->execute([':pid' => $patient_id]);
    $my_appointments = $stmt_my->fetchAll();

    // --- READ: Available Providers ---
    $providers = $pdo->query("SELECT pr.provider_id, u.fname, u.lname, pr.specialization, pr.room_number
                              FROM providers pr
                              JOIN users u ON pr.user_id = u.user_id
                              WHERE pr.is_active = 1
                              ORDER BY u.lname ASC")->fetchAll();

    // --- READ: Available Slots (next 30 days, not booked) ---
    $sql_slots = "SELECT s.slot_id, s.date, s.start_time, s.end_time, s.provider_id,
                         u.fname, u.lname, pr.specialization, pr.room_number
                  FROM slots s
                  JOIN providers pr ON s.provider_id = pr.provider_id
                  JOIN users u ON pr.user_id = u.user_id
                  WHERE s.is_booked = 0
                    AND s.date >= CURDATE()
                    AND s.date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  ORDER BY s.date ASC, s.start_time ASC";
    $available_slots = $pdo->query($sql_slots)->fetchAll();

    // Group slots by provider for the JS filter
    $slots_by_provider = [];
    foreach ($available_slots as $slot) {
        $slots_by_provider[$slot['provider_id']][] = $slot;
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    die("System Error: " . $e->getMessage());
}

// Separate upcoming vs past
$upcoming = array_filter($my_appointments, fn($a) => $a['status'] !== 'Cancelled' && strtotime($a['date']) >= strtotime('today'));
$past      = array_filter($my_appointments, fn($a) => $a['status'] === 'Cancelled' || strtotime($a['date']) < strtotime('today'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        /* =========================================
           STUDENT DASHBOARD ADDITIONS
        ========================================= */

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 28px 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--citu-blue), #003B80);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-icon.gold { background: linear-gradient(135deg, var(--citu-gold), #D4AF37); }
        .stat-icon.green { background: linear-gradient(135deg, #10B981, #059669); }
        .stat-icon.red { background: linear-gradient(135deg, #EF4444, #DC2626); }

        .stat-info .stat-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--citu-blue);
            line-height: 1;
        }

        .stat-info .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Tabs */
        .tab-bar {
            display: flex;
            gap: 4px;
            background: #F1F5F9;
            padding: 5px;
            border-radius: 12px;
            margin-bottom: 28px;
            width: fit-content;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 24px;
            border-radius: 9px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn.active {
            background: var(--white);
            color: var(--citu-blue);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; animation: fadeIn 0.3s ease; }

        /* Booking Panel */
        .booking-grid {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 24px;
        }

        @media (max-width: 900px) { .booking-grid { grid-template-columns: 1fr; } }

        .booking-step {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 24px;
        }

        .booking-step h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--citu-gold);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        /* Provider Cards */
        .provider-list { display: flex; flex-direction: column; gap: 10px; }

        .provider-card {
            background: var(--white);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 18px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .provider-card:hover { border-color: var(--citu-gold); background: #FFFDF0; }
        .provider-card.selected { border-color: var(--citu-blue); background: #EEF2FF; }

        .provider-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--citu-blue), #003B80);
            color: var(--citu-gold);
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .provider-info .name { font-weight: 600; color: var(--citu-blue); font-size: 0.95rem; }
        .provider-info .spec { font-size: 0.8rem; color: var(--text-muted); }
        .provider-info .room { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }

        /* Slot Grid */
        .slots-container { display: none; }
        .slots-container.active { display: block; }

        .slot-date-group { margin-bottom: 20px; }

        .slot-date-label {
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .slot-chips { display: flex; flex-wrap: wrap; gap: 8px; }

        .slot-chip {
            padding: 8px 16px;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            background: var(--white);
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            transition: all 0.2s;
        }

        .slot-chip:hover { border-color: var(--citu-gold); color: var(--citu-blue); }
        .slot-chip.selected { border-color: var(--citu-blue); background: var(--citu-blue); color: var(--white); }

        /* Booking Form */
        .booking-form-panel {
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            padding-top: 20px;
            display: none;
        }

        .booking-form-panel.active { display: block; animation: fadeIn 0.3s ease; }

        .selected-summary {
            background: linear-gradient(135deg, var(--citu-blue), #003B80);
            color: var(--white);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            line-height: 1.8;
        }

        .selected-summary strong { color: var(--citu-gold); }

        /* Appointment Cards */
        .appt-cards { display: flex; flex-direction: column; gap: 14px; }

        .appt-card {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--citu-blue);
            border-radius: 12px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            transition: box-shadow 0.2s;
        }

        .appt-card:hover { box-shadow: var(--card-shadow); }
        .appt-card.pending { border-left-color: #D97706; }
        .appt-card.cancelled { border-left-color: #DC2626; opacity: 0.7; }
        .appt-card.confirmed { border-left-color: #059669; }

        .appt-date-block { text-align: center; min-width: 60px; }
        .appt-day { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--citu-blue); line-height: 1; }
        .appt-month { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; }

        .appt-divider { width: 1px; height: 50px; background: var(--border-color); }

        .appt-details { flex: 1; min-width: 160px; }
        .appt-details .doctor { font-weight: 600; color: var(--citu-blue); }
        .appt-details .time { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }
        .appt-details .reason { font-size: 0.85rem; color: var(--text-muted); margin-top: 2px; }

        .appt-actions { display: flex; align-items: center; gap: 10px; }

        /* No-show warning */
        .noshow-warning {
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            border-left: 4px solid #F97316;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            color: #9A3412;
        }
        .noshow-warning strong { display: block; margin-bottom: 4px; }

        .no-appts {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .no-appts .emoji { font-size: 3rem; margin-bottom: 12px; }
        .no-appts p { font-size: 0.95rem; }

        .placeholder-msg {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 0.9rem;
            background: var(--white);
            border-radius: 12px;
            border: 1px dashed var(--border-color);
        }
    </style>
</head>
<body>

<?php include 'student_navbar.php'; ?>

<div class="main-content">

    <!-- Header -->
    <div class="header">
        <div>
            <h1 style="font-size:1.6rem; color:var(--citu-blue);">Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>!</h1>
            <p style="color:var(--text-muted); font-size:0.95rem; margin-top:4px;"><?php echo $display_name; ?> &mdash; <?php echo htmlspecialchars($patient['university_id']); ?></p>
        </div>
        <div class="user-profile">Patient Portal</div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if ($patient['no_show_count'] >= 2): ?>
        <div class="noshow-warning">
            <strong>⚠️ No-Show Warning</strong>
            You have <?php echo $patient['no_show_count']; ?> missed appointments on record. Reaching 3 no-shows may result in account suspension.
        </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count(array_filter($my_appointments, fn($a) => $a['status'] === 'Pending' || $a['status'] === 'Confirmed')); ?></div>
                <div class="stat-label">Upcoming Appointments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold">✅</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count(array_filter($my_appointments, fn($a) => $a['status'] === 'Confirmed')); ?></div>
                <div class="stat-label">Confirmed Visits</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🩺</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($available_slots); ?></div>
                <div class="stat-label">Open Slots Available</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">⚠️</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $patient['no_show_count']; ?>/3</div>
                <div class="stat-label">No-Show Count</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('appointments')">My Appointments</button>
        <button class="tab-btn" onclick="switchTab('book')">Book a Visit</button>
        <button class="tab-btn" onclick="switchTab('history')">History</button>
    </div>

    <!-- TAB 1: MY APPOINTMENTS -->
    <div id="tab-appointments" class="tab-panel active">
        <div class="card">
            <h2>Upcoming Appointments</h2>

            <?php if (count($upcoming) > 0): ?>
                <div class="appt-cards">
                    <?php foreach ($upcoming as $apt): ?>
                        <?php
                            $status_class = strtolower($apt['status']);
                            $day   = date('d', strtotime($apt['date']));
                            $month = date('M', strtotime($apt['date']));
                        ?>
                        <div class="appt-card <?php echo $status_class; ?>">
                            <div class="appt-date-block">
                                <div class="appt-day"><?php echo $day; ?></div>
                                <div class="appt-month"><?php echo $month; ?></div>
                            </div>
                            <div class="appt-divider"></div>
                            <div class="appt-details">
                                <div class="doctor">Dr. <?php echo htmlspecialchars($apt['dr_lname'] . ', ' . $apt['dr_fname']); ?></div>
                                <div class="time">⏰ <?php echo htmlspecialchars(date('g:i A', strtotime($apt['start_time'])) . ' – ' . date('g:i A', strtotime($apt['end_time']))); ?></div>
                                <div class="reason">📋 <?php echo htmlspecialchars($apt['reason_for_visit']); ?></div>
                            </div>
                            <div class="appt-actions">
                                <span class="status-badge status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($apt['status']); ?></span>
                                <?php if ($apt['status'] !== 'Cancelled'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="cancel_appointment">
                                        <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
                                        <input type="hidden" name="slot_id" value="<?php echo $apt['slot_id']; ?>">
                                        <button type="submit" class="btn-cancel" onclick="return confirm('Cancel this appointment? This may affect your no-show count if done repeatedly.');">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-appts">
                    <div class="emoji">🗓️</div>
                    <p>You have no upcoming appointments.</p>
                    <button class="btn-gold btn" style="margin-top:14px;" onclick="switchTab('book')">Book a Visit Now</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: BOOK APPOINTMENT -->
    <div id="tab-book" class="tab-panel">
        <div class="card">
            <h2>Book a New Appointment</h2>

            <div class="booking-grid">
                <!-- Step 1: Choose Provider -->
                <div class="booking-step">
                    <h4>Step 1 — Choose a Doctor</h4>
                    <div class="provider-list">
                        <?php foreach ($providers as $prov): ?>
                            <?php
                                $initials = strtoupper(substr($prov['fname'], 0, 1) . substr($prov['lname'], 0, 1));
                                $has_slots = isset($slots_by_provider[$prov['provider_id']]);
                            ?>
                            <?php if ($has_slots): ?>
                            <div class="provider-card" onclick="selectProvider(<?php echo $prov['provider_id']; ?>, this)"
                                 data-provider="<?php echo $prov['provider_id']; ?>">
                                <div class="provider-avatar"><?php echo $initials; ?></div>
                                <div class="provider-info">
                                    <div class="name">Dr. <?php echo htmlspecialchars($prov['lname'] . ', ' . $prov['fname']); ?></div>
                                    <div class="spec"><?php echo htmlspecialchars($prov['specialization']); ?></div>
                                    <div class="room">📍 <?php echo htmlspecialchars($prov['room_number']); ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="provider-card" style="opacity:0.45; cursor:not-allowed;">
                                <div class="provider-avatar" style="background:linear-gradient(135deg,#94A3B8,#64748B)"><?php echo $initials; ?></div>
                                <div class="provider-info">
                                    <div class="name">Dr. <?php echo htmlspecialchars($prov['lname'] . ', ' . $prov['fname']); ?></div>
                                    <div class="spec"><?php echo htmlspecialchars($prov['specialization']); ?></div>
                                    <div class="room">No slots available</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Choose Slot + Confirm -->
                <div class="booking-step">
                    <h4>Step 2 — Pick a Time Slot</h4>

                    <div id="slot-placeholder" class="placeholder-msg">
                        ← Select a doctor to see available slots
                    </div>

                    <?php foreach ($providers as $prov): ?>
                        <?php if (!isset($slots_by_provider[$prov['provider_id']])) continue; ?>
                        <div class="slots-container" id="slots-<?php echo $prov['provider_id']; ?>">
                            <?php
                                // Group by date
                                $grouped = [];
                                foreach ($slots_by_provider[$prov['provider_id']] as $slot) {
                                    $grouped[$slot['date']][] = $slot;
                                }
                            ?>
                            <?php foreach ($grouped as $date => $day_slots): ?>
                                <div class="slot-date-group">
                                    <div class="slot-date-label">
                                        <?php echo date('l, F j', strtotime($date)); ?>
                                    </div>
                                    <div class="slot-chips">
                                        <?php foreach ($day_slots as $slot): ?>
                                            <div class="slot-chip"
                                                 onclick="selectSlot(this, <?php echo $slot['slot_id']; ?>, <?php echo $prov['provider_id']; ?>, '<?php echo htmlspecialchars($slot['date']); ?>', '<?php echo htmlspecialchars(date('g:i A', strtotime($slot['start_time']))); ?>', '<?php echo htmlspecialchars(date('g:i A', strtotime($slot['end_time']))); ?>', 'Dr. <?php echo htmlspecialchars($prov['lname']); ?>')">
                                                <?php echo date('g:i A', strtotime($slot['start_time'])); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Step 3: Confirm Form -->
                    <div class="booking-form-panel" id="booking-form-panel">
                        <div class="selected-summary" id="selected-summary"></div>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="book_appointment">
                            <input type="hidden" name="slot_id" id="hidden_slot_id">
                            <input type="hidden" name="provider_id" id="hidden_provider_id">

                            <div class="input-group">
                                <label>Reason for Visit</label>
                                <textarea name="reason_for_visit" rows="3" placeholder="Describe your symptoms or reason..." required></textarea>
                            </div>
                            <button type="submit" class="btn-save">Confirm & Book Appointment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: HISTORY -->
    <div id="tab-history" class="tab-panel">
        <div class="card">
            <h2>Appointment History</h2>

            <?php if (count($past) > 0): ?>
                <div class="appt-cards">
                    <?php foreach ($past as $apt): ?>
                        <?php
                            $status_class = strtolower($apt['status']);
                            $day   = date('d', strtotime($apt['date']));
                            $month = date('M', strtotime($apt['date']));
                        ?>
                        <div class="appt-card <?php echo $status_class; ?>">
                            <div class="appt-date-block">
                                <div class="appt-day"><?php echo $day; ?></div>
                                <div class="appt-month"><?php echo $month; ?></div>
                            </div>
                            <div class="appt-divider"></div>
                            <div class="appt-details">
                                <div class="doctor">Dr. <?php echo htmlspecialchars($apt['dr_lname'] . ', ' . $apt['dr_fname']); ?></div>
                                <div class="time">⏰ <?php echo htmlspecialchars(date('g:i A', strtotime($apt['start_time'])) . ' – ' . date('g:i A', strtotime($apt['end_time']))); ?></div>
                                <div class="reason">📋 <?php echo htmlspecialchars($apt['reason_for_visit']); ?></div>
                            </div>
                            <div class="appt-actions">
                                <span class="status-badge status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($apt['status']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-appts">
                    <div class="emoji">📂</div>
                    <p>No past appointment history yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end main-content -->

<script>
// =====================
// TAB SWITCHING
// =====================
function switchTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.currentTarget.classList.add('active');
}

// =====================
// PROVIDER SELECTION
// =====================
function selectProvider(providerId, card) {
    // Reset all
    document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.slots-container').forEach(s => s.classList.remove('active'));
    document.getElementById('slot-placeholder').style.display = 'none';
    card.classList.add('selected');

    // Deselect any chip and hide booking form
    document.querySelectorAll('.slot-chip').forEach(c => c.classList.remove('selected'));
    document.getElementById('booking-form-panel').classList.remove('active');

    // Show the slots for this provider
    const container = document.getElementById('slots-' + providerId);
    if (container) container.classList.add('active');
}

// =====================
// SLOT SELECTION
// =====================
function selectSlot(chip, slotId, providerId, date, startTime, endTime, doctorName) {
    // Deselect all chips in this provider's container
    const container = chip.closest('.slots-container');
    container.querySelectorAll('.slot-chip').forEach(c => c.classList.remove('selected'));
    chip.classList.add('selected');

    // Fill hidden inputs
    document.getElementById('hidden_slot_id').value = slotId;
    document.getElementById('hidden_provider_id').value = providerId;

    // Build summary
    const dateFormatted = new Date(date + 'T00:00').toLocaleDateString('en-PH', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    document.getElementById('selected-summary').innerHTML =
        `<strong>Doctor:</strong> ${doctorName}<br>` +
        `<strong>Date:</strong> ${dateFormatted}<br>` +
        `<strong>Time:</strong> ${startTime} – ${endTime}`;

    document.getElementById('booking-form-panel').classList.add('active');
}
</script>

</body>
</html>