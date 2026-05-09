<?php
session_start();

// Security Check: If they didn't complete Step 1, kick them back to index
if (!isset($_SESSION['reg_uid']) || !isset($_SESSION['reg_type'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['reg_type'];
$uid = $_SESSION['reg_uid'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Your Profile | CIT-U Clinic</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Reuse the beautiful login page background */
        body {
            background: linear-gradient(rgba(0, 34, 75, 0.85), rgba(0, 34, 75, 0.90)), url('images/clinic_outside.png');
            background-size: cover;
            background-position: center;
            align-items: center;
            justify-content: center;
        }
        .setup-card {
            background: var(--white);
            width: 100%;
            max-width: 600px;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            margin: 40px auto;
        }
        .setup-header { text-align: center; margin-bottom: 30px; }
        .setup-header h2 { color: var(--citu-blue); font-size: 2rem; margin-bottom: 10px; }
        .setup-header p { color: var(--text-muted); font-size: 0.95rem; }
        .role-badge-large { background-color: var(--citu-gold); color: var(--citu-blue); padding: 5px 15px; border-radius: 50px; font-weight: 600; font-size: 0.85rem; display: inline-block; margin-top: 10px; }
        /* Top Left Branding Styles */
        .top-left-brand {
            position: absolute; /* Pins it to the screen */
            top: 40px;
            left: 50px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
            letter-spacing: 1px;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3); /* Makes text pop over the background */
        }

        .top-left-brand img {
            height: 45px; /* Perfect size for a corner logo */
            width: auto;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
        }

        /* Hides the corner logos on very small mobile phones so they don't overlap the form */
        @media (max-width: 768px) {
            .top-left-brand {
                display: none;
            }
        }
    </style>
</head>
<body>


<!-- Top Left Floating Branding -->
<div class="top-left-brand">
    <img src="images/cit-logo.svg" alt="CIT-U Logo">
    <img src="images/clinic-logo.svg" alt="Clinic Logo">
    <span>CIT-U CLINIC</span>
</div>


<div class="setup-card">
    <div class="setup-header">
        <h2>Complete Profile</h2>
        <p>You are setting up the account for ID: <strong><?php echo htmlspecialchars($uid); ?></strong></p>
        <span class="role-badge-large"><?php echo htmlspecialchars($role); ?> Account</span>
    </div>

    <form action="process_register.php" method="POST">
        <!-- Universal Fields -->
        <div class="input-row">
            <div class="input-group">
                <label>First Name</label>
                <input type="text" name="fname" required>
            </div>
            <div class="input-group">
                <label>Last Name</label>
                <input type="text" name="lname" required>
            </div>
        </div>

        <div class="input-row">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="input-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" required>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #E2E8F0; margin: 25px 0;">

        <!-- Dynamic Fields generated safely by PHP based on Session Role -->
        <!-- Dynamic Fields for PATIENTS -->
        <?php if ($role === 'Patient'): ?>
            <div class="input-row">
                <div class="input-group">
                    <label>Emergency Contact Name</label>
                    <input type="text" name="emergency_name" placeholder="e.g., Maria Dela Cruz" required>
                </div>
                <div class="input-group">
                    <label>Emergency Contact Number</label>
                    <input type="text" name="emergency_phone" placeholder="e.g., 0912 345 6789" required>
                </div>
            </div>

            <div class="input-group">
                <label>Medical History Summary</label>
                <!-- Added rows="4" to make the textarea taller and more inviting! -->
                <textarea name="medical_history" rows="4" placeholder="List any known allergies, chronic conditions, or past surgeries here..."></textarea>
            </div>

        <?php elseif ($role === 'Admin'): ?>
            <div class="input-row">
                <div class="input-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="e.g., IT Support" required>
                </div>
                <div class="input-group">
                    <label>Access Level</label>
                    <select name="access_level">
                        <option value="Standard">Standard</option>
                        <option value="Superadmin">Superadmin</option>
                    </select>
                </div>
            </div>

        <?php elseif ($role === 'Provider'): ?>
            <div class="input-group">
                <label>License Number</label>
                <input type="text" name="license_no" placeholder="PRC License No." required>
            </div>
            <div class="input-row">
                <div class="input-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" placeholder="e.g., General Medicine" required>
                </div>
                <div class="input-group">
                    <label>Room Number</label>
                    <input type="text" name="room_number" placeholder="e.g., Rm 102" required>
                </div>
            </div>
        <?php endif; ?>

        <button type="submit" class="submit-btn" style="margin-top: 20px;">Finalize Registration</button>
    </form>
</div>

</body>
</html>
