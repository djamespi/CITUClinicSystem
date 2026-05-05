<?php
// Start a session in case we need to pass error/success messages
session_start();

// Bring in the live Aiven connection ($pdo)
require_once 'db_connection.php';

// Check if the form was actually submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Grab the base user data (UPDATED)
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $dob = $_POST['date_of_birth'];
    $university_id = $_POST['university_id'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

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
            $sql_role = "INSERT INTO patients (user_id, emergency_contact, medical_history_summary) 
                         VALUES (:uid, :contact, :history)";
            $stmt = $pdo->prepare($sql_role);
            $stmt->execute([
                ':uid' => $new_user_id,
                ':contact' => $_POST['emergency_contact'] ?? null,
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
        echo "<h3>Registration Successful!</h3>";
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