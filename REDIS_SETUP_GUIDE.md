# Redis Integration Setup Guide for iSCHO

This guide will walk you through setting up Redis for the iSCHO application with all recommended features.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Integration Steps](#integration-steps)
5. [Feature Implementation](#feature-implementation)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

- PHP 7.4 or higher
- Composer installed
- XAMPP (or similar PHP environment)
- Windows 10/11 (for this guide)

---

## Installation

### Step 1: Install Redis Server for Windows

**Option A: Using Memurai (Recommended for Windows)**
1. Download Memurai from: https://www.memurai.com/get-memurai
2. Install Memurai (it's Redis-compatible)
3. Memurai will run as a Windows service automatically

**Option B: Using Redis for Windows (Legacy)**
1. Download from: https://github.com/microsoftarchive/redis/releases
2. Extract and run `redis-server.exe`
3. Keep the window open (or install as service)

**Option C: Using Docker (If you have Docker)**
```bash
docker run -d -p 6379:6379 --name redis redis:latest
```

### Step 2: Install PHP Redis Extension

**For XAMPP:**

1. **Check your PHP version:**
   ```bash
   php -v
   ```

2. **Download php_redis.dll:**
   - Visit: https://pecl.php.net/package/redis
   - Download the appropriate version for your PHP version and architecture (x86 or x64)
   - Or use precompiled DLLs from: https://windows.php.net/downloads/pecl/releases/redis/

3. **Install the extension:**
   - Copy `php_redis.dll` to `C:\xampp\php\ext\`
   - Open `C:\xampp\php\php.ini`
   - Add this line: `extension=redis`
   - Restart Apache

**Alternative: Use Predis (Pure PHP, no extension needed)**
- This is already included in `composer.json`
- No PHP extension required!

### Step 3: Install Composer Dependencies

Open terminal in your project directory and run:

```bash
composer install
```

This will install `predis/predis` which is a pure PHP Redis client (no extension needed).

---

## Configuration

### Step 1: Create Redis Configuration File

1. Copy the example config file:
   ```bash
   copy config\redis_config.php.example config\redis_config.php
   ```

2. Edit `config/redis_config.php`:
   ```php
   <?php
   return [
       'host' => '127.0.0.1',      // Redis server host
       'port' => 6379,              // Redis server port
       'timeout' => 5,               // Connection timeout
       'password' => null,           // Set if Redis requires password
       'database' => 0,              // Redis database number (0-15)
       'prefix' => 'ischo:'          // Key prefix for all Redis keys
   ];
   ```

### Step 2: Verify Redis Connection

Create a test file `test_redis.php`:

```php
<?php
require_once 'vendor/autoload.php';
require_once 'connect/redis_connection.php';

$redis = RedisManager::getInstance();

if ($redis->isConnected()) {
    echo "✅ Redis connection successful!\n";
    
    // Test set/get
    $redis->set('test', 'Hello Redis!', 60);
    $value = $redis->get('test');
    echo "Test value: $value\n";
    
    // Clean up
    $redis->delete('test');
    echo "✅ Redis is working correctly!\n";
} else {
    echo "❌ Redis connection failed!\n";
    echo "Please check:\n";
    echo "1. Redis server is running\n";
    echo "2. Configuration in config/redis_config.php\n";
    echo "3. Firewall settings\n";
}
```

Run it:
```bash
php test_redis.php
```

---

## Integration Steps

### Phase 1: High Impact, Easy Integration

#### 1.1 Replace File-Based Cache with Redis

**File:** `cache/CacheManager.php` (already exists)

**Update your code to use Redis:**

```php
// Old way (file-based)
require_once 'cache/CacheManager.php';
$cache = new CacheManager();

// New way (Redis-based)
require_once 'utils/redis_cache.php';
$cache = new RedisCache(300); // 5 minutes default TTL
```

**Example usage:**
```php
// Get cached value
$data = $cache->get('analytics:applicants');

// Set cached value
$cache->set('analytics:applicants', $analyticsData, 600); // 10 minutes

// Cache-aside pattern
$data = $cache->remember('analytics:applicants', function() {
    // Expensive query here
    return $expensiveData;
}, 600);
```

#### 1.2 Implement OTP Management with Redis

**File:** `login.php`

**Add at the top:**
```php
require_once __DIR__ . '/utils/redis_otp.php';
require_once __DIR__ . '/utils/redis_rate_limit.php';

$redisOTP = new RedisOTP(600); // 10 minutes TTL
$rateLimit = new RedisRateLimit();
```

**Replace OTP generation:**
```php
// OLD: Database storage
$stmt = $pdo->prepare("INSERT INTO otp_verifications (email, otp, expires_at) VALUES (?, ?, ?)");

// NEW: Redis storage
// Check rate limit first
$rateCheck = $rateLimit->checkOTPLimit($email, 3, 3600); // 3 requests per hour
if (!$rateCheck['allowed']) {
    $register_error = "Too many OTP requests. Please try again later.";
} else {
    $otp = $redisOTP->generateOTP($email, 600); // 10 minutes
    if ($otp) {
        $email_result = sendOTP($email, $otp);
    }
}
```

**Replace OTP verification:**
```php
// OLD: Database verification
$stmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE email = ? AND otp = ?");

// NEW: Redis verification
$result = $redisOTP->verifyOTP($email, $otp_code, 5); // Max 5 attempts
if ($result['valid']) {
    // OTP is valid, proceed with registration
} else {
    $_SESSION['otp_error'] = $result['message'];
}
```

#### 1.3 Add Query Result Caching

**File:** `admindashboard.php` (or any file with analytics)

**Add at the top:**
```php
require_once __DIR__ . '/utils/query_cache.php';

$queryCache = new QueryCache(300); // 5 minutes default
```

**Wrap expensive queries:**
```php
// OLD: Direct query
$stmt = $pdo->prepare("SELECT * FROM users_info WHERE application_status = ?");
$stmt->execute(['Approved']);
$approved = $stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Cached query
$approved = cachedQuery(
    $pdo,
    "SELECT * FROM users_info WHERE application_status = ?",
    ['Approved'],
    $queryCache,
    600 // Cache for 10 minutes
);
```

**Or use the remember pattern:**
```php
$analytics = $queryCache->remember(
    "analytics:applicants:{$programId}",
    ['program_id' => $programId],
    function() use ($pdo, $programId) {
        // Expensive analytics query
        $stmt = $pdo->prepare("SELECT ... complex query ...");
        $stmt->execute([$programId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    },
    600 // 10 minutes cache
);
```

### Phase 2: Medium Impact Integration

#### 2.1 Implement Redis Session Storage

**File:** `route_guard.php`

**Add at the top (before session_start()):**
```php
require_once __DIR__ . '/utils/redis_session.php';

// Initialize Redis session handler
initRedisSession(1800); // 30 minutes session TTL
```

**Note:** This replaces file-based sessions with Redis. Sessions will be stored in Redis instead of files.

#### 2.2 Add Rate Limiting

**File:** `login.php`

**Add rate limiting to login:**
```php
require_once __DIR__ . '/utils/redis_rate_limit.php';

$rateLimit = new RedisRateLimit();

// In login handler
$identifier = $_POST['email'] ?? $_SERVER['REMOTE_ADDR'];
$rateCheck = $rateLimit->checkLoginLimit($identifier, 5, 900); // 5 attempts per 15 minutes

if (!$rateCheck['allowed']) {
    $login_error = "Too many login attempts. Please try again after " . date('H:i:s', $rateCheck['reset']);
} else {
    // Proceed with login
    // ... existing login code ...
}
```

#### 2.3 Implement JWT Token Blacklisting

**File:** `route_guard.php`

**Add at the top:**
```php
require_once __DIR__ . '/utils/redis_jwt.php';

$redisJWT = new RedisJWT();
```

**Check token blacklist:**
```php
// In JWT verification
if (isset($_SESSION['token'])) {
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
```

**File:** `logout.php` (create if doesn't exist)

```php
<?php
require_once __DIR__ . '/utils/redis_jwt.php';
require_once __DIR__ . '/route_guard.php';

$redisJWT = new RedisJWT();

if (isset($_SESSION['token'])) {
    // Blacklist the token
    $redisJWT->blacklistToken($_SESSION['token']);
}

session_destroy();
header('Location: login.php?message=Logged out successfully.');
exit;
```

### Phase 3: Advanced Features

#### 3.1 Real-time Notifications

**File:** `utils/notifications_helper.php` (create new)

```php
<?php
require_once __DIR__ . '/redis_notifications.php';

function sendUserNotification($user_id, $type, $message, $data = []) {
    $notifications = new RedisNotifications();
    return $notifications->sendNotification($user_id, $type, $message, $data);
}

function getUserNotifications($user_id, $limit = 50) {
    $notifications = new RedisNotifications();
    return $notifications->getNotifications($user_id, $limit);
}
```

**Usage example (in application status update):**
```php
require_once __DIR__ . '/utils/notifications_helper.php';

// When application status changes
sendUserNotification(
    $user_id,
    'application_status',
    'Your application status has been updated to: ' . $newStatus,
    ['status' => $newStatus, 'application_id' => $app_id]
);
```

**Create API endpoint:** `ajax_get_notifications.php`

```php
<?php
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
    'notifications' => $userNotifications,
    'unread_count' => $unreadCount
]);
```

#### 3.2 Chatbot Conversation Storage

**File:** `chatbot_api.php`

**Update to use Redis:**
```php
require_once __DIR__ . '/utils/redis_chatbot.php';

$redisChatbot = new RedisChatbot(86400); // 24 hours

// Get or create session ID
$session_id = $_SESSION['chatbot_session_id'] ?? uniqid('chat_', true);
$_SESSION['chatbot_session_id'] = $session_id;

// Store user message
$redisChatbot->addMessage(
    $_SESSION['user_id'] ?? 0,
    $session_id,
    'user',
    $userMessage
);

// Get conversation context
$context = $redisChatbot->getContext($session_id, 10);

// ... send to Gemini API ...

// Store assistant response
$redisChatbot->addMessage(
    $_SESSION['user_id'] ?? 0,
    $session_id,
    'assistant',
    $assistantResponse
);
```

#### 3.3 Job Queue System

**File:** `utils/job_processor.php` (create new)

```php
<?php
require_once __DIR__ . '/redis_queue.php';

// Example: Queue OCR processing
function queueOCRJob($user_id, $document_id, $file_path) {
    $queue = new RedisQueue('ocr');
    return $queue->push('process_ocr', [
        'user_id' => $user_id,
        'document_id' => $document_id,
        'file_path' => $file_path
    ], 10); // Priority 10
}

// Example: Queue email sending
function queueEmailJob($to, $subject, $body) {
    $queue = new RedisQueue('emails');
    return $queue->push('send_email', [
        'to' => $to,
        'subject' => $subject,
        'body' => $body
    ]);
}
```

**Create worker script:** `workers/process_queue.php`

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../connect/connection.php';
require_once __DIR__ . '/../utils/redis_queue.php';

// Run as background process or cron job
$queue = new RedisQueue('ocr');

while (true) {
    $job = $queue->pop();
    
    if ($job === null) {
        sleep(5); // Wait 5 seconds if no jobs
        continue;
    }
    
    try {
        // Process job based on type
        switch ($job['type']) {
            case 'process_ocr':
                // Process OCR
                // ... your OCR processing code ...
                $queue->complete($job['id'], ['status' => 'success']);
                break;
                
            case 'send_email':
                // Send email
                // ... your email sending code ...
                $queue->complete($job['id'], ['status' => 'sent']);
                break;
        }
    } catch (Exception $e) {
        $queue->fail($job['id'], $e->getMessage(), true); // Retry on failure
    }
}
```

---

## Testing

### Test Redis Connection

```bash
php test_redis.php
```

### Test Each Feature

1. **Test Cache:**
   ```php
   require_once 'utils/redis_cache.php';
   $cache = new RedisCache();
   $cache->set('test', 'Hello', 60);
   echo $cache->get('test'); // Should output: Hello
   ```

2. **Test OTP:**
   ```php
   require_once 'utils/redis_otp.php';
   $otp = new RedisOTP();
   $code = $otp->generateOTP('test@example.com');
   var_dump($otp->verifyOTP('test@example.com', $code));
   ```

3. **Test Rate Limiting:**
   ```php
   require_once 'utils/redis_rate_limit.php';
   $rateLimit = new RedisRateLimit();
   for ($i = 0; $i < 6; $i++) {
       $result = $rateLimit->checkLoginLimit('test@example.com', 5, 60);
       echo "Attempt " . ($i + 1) . ": " . ($result['allowed'] ? 'Allowed' : 'Blocked') . "\n";
   }
   ```

---

## Troubleshooting

### Redis Connection Failed

**Problem:** `Redis connection failed`

**Solutions:**
1. Check if Redis server is running:
   ```bash
   redis-cli ping
   # Should return: PONG
   ```

2. Check firewall settings (Windows Firewall)
3. Verify configuration in `config/redis_config.php`
4. Check Redis server logs

### Predis Library Not Found

**Problem:** `Class 'Predis\Client' not found`

**Solution:**
```bash
composer install
```

### Session Not Working

**Problem:** Sessions not persisting

**Solution:**
- Make sure `initRedisSession()` is called before `session_start()`
- Check Redis connection
- Verify session handler is set correctly

### OTP Not Working

**Problem:** OTP verification fails

**Solution:**
- Check Redis connection
- Verify OTP TTL settings
- Check rate limiting isn't blocking requests
- Review error logs

---

## Performance Benefits

After implementing Redis, you should see:

- **Faster page loads:** Cached queries reduce database load
- **Better scalability:** Redis handles high concurrency
- **Real-time features:** Pub/sub enables live updates
- **Reduced database load:** Caching and session storage offload MySQL
- **Automatic cleanup:** TTL-based expiration

---

## Monitoring

### Check Redis Memory Usage

```bash
redis-cli info memory
```

### Monitor Redis Commands

```bash
redis-cli monitor
```

### Check Connected Clients

```bash
redis-cli client list
```

---

## Security Considerations

1. **Set Redis Password:** Update `config/redis_config.php` with a password
2. **Bind to Localhost:** Only allow local connections (default)
3. **Firewall Rules:** Restrict Redis port (6379) access
4. **Key Prefixes:** All keys use `ischo:` prefix to avoid conflicts

---

## Next Steps

1. ✅ Install Redis server
2. ✅ Install Composer dependencies
3. ✅ Configure Redis
4. ✅ Test connection
5. ✅ Integrate Phase 1 features
6. ✅ Integrate Phase 2 features
7. ✅ Integrate Phase 3 features
8. ✅ Monitor performance
9. ✅ Optimize cache TTLs

---

## Support

For issues or questions:
1. Check error logs: `logs/` directory
2. Review Redis logs
3. Test with `test_redis.php`
4. Verify configuration files

---

**Last Updated:** 2025-01-27
**Version:** 2.0

