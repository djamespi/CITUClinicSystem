<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Grab the base data from POST (From the setup_profile.php form)
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $dob = $_POST['date_of_birth'];
    $email = $_POST['email'];

    // 2. Grab the sensitive data from SESSION (From the index.php Step 1 form)
    $university_id = $_SESSION['reg_uid'];
    $user_type = $_SESSION['reg_type'];
    $password_hash = $_SESSION['reg_pass']; // This was securely hashed in Step 1

    try {
        $pdo->beginTransaction();

        // 3. Insert into the main USERS table
        // Notice we explicitly set account_status to 'Active' so they can log in immediately!
        $sql_user = "INSERT INTO users (fname, lname, date_of_birth, university_id, email, password_hash, user_type, account_status) 
                     VALUES (:fname, :lname, :dob, :uni_id, :email, :pass, :type, 'Active')";

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

        // Get the ID of the user we just created
        $new_user_id = $pdo->lastInsertId();

        // 4. Insert into the specific ROLE table
        if ($user_type === 'Patient') {
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

        // 5. Commit the save!
        $pdo->commit();

        // 6. Clear the temporary session data
        unset($_SESSION['reg_uid'], $_SESSION['reg_type'], $_SESSION['reg_pass']);

        // 7. Redirect with Success Toast
        $_SESSION['success'] = "Registration Complete! You may now sign in.";
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();

        // Check for duplicate emails or IDs
        if ($e->getCode() == 23000) {
            $_SESSION['error'] = "Error: That Email or University ID is already registered.";
        } else {
            $_SESSION['error'] = "Registration Failed: " . $e->getMessage();
        }
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>