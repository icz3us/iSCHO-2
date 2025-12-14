<?php
// ajax_superadmin_updates.php - AJAX endpoints for real-time updates in superadmin dashboard

require './connect/connection.php';
require './route_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Superadmin') {
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
            
        case 'get_admins':
            getAdmins($pdo);
            break;
            
        case 'get_application_stats':
            getApplicationStats($pdo);
            break;
            
        case 'post_announcement':
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                postAnnouncement($pdo, $user_id, $_POST, $_FILES);
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
 * Get notices/announcements for superadmin
 */
function getNotices($pdo, $user_id) {
    try {
        // For superadmin, get all notices they've created
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
 * Get all admins (for superadmin to monitor)
 */
function getAdmins($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                firstname,
                lastname,
                email,
                contact_no,
                created_at
            FROM users 
            WHERE role = 'Admin'
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'admins' => $admins,
            'admin_count' => count($admins)
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get application statistics
 */
function getApplicationStats($pdo) {
    try {
        // Get total applications
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users_info");
        $stmt->execute();
        $total = $stmt->fetchColumn();
        
        // Get approved applications
        $stmt = $pdo->prepare("SELECT COUNT(*) as approved FROM users_info WHERE application_status = 'Approved'");
        $stmt->execute();
        $approved = $stmt->fetchColumn();
        
        // Get denied applications
        $stmt = $pdo->prepare("SELECT COUNT(*) as denied FROM users_info WHERE application_status = 'Denied'");
        $stmt->execute();
        $denied = $stmt->fetchColumn();
        
        // Get pending applications
        $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM users_info WHERE application_status IS NULL OR application_status = 'Under Review'");
        $stmt->execute();
        $pending = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => $total,
                'approved' => $approved,
                'denied' => $denied,
                'pending' => $pending
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Post a new announcement
 */
function postAnnouncement($pdo, $user_id, $postData, $files) {
    try {
        $message = trim($postData['message']);
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Announcement message cannot be empty']);
            return;
        }
        
        // Handle image upload
        $image_path = null;
        if (isset($files['image']) && $files['image']['error'] == 0) {
            $upload_dir = 'uploads/announcements/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($files['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($files['image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                }
            }
        }
        
        // Insert announcement into notices table
        if ($image_path) {
            $stmt = $pdo->prepare("INSERT INTO notices (user_id, message, image_path) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $message, $image_path]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO notices (user_id, message) VALUES (?, ?)");
            $stmt->execute([$user_id, $message]);
        }
        
        // Get the inserted announcement for return
        $notice_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
        $stmt->execute([$notice_id]);
        $notice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Announcement posted successfully!',
            'notice' => $notice
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

?>