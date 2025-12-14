<?php
/**
 * Data Validation Utility
 * Validates document dates against scholarship academic year/timeframe
 * Ensures data authenticity by checking document dates match scholarship period
 */

class DataValidation {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get scholarship program academic year
     * 
     * @param int $program_id Scholarship program ID
     * @return array|null Program data with academic_year or null
     */
    public function getProgramAcademicYear($program_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, program_name, academic_year, application_start_date, application_end_date
                FROM scholarship_programs
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$program_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Program Academic Year Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract date from document text using OCR results
     * Looks for various date patterns in document text
     * 
     * @param string $extracted_text OCR extracted text
     * @param string $document_type Type of document (cor, indigency, voter)
     * @return array Extracted dates with confidence
     */
    public function extractDocumentDate($extracted_text, $document_type) {
        $dates = [];
        $text = $extracted_text;
        
        // Common date patterns in Philippine documents
        $date_patterns = [
            // MM/DD/YYYY, DD/MM/YYYY, YYYY-MM-DD
            '/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/',
            // Month name patterns: "January 15, 2025" or "15 January 2025"
            '/(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2}),?\s+(\d{4})/i',
            '/(\d{1,2})\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i',
            // Year only patterns: "2025", "SY 2025-2026", "Academic Year 2025"
            '/(?:SY|School Year|Academic Year|AY)\s*(\d{4})[\/\-]?(\d{4})?/i',
            '/(?:Year|Yr\.?)\s*:?\s*(\d{4})/i',
            // Date issued patterns
            '/(?:Date|Issued|Date of Issue|Date Issued)[\s:]*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i',
            '/(?:Date|Issued|Date of Issue)[\s:]*((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})/i',
        ];
        
        foreach ($date_patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $date_str = $match[0];
                    $parsed_date = $this->parseDateString($date_str);
                    if ($parsed_date) {
                        $dates[] = [
                            'date' => $parsed_date,
                            'source' => $date_str,
                            'confidence' => 0.8
                        ];
                    }
                }
            }
        }
        
        // Also look for standalone years (4 digits)
        if (preg_match_all('/\b(20\d{2})\b/', $text, $year_matches)) {
            foreach ($year_matches[1] as $year) {
                $year_int = (int)$year;
                if ($year_int >= 2020 && $year_int <= 2100) {
                    $dates[] = [
                        'date' => $year . '-01-01',
                        'source' => $year,
                        'confidence' => 0.6,
                        'is_year_only' => true
                    ];
                }
            }
        }
        
        // Remove duplicates and sort by confidence
        $unique_dates = [];
        foreach ($dates as $date_info) {
            $key = $date_info['date'];
            if (!isset($unique_dates[$key]) || $unique_dates[$key]['confidence'] < $date_info['confidence']) {
                $unique_dates[$key] = $date_info;
            }
        }
        
