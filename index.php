<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIT-U Clinic System | Authentication</title>
    <!-- Import External CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="auth-container">
    <!-- Branding Section -->
    <div class="brand-section">
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

        <!-- REGISTRATION FORM -->
        <form id="registerForm" class="form-wrapper" action="process_register.php" method="POST">
            <!-- NEW: Name and DOB Fields -->
            <div style="display: flex; gap: 15px;">
                <div class="input-group" style="flex: 1;">
                    <label>First Name</label>
                    <input type="text" name="fname" placeholder="Juan" required>
                </div>
                <div class="input-group" style="flex: 1;">
                    <label>Last Name</label>
                    <input type="text" name="lname" placeholder="Dela Cruz" required>
                </div>
            </div>

            <div class="input-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" required>
            </div>
            <div style="display: flex; gap: 15px;">
                <div class="input-group" style="flex: 1;">
                    <label>University ID</label>
                    <input type="text" name="university_id" placeholder="12-3456-78" required>
                </div>
                <div class="input-group" style="flex: 1;">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="name@citu.edu" required>
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a strong password" required>
            </div>

            <div class="input-group">
                <label>Account Type</label>
                <select name="user_type" id="userTypeSelect" onchange="showRoleFields()" required>
                    <option value="">-- Select Your Role --</option>
                    <option value="Patient">Student / Patient</option>
                    <option value="Admin">Clinic Administrator</option>
                    <option value="Provider">Healthcare Provider</option>
                </select>
            </div>

            <!-- Dynamic Patient Fields -->
            <div id="patientFields" class="role-fields">
                <div class="input-group">
                    <label>Emergency Contact</label>
                    <input type="text" name="emergency_contact" placeholder="Name & Phone Number">
                </div>
                <div class="input-group">
                    <label>Medical History Summary</label>
                    <input type="text" name="medical_history" placeholder="Any known allergies or conditions?">
                </div>
            </div>

            <!-- Dynamic Admin Fields -->
            <div id="adminFields" class="role-fields">
                <div class="input-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="e.g., IT Support, Records">
                </div>
                <div class="input-group">
                    <label>Access Level</label>
                    <select name="access_level">
                        <option value="Standard">Standard</option>
                        <option value="Superadmin">Superadmin</option>
                    </select>
                </div>
            </div>

            <!-- Dynamic Provider Fields -->
            <div id="providerFields" class="role-fields">
                <div class="input-group">
                    <label>License Number</label>
                    <input type="text" name="license_no" placeholder="PRC License No.">
                </div>
                <div style="display: flex; gap: 15px;">
                    <div class="input-group" style="flex: 1;">
                        <label>Specialization</label>
                        <input type="text" name="specialization" placeholder="e.g., General Medicine">
                    </div>
                    <div class="input-group" style="flex: 1;">
                        <label>Room Number</label>
                        <input type="text" name="room_number" placeholder="e.g., Rm 102">
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn">Create Account</button>
        </form>
    </div>
</div>

<!-- Import External JS -->
<script src="js/auth.js"></script>
</body>
</html>