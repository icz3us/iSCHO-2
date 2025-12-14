<?php
/**
 * Document Verification System
 * Handles verification status tracking and document integrity checks
 */

class DocumentVerification {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createVerificationTable();
    }
    
    /**
     * Create document_verification table if it doesn't exist
     */
    private function createVerificationTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS document_verification (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    document_type VARCHAR(50) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    file_hash VARCHAR(64) NOT NULL,
                    file_size BIGINT NOT NULL,
                    mime_type VARCHAR(100),
                    verification_status ENUM('Pending', 'Verified', 'Rejected', 'Under Review') DEFAULT 'Pending',
                    verified_by INT NULL,
                    verified_at TIMESTAMP NULL,
                    rejection_reason TEXT NULL,
                    notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
                    UNIQUE KEY unique_user_doc (user_id, document_type),
                    INDEX idx_user_id (user_id),
                    INDEX idx_verification_status (verification_status),
                    INDEX idx_document_type (document_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Document Verification Table Creation Error: " . $e->getMessage());
        }
    }
    
    /**
     * Record document submission
     * 
     * @param int $user_id User ID
     * @param string $document_type Document type (cor_file, indigency_file, voter_file)
     * @param string $file_path Path to uploaded file
     * @param string $file_hash SHA-256 hash of file
     * @param int $file_size File size in bytes
     * @param string $mime_type MIME type
     * @return array Result with success status
     */
    public function recordDocumentSubmission($user_id, $document_type, $file_path, $file_hash, $file_size, $mime_type = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO document_verification (
                    user_id, document_type, file_path, file_hash, file_size, mime_type, verification_status
                ) VALUES (?, ?, ?, ?, ?, ?, 'Pending')
                ON DUPLICATE KEY UPDATE
                    file_path = VALUES(file_path),
                    file_hash = VALUES(file_hash),
                    file_size = VALUES(file_size),
                    mime_type = VALUES(mime_type),
                    verification_status = 'Pending',
                    verified_by = NULL,
                    verified_at = NULL,
                    rejection_reason = NULL,
                    updated_at = NOW()
            ");
            
            $stmt->execute([$user_id, $document_type, $file_path, $file_hash, $file_size, $mime_type]);
            
            return ['success' => true, 'message' => 'Document submission recorded'];
        } catch (PDOException $e) {
            error_log("Document Recording Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to record document: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verify document (admin action)
     * 
     * @param int $user_id User ID
     * @param string $document_type Document type
     * @param int $verified_by Admin/Superadmin user ID
     * @param string $status Verification status
     * @param string $notes Optional notes
     * @param string $rejection_reason Optional rejection reason
     * @return array Result with success status
     */
    public function verifyDocument($user_id, $document_type, $verified_by, $status = 'Verified', $notes = null, $rejection_reason = null) {
        try {
            // Validate status
            $valid_statuses = ['Verified', 'Rejected', 'Under Review'];
            if (!in_array($status, $valid_statuses)) {
                return ['success' => false, 'message' => 'Invalid verification status'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE document_verification
                SET verification_status = ?,
                    verified_by = ?,
                    verified_at = NOW(),
                    notes = ?,
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE user_id = ? AND document_type = ?
            ");
            
            $stmt->execute([$status, $verified_by, $notes, $rejection_reason, $user_id, $document_type]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Document verification updated'];
            } else {
                return ['success' => false, 'message' => 'Document not found'];
            }
        } catch (PDOException $e) {
            error_log("Document Verification Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to verify document: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get verification status for user's documents
     * 
     * @param int $user_id User ID
     * @return array Document verification statuses
     */
    public function getUserDocumentStatus($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    document_type,
                    verification_status,
                    verified_at,
                    verified_by,
                    rejection_reason,
                    notes,
                    file_hash,
                    file_size,
                    updated_at
                FROM document_verification
                WHERE user_id = ?
            ");
            
            $stmt->execute([$user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $statuses = [];
            foreach ($results as $result) {
                $statuses[$result['document_type']] = $result;
            }
            
            return $statuses;
        } catch (PDOException $e) {
            error_log("Get Document Status Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get verification status for a specific document
     * 
     * @param int $user_id User ID
     * @param string $document_type Document type
     * @return array|null Document verification data or null
     */
    public function getDocumentStatus($user_id, $document_type) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    document_type,
                    verification_status,
                    verified_at,
                    verified_by,
                    rejection_reason,
                    notes,
                    file_hash,
                    file_size,
                    file_path,
                    updated_at
                FROM document_verification
                WHERE user_id = ? AND document_type = ?
            ");
            
            $stmt->execute([$user_id, $document_type]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Document Status Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if all required documents are verified
     * 
     * @param int $user_id User ID
     * @param array $required_docs Required document types
     * @return array Result with verification status
     */
    public function checkAllDocumentsVerified($user_id, $required_docs = ['cor_file', 'indigency_file', 'voter_file']) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    document_type,
                    verification_status
                FROM document_verification
                WHERE user_id = ? AND document_type IN (" . implode(',', array_fill(0, count($required_docs), '?')) . ")
            ");
            
            $params = array_merge([$user_id], $required_docs);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $statuses = [];
            foreach ($results as $result) {
                $statuses[$result['document_type']] = $result['verification_status'];
            }
            
            // Check if all required documents are present and verified
            $all_present = true;
            $all_verified = true;
            $missing = [];
            $pending = [];
            
            foreach ($required_docs as $doc) {
                if (!isset($statuses[$doc])) {
                    $all_present = false;
                    $missing[] = $doc;
                } elseif ($statuses[$doc] !== 'Verified') {
                    $all_verified = false;
                    if ($statuses[$doc] === 'Pending' || $statuses[$doc] === 'Under Review') {
                        $pending[] = $doc;
                    }
                }
            }
            
            return [
                'all_present' => $all_present,
                'all_verified' => $all_verified,
                'missing' => $missing,
                'pending' => $pending,
                'statuses' => $statuses
            ];
        } catch (PDOException $e) {
            error_log("Check Documents Verified Error: " . $e->getMessage());
            return [
                'all_present' => false,
                'all_verified' => false,
                'missing' => $required_docs,
                'pending' => [],
                'statuses' => []
            ];
        }
    }
    
    /**
     * Verify file integrity for a document
     * 
     * @param int $user_id User ID
     * @param string $document_type Document type
     * @param string $file_path Current file path
     * @return array Integrity check result
     */
    public function verifyDocumentIntegrity($user_id, $document_type, $file_path) {
        $doc_status = $this->getDocumentStatus($user_id, $document_type);
        
        if (!$doc_status) {
            return ['success' => false, 'message' => 'Document not found in verification records'];
        }
        
        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'File not found on disk'];
        }
        
        // Calculate current hash
        $current_hash = hash_file('sha256', $file_path);
        
        if ($current_hash !== $doc_status['file_hash']) {
            return [
                'success' => false,
                'message' => 'File integrity check failed. File may have been modified or corrupted.',
                'stored_hash' => $doc_status['file_hash'],
                'current_hash' => $current_hash
            ];
        }
        
        return [
            'success' => true,
            'message' => 'File integrity verified',
            'file_hash' => $current_hash
        ];
    }
    
    /**
     * Get all documents pending verification (for admin)
     * 
     * @param string $status Filter by status (optional)
     * @return array List of documents
     */
    public function getPendingDocuments($status = null) {
        try {
            if ($status) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        dv.*,
                        u.firstname,
                        u.lastname,
                        u.email
                    FROM document_verification dv
                    JOIN users u ON dv.user_id = u.id
                    WHERE dv.verification_status = ?
                    ORDER BY dv.created_at DESC
                ");
                $stmt->execute([$status]);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        dv.*,
                        u.firstname,
                        u.lastname,
                        u.email
                    FROM document_verification dv
                    JOIN users u ON dv.user_id = u.id
                    WHERE dv.verification_status IN ('Pending', 'Under Review')
                    ORDER BY dv.created_at DESC
                ");
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Pending Documents Error: " . $e->getMessage());
            return [];
        }
    }
}

?>
