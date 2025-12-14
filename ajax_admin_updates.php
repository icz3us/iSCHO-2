<?php
// ajax_admin_updates.php - AJAX endpoints for real-time updates in admin dashboard

require './connect/connection.php';
require './route_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Superadmin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle different AJAX requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_notices':
            getNotices($pdo, $user_id);
            break;
            
        case 'get_applicant_status':
            if (isset($_GET['applicant_id'])) {
                getApplicantStatus($pdo, $_GET['applicant_id']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Applicant ID required']);
            }
            break;
            
        case 'send_notice':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                sendNotice($pdo, $user_id, $_POST);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
}

/**
 * Get notices/announcements for admin
 */
function getNotices($pdo, $user_id) {
    try {
        // For admin/superadmin, get all notices they've created
        $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.message,
                n.image_path,
                n.created_at,
                n.updated_at,
                CONCAT(u.firstname, ' ', u.lastname) as creator_name
            FROM notices n
            JOIN users u ON n.user_id = u.id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'notices' => $notices,
            'notice_count' => count($notices)
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get applicant status (for admin to monitor specific applicants)
 */
function getApplicantStatus($pdo, $applicant_id) {
    try {
        // Fetch applicant status
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.email,
                ui.application_status,
                ui.claim_status,
                ui.created_at as application_date
            FROM users u
            JOIN users_info ui ON u.id = ui.user_id
            WHERE u.id = ? AND u.role = 'Applicant'
        ");
        $stmt->execute([$applicant_id]);
        $applicant_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($applicant_data) {
            // Set default values if null
            $application_status = is_null($applicant_data['application_status']) ? 'Not Yet Submitted' : $applicant_data['application_status'];
            $claim_status = isset($applicant_data['claim_status']) && $applicant_data['claim_status'] === 'Claimed' ? 'Claimed' : 'Not Claimed';
            
            echo json_encode([
                'success' => true,
                'applicant' => [
                    'id' => $applicant_data['id'],
                    'name' => $applicant_data['firstname'] . ' ' . $applicant_data['lastname'],
                    'email' => $applicant_data['email'],
                    'application_status' => $application_status,
                    'claim_status' => $claim_status,
                    'application_date' => $applicant_data['application_date']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Applicant not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Send notice to an applicant
 */
function sendNotice($pdo, $user_id, $postData) {
    try {
        $applicant_id = trim($postData['applicant_id']);
        $message = trim($postData['message']);
        
        if (empty($applicant_id) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Applicant ID and message are required']);
            return;
        }
        
        // Insert notice into notices table
        $stmt = $pdo->prepare("INSERT INTO notices (user_id, message) VALUES (?, ?)");
        $stmt->execute([$applicant_id, $message]);
        
        // Get the inserted notice for return
        $notice_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
        $stmt->execute([$notice_id]);
        $notice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Notice sent successfully!',
            'notice' => $notice
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

?>