<?php
// Start output buffering to prevent any stray output
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors as it will corrupt PDF
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/pdf_errors.log');

require './route_guard.php';
require 'connect/connection.php';

if ($_SESSION['user_role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// Get admin's program_id
$admin_program_id = $_SESSION['program_id'] ?? null;

if (!$admin_program_id) {
    http_response_code(400);
    exit('No program assigned to admin');
}

// Get program name
$program_name = 'Unknown Program';
try {
    $stmt = $pdo->prepare("SELECT program_name FROM scholarship_programs WHERE id = ?");
    $stmt->execute([$admin_program_id]);
    $program_result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($program_result) {
        $program_name = $program_result['program_name'];
    }
} catch (PDOException $e) {
    error_log("Error fetching program name: " . $e->getMessage());
}

// Get application deadline
$deadline = 'Not Set';
try {
    $stmt = $pdo->prepare("SELECT application_deadline FROM application_period ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $deadline_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $deadline = $deadline_result ? date('m/d/Y', strtotime($deadline_result['application_deadline'])) : 'Not Set';
} catch (PDOException $e) {
    error_log("Error fetching deadline: " . $e->getMessage());
}

// Fetch all applicants for the report
$applicants = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.firstname,
            u.lastname,
            u.middlename,
            u.email,
            u.contact_no,
            ui.application_status,
            ui.municipality,
            ui.sex AS gender,
            up.course,
            up.current_college,
            u.created_at
        FROM users u
        LEFT JOIN users_info ui ON u.id = ui.user_id
        LEFT JOIN user_personal up ON u.id = up.user_id
        WHERE u.role = 'Applicant' 
            AND ui.program_id = ?
        ORDER BY u.lastname ASC, u.firstname ASC
    ");
    $stmt->execute([$admin_program_id]);
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching applicants: " . $e->getMessage());
    http_response_code(500);
    exit('Error fetching applicants');
}

if (empty($applicants)) {
    http_response_code(404);
    exit('No applicants found for this program');
}

// Get admin name
$admin_name = trim(($_SESSION['lastname'] ?? '') . ', ' . ($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['middlename'] ?? ''));
if (empty($admin_name) || $admin_name === ',') {
    $admin_name = 'Admin';
}

// Include FPDF
$fpdf_path = __DIR__ . '/fpdf.php';
if (!file_exists($fpdf_path)) {
    http_response_code(500);
    exit('FPDF library not found. Please install FPDF manually.');
}

require($fpdf_path);

// Create PDF
class PDF extends FPDF {
    function Header() {
        global $program_name;
        
        // Dark slate header bar (same as footer)
        $this->SetFillColor(30, 41, 59); // Dark slate
        $this->Rect(0, 0, $this->GetPageWidth(), 12, 'F');
        
        // White text on dark background
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 16);
        $this->SetXY(10, 3);
        $this->Cell(0, 6, 'iSCHO SCHOLARSHIP APPLICATION SYSTEM', 0, 1, 'C');
        
        // Program name subtitle
        $this->SetFont('Arial', '', 9);
        $this->SetX(10);
        $this->Cell(0, 3, 'Applicants Report - ' . $program_name, 0, 1, 'C');
        
        $this->Ln(2);
    }
    
    function Footer() {
        $this->SetY(-12);
        
        // Dark footer bar
        $this->SetFillColor(30, 41, 59); // Dark slate
        $this->Rect(0, $this->GetPageHeight() - 12, $this->GetPageWidth(), 12, 'F');
        
        $this->SetTextColor(255, 255, 255);
        $pageWidth = $this->GetPageWidth();
        
        // Left: iSCHO Team
        $this->SetFont('Arial', 'B', 8);
        $this->SetXY(10, $this->GetPageHeight() - 9);
        $this->Cell(80, 4, 'iSCHO Team', 0, 0, 'L');
        
        // Left: Email
        $this->SetFont('Arial', '', 7);
        $this->SetXY(10, $this->GetPageHeight() - 5);
        $this->Cell(80, 4, 'Email: ischobsit@gmail.com', 0, 0, 'L');
        
        // Center: Page number
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($pageWidth / 2 - 20, $this->GetPageHeight() - 7);
        $this->Cell(40, 4, 'Page ' . $this->PageNo(), 0, 0, 'C');
        
        // Right: Copyright
        $this->SetFont('Arial', '', 7);
        $this->SetXY($pageWidth - 80, $this->GetPageHeight() - 7);
        $this->Cell(70, 4, 'Copyright ' . date('Y') . ' iSCHO. All rights reserved.', 0, 0, 'R');
    }
}

$pdf = new PDF('L', 'mm', 'Letter'); // Landscape
$pdf->SetMargins(10, 18, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Report Info
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(80, 6, 'Generated By: ' . $admin_name, 0, 0);
$pdf->Cell(80, 6, 'Total Applicants: ' . count($applicants), 0, 0);
$pdf->Cell(0, 6, 'Deadline: ' . $deadline, 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(80, 5, 'Generated On: ' . date('F d, Y'), 0, 0);
$pdf->Cell(80, 5, 'Generated At: ' . date('h:i A'), 0, 1);
$pdf->Ln(3);

// Applicants List Title
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(79, 70, 229);
$pdf->Cell(0, 6, 'Applicants List', 0, 1, 'C');
$pdf->Ln(2);

// Calculate table centering
$tableWidth = 8 + 28 + 32 + 22 + 12 + 25 + 30 + 30 + 20 + 20; // Total: 227mm
$pageWidth = $pdf->GetPageWidth();
$leftMargin = 10; // We set this to 10mm in SetMargins
$rightMargin = 10; // We set this to 10mm in SetMargins
$usableWidth = $pageWidth - $leftMargin - $rightMargin;
$tableStartX = $leftMargin + (($usableWidth - $tableWidth) / 2);

// Table Header
$pdf->SetFillColor(79, 70, 229);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 7);

$pdf->SetX($tableStartX);
$pdf->Cell(8, 8, '#', 1, 0, 'C', true);
$pdf->Cell(28, 8, 'Full Name', 1, 0, 'L', true);
$pdf->Cell(32, 8, 'Email', 1, 0, 'L', true);
$pdf->Cell(22, 8, 'Contact', 1, 0, 'L', true);
$pdf->Cell(12, 8, 'Gender', 1, 0, 'L', true);
$pdf->Cell(25, 8, 'Municipality', 1, 0, 'L', true);
$pdf->Cell(30, 8, 'College', 1, 0, 'L', true);
$pdf->Cell(30, 8, 'Course', 1, 0, 'L', true);
$pdf->Cell(20, 8, 'Status', 1, 0, 'L', true);
$pdf->Cell(20, 8, 'Date Applied', 1, 1, 'L', true);

// Table Data
$pdf->SetFont('Arial', '', 6);
$pdf->SetTextColor(0, 0, 0);

foreach ($applicants as $index => $applicant) {
    // Alternate row colors
    if ($index % 2 == 0) {
        $pdf->SetFillColor(255, 255, 255);
    } else {
        $pdf->SetFillColor(248, 250, 252);
    }
    
    // Full name
    $fullName = trim(($applicant['lastname'] ?? '') . ', ' . ($applicant['firstname'] ?? '') . ' ' . ($applicant['middlename'] ?? ''));
    if (empty($fullName) || $fullName === ',') {
        $fullName = 'N/A';
    }
    
    // Status color
    $status = $applicant['application_status'] ?? 'N/A';
    if ($status === 'Approved') {
        $statusColor = [16, 185, 129];
    } elseif ($status === 'Denied') {
        $statusColor = [239, 68, 68];
    } elseif ($status === 'Under Review') {
        $statusColor = [245, 158, 11];
    } else {
        $statusColor = [100, 116, 139];
    }
    
    // Date formatting
    $dateApplied = 'N/A';
    if (!empty($applicant['created_at'])) {
        $dateApplied = date('M d, Y', strtotime($applicant['created_at']));
    }
    
    $pdf->SetX($tableStartX);
    $pdf->Cell(8, 6, ($index + 1), 1, 0, 'C', true);
    $pdf->Cell(28, 6, substr($fullName, 0, 20), 1, 0, 'L', true);
    $pdf->Cell(32, 6, substr($applicant['email'] ?? 'N/A', 0, 25), 1, 0, 'L', true);
    $pdf->Cell(22, 6, $applicant['contact_no'] ?? 'N/A', 1, 0, 'L', true);
    $pdf->Cell(12, 6, ucfirst($applicant['gender'] ?? 'N/A'), 1, 0, 'L', true);
    $pdf->Cell(25, 6, substr($applicant['municipality'] ?? 'N/A', 0, 18), 1, 0, 'L', true);
    $pdf->Cell(30, 6, substr($applicant['current_college'] ?? 'N/A', 0, 22), 1, 0, 'L', true);
    $pdf->Cell(30, 6, substr($applicant['course'] ?? 'N/A', 0, 22), 1, 0, 'L', true);
    
    // Status with color
    $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Cell(20, 6, $status, 1, 0, 'L', true);
    
    // Reset color
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 6);
    $pdf->Cell(20, 6, $dateApplied, 1, 1, 'L', true);
}

// Output PDF
try {
    // Clean output buffer to ensure no stray output
    if (ob_get_length()) {
        ob_clean();
    }
    
    $filename = 'iSCHO_Applicants_Report_' . preg_replace('/[^a-z0-9]/i', '_', $program_name) . '_' . date('Y_m_d') . '.pdf';
    
    // Output as download
    $pdf->Output('D', $filename);
} catch (Exception $e) {
    error_log("PDF Output Error: " . $e->getMessage());
    http_response_code(500);
    exit('Error generating PDF: ' . $e->getMessage());
}
exit;