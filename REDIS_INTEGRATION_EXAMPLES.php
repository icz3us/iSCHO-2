<?php
/**
 * Redis Integration Examples
 * 
 * This file contains practical examples of how to integrate Redis features
 * into your existing iSCHO application files.
 * 
 * DO NOT include this file directly - use it as a reference!
 */

// ============================================================================
// EXAMPLE 1: Login with Rate Limiting and Redis OTP
// ============================================================================
// File: login.php
// Add these requires at the top:

require_once __DIR__ . '/utils/redis_otp.php';
require_once __DIR__ . '/utils/redis_rate_limit.php';

$redisOTP = new RedisOTP(600); // 10 minutes
$rateLimit = new RedisRateLimit();

// In registration handler, replace OTP generation:
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $email = $_POST['email'];
    
    // Check OTP rate limit (3 requests per hour)
    $rateCheck = $rateLimit->checkOTPLimit($email, 3, 3600);
    if (!$rateCheck['allowed']) {
        $register_error = "Too many OTP requests. Please try again after " . 
                         date('H:i:s', $rateCheck['reset']);
    } else {
        // Generate OTP using Redis
        $otp = $redisOTP->generateOTP($email, 600); // 10 minutes TTL
        
        if ($otp) {
            // Send OTP email
            $email_result = sendOTP($email, $otp);
            if ($email_result === true) {
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_verification_pending'] = true;
                $_SESSION['show_otp_popup'] = true;
            }
        }
    }
}

// In OTP verification handler:
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $email = $_SESSION['otp_email'];
    $otp_code = trim($_POST['otp']);
    
    // Verify OTP using Redis
    $result = $redisOTP->verifyOTP($email, $otp_code, 5); // Max 5 attempts
    
    if ($result['valid']) {
        // OTP is valid, proceed with registration
        // ... your registration code ...
    } else {
        $_SESSION['otp_error'] = $result['message'];
    }
}

// In login handler, add rate limiting:
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['register'])) {
    $email = $_POST['email'];
    $identifier = $email . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // Check login rate limit (5 attempts per 15 minutes)
    $rateCheck = $rateLimit->checkLoginLimit($identifier, 5, 900);
    
    if (!$rateCheck['allowed']) {
        $login_error = "Too many login attempts. Please try again after " . 
                      date('H:i:s', $rateCheck['reset']);
    } else {
        // Proceed with normal login
        // ... your existing login code ...
    }
}


// ============================================================================
// EXAMPLE 2: Dashboard with Query Caching
// ============================================================================
// File: admindashboard.php
// Add at the top:

require_once __DIR__ . '/utils/query_cache.php';

$queryCache = new QueryCache(300); // 5 minutes default

// Replace expensive queries with cached versions:

// OLD WAY:
// $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users_info WHERE application_status = 'Approved'");
// $stmt->execute();
// $approved = $stmt->fetch(PDO::FETCH_ASSOC);

// NEW WAY (cached):
$approved = cachedQuery(
    $pdo,
    "SELECT COUNT(*) as total FROM users_info WHERE application_status = 'Approved'",
    [],
    $queryCache,
    600 // Cache for 10 minutes
);

