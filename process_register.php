<?php
// Start a session in case we need to pass error/success messages
session_start();

// Bring in the live Aiven connection ($pdo)
require_once 'db_connection.php';

// Check if the form was actually submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Grab the base user data from POST
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $dob = $_POST['date_of_birth'];
    $email = $_POST['email'];

    // Grab the sensitive data safely stored in the SESSION from Step 1
    $university_id = $_SESSION['reg_uid'];
    $user_type = $_SESSION['reg_type'];
    $password_hash = $_SESSION['reg_pass']; // Already hashed in Step 1!

    // SECURITY: Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // 2. Insert into the main USERS table (UPDATED)
        $sql_user = "INSERT INTO users (fname, lname, date_of_birth, university_id, email, password_hash, user_type) 
                     VALUES (:fname, :lname, :dob, :uni_id, :email, :pass, :type)";
        $stmt = $pdo->prepare($sql_user);
        $stmt->execute([
            ':fname' => $fname,
            ':lname' => $lname,
            ':dob' => $dob,
            ':uni_id' => $university_id,
            ':email' => $email,
            ':pass' => $password_hash,
            ':type' => $user_type
        ]);

        // 3. Get the ID of the user we just created (Foreign Key)
        $new_user_id = $pdo->lastInsertId();

        // 4. Insert into the specific ROLE table based on what they chose
        if ($user_type === 'Patient') {

            // Combine the Name and Phone together to fit into your existing DB column!
            $emergency_name = $_POST['emergency_name'] ?? 'Unknown';
            $emergency_phone = $_POST['emergency_phone'] ?? 'No Number';
            $combined_contact = $emergency_name . ' - ' . $emergency_phone;

            $sql_role = "INSERT INTO patients (user_id, emergency_contact, medical_history_summary) 
                         VALUES (:uid, :contact, :history)";
            $stmt = $pdo->prepare($sql_role);
            $stmt->execute([
                ':uid' => $new_user_id,
                ':contact' => $combined_contact,
                ':history' => $_POST['medical_history'] ?? null
            ]);
        }
        elseif ($user_type === 'Admin') {
            $sql_role = "INSERT INTO admins (user_id, department, access_level) 
                         VALUES (:uid, :dept, :access)";
            $stmt = $pdo->prepare($sql_role);
            $stmt->execute([
                ':uid' => $new_user_id,
                ':dept' => $_POST['department'] ?? null,
                ':access' => $_POST['access_level'] ?? 'Standard'
            ]);
        }
        elseif ($user_type === 'Provider') {
            $sql_role = "INSERT INTO providers (user_id, license_no, specialization, room_number) 
                         VALUES (:uid, :license, :spec, :room)";
            $stmt = $pdo->prepare($sql_role);
            $stmt->execute([
                ':uid' => $new_user_id,
                ':license' => $_POST['license_no'] ?? null,
                ':spec' => $_POST['specialization'] ?? null,
                ':room' => $_POST['room_number'] ?? null
            ]);
        }

        // 5. If everything worked perfectly, commit the save!
        $pdo->commit();

        // Success! Send them back to the login screen
        // Clear the registration sessions
        unset($_SESSION['reg_uid'], $_SESSION['reg_type'], $_SESSION['reg_pass']);

        // Success! Send them back to the login screen with a toast
        $_SESSION['success'] = "Registration Complete! You may now sign in.";
        header("Location: index.php");
        exit();

        echo "<p>Account created in Aiven database. <a href='index.php'>Click here to login</a></p>";

    } catch (PDOException $e) {
        // If anything failed, undo the whole process
        $pdo->rollBack();

        // Check for duplicate emails or IDs
        if ($e->getCode() == 23000) {
            die("Error: That University ID or Email is already registered.");
        }
        die("Registration Failed: " . $e->getMessage());
    }
} else {
    // If they tried to visit this file directly without submitting the form
    header("Location: index.php");
    exit();
}
?>