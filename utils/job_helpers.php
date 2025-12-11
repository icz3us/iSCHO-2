<?php
/**
 * Job Queue Helper Functions
 * Convenience functions for queuing background jobs
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/redis_queue.php';

/**
 * Queue OCR processing job
 * 
 * @param int $user_id User ID
 * @param int $document_id Document ID
 * @param string $file_path File path
 * @return string|false Job ID or false on failure
 */
function queueOCRJob($user_id, $document_id, $file_path) {
    $queue = new RedisQueue('ocr');
    return $queue->push('process_ocr', [
        'user_id' => $user_id,
        'document_id' => $document_id,
        'file_path' => $file_path
    ], 10); // Priority 10
}

/**
 * Queue email sending job
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body
 * @return string|false Job ID or false on failure
 */
function queueEmailJob($to, $subject, $body) {
    $queue = new RedisQueue('emails');
    return $queue->push('send_email', [
        'to' => $to,
        'subject' => $subject,
        'body' => $body
    ]);
}

/**
 * Queue document validation job
 * 
 * @param int $user_id User ID
 * @param int $document_id Document ID
 * @return string|false Job ID or false on failure
 */
function queueDocumentValidationJob($user_id, $document_id) {
    $queue = new RedisQueue('validation');
    return $queue->push('validate_document', [
        'user_id' => $user_id,
        'document_id' => $document_id
    ], 5); // Priority 5
}

