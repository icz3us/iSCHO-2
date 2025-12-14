<?php
/**
 * AJAX Handler for Document Date Validation
 * Validates document dates against scholarship academic year
 */

require './connect/connection.php';
require './route_guard.php';
require './utils/data_validation.php';
require './utils/ocr_service.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : null);

$dataValidation = new DataValidation($pdo);
$ocr = new OCRService($pdo);

switch ($action) {
    case 'validate_document_date':
        validateDocumentDate();
        break;
    
    case 'validate_all_documents':
        validateAllDocuments();
        break;
    
    case 'get_program_academic_year':
        getProgramAcademicYear();
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Validate a single document's date
 */
function validateDocumentDate() {
    global $pdo, $dataValidation, $ocr, $user_id;
    
    try {
        if (!isset($_POST['document_type']) || !isset($_POST['extracted_text'])) {
            throw new Exception('Missing required fields: document_type, extracted_text');
        }
        
        $document_type = sanitize($_POST['document_type']);
        $extracted_text = $_POST['extracted_text']; // Don't trim yet, preserve formatting
        $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        
        // Validate document type
        $valid_types = ['cor', 'cor_file', 'indigency', 'indigency_file', 'voter', 'voter_file'];
        $normalized_type = str_replace('_file', '', $document_type);
        if (!in_array($normalized_type, ['cor', 'indigency', 'voter'])) {
            throw new Exception('Invalid document type');
        }
        
        // Perform validation
        $validation_result = $dataValidation->validateDocumentDate(
            $user_id,
            $normalized_type,
            $extracted_text,
            $program_id
        );
        
        echo json_encode([
            'success' => true,
            'validation' => $validation_result
        ]);
        
    } catch (Exception $e) {
        error_log("Document Validation Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Validate all documents for the user
 */
function validateAllDocuments() {
    global $pdo, $dataValidation, $user_id;
    
    try {
        $result = $dataValidation->validateAllUserDocuments($user_id);
        
        echo json_encode([
            'success' => true,
            'validation' => $result
        ]);
        
    } catch (Exception $e) {
        error_log("Validate All Documents Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get academic year for a program
 */
function getProgramAcademicYear() {
    global $pdo, $dataValidation;
    
    try {
        $program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
        
        if (!$program_id) {
            // Try to get from user's program
            if (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT program_id FROM users_info WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $program_id = $result['program_id'] ?? null;
            }
        }
        
        if (!$program_id) {
            echo json_encode([
                'success' => false,
                'message' => 'No program ID provided'
            ]);
            return;
        }
        
        $program = $dataValidation->getProgramAcademicYear($program_id);
        
        if ($program) {
            echo json_encode([
                'success' => true,
                'program' => $program
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Program not found'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Get Program Academic Year Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

?>