// For analytics queries:
$analytics = $queryCache->remember(
    "analytics:applicants:{$programId}",
    ['program_id' => $programId],
    function() use ($pdo, $programId) {
        $stmt = $pdo->prepare("
            SELECT 
                ui.municipality,
                COUNT(*) as total_applicants,
                SUM(CASE WHEN ui.application_status = 'Approved' THEN 1 ELSE 0 END) as approved
            FROM users_info ui
            WHERE ui.program_id = ?
            GROUP BY ui.municipality
        ");
        $stmt->execute([$programId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    },
    600 // 10 minutes cache
);


// ============================================================================
// EXAMPLE 3: Session Storage with Redis
// ============================================================================
// File: route_guard.php
// Add BEFORE session_start():

require_once __DIR__ . '/utils/redis_session.php';

// Initialize Redis session handler
initRedisSession(1800); // 30 minutes TTL

// Then your existing session_start() will use Redis automatically
session_start();


// ============================================================================
// EXAMPLE 4: JWT Token Blacklisting
// ============================================================================
// File: route_guard.php
// Add at the top:

require_once __DIR__ . '/utils/redis_jwt.php';

$redisJWT = new RedisJWT();

// In JWT verification section:
if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
    // Check if token is blacklisted
    if ($redisJWT->isBlacklisted($_SESSION['token'])) {
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Session expired. Please log in again.');
        exit;
    }
    
    // Verify JWT
    try {
        $decoded = JWT::decode($_SESSION['token'], new Key($jwt_key, 'HS256'));
    } catch (Exception $e) {
        // Blacklist invalid token
        $redisJWT->blacklistToken($_SESSION['token']);
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?message=Invalid session. Please log in again.');
        exit;
    }
}

// In logout.php (create if doesn't exist):
require_once __DIR__ . '/utils/redis_jwt.php';
require_once __DIR__ . '/route_guard.php';

$redisJWT = new RedisJWT();

if (isset($_SESSION['token'])) {
    // Blacklist the token on logout
    $redisJWT->blacklistToken($_SESSION['token']);
}

session_destroy();
header('Location: login.php?message=Logged out successfully.');
exit;


// ============================================================================
// EXAMPLE 5: Real-time Notifications
// ============================================================================
// File: utils/notifications_helper.php (create new)

require_once __DIR__ . '/redis_notifications.php';

function sendUserNotification($user_id, $type, $message, $data = []) {
    $notifications = new RedisNotifications();
    return $notifications->sendNotification($user_id, $type, $message, $data);
}

// Usage when application status changes:
// File: ajax_admin_updates.php or wherever you update application status

require_once __DIR__ . '/utils/notifications_helper.php';

// After updating application status:
if ($statusUpdated) {
    sendUserNotification(
        $user_id,
        'application_status',
        "Your application status has been updated to: {$newStatus}",
        [
            'status' => $newStatus,
            'application_id' => $application_id,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    );
}

// Create API endpoint: ajax_get_notifications.php
require_once __DIR__ . '/route_guard.php';
require_once __DIR__ . '/utils/redis_notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$notifications = new RedisNotifications();
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

$userNotifications = $notifications->getNotifications($_SESSION['user_id'], 50, $unreadOnly);
$unreadCount = $notifications->getUnreadCount($_SESSION['user_id']);

echo json_encode([
    'success' => true,
    'notifications' => $userNotifications,
    'unread_count' => $unreadCount
]);


// ============================================================================
// EXAMPLE 6: Chatbot with Redis Storage
// ============================================================================
// File: chatbot_api.php
// Add at the top:

require_once __DIR__ . '/utils/redis_chatbot.php';

$redisChatbot = new RedisChatbot(86400); // 24 hours

// Get or create session ID
if (!isset($_SESSION['chatbot_session_id'])) {
    $_SESSION['chatbot_session_id'] = uniqid('chat_', true);
}
$session_id = $_SESSION['chatbot_session_id'];

// Store user message
$redisChatbot->addMessage(
    $_SESSION['user_id'] ?? 0,
    $session_id,
    'user',
    $userMessage
);

// Get conversation context for AI
$context = $redisChatbot->getContext($session_id, 10);

// Add context to prompt
$prompt = "Previous conversation:\n{$context}\n\nUser: {$userMessage}\nAssistant:";

// ... send to Gemini API ...

// Store assistant response
$redisChatbot->addMessage(
    $_SESSION['user_id'] ?? 0,
    $session_id,
    'assistant',
    $assistantResponse
);


// ============================================================================
// EXAMPLE 7: Job Queue for Background Processing
// ============================================================================
// File: utils/job_helpers.php (create new)

require_once __DIR__ . '/redis_queue.php';

function queueOCRJob($user_id, $document_id, $file_path) {
    $queue = new RedisQueue('ocr');
    return $queue->push('process_ocr', [
        'user_id' => $user_id,
        'document_id' => $document_id,
        'file_path' => $file_path
    ], 10); // Priority 10
}

function queueEmailJob($to, $subject, $body) {
    $queue = new RedisQueue('emails');
    return $queue->push('send_email', [
        'to' => $to,
        'subject' => $subject,
        'body' => $body
    ]);
}

// Usage when processing documents:
require_once __DIR__ . '/utils/job_helpers.php';

// Instead of processing immediately, queue it:
$jobId = queueOCRJob($user_id, $document_id, $file_path);
echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => 'Document queued for processing']);

// Worker script: workers/process_queue.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../connect/connection.php';
require_once __DIR__ . '/../utils/redis_queue.php';

$queue = new RedisQueue('ocr');

while (true) {
    $job = $queue->pop();
    
    if ($job === null) {
        sleep(5);
        continue;
    }
    
    try {
        switch ($job['type']) {
            case 'process_ocr':
                // Process OCR
                // ... your OCR processing code ...
                $queue->complete($job['id'], ['status' => 'success']);
                break;
        }
    } catch (Exception $e) {
        $queue->fail($job['id'], $e->getMessage(), true);
    }
}


// ============================================================================
// EXAMPLE 8: Cache Invalidation
// ============================================================================
// When data changes, invalidate related cache:

require_once __DIR__ . '/utils/query_cache.php';

$queryCache = new QueryCache();

// After updating application status:
if ($statusUpdated) {
    // Invalidate related caches
    $queryCache->invalidate("analytics:*");
    $queryCache->invalidate("applicants:*");
    
    // Or clear all cache
    // $queryCache->clear();
}


// ============================================================================
// NOTES:
// ============================================================================
// 
// 1. All Redis operations gracefully fail if Redis is unavailable
//    (they return null/false but don't crash the application)
//
// 2. Cache TTLs should be adjusted based on your needs:
//    - Frequently changing data: 60-300 seconds
//    - Moderately changing data: 300-1800 seconds
//    - Rarely changing data: 1800-3600 seconds
//
// 3. Rate limits should be tuned based on your security requirements
//
// 4. Monitor Redis memory usage and adjust TTLs accordingly
//
// 5. Use Redis for temporary data, MySQL for permanent data
//

