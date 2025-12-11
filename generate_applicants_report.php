<?php
// Start output buffering immediately to prevent any stray output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors as it will corrupt PDF
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/pdf_errors.log');

// Suppress any output from required files by using nested buffer
ob_start();
try {
    require './route_guard.php';
    require 'connect/connection.php';
} catch (Exception $e) {
    ob_end_clean();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Error loading required files: " . $e->getMessage());
    http_response_code(500);
    exit('Error loading required files');
}
// Clean any output from required files
$required_output = ob_get_clean();
if (!empty(trim($required_output))) {
    error_log("Warning: Output detected from required files: " . substr($required_output, 0, 100));
    // If there was output, we need to clean it to prevent PDF corruption
    // But if it's a fatal error (like database connection), the script would have died already
}

if ($_SESSION['user_role'] !== 'Admin') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(403);
    exit('Unauthorized');
}

// Get admin's program_id
$admin_program_id = $_SESSION['program_id'] ?? null;

if (!$admin_program_id) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(400);
    exit('No program assigned to admin');
}

// Get program name and application period dates
$program_name = 'Unknown Program';
$application_start_date = 'Not Set';
$application_end_date = 'Not Set';
try {
    $stmt = $pdo->prepare("SELECT program_name, application_start_date, application_end_date FROM scholarship_programs WHERE id = ?");
    $stmt->execute([$admin_program_id]);
    $program_result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($program_result) {
        $program_name = $program_result['program_name'];
        if (!empty($program_result['application_start_date'])) {
            $application_start_date = date('m/d/Y', strtotime($program_result['application_start_date']));
        }
        if (!empty($program_result['application_end_date'])) {
            $application_end_date = date('m/d/Y', strtotime($program_result['application_end_date']));
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching program name and dates: " . $e->getMessage());
}

// Format application period string
$application_period = 'Not Set';
if ($application_start_date !== 'Not Set' && $application_end_date !== 'Not Set') {
    $application_period = $application_start_date . ' - ' . $application_end_date;
} elseif ($application_start_date !== 'Not Set') {
    $application_period = 'From: ' . $application_start_date;
} elseif ($application_end_date !== 'Not Set') {
    $application_period = 'Until: ' . $application_end_date;
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
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    exit('Error fetching applicants');
}

if (empty($applicants)) {
    while (ob_get_level()) {
        ob_end_clean();
    }
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
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    exit('FPDF library not found. Please install FPDF manually.');
}

// Suppress any output from FPDF require
ob_start();
require($fpdf_path);
ob_end_clean();

// Create PDF
class PDF extends FPDF {
    function Header() {
        global $program_name;
        
        // Dark slate header bar (same as footer)
        $this->SetFillColor(30, 41, 59); // Dark slate
        $this->Rect(0, 0, $this->GetPageWidth(), 18, 'F');
        
        // White text on dark background
        // Application name - smaller and less prominent
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', '', 8);
        $this->SetXY(10, 2);
        $this->Cell(0, 4, 'iSCHO SCHOLARSHIP APPLICATION SYSTEM', 0, 1, 'C');
        
        // Main title - Applicants Report with Program name - emphasized
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(10, 7);
        $this->Cell(0, 6, 'APPLICANTS REPORT - ' . strtoupper($program_name), 0, 1, 'C');
        
        $this->Ln(5);
    }
    
    function Footer() {
        global $admin_name, $application_period;
        
        // Report Info near footer (before the dark footer bar)
        $this->SetY(-30);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 7);
        
        $pageWidth = $this->GetPageWidth();
        $currentY = $this->GetY();
        
        // Left side: Generated By
        $this->SetXY(10, $currentY);
        $this->Cell(90, 4, 'Generated By: ' . $admin_name, 0, 0, 'L');
        
        // Right side: Generated On
        $this->SetXY($pageWidth - 100, $currentY);
        $this->Cell(90, 4, 'Generated On: ' . date('F d, Y'), 0, 1, 'R');
        
        // Left side: Generated At
        $currentY = $this->GetY();
        $this->SetXY(10, $currentY);
        $this->Cell(90, 4, 'Generated At: ' . date('h:i A'), 0, 0, 'L');
        
        // Right side: Application Period
        $this->SetXY($pageWidth - 100, $currentY);
        $this->Cell(90, 4, 'Application Period: ' . $application_period, 0, 1, 'R');
        
        // Dark footer bar
        $this->SetY(-12);
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
        
        // Center: Page number with total pages
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($pageWidth / 2 - 30, $this->GetPageHeight() - 7);
        $this->Cell(60, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        
        // Right: Copyright
        $this->SetFont('Arial', '', 7);
        $this->SetXY($pageWidth - 80, $this->GetPageHeight() - 7);
        $this->Cell(70, 4, 'Copyright ' . date('Y') . ' iSCHO. All rights reserved.', 0, 0, 'R');
    }
}

$pdf = new PDF('P', 'mm', 'Letter'); // Portrait
$pdf->SetMargins(10, 22, 10); // Increased top margin to accommodate larger header
$pdf->SetAutoPageBreak(true, 30); // Increased bottom margin to accommodate report info
$pdf->AliasNbPages(); // Enable total page count
$pdf->AddPage();

// Applicants List Title
$pdf->Ln(4); // Space after header
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 7, 'Applicants List', 0, 1, 'C');
$pdf->Ln(4); // Space before table

// Calculate table widths for portrait mode (adjusted for narrower page)
$pageWidth = $pdf->GetPageWidth();
$leftMargin = 10;
$rightMargin = 10;
$usableWidth = $pageWidth - $leftMargin - $rightMargin; // ~196mm for Letter portrait

// Adjusted column widths for portrait mode (total must fit in ~196mm)
// Letter portrait: 216mm - 20mm margins = 196mm usable width
$colWidths = [
    'num' => 8,       // For "TOTAL" and numbers
    'name' => 25,
    'email' => 33,    // Increased for full email addresses
    'contact' => 17,
    'gender' => 11,
    'municipality' => 20,
    'college' => 23,  // Increased for college names
    'course' => 23,   // Increased for course names
    'status' => 15,
    'date' => 18      // Increased for full date format
];
$tableWidth = array_sum($colWidths); // Total: 197mm (fits within usable width)
$tableStartX = $leftMargin;

// Table Header
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 7);

