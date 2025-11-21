<?php
require_once 'vendor/autoload.php';

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

class PredictiveAnalytics {
    private $pdo;
    
    public function __construct($pdo) {
        // Validate that we have a valid PDO connection
        if (!$pdo instanceof PDO) {
            throw new Exception('Invalid database connection provided to PredictiveAnalytics');
        }
        $this->pdo = $pdo;
    }
    
    /**
     * Analyze scholarship trends by municipality
     */
    public function analyzeScholarshipTrends() {
        try {
            // Validate database connection
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection not available'
                ];
            }
            
            // Fetch data by municipality
            $stmt = $this->pdo->prepare("
                SELECT 
                    ui.municipality,
                    COUNT(*) as total_applicants,
                    SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as total_approved,
                    SUM(CASE WHEN ui.application_status = 'Denied' THEN 1 ELSE 0 END) as total_denied
                FROM users_info ui
                JOIN users u ON ui.user_id = u.id
                WHERE u.role = 'Applicant' AND ui.municipality IS NOT NULL AND ui.municipality != ''
                GROUP BY ui.municipality
                ORDER BY total_applicants DESC
            ");
            $stmt->execute();
            $municipalityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [
                'success' => true,
                'data' => [],
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            foreach ($municipalityData as $row) {
                $total = (int)$row['total_applicants'];
                $approved = (int)$row['total_approved'];
                $approvalRate = $total > 0 ? round(($approved / $total) * 100, 2) : 0;
                
                // Simple trend analysis (comparing to average)
                $result['data'][$row['municipality']] = [
                    'total_applicants' => $total,
                    'total_approved' => $approved,
                    'total_denied' => (int)$row['total_denied'],
                    'approval_rate' => $approvalRate,
                    'growth_trend' => $approvalRate > 70 ? 'positive' : ($approvalRate < 30 ? 'negative' : 'neutral')
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("PredictiveAnalytics Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to analyze scholarship trends: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Predict applicant success based on various factors
     */
    public function predictApplicantSuccess($applicantId = null) {
        try {
            // Validate database connection
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection not available'
                ];
            }
            
            $result = [
                'success' => true,
                'total_applicants' => 0,
                'approved_applicants' => 0,
                'overall_success_rate' => 0,
                'success_factors' => [
                    'municipality' => [],
                    'course' => [],
                    'civil_status' => []
                ],
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            // Get overall statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
            ");
            $stmt->execute();
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $result['total_applicants'] = (int)$overall['total'];
            $result['approved_applicants'] = (int)$overall['approved'];
            $result['overall_success_rate'] = $overall['total'] > 0 ? 
                round(($overall['approved'] / $overall['total']) * 100, 2) : 0;
            
            // Municipality success rates
            $stmt = $this->pdo->prepare("
                SELECT 
                    municipality,
                    COUNT(*) as total,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
                WHERE municipality IS NOT NULL AND municipality != ''
                GROUP BY municipality
            ");
            $stmt->execute();
            $municipalities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($municipalities as $row) {
                $successRate = $row['total'] > 0 ? 
                    round(($row['approved'] / $row['total']) * 100, 2) : 0;
                    
                $result['success_factors']['municipality'][$row['municipality']] = [
                    'total' => (int)$row['total'],
                    'approved' => (int)$row['approved'],
                    'success_rate' => $successRate
                ];
            }
            
            // Course success rates
            $stmt = $this->pdo->prepare("
                SELECT 
                    up.course,
                    COUNT(*) as total,
                    SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM user_personal up
                JOIN users_info ui ON up.user_id = ui.user_id
                WHERE up.course IS NOT NULL AND up.course != ''
                GROUP BY up.course
            ");
            $stmt->execute();
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($courses as $row) {
                $successRate = $row['total'] > 0 ? 
                    round(($row['approved'] / $row['total']) * 100, 2) : 0;
                    
                $result['success_factors']['course'][$row['course']] = [
                    'total' => (int)$row['total'],
                    'approved' => (int)$row['approved'],
                    'success_rate' => $successRate
                ];
            }
            
            // Civil status success rates
            $stmt = $this->pdo->prepare("
                SELECT 
                    civil_status,
                    COUNT(*) as total,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
                WHERE civil_status IS NOT NULL AND civil_status != ''
                GROUP BY civil_status
            ");
            $stmt->execute();
            $civilStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($civilStatuses as $row) {
                $successRate = $row['total'] > 0 ? 
                    round(($row['approved'] / $row['total']) * 100, 2) : 0;
                    
                $result['success_factors']['civil_status'][$row['civil_status']] = [
                    'total' => (int)$row['total'],
                    'approved' => (int)$row['approved'],
                    'success_rate' => $successRate
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("PredictiveAnalytics Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to predict applicant success: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate AI-powered recommendations based on analytics data using Gemini AI
     */
    public function generateRecommendations() {
        try {
            // Validate database connection
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection not available'
                ];
            }
            
            // Get analytics data to provide context to AI
            $analyticsData = $this->getAnalyticsSummary();
            
            // Generate AI recommendations using Gemini
            $aiRecommendations = $this->generateAIRecommendations($analyticsData);
            
            if ($aiRecommendations['success']) {
                return [
                    'success' => true,
                    'recommendations' => $aiRecommendations['recommendations'],
                    'generated_at' => date('Y-m-d H:i:s')
                ];
            } else {
                // Fallback to basic recommendations if AI fails
                return $this->generateBasicRecommendations();
            }
        } catch (Exception $e) {
            error_log("PredictiveAnalytics Error: " . $e->getMessage());
            // Fallback to basic recommendations if AI fails
            return $this->generateBasicRecommendations();
        }
    }
    
    /**
     * Get a summary of analytics data for AI context
     */
    private function getAnalyticsSummary() {
        try {
            $summary = [];
            
            // Get overall statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN application_status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN application_status = 'Denied' THEN 1 ELSE 0 END) as denied
                FROM users_info
            ");
            $stmt->execute();
            $summary['overall'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get top municipalities by application count
            $stmt = $this->pdo->prepare("
                SELECT 
                    municipality,
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
                WHERE municipality IS NOT NULL AND municipality != ''
                GROUP BY municipality
                ORDER BY total_applications DESC
                LIMIT 5
            ");
            $stmt->execute();
            $summary['top_municipalities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get popular courses
            $stmt = $this->pdo->prepare("
                SELECT 
                    course,
                    COUNT(*) as total_applications
                FROM user_personal
                WHERE course IS NOT NULL AND course != ''
                GROUP BY course
                ORDER BY total_applications DESC
                LIMIT 5
            ");
            $stmt->execute();
            $summary['popular_courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $summary;
        } catch (Exception $e) {
            error_log("Analytics Summary Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate AI recommendations using Gemini API
     */
    private function generateAIRecommendations($analyticsData) {
        try {
            $apiKey = 'AIzaSyAFROmTOC9U9JGwiR7YZUyYtoK3bRSJhhg';
            
            if (empty($apiKey)) {
                throw new Exception('Gemini API key not configured');
            }
            
            // Format the analytics data for the AI prompt
            $formattedData = $this->formatAnalyticsDataForPrompt($analyticsData);
            
            // Prepare the prompt for Gemini AI
            $prompt = "You are an expert educational policy advisor for a scholarship program in the Philippines (Zambales and Bataan provinces). 
            Based on the following analytics data, provide 5 specific, actionable recommendations to improve the scholarship program:
            
            Analytics Data:
            $formattedData
            
            Please provide recommendations in this exact format:
            1. [First recommendation]
            2. [Second recommendation]
            3. [Third recommendation]
            4. [Fourth recommendation]
            5. [Fifth recommendation]
            
            Each recommendation should be specific, practical, and directly address patterns or issues in the data. 
            Focus on improving approval rates, addressing disparities, optimizing resource allocation, or enhancing the application process.
            Do not include any markdown formatting or numbering prefixes in your response - just the plain text recommendations.";

            // Initialize the Gemini client
            $client = new Client($apiKey);
            
            // Select the model (using gemini-2.5-flash)
            $model = $client->generativeModel('gemini-2.5-flash');
            
            // Generate content using the model
            $response = $model->generateContent(new TextPart($prompt));
            
            // Extract the AI response
            if (isset($response->candidates[0]->content->parts[0]->text)) {
                $aiResponse = $response->candidates[0]->content->parts[0]->text;
                
                // Parse the recommendations (split by line breaks)
                $recommendations = array_filter(array_map('trim', explode("\n", $aiResponse)));
                
                // Clean up the recommendations (remove any numbering or prefixes)
                $cleanRecommendations = [];
                foreach ($recommendations as $rec) {
                    // Remove any numbering or prefixes
                    $cleanRec = preg_replace('/^\d+\.\s*/', '', $rec);
                    if (!empty($cleanRec)) {
                        $cleanRecommendations[] = $cleanRec;
                    }
                }
                
                return [
                    'success' => true,
                    'recommendations' => array_slice($cleanRecommendations, 0, 5) // Limit to 5 recommendations
                ];
            } else {
                throw new Exception('No valid response from Gemini API');
            }
        } catch (Exception $e) {
            error_log("Gemini API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate AI recommendations: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Format analytics data for the AI prompt
     */
    private function formatAnalyticsDataForPrompt($analyticsData) {
        $output = "";
        
        if (isset($analyticsData['overall'])) {
            $overall = $analyticsData['overall'];
            $approvalRate = $overall['total_applications'] > 0 ? 
                round(($overall['approved'] / $overall['total_applications']) * 100, 2) : 0;
                
            $output .= "Overall Statistics:\n";
            $output .= "- Total Applications: {$overall['total_applications']}\n";
            $output .= "- Approved: {$overall['approved']}\n";
            $output .= "- Pending: {$overall['pending']}\n";
            $output .= "- Denied: {$overall['denied']}\n";
            $output .= "- Approval Rate: {$approvalRate}%\n\n";
        }
        
        if (isset($analyticsData['top_municipalities']) && !empty($analyticsData['top_municipalities'])) {
            $output .= "Top Municipalities by Application Volume:\n";
            foreach ($analyticsData['top_municipalities'] as $municipality) {
                $approvalRate = $municipality['total_applications'] > 0 ? 
                    round(($municipality['approved'] / $municipality['total_applications']) * 100, 2) : 0;
                    
                $output .= "- {$municipality['municipality']}: {$municipality['total_applications']} applications ({$approvalRate}% approval)\n";
            }
            $output .= "\n";
        }
        
        if (isset($analyticsData['popular_courses']) && !empty($analyticsData['popular_courses'])) {
            $output .= "Most Popular Courses:\n";
            foreach ($analyticsData['popular_courses'] as $course) {
                $output .= "- {$course['course']}: {$course['total_applications']} applicants\n";
            }
            $output .= "\n";
        }
        
        return $output;
    }
    
    /**
     * Generate basic recommendations as fallback
     */
    private function generateBasicRecommendations() {
        $recommendations = [
            "Review application criteria to ensure fairness and accessibility",
            "Implement targeted outreach programs in underrepresented municipalities",
            "Provide additional support for applicants from courses with lower success rates",
            "Optimize scholarship distribution based on application trends",
            "Establish mentorship programs to assist new applicants"
        ];
        
        return [
            'success' => true,
            'recommendations' => $recommendations,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

?>