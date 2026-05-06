<?php
session_start();
require_once 'db_connection.php';
require('fpdf/fpdf.php'); // Bring in the PDF engine

// --- SECURITY CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    die("Unauthorized Access.");
}

$current_user_id = $_SESSION['user_id'];
$target_patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;

if (!$target_patient_id) {
    die("Error: No Patient ID provided.");
}

try {
    // 1. Get Admin ID for the Audit Log
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $current_user_id]);
    $admin_id = $stmt->fetchColumn();

    // 2. Fetch Patient Details
    $sql_patient = "SELECT u.fname, u.lname, u.university_id, u.date_of_birth, p.emergency_contact, p.medical_history_summary 
                    FROM patients p 
                    JOIN users u ON p.user_id = u.user_id 
                    WHERE p.patient_id = :pid";
    $stmt_patient = $pdo->prepare($sql_patient);
    $stmt_patient->execute([':pid' => $target_patient_id]);
    $patient = $stmt_patient->fetch();

    if (!$patient) die("Patient not found.");

    // 3. Fetch All Medical Records for this Patient
    $sql_records = "SELECT mr.symptoms, mr.diagnosis, mr.prescription_notes, mr.created_at, 
                           pru.lname AS doctor_lname
                    FROM medical_records mr
                    JOIN providers pr ON mr.provider_id = pr.provider_id
                    JOIN users pru ON pr.user_id = pru.user_id
                    WHERE mr.patient_id = :pid
                    ORDER BY mr.created_at DESC";
    $stmt_records = $pdo->prepare($sql_records);
    $stmt_records->execute([':pid' => $target_patient_id]);
    $records = $stmt_records->fetchAll();

    // 4. Log the Export Action (HIPAA/Data Privacy Compliance)
    $action_desc = "Exported Official Medical History PDF for Patient ID: " . $target_patient_id;
    $log_sql = "INSERT INTO audit_logs (action_performed, target_table, target_record_id, admin_id) 
                VALUES (:action, 'medical_records', :target_id, :admin_id)";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([':action' => $action_desc, ':target_id' => $target_patient_id, ':admin_id' => $admin_id]);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --- START PDF GENERATION ---

// Create a custom class to build the Header and Footer
class PDF extends FPDF {
    function Header() {
        // CIT-U Dark Blue color for title
        $this->SetTextColor(10, 25, 47);
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, 'CIT-U CLINIC SYSTEM', 0, 1, 'C');

        // Gold subtitle
        $this->SetTextColor(212, 175, 55);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'OFFICIAL MEDICAL HISTORY REPORT', 0, 1, 'C');

        // Line break
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' | Generated securely by CIT-U Clinic System', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();

// --- PATIENT INFORMATION SECTION ---
$pdf->SetFillColor(10, 25, 47); // Dark Blue background
$pdf->SetTextColor(255, 255, 255); // White text
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, ' PATIENT DETAILS', 0, 1, 'L', true);

$pdf->SetTextColor(0, 0, 0); // Black text
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 8, 'Name:', 0, 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, $patient['lname'] . ', ' . $patient['fname'], 0, 1);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 8, 'University ID:', 0, 0);
$pdf->Cell(0, 8, $patient['university_id'], 0, 1);

$pdf->Cell(40, 8, 'Date of Birth:', 0, 0);
$pdf->Cell(0, 8, $patient['date_of_birth'], 0, 1);

$pdf->Cell(40, 8, 'Known History:', 0, 0);
$pdf->MultiCell(0, 8, $patient['medical_history_summary'] ?: 'None specified');
$pdf->Ln(5);

// --- MEDICAL RECORDS LOOP ---
$pdf->SetFillColor(212, 175, 55); // Gold background
$pdf->SetTextColor(10, 25, 47); // Dark blue text
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, ' CLINICAL VISIT HISTORY', 0, 1, 'L', true);
$pdf->Ln(5);

$pdf->SetTextColor(0, 0, 0);

if (count($records) > 0) {
    foreach ($records as $record) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(240, 240, 240);

        // Date & Doctor Header
        $visit_date = date('F d, Y', strtotime($record['created_at']));
        $pdf->Cell(0, 8, " Visit Date: " . $visit_date . " | Attending: Dr. " . $record['doctor_lname'], 1, 1, 'L', true);

        // Details
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(30, 8, 'Symptoms:', 'L', 0);
        $pdf->MultiCell(0, 8, $record['symptoms'], 'R');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 8, 'Diagnosis:', 'L', 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 8, $record['diagnosis'], 'R');

        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(30, 8, 'Prescription:', 'LB', 0);
        $pdf->MultiCell(0, 8, $record['prescription_notes'], 'RB');
        $pdf->Ln(5);
    }
} else {
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 10, 'No clinical records found for this patient.', 0, 1, 'C');
}

// 5. Output the PDF as a Download
$filename = "CITU_Medical_Record_" . $patient['university_id'] . ".pdf";
$pdf->Output('D', $filename);
exit();
?>