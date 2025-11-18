<?php
/**
 * OCR Service - Handles text extraction from documents
 * Uses Tesseract.js (client-side) with server-side processing support
 * 
 * This service is FREE and does not require any API keys
 * Supports: PDF, PNG, JPG, JPEG, GIF, BMP, WEBP
 */

class OCRService {
    private $pdo;
    private $upload_dir = 'uploads/ocr_results/';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureUploadDirExists();
    }
    
    /**
     * Ensure upload directory exists
     */
    private function ensureUploadDirExists() {
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Store OCR extraction results in database
     * 
     * @param int $user_id User ID
     * @param string $document_type Type of document (cor, indigency, voter)
     * @param string $extracted_text The extracted text from OCR
     * @param array $metadata Additional metadata (confidence, language, etc)
     * @return array Result array with success status and message
     */
    public function storeOCRResult($user_id, $document_type, $extracted_text, $metadata = []) {
        try {
            // Check if table exists, if not create it
            $this->createOCRResultsTableIfNotExists();
            
            $confidence = $metadata['confidence'] ?? null;
            $language = $metadata['language'] ?? 'eng';
            $processing_time = $metadata['processing_time'] ?? null;
            $file_path = $metadata['file_path'] ?? null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO ocr_results (user_id, document_type, extracted_text, confidence, language, processing_time, file_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    extracted_text = VALUES(extracted_text),
                    confidence = VALUES(confidence),
                    processing_time = VALUES(processing_time),
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $user_id,
                $document_type,
                $extracted_text,
                $confidence,
                $language,
                $processing_time,
                $file_path
            ]);
            
            return ['success' => true, 'message' => 'OCR results stored successfully'];
        } catch (PDOException $e) {
            error_log("OCR Storage Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to store OCR results: ' . $e->getMessage()];
        }
    }
    
    /**
     * Retrieve OCR results for a user's document
     * 
     * @param int $user_id User ID
     * @param string $document_type Type of document
     * @return array|null OCR result or null if not found
     */
    public function getOCRResult($user_id, $document_type) {
        try {
            $this->createOCRResultsTableIfNotExists();
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM ocr_results 
                WHERE user_id = ? AND document_type = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$user_id, $document_type]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("OCR Retrieval Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all OCR results for a user
     * 
     * @param int $user_id User ID
     * @return array Array of OCR results
     */
    public function getUserOCRResults($user_id) {
        try {
            $this->createOCRResultsTableIfNotExists();
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM ocr_results 
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("OCR Retrieval Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate extracted text quality
     * Checks if extracted text meets minimum quality standards
     * 
     * @param string $extracted_text The extracted text
     * @param float $confidence Confidence score from OCR
     * @return array Validation result with quality score
     */
    public function validateExtractedText($extracted_text, $confidence = 1.0) {
        $text_length = strlen(trim($extracted_text));
        $word_count = count(array_filter(explode(' ', trim($extracted_text))));
        $has_numbers = preg_match('/\d/', $extracted_text);
        $has_letters = preg_match('/[a-zA-Z]/', $extracted_text);
        
        // Calculate quality score (0-100)
        $quality_score = 0;
        
        if ($text_length > 20) $quality_score += 20;
        if ($word_count > 3) $quality_score += 20;
        if ($has_letters) $quality_score += 20;
        if ($has_numbers) $quality_score += 15;
        if ($confidence > 0.7) $quality_score += 25;
        
        $is_valid = $quality_score >= 50; // Minimum threshold
        
        return [
            'is_valid' => $is_valid,
            'quality_score' => min($quality_score, 100),
            'text_length' => $text_length,
            'word_count' => $word_count,
            'has_numbers' => $has_numbers,
            'has_letters' => $has_letters,
            'confidence' => $confidence
        ];
    }
    
    /**
     * Extract document information from OCR results
     * Looks for specific patterns relevant to scholarship documents
     * 
     * @param string $extracted_text The extracted text
     * @param string $document_type Type of document (cor, voter, indigency)
     * @return array Extracted information
     */
    public function extractDocumentInfo($extracted_text, $document_type) {
        $info = [
            'document_type' => $document_type,
            'extracted_keys' => [],
            'extracted_values' => []
        ];
        
        $text = strtolower($extracted_text);
        
        // Common patterns to look for in documents
        $patterns = [
            'name' => '/(?:name|n?ame)[\s:]*([a-z\s,\.]+?)(?=\n|$|id|tel|phone)/i',
            'id' => '/(?:id|id number|identification)[\s:]*([a-z0-9\-\/]+)/i',
            'address' => '/(?:address|add?r?ess)[\s:]*([a-z0-9\s,\.]+?)(?=\n|$)/i',
            'date' => '/(?:date|date of issue|issued)[\s:]*(\d{1,2}[\s\/\-]\d{1,2}[\s\/\-]\d{2,4})/i',
            'signature' => '/(?:signature|sig\.?|signed)/i',
        ];
        
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $extracted_text, $matches)) {
                $info['extracted_keys'][] = $key;
                $info['extracted_values'][$key] = trim($matches[1] ?? '');
            }
        }
        
        // Check for specific document indicators
        if ($document_type === 'cor') {
            $info['is_cor'] = preg_match('/certificate|registry|record/i', $extracted_text) ? true : false;
        } elseif ($document_type === 'voter') {
            $info['is_voter'] = preg_match('/voter|commission on elections|comelec/i', $extracted_text) ? true : false;
        } elseif ($document_type === 'indigency') {
            $info['is_indigency'] = preg_match('/indigency|certificate of indigency|barangay/i', $extracted_text) ? true : false;
        }
        
        return $info;
    }
    
    /**
     * Create OCR results table if it doesn't exist
     * This is called automatically but can be invoked manually if needed
     */
    public function createOCRResultsTableIfNotExists() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS ocr_results (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    document_type VARCHAR(50) NOT NULL,
                    extracted_text LONGTEXT,
                    confidence DECIMAL(3,2),
                    language VARCHAR(10) DEFAULT 'eng',
                    processing_time INT,
                    file_path VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_user_doc (user_id, document_type),
                    INDEX idx_user_id (user_id),
                    INDEX idx_document_type (document_type),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("OCR Table Creation Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save extracted text to file for backup/audit purposes
     * 
     * @param int $user_id User ID
     * @param string $document_type Document type
     * @param string $extracted_text The extracted text
     * @return string|null File path if successful, null otherwise
     */
    public function saveExtractedTextToFile($user_id, $document_type, $extracted_text) {
        try {
            $this->ensureUploadDirExists();
            
            $filename = $this->upload_dir . $user_id . '_' . $document_type . '_' . time() . '.txt';
            file_put_contents($filename, $extracted_text);
            
            return $filename;
        } catch (Exception $e) {
            error_log("Failed to save extracted text: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get OCR processing stats for dashboard analytics
     * 
     * @return array Statistics about OCR processing
     */
    public function getOCRStats() {
        try {
            $this->createOCRResultsTableIfNotExists();
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_processed,
                    COUNT(DISTINCT user_id) as total_users,
                    AVG(confidence) as avg_confidence,
                    AVG(processing_time) as avg_processing_time,
                    MAX(created_at) as last_processing_time
                FROM ocr_results
            ");
            
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("OCR Stats Error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Helper function to initialize OCR Service
 * Usage: $ocr = initOCRService($pdo);
 */
function initOCRService($pdo) {
    require_once __DIR__ . '/ocr_service.php';
    return new OCRService($pdo);
}
?>
