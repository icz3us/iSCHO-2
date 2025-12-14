<?php
require './route_guard.php';
require_once __DIR__ . '/utils/document_verification.php';

header('Content-Type: application/json');

if ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = intval($_GET['user_id'] ?? 0);
$document_type = trim($_GET['document_type'] ?? '');

if (!$user_id || !$document_type) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $docVerification = new DocumentVerification($pdo);
    $status = $docVerification->getDocumentStatus($user_id, $document_type);
    
    if ($status) {
        echo json_encode(['success' => true, 'status' => $status]);
    } else {
        echo json_encode(['success' => true, 'status' => ['verification_status' => 'Pending']]);
    }
} catch (Exception $e) {
    error_log("Get Document Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
