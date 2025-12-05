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
     * Analyze scholarship trends by municipality (optionally filtered by program)
     */
    public function analyzeScholarshipTrends($programId = null) {
        try {
            // Validate database connection
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection not available'
                ];
            }
            
            // Build query with optional program filter
            $whereClause = "u.role = 'Applicant' AND ui.municipality IS NOT NULL AND ui.municipality != ''";
            $params = [];
            
            if ($programId !== null) {
                $whereClause .= " AND ui.program_id = ?";
                $params[] = $programId;
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
                WHERE $whereClause
                GROUP BY ui.municipality
                ORDER BY total_applicants DESC
            ");
            $stmt->execute($params);
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
     * Analyze trends by scholarship program
     */
    public function analyzeProgramTrends() {
        try {
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection not available'
                ];
            }
            
            // Fetch data by program
            $stmt = $this->pdo->prepare("
                SELECT 
                    sp.id as program_id,
                    sp.program_name,
                    COUNT(*) as total_applicants,
                    SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as total_approved,
                    SUM(CASE WHEN ui.application_status = 'Denied' THEN 1 ELSE 0 END) as total_denied,
                    SUM(CASE WHEN ui.application_status = 'Under Review' THEN 1 ELSE 0 END) as under_review
                FROM users_info ui
                JOIN users u ON ui.user_id = u.id
                LEFT JOIN scholarship_programs sp ON ui.program_id = sp.id
                WHERE u.role = 'Applicant' AND ui.program_id IS NOT NULL
                GROUP BY sp.id, sp.program_name
                ORDER BY total_applicants DESC
            ");
            $stmt->execute();
            $programData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [
                'success' => true,
                'data' => [],
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            foreach ($programData as $row) {
                $total = (int)$row['total_applicants'];
                $approved = (int)$row['total_approved'];
                $denied = (int)$row['total_denied'];
                $underReview = (int)$row['under_review'];
                $approvalRate = $total > 0 ? round(($approved / $total) * 100, 2) : 0;
                $denialRate = $total > 0 ? round(($denied / $total) * 100, 2) : 0;
                
                $result['data'][$row['program_name']] = [
                    'program_id' => (int)$row['program_id'],
                    'total_applicants' => $total,
                    'total_approved' => $approved,
                    'total_denied' => $denied,
                    'under_review' => $underReview,
                    'approval_rate' => $approvalRate,
                    'denial_rate' => $denialRate,
                    'performance' => $approvalRate > 60 ? 'excellent' : ($approvalRate > 40 ? 'good' : ($approvalRate > 20 ? 'fair' : 'needs_improvement'))
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("PredictiveAnalytics Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to analyze program trends: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Compare programs side by side
     */
    public function comparePrograms($programIds = []) {
        try {
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection not available'
                ];
            }
            
            if (empty($programIds)) {
                // Get all active programs if none specified
                $stmt = $this->pdo->prepare("SELECT id FROM scholarship_programs WHERE is_active = 1");
                $stmt->execute();
                $programIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            $comparison = [
                'success' => true,
                'programs' => [],
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            foreach ($programIds as $programId) {
                // Get program details
                $stmt = $this->pdo->prepare("
                    SELECT id, program_name, program_description 
                    FROM scholarship_programs 
                    WHERE id = ?
                ");
                $stmt->execute([$programId]);
                $program = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$program) continue;
                
                // Get statistics for this program
                $stmt = $this->pdo->prepare("
                    SELECT 
                        COUNT(*) as total_applicants,
                        SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN application_status = 'Denied' THEN 1 ELSE 0 END) as denied,
                        SUM(CASE WHEN application_status = 'Under Review' THEN 1 ELSE 0 END) as under_review
                    FROM users_info
                    WHERE program_id = ?
                ");
                $stmt->execute([$programId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $total = (int)$stats['total_applicants'];
                $approved = (int)$stats['approved'];
                $denied = (int)$stats['denied'];
                $underReview = (int)$stats['under_review'];
                
                $comparison['programs'][$program['program_name']] = [
                    'program_id' => (int)$programId,
                    'program_name' => $program['program_name'],
                    'program_description' => $program['program_description'],
                    'total_applicants' => $total,
                    'approved' => $approved,
                    'denied' => $denied,
                    'under_review' => $underReview,
                    'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
                    'denial_rate' => $total > 0 ? round(($denied / $total) * 100, 2) : 0
                ];
            }
            
            return $comparison;
        } catch (Exception $e) {
            error_log("PredictiveAnalytics Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to compare programs: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Predict applicant success based on various factors (optionally filtered by program)
     */
    public function predictApplicantSuccess($applicantId = null, $programId = null) {
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
                    'civil_status' => [],
                    'program' => []
                ],
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            // Build query with optional program filter
            $whereClause = "1=1";
            $params = [];
            
            if ($programId !== null) {
                $whereClause .= " AND program_id = ?";
                $params[] = $programId;
            }
            
            // Get overall statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
                WHERE $whereClause
            ");
            $stmt->execute($params);
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $result['total_applicants'] = (int)$overall['total'];
            $result['approved_applicants'] = (int)$overall['approved'];
            $result['overall_success_rate'] = $overall['total'] > 0 ? 
                round(($overall['approved'] / $overall['total']) * 100, 2) : 0;
            
            // Municipality success rates
            $municipalityWhere = "municipality IS NOT NULL AND municipality != ''";
            if ($programId !== null) {
                $municipalityWhere .= " AND program_id = ?";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    municipality,
                    COUNT(*) as total,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
                WHERE $municipalityWhere
                GROUP BY municipality
            ");
            $stmt->execute($programId !== null ? [$programId] : []);
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
            $courseWhere = "up.course IS NOT NULL AND up.course != ''";
            $courseParams = [];
            if ($programId !== null) {
                $courseWhere .= " AND ui.program_id = ?";
                $courseParams[] = $programId;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    up.course,
                    COUNT(*) as total,
                    SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM user_personal up
                JOIN users_info ui ON up.user_id = ui.user_id
                WHERE $courseWhere
                GROUP BY up.course
            ");
            $stmt->execute($courseParams);
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
            $civilStatusWhere = "civil_status IS NOT NULL AND civil_status != ''";
            $civilStatusParams = [];
            if ($programId !== null) {
                $civilStatusWhere .= " AND program_id = ?";
                $civilStatusParams[] = $programId;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    civil_status,
                    COUNT(*) as total,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
                WHERE $civilStatusWhere
                GROUP BY civil_status
            ");
            $stmt->execute($civilStatusParams);
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
            
            // Program success rates (if not filtering by specific program)
            if ($programId === null) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        sp.program_name,
                        COUNT(*) as total,
                        SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                    FROM users_info ui
                    LEFT JOIN scholarship_programs sp ON ui.program_id = sp.id
                    WHERE ui.program_id IS NOT NULL
                    GROUP BY sp.id, sp.program_name
                ");
                $stmt->execute();
                $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($programs as $row) {
                    $successRate = $row['total'] > 0 ? 
                        round(($row['approved'] / $row['total']) * 100, 2) : 0;
                    
                    $result['success_factors']['program'][$row['program_name']] = [
                        'total' => (int)$row['total'],
                        'approved' => (int)$row['approved'],
                        'success_rate' => $successRate
                    ];
                }
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
     * Generate AI-powered recommendations based on analytics data using Gemini AI (optionally filtered by program)
     */
    public function generateRecommendations($programId = null) {
        try {
            // Validate database connection
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection not available'
                ];
            }
            
            // Get analytics data to provide context to AI
            $analyticsData = $this->getAnalyticsSummary($programId);
            
            // Generate AI recommendations using Gemini
            $aiRecommendations = $this->generateAIRecommendations($analyticsData, $programId);
            
            if ($aiRecommendations['success']) {
                return [
                    'success' => true,
                    'recommendations' => $aiRecommendations['recommendations'],
                    'generated_at' => date('Y-m-d H:i:s')
                ];
            } else {
                // Fallback to basic recommendations if AI fails
                return $this->generateBasicRecommendations($programId);
            }
        } catch (Exception $e) {
            error_log("PredictiveAnalytics Error: " . $e->getMessage());
            // Fallback to basic recommendations if AI fails
            return $this->generateBasicRecommendations($programId);
        }
    }
    
    /**
     * Get a summary of analytics data for AI context (optionally filtered by program)
     */
    private function getAnalyticsSummary($programId = null) {
        try {
            $summary = [];
            
            // Build query with optional program filter
            $whereClause = "1=1";
            $params = [];
            
            if ($programId !== null) {
                $whereClause .= " AND program_id = ?";
                $params[] = $programId;
            }
            
            // Get overall statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN application_status = 'Under Review' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN application_status = 'Denied' THEN 1 ELSE 0 END) as denied
                FROM users_info
                WHERE $whereClause
            ");
            $stmt->execute($params);
            $summary['overall'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get program-specific statistics if not filtering
            if ($programId === null) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        sp.program_name,
                        COUNT(*) as total_applications,
                        SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                    FROM users_info ui
                    LEFT JOIN scholarship_programs sp ON ui.program_id = sp.id
                    WHERE ui.program_id IS NOT NULL
                    GROUP BY sp.id, sp.program_name
                    ORDER BY total_applications DESC
                ");
                $stmt->execute();
                $summary['programs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Get top municipalities by application count
            $municipalityWhere = "municipality IS NOT NULL AND municipality != ''";
            $municipalityParams = [];
            if ($programId !== null) {
                $municipalityWhere .= " AND program_id = ?";
                $municipalityParams[] = $programId;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    municipality,
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN application_status = 'Approved' THEN 1 ELSE 0 END) as approved
                FROM users_info
                WHERE $municipalityWhere
                GROUP BY municipality
                ORDER BY total_applications DESC
                LIMIT 5
            ");
            $stmt->execute($municipalityParams);
            $summary['top_municipalities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get popular courses
            $courseWhere = "up.course IS NOT NULL AND up.course != ''";
            $courseParams = [];
            if ($programId !== null) {
                $courseWhere .= " AND ui.program_id = ?";
                $courseParams[] = $programId;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    up.course,
                    COUNT(*) as total_applications
                FROM user_personal up
                JOIN users_info ui ON up.user_id = ui.user_id
                WHERE $courseWhere
                GROUP BY up.course
                ORDER BY total_applications DESC
                LIMIT 5
            ");
            $stmt->execute($courseParams);
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
    private function generateAIRecommendations($analyticsData, $programId = null) {
        try {
            $apiKey = 'AIzaSyAFROmTOC9U9JGwiR7YZUyYtoK3bRSJhhg';
            
            if (empty($apiKey)) {
                throw new Exception('Gemini API key not configured');
            }
            
            // Format the analytics data for the AI prompt
            $formattedData = $this->formatAnalyticsDataForPrompt($analyticsData, $programId);
            
            // Get program name if filtering
            $programContext = "";
            if ($programId !== null) {
                $stmt = $this->pdo->prepare("SELECT program_name FROM scholarship_programs WHERE id = ?");
                $stmt->execute([$programId]);
                $program = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($program) {
                    $programContext = " for the '{$program['program_name']}' scholarship program";
                }
            } else {
                $programContext = " across all scholarship programs in the unified platform";
            }
            
            // Prepare the prompt for Gemini AI
            $prompt = "You are an expert educational policy advisor for a unified scholarship platform in the Philippines (Zambales and Bataan provinces) that manages multiple scholarship programs. 
            Based on the following analytics data$programContext, provide 5 specific, actionable recommendations to improve the scholarship program(s):
            
            Analytics Data:
            $formattedData
            
            Please provide recommendations in this exact format:
            1. [First recommendation]
            2. [Second recommendation]
            3. [Third recommendation]
            4. [Fourth recommendation]
            5. [Fifth recommendation]
            
            Each recommendation should be specific, practical, and directly address patterns or issues in the data. 
            Focus on improving approval rates, addressing disparities, optimizing resource allocation, enhancing the application process, or improving program-specific outcomes.
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
    private function formatAnalyticsDataForPrompt($analyticsData, $programId = null) {
        $output = "";
        
        if (isset($analyticsData['overall'])) {
            $overall = $analyticsData['overall'];
            $approvalRate = $overall['total_applications'] > 0 ? 
                round(($overall['approved'] / $overall['total_applications']) * 100, 2) : 0;
                
            $output .= "Overall Statistics:\n";
            $output .= "- Total Applications: {$overall['total_applications']}\n";
            $output .= "- Approved: {$overall['approved']}\n";
            $output .= "- Under Review: {$overall['pending']}\n";
            $output .= "- Denied: {$overall['denied']}\n";
            $output .= "- Approval Rate: {$approvalRate}%\n\n";
        }
        
        // Add program-specific data if available
        if (isset($analyticsData['programs']) && !empty($analyticsData['programs'])) {
            $output .= "Scholarship Programs Performance:\n";
            foreach ($analyticsData['programs'] as $program) {
                $approvalRate = $program['total_applications'] > 0 ? 
                    round(($program['approved'] / $program['total_applications']) * 100, 2) : 0;
                $output .= "- {$program['program_name']}: {$program['total_applications']} applications ({$approvalRate}% approval)\n";
            }
            $output .= "\n";
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
    private function generateBasicRecommendations($programId = null) {
        $recommendations = [
            "Review application criteria to ensure fairness and accessibility",
            "Implement targeted outreach programs in underrepresented municipalities",
            "Provide additional support for applicants from courses with lower success rates",
            "Optimize scholarship distribution based on application trends",
            "Establish mentorship programs to assist new applicants"
        ];
        
        if ($programId === null) {
            $recommendations[] = "Compare performance across different scholarship programs to identify best practices";
            $recommendations[] = "Allocate resources based on program-specific needs and applicant volumes";
        }
        
        return [
            'success' => true,
            'recommendations' => array_slice($recommendations, 0, 5),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

?>