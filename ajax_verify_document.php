<?php
require './route_guard.php';
require_once __DIR__ . '/utils/document_verification.php';

header('Content-Type: application/json');

if ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['action']) || $_POST['action'] !== 'verify_document') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
$document_type = trim($_POST['document_type'] ?? '');
$status = trim($_POST['status'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$rejection_reason = trim($_POST['rejection_reason'] ?? '');

if (!$user_id || !$document_type || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$valid_statuses = ['Verified', 'Rejected', 'Under Review'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification status']);
    exit;
}

try {
    $docVerification = new DocumentVerification($pdo);
    $verified_by = $_SESSION['user_id'];
    
    $result = $docVerification->verifyDocument(
        $user_id,
        $document_type,
        $verified_by,
        $status,
        $notes ?: null,
        $rejection_reason ?: null
    );
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
} catch (Exception $e) {
    error_log("Document Verification Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
