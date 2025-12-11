<?php
/**
 * Redis Cache Manager
 * Replacement for file-based CacheManager using Redis
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisCache {
    private $redis;
    private $defaultTTL = 300; // 5 minutes default

    public function __construct($defaultTTL = 300) {
        $this->redis = RedisManager::getInstance();
        $this->defaultTTL = $defaultTTL;
    }

    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public function get($key) {
        return $this->redis->get("cache:{$key}");
    }

    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default: 300)
     * @return bool True on success, false on failure
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }
        return $this->redis->set("cache:{$key}", $value, $ttl);
    }

    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete($key) {
        return $this->redis->delete("cache:{$key}");
    }

    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function exists($key) {
        return $this->redis->exists("cache:{$key}");
    }

    /**
     * Clear all cache
     * 
     * @return bool
     */
    public function clear() {
        $keys = $this->redis->keys("cache:*");
        if (empty($keys)) {
            return true;
        }
        return $this->redis->getClient()->del($keys) > 0;
    }

    /**
     * Clear cache by pattern
     * 
     * @param string $pattern Pattern to match (e.g., "analytics:*")
     * @return bool
     */
    public function clearPattern($pattern) {
        $keys = $this->redis->keys("cache:{$pattern}");
        if (empty($keys)) {
            return true;
        }
        return $this->redis->getClient()->del($keys) > 0;
    }

    /**
     * Get or set cached value (cache-aside pattern)
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to generate value if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed
     */
    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = call_user_func($callback);
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}

