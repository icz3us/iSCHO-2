<?php
/**
 * AJAX Handler for OCR Text Extraction
 * Processes document images and extracts text using client-side Tesseract.js
 * 
 * No external API calls - purely client-side processing
 * Stores results in database for verification and record-keeping
 */

require './route_guard.php';
require './utils/ocr_service.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Initialize OCR Service
$ocr = new OCRService($pdo);

// Handle different OCR actions
$action = isset($_POST['action']) ? sanitize($_POST['action']) : null;

switch ($action) {
    case 'extract_text':
        handleTextExtraction();
        break;
    
    case 'get_results':
        handleGetResults();
        break;
    
    case 'get_user_results':
        handleGetUserResults();
        break;
    
    case 'validate_document':
        handleValidateDocument();
        break;
    
    case 'get_stats':
        handleGetStats();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

/**
 * Handle text extraction from uploaded document
 * Expects base64 image data and document type
 */
function handleTextExtraction() {
    global $pdo, $ocr;
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        if (!isset($_POST['extracted_text']) || !isset($_POST['document_type']) || !isset($_POST['user_id'])) {
            throw new Exception('Missing required fields: extracted_text, document_type, user_id');
        }
        
        $extracted_text = trim($_POST['extracted_text']);
        $document_type = sanitize($_POST['document_type']);
        $user_id = (int)$_POST['user_id'];
        $confidence = isset($_POST['confidence']) ? (float)$_POST['confidence'] : 0.95;
        $processing_time = isset($_POST['processing_time']) ? (int)$_POST['processing_time'] : 0;
        
        // Validate text extraction
        $validation = $ocr->validateExtractedText($extracted_text, $confidence);
        
        if (empty($extracted_text)) {
            throw new Exception('No text could be extracted from the document. Please try another image.');
        }
        
        // Validate document type
        $valid_types = ['cor', 'indigency', 'voter', 'profile_picture', 'claim_photo'];
        if (!in_array($document_type, $valid_types)) {
            throw new Exception('Invalid document type');
        }
        
        // Verify user exists (admin or superadmin)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('Admin', 'Superadmin')");
        $stmt->execute([$_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Unauthorized: Only admins can process OCR');
        }
        
        // Store OCR results
        $metadata = [
            'confidence' => $confidence,
            'processing_time' => $processing_time,
            'language' => 'eng'
        ];
        
        $store_result = $ocr->storeOCRResult($user_id, $document_type, $extracted_text, $metadata);
        
        if (!$store_result['success']) {
            throw new Exception($store_result['message']);
        }
        
        // Extract document-specific information
        $doc_info = $ocr->extractDocumentInfo($extracted_text, $document_type);
        
        $response = [
            'success' => true,
            'message' => 'Text extraction successful',
            'data' => [
                'extracted_text' => $extracted_text,
                'confidence' => $confidence,
                'validation' => $validation,
                'document_info' => $doc_info,
                'processing_time' => $processing_time
            ]
        ];
        
    } catch (Exception $e) {
        error_log("OCR Extraction Error: " . $e->getMessage());
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Handle retrieving OCR results for a specific document
 */
function handleGetResults() {
    global $pdo, $ocr;
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (!isset($_POST['user_id']) || !isset($_POST['document_type'])) {
            throw new Exception('Missing required fields: user_id, document_type');
        }
        
        $user_id = (int)$_POST['user_id'];
        $document_type = sanitize($_POST['document_type']);
        
        // Verify authorization (admin/superadmin)
        if ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Superadmin') {
            throw new Exception('Unauthorized');
        }
        
        $result = $ocr->getOCRResult($user_id, $document_type);
        
        if (!$result) {
            $response['message'] = 'No OCR results found for this document';
        } else {
            $response['success'] = true;
            $response['data'] = $result;
        }
        
    } catch (Exception $e) {
        error_log("OCR Retrieval Error: " . $e->getMessage());
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Handle retrieving all OCR results for a user
 */
function handleGetUserResults() {
    global $pdo, $ocr;
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (!isset($_POST['user_id'])) {
            throw new Exception('Missing required field: user_id');
        }
        
        $user_id = (int)$_POST['user_id'];
        
        // Verify authorization
        if ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Superadmin') {
            throw new Exception('Unauthorized');
        }
        
        $results = $ocr->getUserOCRResults($user_id);
        
        $response['success'] = true;
        $response['message'] = 'OCR results retrieved successfully';
        $response['data'] = $results;
        
    } catch (Exception $e) {
        error_log("OCR Retrieval Error: " . $e->getMessage());
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Handle document validation
 */
function handleValidateDocument() {
    global $pdo, $ocr;
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (!isset($_POST['extracted_text']) || !isset($_POST['document_type'])) {
            throw new Exception('Missing required fields');
        }
        
        $extracted_text = trim($_POST['extracted_text']);
        $document_type = sanitize($_POST['document_type']);
        $confidence = isset($_POST['confidence']) ? (float)$_POST['confidence'] : 1.0;
        
        $validation = $ocr->validateExtractedText($extracted_text, $confidence);
        $doc_info = $ocr->extractDocumentInfo($extracted_text, $document_type);
        
        $response['success'] = true;
        $response['data'] = [
            'validation' => $validation,
            'document_info' => $doc_info
        ];
        
    } catch (Exception $e) {
        error_log("Document Validation Error: " . $e->getMessage());
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Handle getting OCR statistics
 */
function handleGetStats() {
    global $pdo, $ocr;
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Only superadmin or admin can view stats
        if ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Superadmin') {
            throw new Exception('Unauthorized');
        }
        
        $stats = $ocr->getOCRStats();
        
        $response['success'] = true;
        $response['data'] = $stats;
        
    } catch (Exception $e) {
        error_log("OCR Stats Error: " . $e->getMessage());
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Helper function to sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
