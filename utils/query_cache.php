<?php
/**
 * Query Result Caching
 * Caches database query results using Redis
 * 
 * @package iSCHO
 * @version 2.0
 */

require_once __DIR__ . '/redis_cache.php';

class QueryCache {
    private $cache;
    private $defaultTTL = 300; // 5 minutes

    public function __construct($defaultTTL = 300) {
        $this->cache = new RedisCache($defaultTTL);
        $this->defaultTTL = $defaultTTL;
    }

    /**
     * Cache query result
     * 
     * @param string $query SQL query or query identifier
     * @param array $params Query parameters
     * @param callable $callback Callback to execute if cache miss
     * @param int $ttl Time to live in seconds
     * @return mixed Query result
     */
    public function remember($query, $params, $callback, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }

        $key = $this->generateKey($query, $params);
        return $this->cache->remember($key, $callback, $ttl);
    }

    /**
     * Get cached query result
     * 
     * @param string $query SQL query or query identifier
     * @param array $params Query parameters
     * @return mixed|null Cached result or null
     */
    public function get($query, $params = []) {
        $key = $this->generateKey($query, $params);
        return $this->cache->get($key);
    }

    /**
     * Set cached query result
     * 
     * @param string $query SQL query or query identifier
     * @param array $params Query parameters
     * @param mixed $result Query result
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function set($query, $params, $result, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }

        $key = $this->generateKey($query, $params);
        return $this->cache->set($key, $result, $ttl);
    }

    /**
     * Invalidate cache for query pattern
     * 
     * @param string $pattern Query pattern (e.g., "analytics:*")
     * @return bool
     */
    public function invalidate($pattern) {
        return $this->cache->clearPattern("query:{$pattern}");
    }

    /**
     * Clear all query cache
     * 
     * @return bool
     */
    public function clear() {
        return $this->cache->clearPattern("query:*");
    }

    /**
     * Generate cache key from query and parameters
     * 
     * @param string $query SQL query or query identifier
     * @param array $params Query parameters
     * @return string
     */
    private function generateKey($query, $params) {
        $normalizedQuery = preg_replace('/\s+/', ' ', trim($query));
        $paramsString = !empty($params) ? md5(serialize($params)) : '';
        return "query:" . md5($normalizedQuery . $paramsString);
    }
}

/**
 * Helper function to cache PDO query results
 * 
 * @param PDO $pdo PDO instance
 * @param string $query SQL query
 * @param array $params Query parameters
 * @param QueryCache $queryCache QueryCache instance
 * @param int $ttl Cache TTL in seconds
 * @return array Query results
 */
function cachedQuery($pdo, $query, $params = [], $queryCache = null, $ttl = 300) {
    if ($queryCache === null) {
        $queryCache = new QueryCache($ttl);
    }

    return $queryCache->remember($query, $params, function() use ($pdo, $query, $params) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, $ttl);
}

