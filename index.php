<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIT-U Clinic System | Authentication</title>
    <!-- Import External CSS -->
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }
    </style>
</head>
<body>


<!-- THE POP-UP MESSAGE BOX -->
<?php if(isset($_SESSION['error']) || isset($_SESSION['success'])): ?>
    <?php
    $isError = isset($_SESSION['error']);
    $message = $isError ? $_SESSION['error'] : $_SESSION['success'];
    $toastClass = $isError ? 'toast-error' : 'toast-success';
    // Removed the warning sign for errors, kept the green check for success
    $icon = $isError ? '' : '✅';
    ?>
    <div id="toastBox" class="toast-box <?= $toastClass ?>">
        <?php if($icon): ?>
            <div class="toast-icon"><?= $icon ?></div>
        <?php endif; ?>
        <div class="toast-message"><?= htmlspecialchars($message) ?></div>
        <button class="toast-close" onclick="closeToast()">&times;</button>
    </div>
    <?php
    unset($_SESSION['error']);
    unset($_SESSION['success']);
    ?>
<?php endif; ?>

<div class="auth-container">
    <!-- Branding Section (Left Side) -->
    <div class="brand-section">
        <div class="logo-wrapper">
            <!-- Assuming clinic-logo is also an SVG. If it's a PNG, just change the extension! -->
            <img src="images/cit-logo.svg" alt="CIT-U Logo" class="brand-logo">
            <img src="images/clinic-logo.svg" alt="Clinic Logo" id="clinic-logo" class="brand-logo">
        </div>
        <h1>CIT-U Clinic</h1>
        <p>Secure Medical Records & Appointment Management System</p>
    </div>

    <!-- Form Section -->
    <div class="form-section">
        <div class="form-toggle">
            <button class="toggle-btn active" onclick="switchForm('login')">Login</button>
            <button class="toggle-btn" onclick="switchForm('register')">Register</button>
        </div>

        <!-- LOGIN FORM -->
        <form id="loginForm" class="form-wrapper active" action="process_login.php" method="POST">
            <div class="input-group">
                <label>University ID</label>
                <input type="text" name="university_id" placeholder="e.g., 12-3456-78" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="submit-btn">Sign In</button>
        </form>

        <!-- REGISTRATION FORM (STEP 1) -->
        <form id="registerForm" class="form-wrapper" action="process_step1.php" method="POST">
            <div class="input-group">
                <label>University ID</label>
                <input type="text" name="university_id" placeholder="e.g., 12-3456-78" required>
            </div>

            <div class="input-group">
                <label>Account Type</label>
                <select name="user_type" required>
                    <option value="">-- Select Your Role --</option>
                    <option value="Patient">Student / Patient</option>
                    <option value="Admin">Clinic Administrator</option>
                    <option value="Provider">Healthcare Provider</option>
                </select>
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a password" required>
                </div>
                <div class="input-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="submit-btn">Continue to Profile Setup →</button>
        </form>
    </div>
</div>

<!-- Import External JS -->
<script src="js/auth.js?v=<?php echo time(); ?>"></script>
</body>
</html>