        return array_values($unique_dates);
    }
    
    /**
     * Parse date string to YYYY-MM-DD format
     * 
     * @param string $date_str Date string
     * @return string|null Parsed date in YYYY-MM-DD format or null
     */
    private function parseDateString($date_str) {
        $date_str = trim($date_str);
        
        // Try standard date parsing
        $timestamp = strtotime($date_str);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        // Try parsing MM/DD/YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $date_str, $parts)) {
            $month = (int)$parts[1];
            $day = (int)$parts[2];
            $year = (int)$parts[3];
            
            // Handle 2-digit years
            if ($year < 100) {
                $year += ($year < 50) ? 2000 : 1900;
            }
            
            // Try both MM/DD and DD/MM formats
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            } elseif (checkdate($day, $month, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $day, $month);
            }
        }
        
        return null;
    }
    
    /**
     * Validate document date against scholarship academic year
     * 
     * @param int $user_id User ID
     * @param string $document_type Document type
     * @param string $extracted_text OCR extracted text
     * @param int|null $program_id Optional program ID (if not provided, gets from user)
     * @return array Validation result
     */
    public function validateDocumentDate($user_id, $document_type, $extracted_text, $program_id = null) {
        try {
            // Get program ID if not provided
            if ($program_id === null) {
                $stmt = $this->pdo->prepare("SELECT program_id FROM users_info WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $program_id = $result['program_id'] ?? null;
            }
            
            if (!$program_id) {
                return [
                    'valid' => false,
                    'message' => 'No scholarship program assigned. Cannot validate document date.',
                    'error_code' => 'NO_PROGRAM'
                ];
            }
            
            // Get program academic year
            $program = $this->getProgramAcademicYear($program_id);
            if (!$program) {
                return [
                    'valid' => false,
                    'message' => 'Scholarship program not found.',
                    'error_code' => 'PROGRAM_NOT_FOUND'
                ];
            }
            
            $academic_year = $program['academic_year'] ?? null;
            $application_start = $program['application_start_date'] ?? null;
            $application_end = $program['application_end_date'] ?? null;
            
            // If no academic year is set, allow the document (backward compatibility)
            if (!$academic_year && !$application_start && !$application_end) {
                return [
                    'valid' => true,
                    'message' => 'No academic year validation configured for this program.',
                    'warning' => true
                ];
            }
            
            // Extract dates from document
            $extracted_dates = $this->extractDocumentDate($extracted_text, $document_type);
            
            if (empty($extracted_dates)) {
                return [
                    'valid' => false,
                    'message' => 'Could not extract date from document. Please ensure the document date is clearly visible.',
                    'error_code' => 'NO_DATE_FOUND',
                    'extracted_dates' => []
                ];
            }
            
            // Determine valid date range
            $valid_start = null;
            $valid_end = null;
            
            if ($academic_year) {
                // Academic year can be "2025" or "2025-2026"
                if (preg_match('/^(\d{4})[\/\-]?(\d{4})?$/', $academic_year, $matches)) {
                    $start_year = (int)$matches[1];
                    $end_year = isset($matches[2]) ? (int)$matches[2] : $start_year;
                    
                    // Documents should be from the academic year or within 6 months before
                    $valid_start = ($start_year - 1) . '-07-01'; // July of previous year
                    $valid_end = ($end_year + 1) . '-06-30'; // June of next year
                } else {
                    // Single year format
                    $year = (int)$academic_year;
                    $valid_start = ($year - 1) . '-07-01';
                    $valid_end = ($year + 1) . '-06-30';
                }
            }
            
            // Override with application period if available
            if ($application_start) {
                $valid_start = $application_start;
            }
            if ($application_end) {
                // Allow documents up to 3 months after application end
                $end_date = new DateTime($application_end);
                $end_date->modify('+3 months');
                $valid_end = $end_date->format('Y-m-d');
            }
            
            // If no valid range determined, use current year
            if (!$valid_start) {
                $current_year = (int)date('Y');
                $valid_start = ($current_year - 1) . '-01-01';
                $valid_end = ($current_year + 1) . '-12-31';
            }
            
            // Check if any extracted date falls within valid range
            $valid_dates = [];
            $invalid_dates = [];
            
            foreach ($extracted_dates as $date_info) {
                $doc_date = $date_info['date'];
                
                // For year-only dates, check if year matches
                if (isset($date_info['is_year_only']) && $date_info['is_year_only']) {
                    $doc_year = (int)substr($doc_date, 0, 4);
                    if ($academic_year) {
                        if (preg_match('/^(\d{4})[\/\-]?(\d{4})?$/', $academic_year, $matches)) {
                            $start_year = (int)$matches[1];
                            $end_year = isset($matches[2]) ? (int)$matches[2] : $start_year;
                            if ($doc_year >= $start_year && $doc_year <= $end_year) {
                                $valid_dates[] = $date_info;
                            } else {
                                $invalid_dates[] = $date_info;
                            }
                        } else {
                            $year = (int)$academic_year;
                            if ($doc_year == $year) {
                                $valid_dates[] = $date_info;
                            } else {
                                $invalid_dates[] = $date_info;
                            }
                        }
                    } else {
                        $valid_dates[] = $date_info; // No validation if no academic year
                    }
                } else {
                    // Full date validation
                    if ($doc_date >= $valid_start && $doc_date <= $valid_end) {
                        $valid_dates[] = $date_info;
                    } else {
                        $invalid_dates[] = $date_info;
                    }
                }
            }
            
            if (!empty($valid_dates)) {
                return [
                    'valid' => true,
                    'message' => 'Document date is valid for the scholarship academic year.',
                    'valid_dates' => $valid_dates,
                    'invalid_dates' => $invalid_dates,
                    'academic_year' => $academic_year,
                    'valid_range' => ['start' => $valid_start, 'end' => $valid_end]
                ];
            } else {
                $error_msg = 'Document date does not match the scholarship academic year. ';
                if ($academic_year) {
                    $error_msg .= "Expected documents from academic year: {$academic_year}.";
                }
                if (!empty($invalid_dates)) {
                    $found_dates = array_map(function($d) { return $d['source']; }, $invalid_dates);
                    $error_msg .= " Found dates: " . implode(', ', array_unique($found_dates));
                }
                
                return [
                    'valid' => false,
                    'message' => $error_msg,
                    'error_code' => 'DATE_MISMATCH',
                    'valid_dates' => [],
                    'invalid_dates' => $invalid_dates,
                    'academic_year' => $academic_year,
                    'valid_range' => ['start' => $valid_start, 'end' => $valid_end]
                ];
            }
            
        } catch (Exception $e) {
            error_log("Document Date Validation Error: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Error validating document date: ' . $e->getMessage(),
                'error_code' => 'VALIDATION_ERROR'
            ];
        }
    }
    
    /**
     * Validate all documents for a user against their scholarship program
     * 
     * @param int $user_id User ID
     * @return array Validation results for all documents
     */
    public function validateAllUserDocuments($user_id) {
        try {
            require_once __DIR__ . '/ocr_service.php';
            $ocr = new OCRService($this->pdo);
            
            // Get user's program
            $stmt = $this->pdo->prepare("SELECT program_id FROM users_info WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $program_id = $result['program_id'] ?? null;
            
            if (!$program_id) {
                return [
                    'valid' => false,
                    'message' => 'User has no assigned scholarship program',
                    'documents' => []
                ];
            }
            
            // Get OCR results for all documents
            $document_types = ['cor', 'indigency', 'voter'];
            $results = [];
            
            foreach ($document_types as $doc_type) {
                $ocr_result = $ocr->getOCRResult($user_id, $doc_type);
                if ($ocr_result && !empty($ocr_result['extracted_text'])) {
                    $validation = $this->validateDocumentDate(
                        $user_id,
                        $doc_type,
                        $ocr_result['extracted_text'],
                        $program_id
                    );
                    $results[$doc_type] = $validation;
                } else {
                    $results[$doc_type] = [
                        'valid' => false,
                        'message' => 'No OCR data available for this document',
                        'error_code' => 'NO_OCR_DATA'
                    ];
                }
            }
            
            $all_valid = true;
            foreach ($results as $result) {
                if (!$result['valid']) {
                    $all_valid = false;
                    break;
                }
            }
            
            return [
                'valid' => $all_valid,
                'message' => $all_valid ? 'All documents are valid' : 'Some documents have validation issues',
                'documents' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Validate All Documents Error: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Error validating documents: ' . $e->getMessage(),
                'documents' => []
            ];
        }
    }
}

?>
