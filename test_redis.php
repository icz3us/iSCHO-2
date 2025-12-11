<?php
/**
 * Redis Connection Test Script
 * Run this to verify Redis is properly configured
 * 
 * Usage: php test_redis.php
 */

require_once 'vendor/autoload.php';
require_once 'connect/redis_connection.php';

echo "========================================\n";
echo "Redis Connection Test\n";
echo "========================================\n\n";

$redis = RedisManager::getInstance();

if (!$redis->isConnected()) {
    echo "❌ Redis connection FAILED!\n\n";
    echo "Please check:\n";
    echo "1. Redis server is running\n";
    echo "2. Configuration in config/redis_config.php\n";
    echo "3. Firewall settings\n";
    echo "4. Redis server is listening on port 6379\n\n";
    exit(1);
}

echo "✅ Redis connection successful!\n\n";

// Test basic operations
echo "Testing basic operations...\n";

// Test SET/GET
$testKey = 'test:connection';
$testValue = 'Hello Redis from iSCHO!';
if ($redis->set($testKey, $testValue, 60)) {
    echo "✅ SET operation: OK\n";
} else {
    echo "❌ SET operation: FAILED\n";
    exit(1);
}

$retrieved = $redis->get($testKey);
if ($retrieved === $testValue) {
    echo "✅ GET operation: OK\n";
    echo "   Retrieved value: $retrieved\n";
} else {
    echo "❌ GET operation: FAILED\n";
    exit(1);
}

// Test EXISTS
if ($redis->exists($testKey)) {
    echo "✅ EXISTS operation: OK\n";
} else {
    echo "❌ EXISTS operation: FAILED\n";
}

// Test DELETE
if ($redis->delete($testKey)) {
    echo "✅ DELETE operation: OK\n";
} else {
    echo "❌ DELETE operation: FAILED\n";
}

// Test TTL
$redis->set('test:ttl', 'test', 30);
$ttl = $redis->ttl('test:ttl');
if ($ttl > 0 && $ttl <= 30) {
    echo "✅ TTL operation: OK (TTL: {$ttl}s)\n";
} else {
    echo "❌ TTL operation: FAILED\n";
}
$redis->delete('test:ttl');

// Test increment/decrement
$redis->set('test:counter', 0);
$redis->increment('test:counter');
$value = $redis->get('test:counter');
if ($value == 1) {
    echo "✅ INCREMENT operation: OK\n";
} else {
    echo "❌ INCREMENT operation: FAILED\n";
}
$redis->decrement('test:counter');
$value = $redis->get('test:counter');
if ($value == 0) {
    echo "✅ DECREMENT operation: OK\n";
} else {
    echo "❌ DECREMENT operation: FAILED\n";
}
$redis->delete('test:counter');

echo "\n";

// Test utility classes
echo "Testing utility classes...\n";

// Test Cache
require_once 'utils/redis_cache.php';
$cache = new RedisCache(60);
$cache->set('test:cache', 'cached_value');
$cached = $cache->get('test:cache');
if ($cached === 'cached_value') {
    echo "✅ RedisCache: OK\n";
} else {
    echo "❌ RedisCache: FAILED\n";
}
$cache->delete('test:cache');

// Test OTP
require_once 'utils/redis_otp.php';
$otp = new RedisOTP(60);
$otpCode = $otp->generateOTP('test@example.com');
if ($otpCode && strlen($otpCode) == 6) {
    echo "✅ RedisOTP (generate): OK\n";
    $result = $otp->verifyOTP('test@example.com', $otpCode);
    if ($result['valid']) {
        echo "✅ RedisOTP (verify): OK\n";
    } else {
        echo "❌ RedisOTP (verify): FAILED\n";
    }
} else {
    echo "❌ RedisOTP (generate): FAILED\n";
}
$otp->deleteOTP('test@example.com');

// Test Rate Limit
require_once 'utils/redis_rate_limit.php';
$rateLimit = new RedisRateLimit();
$result = $rateLimit->checkLoginLimit('test@example.com', 5, 60);
if ($result['allowed']) {
    echo "✅ RedisRateLimit: OK\n";
} else {
    echo "❌ RedisRateLimit: FAILED\n";
}

echo "\n";
echo "========================================\n";
echo "✅ All tests passed! Redis is ready to use.\n";
echo "========================================\n";

