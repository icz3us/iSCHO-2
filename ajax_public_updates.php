<?php
// ajax_public_updates.php - AJAX endpoints for public real-time updates
// This file is for publicly accessible AJAX endpoints (no authentication required)

require './connect/connection.php';

header('Content-Type: application/json');

/**
 * Get announcements for public viewing
 */
function getPublicAnnouncements($pdo) {
    try {
        // Fetch all announcements from superadmins
        $stmt = $pdo->prepare("SELECT n.message, n.created_at, n.image_path, u.firstname, u.lastname FROM notices n JOIN users u ON n.user_id = u.id WHERE UPPER(u.role) = 'SUPERADMIN' ORDER BY n.created_at DESC");
        $stmt->execute();
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'announcements' => $announcements
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_announcements':
            getPublicAnnouncements($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
}
?>