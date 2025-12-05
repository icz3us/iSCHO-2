<?php
/**
 * Secure File Upload and Verification Utility
 * Provides secure file handling with content validation, integrity checks, and verification
 */

class FileSecurity {
    private $pdo;
    private $upload_dir;
    private $max_file_size;
    private $allowed_mime_types;
    private $allowed_extensions;
    
    // Magic bytes for file type validation
    private $magic_bytes = [
        'pdf' => ['%PDF'],
        'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'jpg' => ["\xFF\xD8\xFF"],
        'gif' => ['GIF87a', 'GIF89a']
    ];
    
    public function __construct($pdo, $upload_dir = './Uploads/') {
        $this->pdo = $pdo;
        $this->upload_dir = rtrim($upload_dir, '/') . '/';
        $this->max_file_size = 5 * 1024 * 1024; // 5MB
        $this->allowed_mime_types = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/jpg'
        ];
        $this->allowed_extensions = ['pdf', 'png', 'jpeg', 'jpg'];
        
        // Ensure upload directory exists with secure permissions
        $this->ensureUploadDir();
    }
    
    /**
     * Ensure upload directory exists with secure permissions
     */
    private function ensureUploadDir() {
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0750, true);
        }
        // Set secure permissions even if directory exists
        chmod($this->upload_dir, 0750);
    }
    
    /**
     * Validate file using multiple security checks
     * 
     * @param array $file $_FILES array element
     * @param string $file_key File identifier (cor_file, indigency_file, etc.)
     * @return array Validation result with success status and message
     */
    public function validateFile($file, $file_key) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'File was not properly uploaded'];
        }
        
        // Check upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            return ['success' => false, 'message' => $error_messages[$file['error']] ?? 'Unknown upload error'];
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return ['success' => false, 'message' => "File size exceeds maximum allowed size of " . ($this->max_file_size / 1024 / 1024) . "MB"];
        }
        
        // Check minimum file size (prevent empty files)
        if ($file['size'] < 100) {
            return ['success' => false, 'message' => 'File is too small or corrupted'];
        }
        
        // Validate file extension
        $file_name = basename($file['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $this->allowed_extensions)) {
            return ['success' => false, 'message' => 'Invalid file extension. Allowed: ' . implode(', ', $this->allowed_extensions)];
        }
        
        // Validate MIME type (can be spoofed, so we also check magic bytes)
        $detected_mime = null;
        if (function_exists('mime_content_type')) {
            $detected_mime = @mime_content_type($file['tmp_name']);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        
        // If we couldn't detect MIME type, rely on magic bytes validation
        if ($detected_mime && !in_array($detected_mime, $this->allowed_mime_types) && !in_array($file['type'], $this->allowed_mime_types)) {
            return ['success' => false, 'message' => 'Invalid file type detected'];
        }
        
        // Validate magic bytes (file signature) - most secure method
        $magic_validation = $this->validateMagicBytes($file['tmp_name'], $file_ext);
        if (!$magic_validation['success']) {
            return $magic_validation;
        }
        
        // Additional validation for images
        if (in_array($file_ext, ['png', 'jpeg', 'jpg'])) {
            $image_validation = $this->validateImage($file['tmp_name']);
            if (!$image_validation['success']) {
                return $image_validation;
            }
        }
        
        // Validate PDF structure if PDF
        if ($file_ext === 'pdf') {
            $pdf_validation = $this->validatePDF($file['tmp_name']);
            if (!$pdf_validation['success']) {
                return $pdf_validation;
            }
        }
        
        return ['success' => true, 'message' => 'File validation passed'];
    }
    
    /**
     * Validate file magic bytes (file signature)
     * 
     * @param string $file_path Path to uploaded file
     * @param string $extension File extension
     * @return array Validation result
     */
    private function validateMagicBytes($file_path, $extension) {
        if (!isset($this->magic_bytes[$extension])) {
            return ['success' => false, 'message' => 'Unsupported file type'];
        }
        
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return ['success' => false, 'message' => 'Cannot read file for validation'];
        }
        
        $header = fread($handle, 12);
        fclose($handle);
        
        $valid_signatures = $this->magic_bytes[$extension];
        foreach ($valid_signatures as $signature) {
            if (strpos($header, $signature) === 0) {
                return ['success' => true];
            }
        }
        
        return ['success' => false, 'message' => 'File signature does not match file extension. Possible file type mismatch or corruption.'];
    }
    
    /**
     * Validate image file
     * 
     * @param string $file_path Path to image file
     * @return array Validation result
     */
    private function validateImage($file_path) {
        $image_info = @getimagesize($file_path);
        
        if ($image_info === false) {
            return ['success' => false, 'message' => 'Invalid or corrupted image file'];
        }
        
        // Check if image dimensions are reasonable
        if ($image_info[0] < 10 || $image_info[1] < 10) {
            return ['success' => false, 'message' => 'Image dimensions are too small'];
        }
        
        // Check if image is too large (prevent memory exhaustion)
        if ($image_info[0] > 10000 || $image_info[1] > 10000) {
            return ['success' => false, 'message' => 'Image dimensions are too large'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Validate PDF file structure
     * 
     * @param string $file_path Path to PDF file
     * @return array Validation result
     */
    private function validatePDF($file_path) {
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return ['success' => false, 'message' => 'Cannot read PDF file'];
        }
        
        // Check PDF header
        $header = fread($handle, 4);
        if ($header !== '%PDF') {
            fclose($handle);
            return ['success' => false, 'message' => 'Invalid PDF structure'];
        }
        
        // Check for PDF footer (EOF marker)
        fseek($handle, -1024, SEEK_END);
        $footer = fread($handle, 1024);
        fclose($handle);
        
        if (strpos($footer, '%%EOF') === false) {
            return ['success' => false, 'message' => 'PDF file appears to be incomplete or corrupted'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate secure file name
     * 
     * @param string $file_key File identifier
     * @param int $user_id User ID
     * @param string $extension File extension
     * @return string Secure file name
     */
    public function generateSecureFileName($file_key, $user_id, $extension) {
        // Sanitize file key
        $file_key = preg_replace('/[^a-z0-9_]/', '', strtolower($file_key));
        
        // Generate unique identifier
        $unique_id = bin2hex(random_bytes(8));
        $timestamp = time();
        
        // Create secure filename: filekey_userid_timestamp_uniqueid.ext
        return $file_key . '_' . $user_id . '_' . $timestamp . '_' . $unique_id . '.' . strtolower($extension);
    }
    
    /**
     * Calculate file hash for integrity verification
     * 
     * @param string $file_path Path to file
     * @return string|false SHA-256 hash or false on failure
     */
    public function calculateFileHash($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        return hash_file('sha256', $file_path);
    }
    
    /**
     * Securely upload file with validation and integrity tracking
     * 
     * @param array $file $_FILES array element
     * @param string $file_key File identifier
     * @param int $user_id User ID
     * @return array Upload result with file path, hash, and metadata
     */
    public function secureUpload($file, $file_key, $user_id) {
        // Validate file first
        $validation = $this->validateFile($file, $file_key);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Generate secure file name
        $file_ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
        $secure_filename = $this->generateSecureFileName($file_key, $user_id, $file_ext);
        $target_path = $this->upload_dir . $secure_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
        
        // Set secure file permissions
        chmod($target_path, 0640);
        
        // Calculate file hash for integrity verification
        $file_hash = $this->calculateFileHash($target_path);
        if ($file_hash === false) {
            // Clean up on failure
            @unlink($target_path);
            return ['success' => false, 'message' => 'Failed to calculate file hash'];
        }
        
        // Get file metadata
        $file_size = filesize($target_path);
        $mime_type = null;
        if (function_exists('mime_content_type')) {
            $mime_type = @mime_content_type($target_path);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $target_path);
            finfo_close($finfo);
        }
        
        return [
            'success' => true,
            'file_path' => $target_path,
            'file_name' => $secure_filename,
            'file_hash' => $file_hash,
            'file_size' => $file_size,
            'mime_type' => $mime_type,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Verify file integrity using stored hash
     * 
     * @param string $file_path Path to file
     * @param string $stored_hash Stored hash value
     * @return array Verification result
     */
    public function verifyFileIntegrity($file_path, $stored_hash) {
        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'File not found'];
        }
        
        $current_hash = $this->calculateFileHash($file_path);
        if ($current_hash === false) {
            return ['success' => false, 'message' => 'Failed to calculate file hash'];
        }
        
        if ($current_hash !== $stored_hash) {
            return ['success' => false, 'message' => 'File integrity check failed. File may have been modified or corrupted.'];
        }
        
        return ['success' => true, 'message' => 'File integrity verified'];
    }
    
    /**
     * Delete file securely
     * 
     * @param string $file_path Path to file
     * @return bool Success status
     */
    public function secureDelete($file_path) {
        if (file_exists($file_path) && is_file($file_path)) {
            return unlink($file_path);
        }
        return false;
    }
    
    /**
     * Sanitize file name for display
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    public function sanitizeFileName($filename) {
        return htmlspecialchars(basename($filename), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate and store CSRF token in session
 * Call this before rendering forms
 */
function initCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Validate CSRF token from POST request
 * 
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    $is_valid = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    
    // Regenerate token after use (one-time use)
    if ($is_valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $is_valid;
}

?>