$pdf->SetX($tableStartX);
$pdf->Cell($colWidths['num'], 8, '#', 1, 0, 'C', true);
$pdf->Cell($colWidths['name'], 8, 'Full Name', 1, 0, 'L', true);
$pdf->Cell($colWidths['email'], 8, 'Email', 1, 0, 'L', true);
$pdf->Cell($colWidths['contact'], 8, 'Contact', 1, 0, 'L', true);
$pdf->Cell($colWidths['gender'], 8, 'Gender', 1, 0, 'L', true);
$pdf->Cell($colWidths['municipality'], 8, 'Municipality', 1, 0, 'L', true);
$pdf->Cell($colWidths['college'], 8, 'College', 1, 0, 'L', true);
$pdf->Cell($colWidths['course'], 8, 'Course', 1, 0, 'L', true);
$pdf->Cell($colWidths['status'], 8, 'Status', 1, 0, 'L', true);
$pdf->Cell($colWidths['date'], 8, 'Date Applied', 1, 1, 'L', true);

// Table Data
$pdf->SetFont('Arial', '', 6);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

foreach ($applicants as $index => $applicant) {
    // Full name
    $fullName = trim(($applicant['lastname'] ?? '') . ', ' . ($applicant['firstname'] ?? '') . ' ' . ($applicant['middlename'] ?? ''));
    if (empty($fullName) || $fullName === ',') {
        $fullName = 'N/A';
    }
    
    // Status
    $status = $applicant['application_status'] ?? 'N/A';
    
    // Date formatting
    $dateApplied = 'N/A';
    if (!empty($applicant['created_at'])) {
        $dateApplied = date('M d, Y', strtotime($applicant['created_at']));
    }
    
    $pdf->SetX($tableStartX);
    $pdf->Cell($colWidths['num'], 6, ($index + 1), 1, 0, 'C', true);
    // At font size 6, Arial is approximately 1.2-1.5mm per character
    // Use more generous multipliers to allow more text to display
    $pdf->Cell($colWidths['name'], 6, substr($fullName, 0, floor($colWidths['name'] * 0.7)), 1, 0, 'L', true);
    $pdf->Cell($colWidths['email'], 6, substr($applicant['email'] ?? 'N/A', 0, floor($colWidths['email'] * 0.7)), 1, 0, 'L', true);
    $pdf->Cell($colWidths['contact'], 6, substr($applicant['contact_no'] ?? 'N/A', 0, floor($colWidths['contact'] * 0.8)), 1, 0, 'L', true);
    $pdf->Cell($colWidths['gender'], 6, ucfirst($applicant['gender'] ?? 'N/A'), 1, 0, 'L', true);
    $pdf->Cell($colWidths['municipality'], 6, substr($applicant['municipality'] ?? 'N/A', 0, floor($colWidths['municipality'] * 0.7)), 1, 0, 'L', true);
    $pdf->Cell($colWidths['college'], 6, substr($applicant['current_college'] ?? 'N/A', 0, floor($colWidths['college'] * 0.7)), 1, 0, 'L', true);
    $pdf->Cell($colWidths['course'], 6, substr($applicant['course'] ?? 'N/A', 0, floor($colWidths['course'] * 0.7)), 1, 0, 'L', true);
    $pdf->Cell($colWidths['status'], 6, $status, 1, 0, 'L', true);
    $pdf->Cell($colWidths['date'], 6, $dateApplied, 1, 1, 'L', true);
}

// Total Row
$pdf->SetFont('Arial', 'B', 6); // Slightly smaller font to ensure "TOTAL" fits
$pdf->SetFillColor(255, 255, 255);
$pdf->SetX($tableStartX);
$pdf->Cell($colWidths['num'], 7, 'TOTAL', 1, 0, 'C', true); // "TOTAL" text in # column
$pdf->Cell($colWidths['name'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['email'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['contact'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['gender'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['municipality'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['college'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['course'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['status'], 7, '', 1, 0, 'L', true);
$pdf->Cell($colWidths['date'], 7, (string)count($applicants), 1, 1, 'C', true); // Total count at end of row

// Output PDF
try {
    // Clean all output buffers to ensure no stray output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $filename = 'iSCHO_Applicants_Report_' . preg_replace('/[^a-z0-9]/i', '_', $program_name) . '_' . date('Y_m_d') . '.pdf';
    
    // Output as download
    $pdf->Output('D', $filename);
    exit;
} catch (Exception $e) {
    // Clean all output buffers before sending error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("PDF Output Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Error generating PDF: ' . $e->getMessage());
}