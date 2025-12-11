<?php
/**
 * Redis JWT Token Blacklist Manager
 * Manages blacklisted JWT tokens using Redis
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisJWT {
    private $redis;

    public function __construct() {
        $this->redis = RedisManager::getInstance();
    }

    /**
     * Blacklist a JWT token
     * 
     * @param string $token JWT token
     * @param int $ttl Time to live in seconds (should match token expiration)
     * @return bool
     */
    public function blacklistToken($token, $ttl = null) {
        // If TTL not provided, try to extract from token
        if ($ttl === null) {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                if (isset($payload['exp'])) {
                    $ttl = max(0, $payload['exp'] - time());
                } else {
                    $ttl = 1800; // Default 30 minutes
                }
            } else {
                $ttl = 1800; // Default 30 minutes
            }
        }

        // Store token hash in blacklist
        $tokenHash = hash('sha256', $token);
        return $this->redis->set("jwt:blacklist:{$tokenHash}", time(), $ttl);
    }

    /**
     * Check if token is blacklisted
     * 
     * @param string $token JWT token
     * @return bool True if blacklisted, false otherwise
     */
    public function isBlacklisted($token) {
        $tokenHash = hash('sha256', $token);
        return $this->redis->exists("jwt:blacklist:{$tokenHash}");
    }

    /**
     * Remove token from blacklist (if needed)
     * 
     * @param string $token JWT token
     * @return bool
     */
    public function removeFromBlacklist($token) {
        $tokenHash = hash('sha256', $token);
        return $this->redis->delete("jwt:blacklist:{$tokenHash}");
    }

    /**
     * Blacklist all tokens for a user (on password change, etc.)
     * 
     * @param int $user_id User ID
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function blacklistUserTokens($user_id, $ttl = 1800) {
        $key = "jwt:blacklist:user:{$user_id}";
        $this->redis->set($key, time(), $ttl);
        
        // Store user ID for quick lookup
        return true;
    }

    /**
     * Check if user's tokens are blacklisted
     * 
     * @param int $user_id User ID
     * @return bool
     */
    public function isUserBlacklisted($user_id) {
        return $this->redis->exists("jwt:blacklist:user:{$user_id}");
    }
}

