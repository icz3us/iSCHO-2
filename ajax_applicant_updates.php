<?php
// ajax_applicant_updates.php - AJAX endpoints for real-time updates in applicant dashboard

require './connect/connection.php';
require './route_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Applicant') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Log AJAX requests for debugging
error_log("AJAX request: action=" . ($_GET['action'] ?? 'none') . ", user_id=" . $user_id);

// Handle different AJAX requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_status':
            getApplicationAndClaimStatus($pdo, $user_id);
            break;
            
        case 'get_notices':
            getNotices($pdo, $user_id);
            break;
            
        case 'get_announcements':
            getAnnouncements($pdo, $user_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
}

/**
 * Get application and claim status
 */
function getApplicationAndClaimStatus($pdo, $user_id) {
    try {
        // Fetch application status
        $stmt = $pdo->prepare("
            SELECT 
                ui.application_status,
                ui.claim_status,
                ui.claimed_at,
                ud.claim_photo_path
            FROM users_info ui
            LEFT JOIN user_docs ud ON ui.user_id = ud.user_id
            WHERE ui.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $status_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($status_data) {
            // Set default values if null
            $application_status = is_null($status_data['application_status']) ? 'Not Yet Submitted' : $status_data['application_status'];
            $claim_status = isset($status_data['claim_status']) && $status_data['claim_status'] === 'Claimed' ? 'Claimed' : 'Not Claimed';
            $claim_photo_path = !empty($status_data['claim_photo_path']) ? $status_data['claim_photo_path'] : '';
            $claimed_at = !empty($status_data['claimed_at']) ? $status_data['claimed_at'] : null;
            
            echo json_encode([
                'success' => true,
                'application_status' => $application_status,
                'claim_status' => $claim_status,
                'claim_photo_path' => $claim_photo_path,
                'claimed_at' => $claimed_at
            ]);
        } else {
            // If no application data found, return default values
            echo json_encode([
                'success' => true,
                'application_status' => 'Not Yet Submitted',
                'claim_status' => 'Not Claimed',
                'claim_photo_path' => '',
                'claimed_at' => null
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get notices/announcements
 */
function getNotices($pdo, $user_id) {
    try {
        // Debug: Log the user ID we're looking for
        error_log("Fetching data for user_id: $user_id");
        
        // Fetch personal notices for this user
        $stmt = $pdo->prepare("
            SELECT message, created_at, image_path 
            FROM notices 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        $personal_notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log personal notices for debugging
        error_log("Personal notices for user $user_id: " . count($personal_notices));
        if (count($personal_notices) > 0) {
            error_log("First personal notice: " . substr($personal_notices[0]['message'], 0, 50) . "...");
        }
        
        // Fetch announcements from superadmins
        $stmt = $pdo->prepare("
            SELECT n.message, n.created_at, n.image_path 
            FROM notices n 
            JOIN users u ON n.user_id = u.id 
            WHERE UPPER(u.role) = 'SUPERADMIN' 
            ORDER BY n.created_at DESC
        ");
        $stmt->execute();
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log announcements for debugging
        error_log("Announcements found: " . count($announcements));
        if (count($announcements) > 0) {
            error_log("First announcement: " . substr($announcements[0]['message'], 0, 50) . "...");
        }
        
        // Combine notices and announcements
        $all_notices = array_merge($personal_notices, $announcements);
        
        // Sort by creation date (newest first)
        usort($all_notices, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Log total notices for debugging
        error_log("Total notices/announcements for user $user_id: " . count($all_notices));
        
        echo json_encode([
            'success' => true,
            'notices' => $all_notices,
            'notice_count' => count($all_notices)
        ]);
    } catch (PDOException $e) {
        error_log("Database error in getNotices: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get announcements for applicants
 */
function getAnnouncements($pdo, $user_id) {
    try {
        // Debug: Log the user ID we're looking for
        error_log("Fetching announcements for user_id: $user_id");
        
        // Fetch all announcements from superadmins
        $stmt = $pdo->prepare("SELECT n.message, n.created_at, n.image_path, u.firstname, u.lastname FROM notices n JOIN users u ON n.user_id = u.id WHERE UPPER(u.role) = 'SUPERADMIN' ORDER BY n.created_at DESC");
        $stmt->execute();
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log announcements for debugging
        error_log("Announcements returned to user $user_id: " . count($announcements));
        if (count($announcements) > 0) {
            error_log("First announcement: " . substr($announcements[0]['message'], 0, 50) . "...");
        }
        
        echo json_encode([
            'success' => true,
            'announcements' => $announcements
        ]);
    } catch (PDOException $e) {
        error_log("Database error in getAnnouncements: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

?>