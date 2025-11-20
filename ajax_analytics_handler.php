<?php
/**
 * AJAX Handler for Predictive Analytics
 * Provides endpoints for analytics data and predictions
 */

require './route_guard.php';
require './utils/predictive_analytics.php';

header('Content-Type: application/json');

// Only allow Superadmin access
if ($_SESSION['user_role'] !== 'Superadmin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    $analytics = new PredictiveAnalytics($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'get_scholarship_trends':
                $result = $analytics->analyzeScholarshipTrends();
                echo json_encode($result);
                break;
                
            case 'get_applicant_predictions':
                $applicantId = $_POST['applicant_id'] ?? null;
                $result = $analytics->predictApplicantSuccess($applicantId);
                echo json_encode($result);
                break;
                
            case 'get_recommendations':
                $result = $analytics->generateRecommendations();
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                break;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>