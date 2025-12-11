<?php

class RedisManager {
    private static $instance = null;
    private $redis = null;
    private $connected = false;
    private $config = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 5,
        'password' => null,
        'database' => 0,
        'prefix' => 'ischo:'
    ];

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->loadConfig();
        $this->connect();
    }

    /**
     * Get singleton instance
     * 
     * @return RedisManager
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load Redis configuration from environment or config file
     */
    private function loadConfig() {
        // Try to load from environment variables first
        $this->config['host'] = getenv('REDIS_HOST') ?: '127.0.0.1';
        $this->config['port'] = (int)(getenv('REDIS_PORT') ?: 6379);
        $this->config['password'] = getenv('REDIS_PASSWORD') ?: null;
        $this->config['database'] = (int)(getenv('REDIS_DATABASE') ?: 0);
        $this->config['prefix'] = getenv('REDIS_PREFIX') ?: 'ischo:';

        // Try to load from config file if exists
        $configFile = __DIR__ . '/../config/redis_config.php';
        if (file_exists($configFile)) {
            $fileConfig = require $configFile;
            $this->config = array_merge($this->config, $fileConfig);
        }
    }

    /**
     * Connect to Redis server
     * 
     * @return bool True if connected, false otherwise
     */
    private function connect() {
        try {
            if (!class_exists('Predis\Client')) {
                error_log("Redis Error: Predis library not found. Run 'composer install'");
                return false;
            }

            $this->redis = new Predis\Client([
                'scheme' => 'tcp',
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'timeout' => $this->config['timeout'],
                'password' => $this->config['password'],
                'database' => $this->config['database']
            ]);

            // Test connection
            $this->redis->ping();
            $this->connected = true;
            
            return true;
        } catch (Exception $e) {
            $this->connected = false;
            error_log("Redis Connection Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Redis client instance
     * 
     * @return Predis\Client|null
     */
    public function getClient() {
        if (!$this->connected && !$this->connect()) {
            return null;
        }
        return $this->redis;
    }

    /**
     * Check if Redis is connected
     * 
     * @return bool
     */
    public function isConnected() {
        if (!$this->connected) {
            return $this->connect();
        }
        return $this->connected;
    }

    /**
     * Add prefix to key
     * 
     * @param string $key
     * @return string
     */
    public function prefixKey($key) {
        return $this->config['prefix'] . $key;
    }

    /**
     * Get value from Redis
     * 
     * @param string $key
     * @return mixed|null
     */
    public function get($key) {
        if (!$this->isConnected()) {
            return null;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            $value = $this->redis->get($prefixedKey);
            
            if ($value === null) {
                return null;
            }

            // Try to unserialize if it's a serialized value
            $unserialized = @unserialize($value);
            return $unserialized !== false ? $unserialized : $value;
        } catch (Exception $e) {
            error_log("Redis Get Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set value in Redis
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool
     */
    public function set($key, $value, $ttl = 0) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            $serialized = is_string($value) ? $value : serialize($value);
            
            if ($ttl > 0) {
                return $this->redis->setex($prefixedKey, $ttl, $serialized);
            } else {
                return $this->redis->set($prefixedKey, $serialized);
            }
        } catch (Exception $e) {
            error_log("Redis Set Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete key from Redis
     * 
     * @param string $key
     * @return bool
     */
    public function delete($key) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            return $this->redis->del($prefixedKey) > 0;
        } catch (Exception $e) {
            error_log("Redis Delete Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if key exists
     * 
     * @param string $key
     * @return bool
     */
    public function exists($key) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            return $this->redis->exists($prefixedKey) > 0;
        } catch (Exception $e) {
            error_log("Redis Exists Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set expiration on key
     * 
     * @param string $key
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function expire($key, $ttl) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            return $this->redis->expire($prefixedKey, $ttl);
        } catch (Exception $e) {
            error_log("Redis Expire Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment value
     * 
     * @param string $key
     * @param int $increment
     * @return int|false
     */
    public function increment($key, $increment = 1) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            return $this->redis->incrby($prefixedKey, $increment);
        } catch (Exception $e) {
            error_log("Redis Increment Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrement value
     * 
     * @param string $key
     * @param int $decrement
     * @return int|false
     */
    public function decrement($key, $decrement = 1) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            return $this->redis->decrby($prefixedKey, $decrement);
        } catch (Exception $e) {
            error_log("Redis Decrement Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get multiple keys
     * 
     * @param array $keys
     * @return array
     */
    public function mget($keys) {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            $prefixedKeys = array_map([$this, 'prefixKey'], $keys);
            $values = $this->redis->mget($prefixedKeys);
            
            $result = [];
            foreach ($values as $index => $value) {
                if ($value !== null) {
                    $unserialized = @unserialize($value);
                    $result[$keys[$index]] = $unserialized !== false ? $unserialized : $value;
                } else {
                    $result[$keys[$index]] = null;
                }
            }
            return $result;
        } catch (Exception $e) {
            error_log("Redis MGet Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Set multiple keys
     * 
     * @param array $data Key-value pairs
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool
     */
    public function mset($data, $ttl = 0) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedData = [];
            foreach ($data as $key => $value) {
                $prefixedKey = $this->prefixKey($key);
                $prefixedData[$prefixedKey] = is_string($value) ? $value : serialize($value);
            }

            $result = $this->redis->mset($prefixedData);
            
            if ($ttl > 0) {
                foreach (array_keys($data) as $key) {
                    $this->expire($key, $ttl);
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Redis MSet Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all keys matching pattern
     * 
     * @param string $pattern
     * @return array
     */
    public function keys($pattern) {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            $prefixedPattern = $this->prefixKey($pattern);
            return $this->redis->keys($prefixedPattern);
        } catch (Exception $e) {
            error_log("Redis Keys Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear all keys with prefix
     * 
     * @return bool
     */
    public function clear() {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $keys = $this->keys('*');
            if (!empty($keys)) {
                return $this->redis->del($keys) > 0;
            }
            return true;
        } catch (Exception $e) {
            error_log("Redis Clear Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get TTL (time to live) of a key
     * 
     * @param string $key
     * @return int|false TTL in seconds, -1 if no expiration, false on error
     */
    public function ttl($key) {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $prefixedKey = $this->prefixKey($key);
            return $this->redis->ttl($prefixedKey);
        } catch (Exception $e) {
            error_log("Redis TTL Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

