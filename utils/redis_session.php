<?php
/**
 * Redis Session Handler
 * Custom session handler using Redis instead of file-based sessions
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/../connect/redis_connection.php';

class RedisSessionHandler implements SessionHandlerInterface {
    private $redis;
    private $ttl = 1800; // 30 minutes default

    public function __construct($ttl = 1800) {
        $this->redis = RedisManager::getInstance();
        $this->ttl = $ttl;
    }

    /**
     * Initialize session
     * 
     * @param string $save_path
     * @param string $name
     * @return bool
     */
    public function open($save_path, $name) {
        return $this->redis->isConnected();
    }

    /**
     * Close session
     * 
     * @return bool
     */
    public function close() {
        return true;
    }

    /**
     * Read session data
     * 
     * @param string $session_id
     * @return string
     */
    public function read($session_id) {
        try {
            if (!$this->redis->isConnected()) {
                return '';
            }
            $data = $this->redis->get("session:{$session_id}");
            return $data !== null ? $data : '';
        } catch (Exception $e) {
            error_log("Redis session read error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Write session data
     * 
     * @param string $session_id
     * @param string $session_data
     * @return bool
     */
    public function write($session_id, $session_data) {
        try {
            if (!$this->redis->isConnected()) {
                return false;
            }
            return $this->redis->set("session:{$session_id}", $session_data, $this->ttl);
        } catch (Exception $e) {
            error_log("Redis session write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Destroy session
     * 
     * @param string $session_id
     * @return bool
     */
    public function destroy($session_id) {
        try {
            if (!$this->redis->isConnected()) {
                return false;
            }
            return $this->redis->delete("session:{$session_id}");
        } catch (Exception $e) {
            error_log("Redis session destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Garbage collection
     * 
     * @param int $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime) {
        // Redis handles expiration automatically, so this is a no-op
        return true;
    }
}

/**
 * Initialize Redis session handler
 * Call this function before session_start() to use Redis for sessions
 * Falls back to file-based sessions if Redis is not available
 * 
 * @param int $ttl Session TTL in seconds (default: 1800 = 30 minutes)
 * @return bool True if Redis session handler is active, false if falling back to file sessions
 */
function initRedisSession($ttl = 1800) {
    try {
        $redis = RedisManager::getInstance();
        
        // Check if Redis is available
        if (!$redis->isConnected()) {
            // Redis not available, use default file-based sessions
            error_log("Redis not available, using file-based sessions");
            return false;
        }
        
        // Redis is available, set up Redis session handler
        $handler = new RedisSessionHandler($ttl);
        $result = session_set_save_handler($handler, true);
        
        if ($result) {
            error_log("Redis session handler initialized successfully");
        }
        
        return $result;
    } catch (Exception $e) {
        // If any error occurs, fall back to file-based sessions
        error_log("Redis session handler error: " . $e->getMessage() . " - Falling back to file sessions");
        return false;
    }
}

