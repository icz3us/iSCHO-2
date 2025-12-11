<?php
/**
 * Redis Rate Limiter
 * Implements rate limiting using Redis
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisRateLimit {
    private $redis;

    public function __construct() {
        $this->redis = RedisManager::getInstance();
    }

    /**
     * Check if action is allowed (sliding window algorithm)
     * 
     * @param string $key Rate limit key (e.g., "login:user@example.com" or "api:192.168.1.1")
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return array Result with 'allowed', 'remaining', 'reset' keys
     */
    public function checkLimit($key, $maxAttempts, $windowSeconds) {
        $redisKey = "ratelimit:{$key}";
        $current = time();
        $windowStart = $current - $windowSeconds;

        try {
            $client = $this->redis->getClient();
            if (!$client) {
                // If Redis is not available, allow the request (fail open)
                return [
                    'allowed' => true,
                    'remaining' => $maxAttempts,
                    'reset' => $current + $windowSeconds
                ];
            }

            // Use sorted set to track attempts with timestamps
            $zsetKey = $redisKey . ":attempts";
            
            // Remove old attempts outside the window
            $client->zremrangebyscore($zsetKey, 0, $windowStart);
            
            // Count current attempts in window
            $attempts = $client->zcard($zsetKey);
            
            if ($attempts >= $maxAttempts) {
                // Get oldest attempt to calculate reset time
                $oldest = $client->zrange($zsetKey, 0, 0, ['WITHSCORES' => true]);
                $resetTime = !empty($oldest) ? (int)$oldest[1] + $windowSeconds : $current + $windowSeconds;
                
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset' => $resetTime,
                    'message' => "Rate limit exceeded. Try again after " . date('H:i:s', $resetTime)
                ];
            }

            // Add current attempt
            $client->zadd($zsetKey, $current, $current . ':' . uniqid());
            
            // Set expiration on the sorted set
            $client->expire($zsetKey, $windowSeconds);

            return [
                'allowed' => true,
                'remaining' => $maxAttempts - $attempts - 1,
                'reset' => $current + $windowSeconds
            ];
        } catch (Exception $e) {
            error_log("Rate Limit Error: " . $e->getMessage());
            // Fail open - allow request if Redis fails
            return [
                'allowed' => true,
                'remaining' => $maxAttempts,
                'reset' => $current + $windowSeconds
            ];
        }
    }

    /**
     * Check login rate limit
     * 
     * @param string $identifier Email or IP address
     * @param int $maxAttempts Maximum attempts (default: 5)
     * @param int $windowSeconds Time window in seconds (default: 900 = 15 minutes)
     * @return array
     */
    public function checkLoginLimit($identifier, $maxAttempts = 5, $windowSeconds = 900) {
        return $this->checkLimit("login:{$identifier}", $maxAttempts, $windowSeconds);
    }

    /**
     * Check OTP request rate limit
     * 
     * @param string $email Email address
     * @param int $maxAttempts Maximum attempts (default: 3)
     * @param int $windowSeconds Time window in seconds (default: 3600 = 1 hour)
     * @return array
     */
    public function checkOTPLimit($email, $maxAttempts = 3, $windowSeconds = 3600) {
        return $this->checkLimit("otp:{$email}", $maxAttempts, $windowSeconds);
    }

    /**
     * Check API rate limit
     * 
     * @param string $identifier IP address or user ID
     * @param int $maxAttempts Maximum attempts (default: 100)
     * @param int $windowSeconds Time window in seconds (default: 3600 = 1 hour)
     * @return array
     */
    public function checkAPILimit($identifier, $maxAttempts = 100, $windowSeconds = 3600) {
        return $this->checkLimit("api:{$identifier}", $maxAttempts, $windowSeconds);
    }

    /**
     * Reset rate limit for a key
     * 
     * @param string $key Rate limit key
     * @return bool
     */
    public function resetLimit($key) {
        $redisKey = "ratelimit:{$key}";
        $zsetKey = $redisKey . ":attempts";
        return $this->redis->delete($zsetKey);
    }

    /**
     * Get remaining attempts
     * 
     * @param string $key Rate limit key
     * @param int $maxAttempts Maximum attempts
     * @param int $windowSeconds Time window in seconds
     * @return int
     */
    public function getRemaining($key, $maxAttempts, $windowSeconds) {
        $result = $this->checkLimit($key, $maxAttempts, $windowSeconds);
        return $result['remaining'];
    }
}

